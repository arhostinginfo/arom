<?php

namespace App\Http\Requests\Crm;

use App\Models\SiteVisit;
use App\Support\WorkContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add / edit site visit. Always mandatory: Client (the enquiry), Visit date and Status; the other fields show and
 * are mandatory as set in Admin > Page setup (all shown and optional by default: filled in at the visit).
 */
class SiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('site_visit') ? 'site-visit.edit' : 'site-visit.add');
    }

    public function rules(): array
    {
        return app(\App\Services\PageSetupService::class)->rules('site-visit', [
            'enquiry_id' => ['required', 'integer', Rule::exists('enquiries', 'id')->whereNull('deleted_at')->whereIn('unit_id', WorkContext::unitIds())],
            'visit_date' => ['required', 'date_format:d/m/Y'],
            'visit_time' => ['nullable', 'date_format:H:i'],
            'poc' => ['nullable', 'string', 'max:150'],
            'technical_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'site_address' => ['nullable', 'string', 'max:1000'],
            'area_measurements' => ['nullable', 'string', 'max:255'],
            'floors' => ['nullable', 'string', 'max:100'],
            'indoor_outdoor_area' => ['nullable', 'string', 'max:255'],
            'existing_plants' => ['nullable', 'string', 'max:2000'],
            'existing_planters' => ['nullable', 'string', 'max:2000'],
            'lighting_condition' => ['nullable', 'string', 'max:150'],
            'water_availability' => ['nullable', 'string', 'max:150'],
            'access_restrictions' => ['nullable', 'string', 'max:255'],
            'customer_requirement' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(SiteVisit::STATUSES)],
        ]);
    }

    public function messages(): array
    {
        return [
            'enquiry_id.required' => 'Choose the enquiry (client) of this visit.',
            'enquiry_id.exists' => 'Choose an enquiry of your units.',
            'visit_date.date_format' => 'Enter the visit date as dd/mm/yyyy.',
            'visit_time.date_format' => 'Enter the visit time as HH:MM, for example 14:30.',
        ];
    }

    public function attributes(): array
    {
        return ['enquiry_id' => 'enquiry', 'visit_date' => 'visit date', 'visit_time' => 'visit time', 'technical_employee_id' => 'technical person', 'poc' => 'POC'];
    }
}
