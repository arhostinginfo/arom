<?php

namespace App\Http\Requests\Crm;

use App\Enums\LeadRating;
use App\Enums\ProspectType;
use App\Services\PageSetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit enquiry (CRM changes, Sat 13/10/2026). Which fields show and which are mandatory comes
 * from Admin > Page setup (defaults as in the Word file). A hidden field is not validated or saved,
 * so an existing value stays as it was.
 */
class EnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('enquiry') ? 'enquiry.edit' : 'enquiry.add');
    }

    protected function prepareForValidation(): void
    {
        // Contact no is saved as typed; it is no longer taken from the Prospect (22/10/2026).
        $this->merge([
            'website' => ($w = trim((string) $this->input('website'))) !== '' && ! preg_match('#^https?://#i', $w) ? 'https://'.$w : ($w ?: null),
            'estimated_value' => str_replace([',', ' '], '', (string) $this->input('estimated_value')) ?: null,
        ]);
    }

    public function rules(): array
    {
        // An inactive master row is refused, except the one already saved on this enquiry (full test B5).
        $current = $this->route('enquiry');
        $active = fn (string $table, string $column) => Rule::exists($table, 'id')->where(function ($q) use ($current, $column) {
            $q->where('is_active', true);
            if ($current?->{$column}) {
                $q->orWhere('id', $current->{$column});
            }
        });
        $all = [
            'enquiry_date' => ['required', 'date_format:d/m/Y', 'before_or_equal:'.now()->addDay()->format('d/m/Y')],
            'lead_source_id' => ['integer', $active('lead_sources', 'lead_source_id')],
            'sub_source' => ['string', 'max:150'],
            'competitor_details' => ['string', 'max:255'],
            'prospect_type' => [Rule::enum(ProspectType::class)],
            // Customer or prospect: the pair is checked in after().
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->whereNull('deleted_at')->where('status', 'Active')->whereIn('type', ['Customer', 'Both'])],
            'prospect_id' => ['nullable', 'integer', Rule::exists('prospects', 'id')->whereNull('deleted_at')],
            'industry' => ['string', 'max:100'],
            'industry_type_id' => ['integer', $active('industry_types', 'industry_type_id')],
            'city_id' => ['integer', $active('cities', 'city_id')],
            'address' => ['string', 'max:1000'],
            'website' => ['url:http,https', 'max:191'],
            'contact_name' => ['string', 'max:100'],
            'contact_no' => ['regex:'.ProspectRequest::PHONE],
            'service_category_id' => ['integer', $active('service_categories', 'service_category_id')],
            'approx_requirement' => ['string', 'max:255'],
            'estimated_value' => ['numeric', 'min:0', 'max:9999999999999'],
            'expected_closing_date' => ['date_format:d/m/Y'],
            'lead_rating' => [Rule::enum(LeadRating::class)],
            'assigned_to' => ['integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'next_action' => ['string', 'max:255'],
            'next_action_date' => ['date_format:d/m/Y'],
            'remarks' => ['string', 'max:2000'],
        ];

        // Admin > Page setup: hidden fields are not read; the rest are mandatory or optional as set up.
        $rules = app(PageSetupService::class)->rules('enquiry', $all);
        // With a Prospect the contact name / no are not asked for or read; EnquiryService takes them from the Prospect (22/10/2026).
        if ($this->filled('prospect_id')) {
            unset($rules['contact_name'], $rules['contact_no']);
        }

        return $rules;
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
            // A new lead's assignee must be an active employee; an inactive one already on the lead may stay.
            $assigned = $this->input('assigned_to');
            if ($assigned && (int) $assigned !== (int) $this->route('enquiry')?->assigned_to
                && ! \App\Models\Employee::active()->whereKey((int) $assigned)->exists()) {
                $v->errors()->add('assigned_to', 'Choose an active employee.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'enquiry_date.date_format' => 'Enter the enquiry date as dd/mm/yyyy.',
            'enquiry_date.before_or_equal' => 'The enquiry date cannot be in the future.',
            'expected_closing_date.date_format' => 'Enter the expected closing date as dd/mm/yyyy.',
            'next_action_date.date_format' => 'Enter the next action date as dd/mm/yyyy.',
            'contact_no.regex' => 'Enter a phone number of 6 to 20 digits, for example +91 98220 00001.',
            'website.url' => 'Enter a website address, for example www.example.com.',
            'estimated_value.numeric' => 'Enter the estimated value as a number.',
        ];
    }

    public function attributes(): array
    {
        return [
            'lead_source_id' => 'source',
            'sub_source' => 'sub-source / reference',
            'prospect_type' => 'prospect type',
            'party_id' => 'customer',
            'prospect_id' => 'prospect',
            'industry_type_id' => 'industry type',
            'city_id' => 'city',
            'contact_name' => 'contact name',
            'contact_no' => 'contact no',
            'service_category_id' => 'service type',
            'approx_requirement' => 'approx. requirement',
            'estimated_value' => 'estimated value',
            'expected_closing_date' => 'expected closing date',
            'lead_rating' => 'lead rating',
            'assigned_to' => 'lead assigned to',
            'next_action' => 'next action',
            'next_action_date' => 'next action date',
            'enquiry_date' => 'enquiry date',
        ];
    }
}
