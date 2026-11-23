<?php

namespace App\Http\Requests\Amc;

use App\Models\Amc\AmcEmployee;
use App\Support\Dates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Add / edit a Return & Replace entry. The location line must belong to the chosen client; replace date on or after the request date. */
class AmcReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('return') ? 'amc-return.edit' : 'amc-return.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rate' => ($r = str_replace([',', '₹', ' '], '', (string) $this->input('rate'))) === '' ? null : $r,
            'return_remark' => ($t = trim((string) $this->input('return_remark'))) === '' ? null : $t,
            'replace_date' => ($d = trim((string) $this->input('replace_date'))) === '' ? null : $d,
            // (22/11/2026, end-to-end test fix) the entry is a radio: none picked sends nothing. Without this, an edit that
            // changes the Location (or customer) and picks no entry kept the old entry and its Location 1 / Location 2.
            'amc_client_line_id' => $this->filled('amc_client_line_id') ? $this->input('amc_client_line_id') : null,
        ]);
    }

    public function rules(): array
    {
        // (21/11/2026) The gardener must be an active AMC employee whose role is Gardener, as on the contract; the one
        // already on the entry may stay after a change in the master.
        $keep = $this->route('return')?->gardener_id;
        $gardener = Rule::exists('amc_employees', 'id')->where(fn ($q) => $q->where('role', AmcEmployee::GARDENER)->where('status', 'Active')
            ->when($keep, fn ($k) => $k->orWhere('id', $keep)));

        return [
            'amc_client_id' => ['required', 'integer', Rule::exists('amc_clients', 'id')],
            'amc_client_line_id' => ['nullable', 'integer', Rule::exists('amc_client_lines', 'id')->where('amc_client_id', (int) $this->input('amc_client_id'))],
            // (22/11/2026) Location 1 of the customer's lines, picked before the entry
            'location_1' => ['nullable', 'string', 'max:150'],
            'request_date' => ['required', 'date_format:d/m/Y'],
            'replace_date' => ['nullable', 'date_format:d/m/Y'],
            'reported_by_id' => ['nullable', 'integer', Rule::exists('amc_employees', 'id')],
            'amc_type_id' => ['nullable', 'integer', Rule::exists('amc_types', 'id')],
            'amc_plant_id' => ['required', 'integer', Rule::exists('amc_plants', 'id')],
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'gardener_id' => ['nullable', 'integer', $gardener],
            'return_remark' => ['nullable', 'string', 'max:500'],
            'quality' => ['required', Rule::in(config('amc.qualities'))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            // (22/11/2026) Location 1 must be one of the customer's; a picked entry must be under it.
            $loc1 = $this->input('location_1');
            if ($loc1 !== null && $loc1 !== '' && ! $v->errors()->hasAny(['amc_client_id', 'location_1'])) {
                $lines = \App\Models\Amc\AmcClientLine::where('amc_client_id', (int) $this->input('amc_client_id'));
                if (! (clone $lines)->where('location_1', $loc1)->exists() && $loc1 !== $this->route('return')?->location_1) {
                    $v->errors()->add('location_1', 'Choose a Location of this customer.');
                } elseif ($this->input('amc_client_line_id') && ! $v->errors()->has('amc_client_line_id')
                    && (clone $lines)->whereKey((int) $this->input('amc_client_line_id'))->value('location_1') !== $loc1) {
                    $v->errors()->add('amc_client_line_id', 'Pick an entry under the chosen Location.');
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

        return $d;
    }

    public function messages(): array
    {
        return [
            '*.date_format' => 'Enter the date as dd/mm/yyyy.',
            'amc_client_line_id.exists' => 'Choose a location of this customer.',
            'gardener_id.exists' => 'Choose an active AMC employee whose role is Gardener.',
            'rate.decimal' => 'The rate can have at most 2 decimals.',
        ];
    }

    public function attributes(): array
    {
        return ['amc_client_id' => 'customer', 'amc_client_line_id' => 'location', 'reported_by_id' => 'reported by', 'amc_type_id' => 'AMC type', 'amc_plant_id' => 'plant'];
    }
}
