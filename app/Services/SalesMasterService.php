<?php

namespace App\Services;

use App\Models\Master;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The masters kept through the shared master screen (Sales module, 15/10/2026): Tax master, HSN/SAC, UOM, Category
 * (items) and Transporter, each described in config('nursery.sales_masters'). Each change writes its activity log
 * row in the same transaction. A row in use cannot be deleted; it can be made inactive.
 */
class SalesMasterService
{
    public static function config(string $key): array
    {
        $cfg = config("nursery.sales_masters.$key");
        abort_unless($cfg, 404);

        return $cfg;
    }

    public function save(string $key, ?Master $row, array $data, User $actor): Master
    {
        $cfg = self::config($key);

        return DB::transaction(function () use ($cfg, $row, $data, $actor) {
            $isNew = ! $row;
            $row = $isNew ? new $cfg['model']() : $row->newQuery()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($cfg, $row);
            // Only the fields the form sent: a field hidden in Admin › Page setup keeps its value.
            $row->fill(array_intersect_key($data, $cfg['fields']));
            $row->is_active = (bool) ($data['is_active'] ?? false);
            if ($isNew) {
                $row->created_by = $actor->id;
            }
            $row->updated_by = $actor->id;
            $row->save();

            if ($isNew) {
                ActivityLogger::log('master.created', $row, 'Added '.$row->label().' to the '.$cfg['title'], ['fields' => $this->snapshot($cfg, $row)]);
            } elseif ($changes = ActivityLogger::diff($before, $this->snapshot($cfg, $row))) {
                ActivityLogger::log('master.updated', $row, 'Updated '.$row->label().' in the '.$cfg['title'], $changes);
            }

            return $row;
        });
    }

    public function delete(string $key, Master $row, User $actor): void
    {
        $cfg = self::config($key);
        DB::transaction(function () use ($cfg, $row) {
            $row = $row->newQuery()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $count = 0;
            foreach ($cfg['used'] as $ref) {
                [$table, $column] = explode('.', $ref);
                $count += DB::table($table)->where($column, $row->getKey())->count();
            }
            if ($count) {
                throw ValidationException::withMessages(['delete' => 'This '.$cfg['item'].' is used on '.$count.' record(s), so it cannot be deleted. Make it inactive instead.']);
            }
            ActivityLogger::log('master.deleted', $row, 'Deleted '.$row->label().' from the '.$cfg['title'], ['fields' => $this->snapshot($cfg, $row)]);
            $row->delete();
        });
    }

    /** Values shown in the log, by field label; a picked row by its name. */
    public function snapshot(array $cfg, Master $row): array
    {
        $out = [];
        foreach ($cfg['fields'] as $f => $field) {
            $value = $row->{$f};
            if (($field['type'] ?? '') === 'select' && $value) {
                $value = $field['model']::find($value)?->label();
            }
            $out[$field['label']] = $value === null ? null : (string) $value;
        }
        $out['Active'] = $row->exists ? ($row->is_active ? 'Yes' : 'No') : null;

        return $out;
    }
}
