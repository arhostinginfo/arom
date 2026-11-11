<?php

namespace App\Http\Requests\Purchase;

use App\Models\Grn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Quality decision / Cancel of a GRN. Who may press them is checked by the route. Quality: decision Approved or
 * Rejected (a reason is needed to reject), and optionally a rejected quantity per line; Cancel needs a reason.
 */
class GrnActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grn = $this->route('grn');

        return ! $grn || in_array($grn->unit_id, \App\Support\WorkContext::unitIds($this->user()), true);
    }

    public function rules(): array
    {
        if ($this->route()->getActionMethod() === 'cancel') {
            return ['remarks' => ['required', 'string', 'max:500']];
        }

        return [
            'result' => ['required', Rule::in([Grn::QA_APPROVED, Grn::QA_REJECTED])],
            'remarks' => [$this->input('result') === Grn::QA_REJECTED ? 'required' : 'nullable', 'string', 'max:500'],
            'rejected' => ['nullable', 'array', 'max:200'],
            'rejected.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('rejected'))) {
            $this->merge(['rejected' => array_map(fn ($q) => is_string($q) ? str_replace([',', ' '], '', $q) : $q, $this->input('rejected'))]);
        }
    }

    public function messages(): array
    {
        return [
            'remarks.required' => $this->route()->getActionMethod() === 'cancel' ? 'Give the reason for cancelling the GRN.' : 'Give the reason for rejecting the goods.',
            'result.required' => 'Choose Approved or Rejected.',
            'rejected.*.numeric' => 'Enter the rejected quantity as a number.',
        ];
    }
}
