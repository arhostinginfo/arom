<?php

namespace App\Http\Requests\Amc;

use App\Models\Amc\AmcClientLine;
use App\Models\Amc\AmcEmployee;
use App\Support\Dates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a Return & Replace entry. (23/11/2026) It holds rows: each one an entry of the chosen Location of the
 * customer. The planter, plant, plant size and rate come from the entry and the Plant master and are not typed; only the
 * planter qty and plant qty (each at most the entry's own qty in the AMC Client Inventory) and the row's quality are
 * chosen. Replace date on or after the request date.
 */
class AmcReturnRequest extends FormRequest
{
    public const MAX_ROWS = 200;

    public function authorize(): bool
    {
        return $this->user()->can($this->route('return') ? 'amc-return.edit' : 'amc-return.add');
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        // A post from the earlier one-plant page (a page left open over the change): its plant becomes one row.
        if ($items === null && $this->filled('amc_plant_id')) {
            $items = [['amc_client_line_id' => $this->input('amc_client_line_id'), 'amc_plant_id' => $this->input('amc_plant_id'),
                'plant_qty' => $this->input('qty')]];
        }
        $blank = fn ($x) => ($x = trim((string) $x)) === '' ? null : $x;
        if (is_array($items)) {
            foreach ($items as $k => $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['amc_client_line_id', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_qty', 'quality', 'location_2'] as $f) {
                    $row[$f] = $blank($row[$f] ?? null);
                }
                // (23/11/2026) quality is per row; a post with one quality for the entry (an older page) gives it to each row
                $row['quality'] ??= $blank($this->input('quality'));
                unset($row['rate']); // taken from the Plant master, never typed
                // a row of an entry takes the entry's planter and plant (filled in by the service), never what is posted
                if ($row['amc_client_line_id'] !== null) {
                    $row['amc_planter_id'] = $row['amc_plant_id'] = $row['location_2'] = null;
                }
                $items[$k] = $row;
            }
        }
        $this->merge([
            'items' => $items,
            'return_remark' => ($t = trim((string) $this->input('return_remark'))) === '' ? null : $t,
            'replace_date' => ($d = trim((string) $this->input('replace_date'))) === '' ? null : $d,
            'location_1' => $blank($this->input('location_1')),
        ]);
    }

    public function rules(): array
    {
        // (21/11/2026) The gardener must be an active AMC employee whose role is Gardener, as on the contract; the one
        // already on the entry may stay after a change in the master.
        $r = $this->route('return');
        $keep = $r?->gardener_id;
        $gardener = Rule::exists('amc_employees', 'id')->where(fn ($q) => $q->where('role', AmcEmployee::GARDENER)->where('status', 'Active')
            ->when($keep, fn ($k) => $k->orWhere('id', $keep)));
        // A planter / plant from the master: an active one, or one already on this entry's rows.
        $master = function (string $table, string $column) use ($r) {
            $kept = $r ? $r->items()->whereNotNull($column)->pluck($column)->all() : [];

            return Rule::exists($table, 'id')->where(fn ($q) => $q->where('is_active', true)->when($kept, fn ($k) => $k->orWhereIn('id', $kept)));
        };

        return [
            'amc_client_id' => ['required', 'integer', Rule::exists('amc_clients', 'id')],
            'location_1' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'items.*' => ['array'],
            'items.*.amc_client_line_id' => ['nullable', 'integer', Rule::exists('amc_client_lines', 'id')->where('amc_client_id', (int) $this->input('amc_client_id'))],
            'items.*.location_2' => ['nullable', 'string', 'max:150'], // a row without an entry: its own (24/11/2026)
            'items.*.amc_planter_id' => ['nullable', 'integer', $master('amc_planters', 'amc_planter_id')],
            'items.*.planter_qty' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'items.*.amc_plant_id' => ['nullable', 'integer', $master('amc_plants', 'amc_plant_id')],
            'items.*.plant_qty' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'items.*.quality' => ['required', Rule::in(config('amc.qualities'))],
            'request_date' => ['required', 'date_format:d/m/Y'],
            'replace_date' => ['nullable', 'date_format:d/m/Y'],
            'reported_by_id' => ['nullable', 'integer', Rule::exists('amc_employees', 'id')],
            'amc_type_id' => ['nullable', 'integer', Rule::exists('amc_types', 'id')],
            'gardener_id' => ['nullable', 'integer', $gardener],
            'return_remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->hasAny(['amc_client_id', 'location_1', 'items'])) {
                return;
            }
            $loc1 = $this->input('location_1');
            $lines = AmcClientLine::where('amc_client_id', (int) $this->input('amc_client_id'));
            // (22/11/2026) Location must be one of the customer's (or the one the entry already had).
            if ($loc1 !== null && ! (clone $lines)->where('location_1', $loc1)->exists() && $loc1 !== $this->route('return')?->location_1) {
                $v->errors()->add('location_1', 'Choose a Location of this customer.');

                return;
            }
            $entries = (clone $lines)->get(['id', 'location_1', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_qty'])->keyBy('id');
            $seen = [];
            foreach ((array) $this->input('items') as $k => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $line = $row['amc_client_line_id'] ?? null;
                $pq = $row['planter_qty'] ?? null;
                $q = $row['plant_qty'] ?? null;
                if ($pq === null && $q === null) {
                    // (24/11/2026, test fix) under the box the row can fill: an entry without a plant has its plant qty disabled
                    $e0 = $line !== null ? $entries->get((int) $line) : null;
                    $v->errors()->add('items.'.$k.'.'.($e0 && ! $e0->amc_plant_id && $e0->amc_planter_id ? 'planter_qty' : 'plant_qty'), 'Enter the planter qty or the plant qty.');
                }
                if ($line === null) {
                    // a row saved before the rows came from entries (21/11/2026): its own planter / plant
                    if ($pq !== null && ($row['amc_planter_id'] ?? null) === null) {
                        $v->errors()->add("items.$k.planter_qty", 'This row has no planter.');
                    }
                    if ($q !== null && ($row['amc_plant_id'] ?? null) === null) {
                        $v->errors()->add("items.$k.plant_qty", 'This row has no plant.');
                    }

                    continue;
                }
                if ($v->errors()->has("items.$k.amc_client_line_id") || ! ($e = $entries->get((int) $line))) {
                    continue;
                }
                if ($loc1 !== null && (string) $e->location_1 !== (string) $loc1) {
                    $v->errors()->add("items.$k.amc_client_line_id", 'This entry is not under the chosen Location.');
                } elseif (isset($seen[(int) $line])) {
                    $v->errors()->add("items.$k.amc_client_line_id", 'This entry is already in the return.');
                }
                $seen[(int) $line] = true;
                // at most what the entry has in the AMC Client Inventory
                foreach ([['planter_qty', $pq, $e->amc_planter_id, $e->planter_qty, 'planter'], ['plant_qty', $q, $e->amc_plant_id, $e->plant_qty, 'plant']] as [$f, $given, $has, $max, $what]) {
                    if ($given === null || $v->errors()->has("items.$k.$f")) {
                        continue;
                    }
                    if (! $has) {
                        $v->errors()->add("items.$k.$f", 'This entry has no '.$what.'.');
                    } elseif ($max !== null && (int) $given > (int) $max) {
                        $v->errors()->add("items.$k.$f", 'At most '.$max.' (the '.$what.' qty in the AMC Client Inventory).');
                    }
                }
            }
        }, function (Validator $v) {
            if ($v->errors()->hasAny(['request_date', 'replace_date']) || ! $this->input('replace_date')) {
                return;
            }
            if (Dates::toDb($this->input('replace_date')) < Dates::toDb($this->input('request_date'))) {
                $v->errors()->add('replace_date', 'The replace date must be on or after the request date.');
            }
        }];
    }

    public function forDb(): array
    {
        $d = $this->validated();
        $d['request_date'] = Dates::toDb($d['request_date']);
        $d['replace_date'] = Dates::toDb($d['replace_date'] ?? null);
        $d['items'] = array_values($d['items']);

        return $d;
    }

    public function messages(): array
    {
        return [
            '*.date_format' => 'Enter the date as dd/mm/yyyy.',
            'items.required' => 'Click an entry of the Location to add it to the return.',
            'items.min' => 'Click an entry of the Location to add it to the return.',
            'items.max' => 'A return can have at most '.self::MAX_ROWS.' rows.',
            'items.*.amc_client_line_id.exists' => 'Choose an entry of this customer.',
            'items.*.amc_planter_id.exists' => 'Choose an active planter from the Planter master.',
            'items.*.amc_plant_id.exists' => 'Choose an active plant from the Plant master.',
            'items.*.planter_qty.*' => 'Enter the planter qty as a whole number from 1.',
            'items.*.plant_qty.*' => 'Enter the plant qty as a whole number from 1.',
            'items.*.quality.required' => 'Choose the quality.',
            'items.*.quality.in' => 'Choose the quality.',
            'gardener_id.exists' => 'Choose an active AMC employee whose role is Gardener.',
        ];
    }

    public function attributes(): array
    {
        return ['amc_client_id' => 'customer', 'reported_by_id' => 'reported by', 'amc_type_id' => 'AMC type'];
    }
}
