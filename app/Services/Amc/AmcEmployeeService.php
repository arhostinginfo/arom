<?php

namespace App\Services\Amc;

use App\Models\Amc\AmcEmployee;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AMC Employee master (21/11/2026): add, change, delete and the photo. A new photo replaces the old one, whose file is
 * deleted once the change is saved. Code blank = the next AE0001 style number.
 */
class AmcEmployeeService
{
    /** Contract and Return & Replace columns that point at an employee: an employee in use cannot be deleted. */
    public const USED = ['amc_contracts.operation_manager_id', 'amc_contracts.gardener_id', 'amc_returns.reported_by_id', 'amc_returns.gardener_id'];

    public function save(?AmcEmployee $e, array $data, ?UploadedFile $photo, User $actor): AmcEmployee
    {
        $newPath = $photo ? $this->storePhoto($photo) : null;
        $oldPath = null;
        try {
            $e = DB::transaction(function () use ($e, $data, $newPath, $actor, &$oldPath) {
                $isNew = ! $e;
                $e = $isNew ? new AmcEmployee() : AmcEmployee::whereKey($e->id)->lockForUpdate()->firstOrFail();
                $before = $this->snapshot($e);
                $e->fill(array_intersect_key($data, array_flip(['code', 'name', 'role', 'mobile', 'status'])));
                if ($isNew && blank($e->code)) {
                    $e->code = $this->nextCode();
                }
                if ($newPath) {
                    $oldPath = $e->photo_path;
                    $e->photo_path = $newPath;
                }
                if ($isNew) {
                    $e->created_by = $actor->id;
                }
                $e->updated_by = $actor->id;
                $e->save();
                if ($isNew) {
                    ActivityLogger::log('amc_employee.created', $e, 'Added AMC employee '.$e->label(), ['fields' => $this->snapshot($e)], $actor);
                } else {
                    $changes = ActivityLogger::diff($before, $this->snapshot($e));
                    if ($newPath) {
                        $changes['Photo'] = ['old' => $oldPath ? 'Earlier photo' : 'None', 'new' => 'New photo'];
                    }
                    if ($changes) {
                        ActivityLogger::log('amc_employee.updated', $e, 'Updated AMC employee '.$e->label(), $changes, $actor);
                    }
                }

                return $e;
            });
        } catch (\Throwable $ex) {
            if ($newPath) {
                AmcFiles::disk()->delete($newPath);
            }
            throw $ex;
        }
        if ($oldPath) {
            AmcFiles::disk()->delete($oldPath);
        }

        return $e;
    }

    /** Replace only the photo (also from the contract page). */
    public function replacePhoto(AmcEmployee $e, UploadedFile $photo, User $actor): AmcEmployee
    {
        return $this->save($e, [], $photo, $actor);
    }

    public function delete(AmcEmployee $e, User $actor): void
    {
        $path = DB::transaction(function () use ($e, $actor) {
            $e = AmcEmployee::whereKey($e->id)->lockForUpdate()->firstOrFail();
            $count = 0;
            foreach (self::USED as $ref) {
                [$table, $column] = explode('.', $ref);
                $count += DB::table($table)->where($column, $e->id)->count();
            }
            if ($count) {
                throw ValidationException::withMessages(['delete' => $e->label().' is used on '.$count.' record(s), so it cannot be deleted. Make it Inactive instead.']);
            }
            ActivityLogger::log('amc_employee.deleted', $e, 'Deleted AMC employee '.$e->label(), ['fields' => $this->snapshot($e)], $actor);
            $path = $e->photo_path;
            $e->delete();

            return $path;
        });
        if ($path) {
            AmcFiles::disk()->delete($path);
        }
    }

    /** AE0001, AE0002 … after the highest number in use. */
    public function nextCode(): string
    {
        $last = AmcEmployee::where('code', 'like', 'AE%')->pluck('code')->map(fn ($c) => (int) preg_replace('/\D/', '', $c))->max() ?? 0;

        return sprintf('AE%04d', $last + 1);
    }

    /** The photo as a JPEG of at most config('amc.photo_max') pixels; without GD the file as it came. */
    private function storePhoto(UploadedFile $photo): string
    {
        $dir = 'amc/employees/'.Str::random(8);
        $bytes = AmcImages::resized($photo, (int) config('amc.photo_max'));
        if ($bytes !== null) {
            return AmcFiles::put($dir.'/'.Str::random(40).'.jpg', $bytes);
        }
        $path = $photo->store($dir, AmcFiles::diskName());
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Could not store the photo');
        }

        return $path;
    }

    public function snapshot(AmcEmployee $e): array
    {
        return [
            'Code' => $e->code, 'Name' => $e->name, 'Role' => $e->role, 'Mobile' => $e->mobile, 'Status' => $e->status,
            'Photo' => $e->photo_path ? 'Yes' : 'No',
        ];
    }
}
