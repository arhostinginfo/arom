<?php

namespace App\Http\Requests\Crm;

use App\Enums\QuotationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The quotation's workflow buttons: Approve / Reject (approver), Email quotation, Mark as sent and the
 * customer status. Who may press each one is checked by its route; this checks what was typed.
 */
class QuotationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'approve' => ['remarks' => ['nullable', 'string', 'max:500']],
            'reject' => ['remarks' => ['required', 'string', 'max:500']],
            'email' => [
                'to' => ['required', 'email', 'max:191'],
                'cc' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                    foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $e) {
                        if (! filter_var($e, FILTER_VALIDATE_EMAIL)) {
                            $fail('Enter CC as email addresses separated by commas.');

                            return;
                        }
                    }
                }],
                'message' => ['nullable', 'string', 'max:2000'],
            ],
            'customerStatus' => [
                'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, QuotationStatus::customerStatuses()))],
                'status_reason' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'remarks.required' => 'Give the reason for rejecting, so the quotation can be corrected.',
            'to.required' => 'Enter the customer email address.',
            'to.email' => 'Enter a valid email address.',
        ];
    }
}
