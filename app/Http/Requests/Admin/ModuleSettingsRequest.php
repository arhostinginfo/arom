<?php

namespace App\Http\Requests\Admin;

use App\Services\ModuleSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Admin > Page setup › Optional parts: an On / Off value for every part in config('nursery.modules').
 * Every part must be sent, so a stale or hand-made request cannot switch parts off by leaving them out (code review S2).
 * Switching Cost details on or off changes what approval needs, so it also needs Approval · Edit (code review S1).
 */
class ModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('page-setup.edit');
    }

    public function rules(): array
    {
        $rules = ['modules' => ['required', 'array:'.implode(',', array_keys(config('nursery.modules')))]];
        foreach (array_keys(config('nursery.modules')) as $key) {
            $rules['modules.'.$key] = ['required', 'boolean'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $costing = $this->input('modules.costing');
            if ($costing !== null && (bool) $costing !== app(ModuleSettings::class)->on('costing') && ! $this->user()->can('approval.edit')) {
                $v->errors()->add('modules.costing', 'Only someone who can change Approval can switch Cost details on or off, because it changes what approvers see.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'modules.required' => 'Send an On or Off value for every optional part.',
            'modules.array' => 'Only the listed parts can be switched.',
            'modules.*.required' => 'Send an On or Off value for every optional part.',
        ];
    }
}
