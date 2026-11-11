<?php

namespace App\Http\Requests\Admin;

use App\Support\UploadLimit;
use Illuminate\Foundation\Http\FormRequest;

/** Admin > Config (22/10/2026): the maximum upload size in MB, 0.5 to 50 with at most one decimal. */
class ConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('config.edit');
    }

    protected function prepareForValidation(): void
    {
        // Only a single value is cleaned; anything else is left for the rules to reject.
        $v = $this->input('upload_max_mb');
        if (is_scalar($v)) {
            $this->merge(['upload_max_mb' => trim(str_replace(',', '.', (string) $v))]);
        }
    }

    public function rules(): array
    {
        return [
            'upload_max_mb' => ['required', 'numeric', 'regex:/^\d+(\.\d)?$/', 'min:'.UploadLimit::MIN_MB, 'max:'.UploadLimit::MAX_MB],
        ];
    }

    public function messages(): array
    {
        $range = 'Enter the size in MB from '.UploadLimit::MIN_MB.' to '.UploadLimit::MAX_MB.', for example 1 or 2.5.';

        return [
            'upload_max_mb.required' => $range,
            'upload_max_mb.numeric' => $range,
            'upload_max_mb.regex' => $range,
            'upload_max_mb.min' => $range,
            'upload_max_mb.max' => $range,
        ];
    }

    public function attributes(): array
    {
        return ['upload_max_mb' => 'maximum upload size'];
    }
}
