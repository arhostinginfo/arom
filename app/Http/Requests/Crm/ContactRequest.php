<?php

namespace App\Http\Requests\Crm;

use App\Models\Contact;
use App\Services\PageSetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a contact (Contact / POC of the CRM changes Word file). Always mandatory: the client (a customer or a
 * prospect) and Name. Mobile is mandatory by default; the other fields show and are mandatory as set in
 * Admin > Page setup. A hidden field is not validated or saved, so what is stored in it stays as it was.
 */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('contact') ? 'contact.edit' : 'contact.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))) ?: null,
        ]);
    }

    public function rules(): array
    {
        $rules = [
            // Customer or prospect: the pair is checked in after().
            // An active customer, or the one already saved on this contact (code review S4).
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->whereNull('deleted_at')->whereIn('type', ['Customer', 'Both'])
                ->where(fn ($q) => $q->where('status', 'Active')->when($this->route('contact')?->party_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'prospect_id' => ['nullable', 'integer', Rule::exists('prospects', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'regex:'.ProspectRequest::PHONE],
            'whatsapp' => ['nullable', 'regex:'.ProspectRequest::PHONE],
            'email' => ['nullable', 'email', 'max:191'],
            'alternate_no' => ['nullable', 'regex:'.ProspectRequest::PHONE],
            'preferred_communication' => ['nullable', Rule::in(config('nursery.contact_channels'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
        foreach (array_keys(Contact::ROLES) as $f) {
            $rules[$f] = ['nullable', 'boolean'];
        }

        return app(PageSetupService::class)->rules('contact', $rules);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $party = $this->input('party_id');
            $prospect = $this->input('prospect_id');
            if (! $party && ! $prospect) {
                $v->errors()->add('prospect_id', 'Select the company / client: a customer or a prospect.');
            }
            if ($party && $prospect) {
                $v->errors()->add('prospect_id', 'Pick a customer or a prospect, not both.');
            }
        }];
    }

    public function messages(): array
    {
        $phone = 'Enter a phone number of 6 to 20 digits, for example +91 98220 00001.';

        return ['mobile.regex' => $phone, 'whatsapp.regex' => $phone, 'alternate_no.regex' => $phone,
            'preferred_communication.in' => 'Choose Call, WhatsApp or Email.'];
    }

    public function attributes(): array
    {
        return ['party_id' => 'customer', 'prospect_id' => 'prospect', 'alternate_no' => 'alternate number',
            'preferred_communication' => 'preferred communication', 'whatsapp' => 'WhatsApp'];
    }
}
