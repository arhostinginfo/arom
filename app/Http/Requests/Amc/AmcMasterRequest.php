<?php

namespace App\Http\Requests\Amc;

use App\Services\Amc\AmcMasterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit an entry of a simple AMC master (Business segment, Planter, Plant, AMC type). Fields from config('amc.masters'). */
class AmcMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cfg = AmcMasterService::config((string) $this->route('master'));

        return $this->user()->can($cfg['perm'].($this->route('id') ? '.edit' : '.add'));
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (AmcMasterService::config((string) $this->route('master'))['fields'] as $f => $field) {
            if ($this->has($f)) {
                $v = preg_replace('/\s+/', ' ', trim((string) $this->input($f)));
                // (23/11/2026) an amount: "1,250.50" or "₹ 120" is saved as 1250.50 / 120
                if (! empty($field['money'])) {
                    $v = str_replace([',', '₹', ' '], '', $v);
                }
                $clean[$f] = $v === '' ? null : $v;
            }
        }
        $this->merge($clean);
    }

    public function rules(): array
    {
        $cfg = AmcMasterService::config((string) $this->route('master'));
        $table = (new $cfg['model']())->getTable();
        $rules = [];
        foreach ($cfg['fields'] as $f => $field) {
            $rules[$f] = empty($field['money'])
                ? [empty($field['optional']) ? 'required' : 'nullable', 'string', 'max:'.$field['max']]
                : [empty($field['optional']) ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'];
            if ($f === 'name') {
                $rules[$f][] = UniqueName::rule($table, $this->route('id') ? (int) $this->route('id') : null, 'This name is already in the list.');
            }
        }
        $rules['is_active'] = ['nullable', 'boolean'];

        return $rules;
    }

    public function messages(): array
    {
        return ['name.unique' => 'This name is already in the list.', 'rate.numeric' => 'Enter the rate as an amount, e.g. 120 or 120.50.',
            'rate.decimal' => 'The rate can have at most 2 decimals.', 'rate.min' => 'The rate cannot be below 0.'];
    }

    public function attributes(): array
    {
        return collect(AmcMasterService::config((string) $this->route('master'))['fields'])->map(fn ($f) => strtolower($f['label']))->all();
    }
}
