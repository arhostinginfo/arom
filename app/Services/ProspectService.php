<?php

namespace App\Services;

use App\Http\Requests\Crm\ProspectRequest;
use App\Models\Prospect;
use App\Models\Unit;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Adds, changes, deletes and imports prospects. Each operation writes its activity log row in the same transaction. */
class ProspectService
{
    /** CSV columns for download and import, in the order of the form. */
    public const COLUMNS = [
        'name' => 'Prospect name',
        'market' => 'Domestic / Export',
        'contact_person' => 'Contact person',
        'position' => 'Position',
        'phone_1' => 'Contact no 1',
        'phone_2' => 'Contact no 2',
        'email' => 'Email',
        'gstin' => 'GSTIN',
        'industry_type' => 'Industry type',
        'address' => 'Address',
    ];

    /** COLUMNS without the fields hidden in Admin > Page setup: the download, template and import use these. */
    public static function columns(): array
    {
        $setup = app(PageSetupService::class);

        return array_filter(self::COLUMNS, fn ($field) => $setup->visible('prospect', $field), ARRAY_FILTER_USE_KEY);
    }

    /** A prospect's values for the CSV download, in the order of columns(). */
    public function csvRow(Prospect $p): array
    {
        return array_values(array_intersect_key($this->snapshot($p), array_flip(self::columns())));
    }

    public function create(array $data, User $actor, ?Unit $unit): Prospect
    {
        $p = DB::transaction(function () use ($data, $actor, $unit) {
            $p = $this->make($data, $actor, $unit);
            ActivityLogger::log('prospect.created', $p, 'Added prospect '.$p->name, ['fields' => $this->snapshot($p)]);

            return $p;
        });
        self::forgetCount();

        return $p;
    }

    public function update(Prospect $p, array $data, User $actor): Prospect
    {
        return DB::transaction(function () use ($p, $data, $actor) {
            $before = $this->snapshot($p);
            $p->fill($this->attributes($data));
            $p->updated_by = $actor->id;
            $p->save();
            if ($changes = ActivityLogger::diff($before, $this->snapshot($p))) {
                ActivityLogger::log('prospect.updated', $p, 'Updated prospect '.$p->name, $changes);
            }

            return $p;
        });
    }

    /** A prospect used on a quotation cannot be deleted (decision C3). */
    public function delete(Prospect $p, User $actor): void
    {
        DB::transaction(function () use ($p, $actor) {
            // Lock the prospect first and count inside the transaction: a quotation being saved
            // for it at the same moment waits for this lock and then sees it deleted (A7 / DB-S5).
            $p = Prospect::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $used = $p->quotations()->count();
            if ($used) {
                throw ValidationException::withMessages(['delete' => $p->name.' is used on '.$used.' quotation(s), so it cannot be deleted.']);
            }
            // Enquiries (CRM changes) point at the prospect too.
            if ($leads = $p->enquiries()->count()) {
                throw ValidationException::withMessages(['delete' => $p->name.' is used on '.$leads.' enquiry(ies), so it cannot be deleted.']);
            }

            // Its contacts go with it (soft deleted in the same transaction), so none is left without a client (database review PS-S3).
            $contacts = \App\Models\Contact::where('prospect_id', $p->id)->lockForUpdate()->get(['id', 'contact_no', 'name']);
            $fields = ['fields' => $this->snapshot($p)];
            if ($contacts->isNotEmpty()) {
                $fields['contacts'] = ['old' => $contacts->map(fn ($c) => $c->contact_no.' '.$c->name)->implode(', '), 'new' => null];
            }
            ActivityLogger::log('prospect.deleted', $p, 'Deleted prospect '.$p->name.($contacts->isNotEmpty() ? ' and its '.$contacts->count().' contact(s)' : ''), $fields);
            \App\Models\Contact::whereIn('id', $contacts->pluck('id'))->update(['deleted_at' => now(), 'updated_by' => $actor->id]);
            $p->delete();
        });
        self::forgetCount();
    }

    /**
     * Import adds every row of the file as a new prospect (decision C4). The whole file is checked
     * first: if any row has an error, or repeats a prospect name + mobile number already saved or
     * earlier in the file, nothing is saved and the errors list the row numbers. Columns hidden in
     * Admin > Page setup are not read.
     */
    public function import(array $rows, User $actor, ?Unit $unit): int
    {
        $valid = [];
        $errors = [];
        $columns = self::columns();
        foreach ($rows as $row) {
            $data = [];
            foreach ($columns as $field => $label) {
                $data[$field] = ($row[mb_strtolower($label)] ?? '') !== '' ? $row[mb_strtolower($label)] : null;
            }
            $data['market'] = ($data['market'] ?? null) ? ucfirst(strtolower($data['market'])) : null;
            $data = array_merge($data, ProspectRequest::normalise($data));
            $v = Validator::make($data, ProspectRequest::fieldRules(), ProspectRequest::fieldMessages(), ProspectRequest::fieldAttributes());
            if ($v->fails()) {
                foreach ($v->errors()->all() as $msg) {
                    $errors[] = 'Row '.$row['_row'].': '.$msg;
                }

                continue;
            }
            $valid[$row['_row']] = $v->validated();
        }
        $errors = array_merge($errors, $this->duplicateErrors($valid));
        if ($errors) {
            // In row order, so a duplicate is not hidden behind 30 other errors.
            usort($errors, fn ($a, $b) => (int) substr($a, 4) <=> (int) substr($b, 4));
            $shown = array_slice($errors, 0, 30);
            if (count($errors) > 30) {
                $shown[] = '…and '.(count($errors) - 30).' more.';
            }
            throw ValidationException::withMessages(['file' => $shown]);
        }

        $count = DB::transaction(function () use ($valid, $actor, $unit) {
            $names = [];
            foreach ($valid as $data) {
                $names[] = $this->make($data, $actor, $unit)->name;
            }
            ActivityLogger::log('prospect.imported', null, 'Imported '.count($names).' prospect(s) from a CSV file', [
                'added' => array_slice($names, 0, 100),
            ]);

            return count($names);
        });
        self::forgetCount();

        return $count;
    }

    /** The prospect total on the Prospect page and the dashboard is cached for a minute; drop it when prospects change (22/10/2026). */
    public static function forgetCount(): void
    {
        Cache::forget('crm.prospects.count');
    }

    /**
     * A row is a duplicate when its prospect name and a mobile number (contact no 1 or 2) match
     * another row of the file or a prospect already saved. Names ignore case and extra spaces;
     * numbers compare on their last 10 digits, so "+91 98220 11111" matches "9822011111".
     *
     * @param  array<int, array>  $valid  validated rows keyed by CSV row number
     * @return string[]
     */
    private function duplicateErrors(array $valid): array
    {
        if (! $valid) {
            return [];
        }
        $name = fn (?string $s) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $s)));
        $phone = function (?string $s) {
            $digits = preg_replace('/\D/', '', (string) $s);

            return strlen($digits) > 10 ? substr($digits, -10) : $digits;
        };
        $keys = fn ($n, $p1, $p2) => array_map(
            fn ($p) => $name($n).'|'.$p,
            array_unique(array_filter([$phone($p1), $phone($p2)]))
        );

        // Every saved prospect, compared in PHP: names are stored as typed, so an SQL match on the
        // name would miss a difference in case or inner spaces (import test, 22/10/2026).
        $existing = [];
        foreach (Prospect::query()->select(['id', 'name', 'phone_1', 'phone_2'])->lazyById(2000) as $p) {
            foreach ($keys($p->name, $p->phone_1, $p->phone_2) as $k) {
                $existing[$k] = true;
            }
        }

        $errors = [];
        $seen = [];
        foreach ($valid as $rowNo => $d) {
            $d['phone_2'] ??= null; // hidden in Page setup
            $label = '"'.$d['name'].'" with mobile '.$d['phone_1'].($d['phone_2'] ? ' / '.$d['phone_2'] : '');
            foreach ($keys($d['name'], $d['phone_1'], $d['phone_2']) as $k) {
                if (isset($existing[$k])) {
                    $errors[] = 'Row '.$rowNo.': prospect '.$label.' already exists.';
                    break;
                }
                if (isset($seen[$k])) {
                    $errors[] = 'Row '.$rowNo.': prospect '.$label.' is repeated in the file (same as row '.$seen[$k].').';
                    break;
                }
            }
            foreach ($keys($d['name'], $d['phone_1'], $d['phone_2']) as $k) {
                $seen[$k] ??= $rowNo;
            }
        }

        return $errors;
    }

    public function snapshot(Prospect $p): array
    {
        $out = [];
        foreach (self::COLUMNS as $field => $label) {
            $value = $p->{$field};
            $out[$label] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $out;
    }

    private function make(array $data, User $actor, ?Unit $unit): Prospect
    {
        $p = new Prospect($this->attributes($data));
        $p->unit_id = $unit?->id;
        $p->created_by = $actor->id;
        $p->updated_by = $actor->id;
        $p->save();

        return $p;
    }

    private function attributes(array $data): array
    {
        return array_intersect_key($data, self::COLUMNS);
    }
}
