<?php

namespace App\Services\Amc;

use App\Models\Amc\AmcMaster;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The simple AMC masters (21/11/2026): Business segment, Planter, Plant and AMC type, each described in
 * config('amc.masters'). Each change writes its activity log row in the same transaction. A row in use cannot be
 * deleted; it can be made inactive.
 */
class AmcMasterService
{
    public static function config(string $key): array
    {
        $cfg = config("amc.masters.$key");
        abort_unless($cfg, 404);

        return $cfg;
    }

    public function save(string $key, ?AmcMaster $row, array $data, User $actor): AmcMaster
    {
        $cfg = self::config($key);

        return DB::transaction(function () use ($cfg, $row, $data, $actor) {
            $isNew = ! $row;
            $row = $isNew ? new $cfg['model']() : $row->newQuery()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($cfg, $row);
            $row->fill(array_intersect_key($data, $cfg['fields']));
            $row->is_active = (bool) ($data['is_active'] ?? false);
            if ($isNew) {
                $row->created_by = $actor->id;
            }
            $row->updated_by = $actor->id;
            $row->save();

            if ($isNew) {
                ActivityLogger::log('amc_master.created', $row, 'Added '.$row->label().' to the '.$cfg['title'], ['fields' => $this->snapshot($cfg, $row)], $actor);
            } elseif ($changes = ActivityLogger::diff($before, $this->snapshot($cfg, $row))) {
                ActivityLogger::log('amc_master.updated', $row, 'Updated '.$row->label().' in the '.$cfg['title'], $changes, $actor);
            }

            return $row;
        });
    }

    public function delete(string $key, AmcMaster $row, User $actor): void
    {
        $cfg = self::config($key);
        DB::transaction(function () use ($cfg, $row, $actor) {
            $row = $row->newQuery()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $count = self::usedCount($cfg, $row->id);
            if ($count) {
                throw ValidationException::withMessages(['delete' => 'This '.$cfg['item'].' is used on '.$count.' record(s), so it cannot be deleted. Make it inactive instead.']);
            }
            ActivityLogger::log('amc_master.deleted', $row, 'Deleted '.$row->label().' from the '.$cfg['title'], ['fields' => $this->snapshot($cfg, $row)], $actor);
            $row->delete();
        });
    }

    public static function usedCount(array $cfg, int $id): int
    {
        $count = 0;
        foreach ($cfg['used'] as $ref) {
            [$table, $column] = explode('.', $ref);
            $count += DB::table($table)->where($column, $id)->count();
        }

        return $count;
    }

    /** Values shown in the log, by field label. */
    public function snapshot(array $cfg, AmcMaster $row): array
    {
        $out = [];
        foreach ($cfg['fields'] as $f => $field) {
            $out[$field['label']] = $row->{$f} === null ? null : (string) $row->{$f};
        }
        $out['Active'] = $row->exists ? ($row->is_active ? 'Yes' : 'No') : null;

        return $out;
    }
}
