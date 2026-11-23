<?php

namespace App\Http\Requests\Amc;

use App\Models\Party;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit an AMC client: customer name (unique), business segment and its location lines. A line left completely
 * empty is dropped. The import uses the same field rules (fieldRules) row by row.
 */
class AmcClientRequest extends FormRequest
{
    /** Lines on one form: 7 fields each stays well inside PHP's max_input_vars (1,000). Import has no such limit. */
    public const MAX_LINES = 120;

    /** A customer typed by name (no party picked) that is not an active customer of the Party master. */
    private ?string $unknownCustomer = null;

    /** (22/11/2026) Parties an AMC client can be: Active, of type Customer or Both. */
    public static function customerParties(): Builder
    {
        return Party::query()->whereIn('type', ['Customer', 'Both'])->where('status', 'Active');
    }

    /** The active customer party of this name (any case), or null. */
    public static function partyByName(?string $name): ?Party
    {
        return $name === null ? null : self::customerParties()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->orderBy('id')->first();
    }

    public function authorize(): bool
    {
        return $this->user()->can($this->route('client') ? 'amc-client.edit' : 'amc-client.add');
    }

    protected function prepareForValidation(): void
    {
        $lines = [];
        // (22/11/2026) Keys kept, so an error is shown on the row it belongs to (rows are grouped by Location 1 on the page).
        foreach ((array) $this->input('lines', []) as $k => $l) {
            if (! is_array($l)) {
                continue;
            }
            $l = array_map(fn ($v) => is_string($v) ? (($v = preg_replace('/\s+/', ' ', trim($v))) === '' ? null : $v) : $v, $l);
            $filled = array_filter(array_intersect_key($l, array_flip(['location_1', 'location_2', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_qty'])), fn ($v) => $v !== null);
            if ($filled) {
                $lines[$k] = $l;
            }
        }
        // (22/11/2026) The customer is picked from the Party master; the name is the party's. A name sent without a party
        // (an old form) is matched to an active customer party of that name.
        $name = ($n = preg_replace('/\s+/', ' ', trim((string) $this->input('name')))) === '' ? null : $n;
        $partyId = $this->filled('party_id') && is_numeric($this->input('party_id')) ? (int) $this->input('party_id') : null;
        if ($partyId) {
            $name = Party::query()->whereKey($partyId)->value('name') ?? $name;
        } elseif ($name !== null) {
            $partyId = self::partyByName($name)?->id;
            $this->unknownCustomer = $partyId ? null : $name;
        }
        $this->merge(['name' => $name, 'party_id' => $partyId, 'lines' => $lines]);
    }

    public function rules(): array
    {
        $rules = self::fieldRules();
        $rules['name'][] = UniqueName::rule('amc_clients', $this->route('client')?->id, 'This customer is already in the AMC Client Inventory.');
        $rules['lines'][] = 'max:'.self::MAX_LINES;
        $rules['lines.*.id'] = ['nullable', 'integer'];
        $client = $this->route('client');
        $rules['party_id'] = ['required', 'integer',
            Rule::exists('parties', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where(fn ($w) => $w->where(fn ($a) => $a->whereIn('type', ['Customer', 'Both'])->where('status', 'Active'))
                ->when($client?->party_id, fn ($k) => $k->orWhere('id', $client->party_id)))),
            Rule::unique('amc_clients', 'party_id')->ignore($client?->id)];
        // (22/11/2026) An active Gardener; the one already on the client may stay after a change in the Employee master.
        $keep = $this->route('client')?->gardener_id;
        $rules['gardener_id'] = ['nullable', 'integer', Rule::exists('amc_employees', 'id')->where(fn ($q) => $q->where('role', \App\Models\Amc\AmcEmployee::GARDENER)->where('status', 'Active')
            ->when($keep, fn ($k) => $k->orWhere('id', $keep)))];

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->unknownCustomer !== null) {
                $v->errors()->forget('party_id');
                $v->errors()->add('party_id', '"'.$this->unknownCustomer.'" is not an active customer in the Party master.');
            }
            // (22/11/2026, test fix) the name is the party's: when the customer itself is wrong, a second message
            // about the name ("required", "already in the master") only repeats it.
            if ($v->errors()->has('party_id')) {
                $v->errors()->forget('name');
            }
        }];
    }

    public static function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'amc_vertical_id' => ['required', 'integer', Rule::exists('amc_verticals', 'id')],
            'lines' => ['array'],
            'lines.*.location_1' => ['nullable', 'string', 'max:150'],
            'lines.*.location_2' => ['nullable', 'string', 'max:150'],
            'lines.*.amc_planter_id' => ['nullable', 'integer', Rule::exists('amc_planters', 'id')],
            'lines.*.planter_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lines.*.amc_plant_id' => ['nullable', 'integer', Rule::exists('amc_plants', 'id')],
            'lines.*.plant_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public static function fieldMessages(): array
    {
        return [
            'name.unique' => 'This customer is already in the AMC Client Inventory.',
            'party_id.required' => 'Choose the customer from the Party master.',
            'party_id.exists' => 'Choose an active customer from the Party master.',
            'party_id.unique' => 'This customer is already in the AMC Client Inventory.',
            'gardener_id.exists' => 'Choose an active AMC employee whose role is Gardener.',
            'lines.max' => 'A client can have at most '.self::MAX_LINES.' lines on this form. Import a CSV file for more.',
            'lines.*.planter_qty.integer' => 'Planter qty must be a whole number.',
            'lines.*.plant_qty.integer' => 'Plant qty must be a whole number.',
        ];
    }

    public static function fieldAttributes(): array
    {
        return [
            'name' => 'customer name', 'amc_vertical_id' => 'business segment',
            'lines.*.location_1' => 'location 1', 'lines.*.location_2' => 'location 2',
            'lines.*.amc_planter_id' => 'planter', 'lines.*.planter_qty' => 'planter qty',
            'lines.*.amc_plant_id' => 'plant', 'lines.*.plant_qty' => 'plant qty',
        ];
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public function attributes(): array
    {
        return self::fieldAttributes();
    }
}
