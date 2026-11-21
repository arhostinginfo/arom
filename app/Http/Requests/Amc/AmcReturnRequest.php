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
