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
            $rules[$f] = [empty($field['optional']) ? 'required' : 'nullable', 'string', 'max:'.$field['max']];
            if ($f === 'name') {
                $rules[$f][] = UniqueName::rule($table, $this->route('id') ? (int) $this->route('id') : null, 'This name is already in the list.');
            }
        }
        $rules['is_active'] = ['nullable', 'boolean'];

        return $rules;
    }

    public function messages(): array
    {
        return ['name.unique' => 'This name is already in the list.'];
    }

    public function attributes(): array
    {
        return collect(AmcMasterService::config((string) $this->route('master'))['fields'])->map(fn ($f) => strtolower($f['label']))->all();
    }
}
