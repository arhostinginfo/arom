<?php

namespace App\Services\Amc;

use App\Models\Amc\AmcContract;
use App\Models\Amc\AmcFile;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Files of an AMC contract (21/11/2026) on the private disk. PDFs are kept as they are. Images chosen together in one
 * box (two or more) become one collage JPEG, a single image is saved again at most 1,600 pixels (App\Services\Amc\AmcImages),
 * so the disk keeps one small file instead of several phone photos. Without PHP's GD extension the images are kept one by
 * one, unchanged, and the upload still works. Deployment images older than a year are deleted by purgeOldDeployment().
 */
class AmcFileService
{
    /**
     * @param  UploadedFile[]  $files
     * @return array{saved: int, collage: bool, gd: bool}
     */
    public function upload(AmcContract $c, string $category, array $files, User $actor): array
    {
        $images = array_values(array_filter($files, fn (UploadedFile $f) => AmcImages::isImage($f)));
        $others = array_values(array_filter($files, fn (UploadedFile $f) => ! AmcImages::isImage($f)));
        $gd = AmcImages::available();
        $dir = 'amc/contracts/'.$c->id;

        // [path, original name, mime, size] of each file to save, written to the disk before the rows.
        $stored = [];
        $collage = null;
        try {
            $collage = $gd && count($images) > 1 ? AmcImages::collage($images) : null;
            if ($collage !== null) {
                $stored[] = [AmcFiles::put($dir.'/'.Str::random(40).'.jpg', $collage), 'Collage of '.count($images).' images.jpg', 'image/jpeg', strlen($collage)];
            } else {
                foreach ($images as $img) {
                    $bytes = $gd ? AmcImages::resized($img) : null;
                    $name = mb_substr($img->getClientOriginalName(), 0, 255);
                    $stored[] = $bytes !== null
                        ? [AmcFiles::put($dir.'/'.Str::random(40).'.jpg', $bytes), preg_replace('/\.(png|jpe?g)$/i', '', $name).'.jpg', 'image/jpeg', strlen($bytes)]
                        : [$this->storeAsIs($img, $dir), $name, $img->getMimeType(), (int) $img->getSize()];
                }
            }
            foreach ($others as $f) {
                $stored[] = [$this->storeAsIs($f, $dir), mb_substr($f->getClientOriginalName(), 0, 255), $f->getMimeType(), (int) $f->getSize()];
            }

            DB::transaction(function () use ($c, $category, $stored, $actor, $collage, $images) {
                $names = [];
                foreach ($stored as [$path, $name, $mime, $size]) {
                    $row = new AmcFile(['category' => $category, 'path' => $path, 'original_name' => $name, 'mime' => $mime, 'size' => $size]);
                    $row->amc_contract_id = $c->id;
                    $row->uploaded_by = $actor->id;
                    $row->save();
                    $names[] = $name.' ('.$row->sizeLabel().')';
                }
                $label = config('amc.file_categories.'.$category.'.label');
                ActivityLogger::log('amc_file.uploaded', $c, 'Uploaded '.$label.': '.implode(', ', $names).($collage !== null ? ' from '.count($images).' images' : ''),
                    ['File' => ['old' => null, 'new' => $label.': '.implode(', ', $names)]], $actor);
            });
        } catch (\Throwable $e) {
            // The rows were not saved, so the stored files would be orphaned.
            AmcFiles::disk()->delete(array_column($stored, 0));
            throw $e;
        }

        return ['saved' => count($stored), 'collage' => $collage !== null, 'gd' => $gd];
    }

    public function delete(AmcFile $file, User $actor): void
    {
        DB::transaction(function () use ($file, $actor) {
            $c = $file->contract;
            ActivityLogger::log('amc_file.deleted', $c, 'Deleted '.$file->categoryLabel().' "'.$file->original_name.'"',
                ['File' => ['old' => $file->categoryLabel().': '.$file->original_name, 'new' => null]], $actor);
            $file->delete();
        });
        AmcFiles::disk()->delete($file->path);
    }

    /**
     * Deployment images uploaded more than config('amc.deployment_keep_days') days ago: file and row deleted, in batches.
     * Only the "deployment" box: PO / agreement and delivery challan files are never deleted here.
     */
    public function purgeOldDeployment(?User $actor = null): int
    {
        $before = now()->subDays((int) config('amc.deployment_keep_days'));
        $count = 0;
        AmcFile::where('category', 'deployment')->where('created_at', '<', $before)->with('contract')
            ->chunkById(200, function ($files) use (&$count, $actor) {
                foreach ($files as $f) {
                    DB::transaction(function () use ($f, $actor) {
                        ActivityLogger::log('amc_file.purged', $f->contract, 'Deleted deployment image "'.$f->original_name.'" uploaded on '
                            .$f->created_at->format(config('nursery.date_format')).' (older than 1 year)', null, $actor);
                        $f->delete();
                    });
                    AmcFiles::disk()->delete($f->path);
                    $count++;
                }
            });

        return $count;
    }

    private function storeAsIs(UploadedFile $f, string $dir): string
    {
        $path = $f->store($dir, AmcFiles::diskName());
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Could not store '.$f->getClientOriginalName());
        }

        return $path;
    }
}
