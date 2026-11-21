<?php

namespace App\Http\Requests\Amc;

use App\Models\Amc\AmcEmployee;
use App\Support\Dates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a Customer PO / AMC contract. Dates dd/mm/yyyy, end date on or after the start date. The operation manager
 * and gardener must be active AMC employees of that role; one already on the contract may stay after a change in the master.
 */
class AmcContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('contract') ? 'amc-contract.edit' : 'amc-contract.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => ($a = str_replace([',', '₹', ' '], '', (string) $this->input('amount'))) === '' ? null : $a,
            'visit_plan' => ($v = trim((string) $this->input('visit_plan'))) === '' ? null : $v,
        ]);
    }

    public function rules(): array
    {
        $c = $this->route('contract');
        $person = fn (string $role, ?int $keep) => Rule::exists('amc_employees', 'id')->where(fn ($q) => $q->where('role', $role)->where('status', 'Active')
            ->when($keep, fn ($k) => $k->orWhere('id', $keep)));

        return [
            'amc_client_id' => ['required', 'integer', Rule::exists('amc_clients', 'id')],
            'amc_type_id' => ['required', 'integer', Rule::exists('amc_types', 'id')],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['required', 'date_format:d/m/Y'],
            'billing_cycle' => ['required', Rule::in(config('amc.billing_cycles'))],
            'visit_plan' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'operation_manager_id' => ['nullable', 'integer', $person(AmcEmployee::OPERATION_MANAGER, $c?->operation_manager_id)],
            'gardener_id' => ['nullable', 'integer', $person(AmcEmployee::GARDENER, $c?->gardener_id)],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }
            if (Dates::toDb($this->input('end_date')) < Dates::toDb($this->input('start_date'))) {
                $v->errors()->add('end_date', 'The end date must be on or after the start date.');
            }
        }];
    }

    /** Validated values with the dates as Y-m-d for the database. */
    public function forDb(): array
    {
        $d = $this->validated();
        $d['start_date'] = Dates::toDb($d['start_date']);
        $d['end_date'] = Dates::toDb($d['end_date']);

        return $d;
    }

    public function messages(): array
    {
        return [
            '*.date_format' => 'Enter the date as dd/mm/yyyy.',
            'operation_manager_id.exists' => 'Choose an active AMC employee whose role is Operation Manager.',
            'gardener_id.exists' => 'Choose an active AMC employee whose role is Gardener.',
            'amount.decimal' => 'The AMC amount can have at most 2 decimals.',
        ];
    }

    public function attributes(): array
    {
        return ['amc_client_id' => 'customer', 'amc_type_id' => 'AMC type', 'amount' => 'AMC amount', 'visit_plan' => 'visit plan'];
    }
}
