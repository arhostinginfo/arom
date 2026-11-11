<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/** Approve / Reject of a sales order (D3). Who may press them is checked by the route; a rejection needs its reason. */
class SalesOrderActionRequest extends FormRequest
{
    protected $errorBag = 'order';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['remarks' => [$this->route()->getActionMethod() === 'reject' ? 'required' : 'nullable', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['remarks.required' => 'Give the reason for rejecting, so the order can be corrected.'];
    }
}
