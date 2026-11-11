<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Admin > Approval: quotation approval required, and the amount above which it is needed (blank = every quotation). */
class ApprovalRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approval.edit');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['approve_above_amount' => str_replace([',', ' '], '', (string) $this->input('approve_above_amount'))]);
    }

    public function rules(): array
    {
        return [
            'is_required' => ['required', 'boolean'],
            'approve_above_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    public function messages(): array
    {
        return ['approve_above_amount.numeric' => 'Enter the amount as a number, for example 100000.'];
    }
}
