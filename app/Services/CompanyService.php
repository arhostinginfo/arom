<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Company master (D21, S6): the one company row, printed on tax invoices. Logged in the same transaction. */
class CompanyService
{
    /**
     * Save the details; $logo replaces the logo and $removeLogo drops it (23/10/2026). The logo is kept on the
     * private documents disk, never in public; the old file is deleted only after the row is saved.
     */
    public function save(array $data, User $actor, ?UploadedFile $logo = null, bool $removeLogo = false): Company
    {
        $newPath = $logo ? $logo->store('company', DocumentService::diskName()) : null;
        $oldPath = null;
        try {
            $company = DB::transaction(function () use ($data, $actor, $logo, $newPath, $removeLogo, &$oldPath) {
                $c = Company::query()->orderBy('id')->lockForUpdate()->first() ?? new Company();
                $before = $this->snapshot($c);
                $c->fill(array_intersect_key($data, array_flip($c->getFillable())));
                $logoChange = null;
                if ($newPath || ($removeLogo && $c->logo_path)) {
                    $oldPath = $c->logo_path;
                    $logoChange = ['old' => $oldPath ? 'Previous logo' : null, 'new' => $newPath ? $logo->getClientOriginalName() : null];
                    $c->logo_path = $newPath;
                }
                $c->updated_by = $actor->id;
                $c->save();
                $changes = ActivityLogger::diff($before, $this->snapshot($c)) + array_filter(['Logo' => $logoChange]);
                if ($changes) {
                    ActivityLogger::log('company.updated', $c, 'Changed the company details', $changes);
                }

                return $c;
            });
        } catch (\Throwable $e) {
            if ($newPath) {
                DocumentService::disk()->delete($newPath); // the row was not saved, so the file would be orphaned
            }
            throw $e;
        }
        if ($oldPath) {
            DocumentService::disk()->delete($oldPath);
        }

        return $company;
    }

    private function snapshot(Company $c): array
    {
        return ['Name' => $c->exists ? $c->name : null, 'GSTIN' => $c->gstin, 'PAN' => $c->pan, 'Address' => $c->address, 'Phone' => $c->phone, 'Email' => $c->email];
    }
}
