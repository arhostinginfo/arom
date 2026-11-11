<?php

namespace App\Http\Requests\Crm;

use App\Models\Activity;
use App\Services\PageSetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Follow-up on an enquiry: type and date are always required; details and the next action show and are mandatory
 * as set in Admin > Page setup (22/10/2026; by default details mandatory, next action optional). A next action
 * needs its target date. A hidden field is not validated or saved.
 */
class ActivityRequest extends FormRequest
{
    /** Its own error bag, so its messages and typed values do not mix with the enquiry form on the same page. */
    protected $errorBag = 'followup';

    public function authorize(): bool
    {
        return $this->user()->can('enquiry.edit');
    }

    public function rules(): array
    {
        return app(PageSetupService::class)->rules('enquiry-followup', [
            'activity_type' => ['required', Rule::in(Activity::TYPES)],
            'activity_date' => ['required', 'date_format:d/m/Y'],
            'details' => ['required', 'string', 'max:2000'],
            'followup_action' => ['nullable', 'string', 'max:255', 'required_with:target_date'],
            'target_date' => ['nullable', 'date_format:d/m/Y', 'required_with:followup_action'],
        ]);
    }

    public function messages(): array
    {
        return [
            'activity_date.date_format' => 'Enter the follow-up date as dd/mm/yyyy.',
            'target_date.date_format' => 'Enter the target date as dd/mm/yyyy.',
            'target_date.required_with' => 'Give the target date of the next action.',
            'followup_action.required_with' => 'Say what the next action is.',
        ];
    }

    public function attributes(): array
    {
        return ['activity_type' => 'follow-up type', 'activity_date' => 'follow-up date', 'details' => 'follow-up details', 'followup_action' => 'next action', 'target_date' => 'target date'];
    }
}
