<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/** Cancel a tax invoice / credit / debit note: its number stays in the series (D16), the reason is kept. */
class InvoiceCancelRequest extends FormRequest
{
    protected $errorBag = 'invoice';

    public function authorize(): bool
    {
        return $this->user()->can('tax-invoice.delete');
    }

    public function rules(): array
    {
        return ['cancel_reason' => ['required', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['cancel_reason.required' => 'Give the reason for cancelling the invoice.'];
    }
}
