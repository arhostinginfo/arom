<?php

namespace App\Http\Requests\Admin;

use App\Services\MasterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit an entry of a simple CRM master (Source, Category, Industry type, City). Name is required and unique. */
class MasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('id') ? 'masters.edit' : 'masters.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name')))]);
    }

    public function rules(): array
    {
        $table = (new (MasterService::list($this->route('master'))['model']))->getTable();

        // Admin > Page setup of this list (master-sources, master-categories, …): Sort order shown or hidden, optional or mandatory.
        return app(\App\Services\PageSetupService::class)->rules('master-'.$this->route('master'), [
            'name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')->ignore($this->route('id')),
                // Also when only the case differs ("trade fair" and "Trade Fair"), on any database collation.
                function ($attr, $value, $fail) use ($table) {
                    if (\Illuminate\Support\Facades\DB::table($table)->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                        ->when($this->route('id'), fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
                        $fail('This name is already in the list.');
                    }
                }],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    public function messages(): array
    {
        return ['name.unique' => 'This name is already in the list.'];
    }
}
