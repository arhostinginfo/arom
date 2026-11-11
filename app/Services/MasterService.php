<?php

namespace App\Services;

use App\Models\City;
use App\Models\Employee;
use App\Models\IndustryType;
use App\Models\LeadSource;
use App\Models\ServiceCategory;
use App\Models\SimpleMaster;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Masters for the CRM (CRM changes): the simple dropdown lists and the Employee master.
 * Each change writes its activity log row in the same transaction. A row in use cannot be
 * deleted; it can be made inactive so it no longer shows in dropdowns.
 */
class MasterService
{
    /** URL key => model, title, and the columns that use it (table.column). */
    public const LISTS = [
        'sources' => ['model' => LeadSource::class, 'title' => 'Source master', 'item' => 'source', 'used' => ['enquiries.lead_source_id']],
        'categories' => ['model' => ServiceCategory::class, 'title' => 'Category master', 'item' => 'category', 'used' => ['enquiries.service_category_id']],
        'industry-types' => ['model' => IndustryType::class, 'title' => 'Industry type master', 'item' => 'industry type', 'used' => ['enquiries.industry_type_id']],
        'cities' => ['model' => City::class, 'title' => 'City master', 'item' => 'city', 'used' => ['enquiries.city_id']],
    ];

    public const EMPLOYEE_USED = ['enquiries.assigned_to', 'site_visits.technical_employee_id', 'customer_pos.received_by'];

    public static function list(string $key): array
    {
        abort_unless(isset(self::LISTS[$key]), 404);

        return self::LISTS[$key];
    }

    public function saveListItem(string $key, ?SimpleMaster $row, array $data, User $actor): SimpleMaster
    {
        $cfg = self::list($key);

        return DB::transaction(function () use ($cfg, $row, $data, $actor) {
            $isNew = ! $row;
            $row ??= new $cfg['model']();
            $before = $this->listSnapshot($row);
            $row->fill([
                'name' => $data['name'],
                // Order hidden in Admin > Page setup: a new entry goes last, an existing one keeps its order.
                'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order']
                    : ($row->exists ? (int) $row->sort_order : (int) $cfg['model']::max('sort_order') + 1),
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            if ($isNew) {
                $row->created_by = $actor->id;
            }
            $row->updated_by = $actor->id;
            $row->save();

            if ($isNew) {
                ActivityLogger::log('master.created', $row, 'Added '.$row->name.' to the '.$cfg['title'], ['fields' => $this->listSnapshot($row)]);
            } elseif ($changes = ActivityLogger::diff($before, $this->listSnapshot($row))) {
                ActivityLogger::log('master.updated', $row, 'Updated '.$row->name.' in the '.$cfg['title'], $changes);
            }

            return $row;
        });
    }

    public function deleteListItem(string $key, SimpleMaster $row, User $actor): void
    {
        $cfg = self::list($key);
        DB::transaction(function () use ($cfg, $row) {
            $row = $row->newQuery()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $this->ensureUnused($row, $cfg['used'], 'This '.$cfg['item']);
            ActivityLogger::log('master.deleted', $row, 'Deleted '.$row->name.' from the '.$cfg['title'], ['fields' => $this->listSnapshot($row)]);
            $row->delete();
        });
    }

    public function saveEmployee(?Employee $e, array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($e, $data, $actor) {
            $isNew = ! $e;
            $e ??= new Employee();
            $before = $this->employeeSnapshot($e);
            $e->fill(array_intersect_key($data, array_flip($e->getFillable())));
            if ($isNew) {
                $e->created_by = $actor->id;
            }
            $e->updated_by = $actor->id;
            $e->save();

            if ($isNew) {
                ActivityLogger::log('employee.created', $e, 'Added employee '.$e->activityLabel(), ['fields' => $this->employeeSnapshot($e)]);
            } elseif ($changes = ActivityLogger::diff($before, $this->employeeSnapshot($e))) {
                ActivityLogger::log('employee.updated', $e, 'Updated employee '.$e->activityLabel(), $changes);
            }

            return $e;
        });
    }

    public function deleteEmployee(Employee $e, User $actor): void
    {
        DB::transaction(function () use ($e) {
            $e = Employee::whereKey($e->id)->lockForUpdate()->firstOrFail();
            $this->ensureUnused($e, self::EMPLOYEE_USED, $e->name);
            ActivityLogger::log('employee.deleted', $e, 'Deleted employee '.$e->activityLabel(), ['fields' => $this->employeeSnapshot($e)]);
            // The login is freed, so it can be linked to another employee (end-to-end test M1). The log above keeps
            // which login it was.
            $e->user_id = null;
            $e->save();
            $e->delete();
        });
    }

    public function employeeSnapshot(Employee $e): array
    {
        return [
            'Employee code' => $e->code,
            'Name' => $e->name,
            'Designation' => $e->designation,
            'Department' => $e->department,
            'Mobile' => $e->mobile,
            'Email' => $e->email,
            'Date of joining' => $e->joining_date?->format(config('nursery.date_format')),
            'Address' => $e->address,
            'Login user' => $e->user_id ? User::withTrashed()->find($e->user_id)?->name : null,
            'Status' => $e->status,
        ];
    }

    private function listSnapshot(SimpleMaster $row): array
    {
        return ['Name' => $row->name, 'Order' => $row->sort_order, 'Active' => $row->exists ? ($row->is_active ? 'Yes' : 'No') : null];
    }

    /** Refuses the delete when any record still points at the row (counted under its lock). */
    private function ensureUnused(Model $row, array $usedBy, string $what): void
    {
        $count = 0;
        foreach ($usedBy as $ref) {
            [$table, $column] = explode('.', $ref);
            $count += DB::table($table)->where($column, $row->getKey())->count();
        }
        if ($count) {
            throw ValidationException::withMessages(['delete' => $what.' is used on '.$count.' record(s), so it cannot be deleted. Make it inactive instead.']);
        }
    }
}
