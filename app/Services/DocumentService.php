<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Models\Document;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Modules;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Documents & attachments (CRM changes): files are kept on a private disk, never in public, and are
 * downloaded through the app so the record's permission is checked. The disk comes from DOCUMENTS_DISK
 * (config nursery.documents_disk): "local" on the development PC, a shared private S3-compatible bucket
 * in production, so every app server and the queue worker see the same files (architecture review C2).
 * Each document keeps its uploader, upload time and linked record; uploads and deletes are logged.
 */
class DocumentService
{
    public static function diskName(): string
    {
        return (string) config('nursery.documents_disk', 'local');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /**
     * A quotation's documents are part of what is approved (costing + BOQ + quotation), so they can be
     * added or removed only on the current revision while it is a Draft (code review M3). A sales order's customer PO
     * file is part of what is approved too: once the order is Pending or Approved only an Admin can change it, as with
     * the order itself (Sales code review M4).
     */
    public static function canChange(Model $record): bool
    {
        if ($record instanceof SalesOrder) {
            return ($u = auth()->user()) !== null && $record->isEditableBy($u);
        }

        return ! $record instanceof Quotation || ($record->is_latest && $record->status === QuotationStatus::Draft);
    }

    public const PER_PAGE = 10;

    /** Document types a record of this kind takes now: BOQ and Costing approval only while those parts are on. */
    public static function categories(string $type): array
    {
        return array_values(array_filter(config('nursery.document_categories.'.$type, []), [self::class, 'categoryShown']));
    }

    /** False for a document type of an optional part that is switched off (config 'document_category_modules'). */
    public static function categoryShown(string $category): bool
    {
        return Modules::allows(config('nursery.document_category_modules')[$category] ?? null);
    }

    /** Document types to leave out of lists while their optional part is off; the files themselves are kept. */
    public static function hiddenCategories(): array
    {
        return array_keys(array_filter(config('nursery.document_category_modules'), fn ($module) => ! Modules::on($module)));
    }

    /**
     * The Documents box of a record: a page of its documents, newest first, paged on the server ("dpage"),
     * the total, and whether the record's documents can be changed now.
     */
    public static function box(Model $record): array
    {
        if (! $record->exists) {
            return ['list' => null, 'count' => 0, 'changeable' => false];
        }
        $query = Document::where('documentable_type', $record->getMorphClass())->where('documentable_id', $record->getKey())
            ->when(self::hiddenCategories(), fn ($q, $hidden) => $q->whereNotIn('category', $hidden));

        return [
            'list' => (clone $query)->with('uploader:id,name')->latest('id')->simplePaginate(self::PER_PAGE, ['*'], 'dpage')->withQueryString()->fragment('documents'),
            'count' => $query->count(),
            'changeable' => self::canChange($record),
        ];
    }

    public function upload(Model $record, string $category, UploadedFile $file, User $actor): Document
    {
        $this->ensureCanChange($record);
        $path = $file->store('documents/'.$record->getMorphClass().'/'.$record->getKey(), self::diskName());
        // The disk does not throw on a failed write (filesystems 'throw' => false): stop here so no row points at a missing file (23/10/2026).
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Could not store '.$file->getClientOriginalName());
        }

        try {
            return DB::transaction(function () use ($record, $category, $file, $path, $actor) {
                $doc = $this->makeRow($record, $category, mb_substr($file->getClientOriginalName(), 0, 255), $path, $file->getMimeType(), $file->getSize(), $actor);
                ActivityLogger::log('document.uploaded', $record, 'Uploaded '.$category.' "'.$doc->original_name.'"', [
                    'Document' => ['old' => null, 'new' => $category.': '.$doc->original_name.' ('.$doc->sizeLabel().')'],
                ]);

                return $doc;
            });
        } catch (\Throwable $e) {
            // The row was not saved, so the stored file would be orphaned.
            self::disk()->delete($path);
            throw $e;
        }
    }

    public function delete(Document $doc, User $actor): void
    {
        DB::transaction(function () use ($doc) {
            $record = $doc->documentable;
            if ($record) {
                $this->ensureCanChange($record);
            }
            ActivityLogger::log('document.deleted', $record, 'Deleted '.$doc->category.' "'.$doc->original_name.'"', [
                'Document' => ['old' => $doc->category.': '.$doc->original_name, 'new' => null],
            ]);
            $doc->delete();
        });
        self::disk()->delete($doc->path);
    }

    /** Revise: the new revision gets its own copy of each file of a category, so deleting one keeps the other. */
    public function copyCategory(Model $from, Model $to, string $category, User $actor): void
    {
        foreach (Document::where('documentable_type', $from->getMorphClass())->where('documentable_id', $from->getKey())->where('category', $category)->orderBy('id')->get() as $doc) {
            $path = 'documents/'.$to->getMorphClass().'/'.$to->getKey().'/'.Str::random(40).'.'.pathinfo($doc->path, PATHINFO_EXTENSION);
            if (! self::disk()->exists($doc->path) || ! self::disk()->copy($doc->path, $path)) {
                continue;
            }
            $this->makeRow($to, $doc->category, $doc->original_name, $path, $doc->mime, $doc->size, $actor);
        }
    }

    private function makeRow(Model $record, string $category, string $name, string $path, ?string $mime, int $size, User $actor): Document
    {
        $doc = new Document(['category' => $category, 'original_name' => $name, 'path' => $path, 'mime' => $mime, 'size' => $size]);
        $doc->documentable()->associate($record);
        $doc->uploaded_by = $actor->id;
        $doc->save();

        return $doc;
    }

    private function ensureCanChange(Model $record): void
    {
        if ($record instanceof SalesOrder && ! self::canChange($record)) {
            throw ValidationException::withMessages(['file' => $record->so_no.' is '.$record->status.', so only an Admin can change its documents.']);
        }
        if (! self::canChange($record)) {
            throw ValidationException::withMessages(['file' => $record->activityLabel().' is '.($record->is_latest ? $record->status->value : 'an earlier revision')
                .', so its documents cannot be changed. Use Revise to make a new Draft.']);
        }
    }
}
