<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

/** Approve / Reject / Cancel of a purchase order. Who may press them is checked by the route; Reject and Cancel need a reason. */
class PurchaseOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $needsReason = in_array($this->route()->getActionMethod(), ['reject', 'cancel'], true);

        return ['remarks' => [$needsReason ? 'required' : 'nullable', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['remarks.required' => $this->route()->getActionMethod() === 'cancel'
            ? 'Give the reason for cancelling the purchase order.'
            : 'Give the reason for rejecting, so the purchase order can be corrected.'];
    }
}
