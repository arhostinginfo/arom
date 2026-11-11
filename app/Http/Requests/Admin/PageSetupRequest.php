<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Admin > Page setup: for each field of the page, Show and Mandatory ticks. */
class PageSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('page-setup.edit');
    }

    public function rules(): array
    {
        $fields = array_keys(config('nursery.page_fields.'.$this->route('page').'.fields', []));

        return [
            'fields' => ['nullable', 'array:'.implode(',', $fields)],
            'fields.*.visible' => ['nullable', 'boolean'],
            'fields.*.required' => ['nullable', 'boolean'],
        ];
    }
}
