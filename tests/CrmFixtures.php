<?php

namespace Tests;

use App\Models\City;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\IndustryType;
use App\Models\LeadSource;
use App\Models\Prospect;
use App\Models\ServiceCategory;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Services\EnquiryService;

/**
 * CRM records for tests (CRM changes, 13/10/2026): an employee, an enquiry and the form payloads.
 * The using test class provides admin() and sales().
 */
trait CrmFixtures
{
    protected function crmEmployee(array $over = []): Employee
    {
        $e = Employee::where('code', $over['code'] ?? 'E001')->first();
        if (! $e) {
            $e = new Employee(array_merge(['code' => 'E001', 'name' => 'Ravi Kumar', 'designation' => 'Sales executive', 'status' => 'Active'], $over));
            $e->save();
        }

        return $e;
    }

    /**
     * PO as its own module (01/11/2026): every PO needs a customer of the Party master, so a PO against a prospect's
     * quotation (the default fixtures) is entered for this customer.
     */
    protected function poCustomer(): \App\Models\Party
    {
        return \App\Models\Party::where('name', 'Kaveri Precision Pvt Ltd')->first() ?? \App\Models\Party::create(['name' => 'Kaveri Precision Pvt Ltd',
            'gstin' => '27AABCK7777F1Z5', 'type' => 'Customer', 'billing_address' => 'Chakan', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
    }

    protected function prospectFixture(): Prospect
    {
        return Prospect::first() ?? Prospect::create(['name' => 'Kaveri Precision', 'market' => 'Domestic', 'contact_person' => 'Kavita Rao', 'phone_1' => '+91 98220 11111', 'email' => 'buyer@kaveri.example']);
    }

    /** What the Enquiry page posts, with every field the Word file marks mandatory filled in. */
    protected function enquiryPayload(array $over = []): array
    {
        return array_merge([
            'enquiry_date' => '05/10/2026',
            'lead_source_id' => LeadSource::where('name', 'Website')->value('id'),
            'sub_source' => 'Contact form',
            'competitor_details' => '',
            'prospect_type' => 'New',
            'party_id' => '',
            'prospect_id' => $this->prospectFixture()->id,
            'industry' => 'IT services',
            'industry_type_id' => IndustryType::where('name', 'Corporate')->value('id'),
            'city_id' => City::where('name', 'Pune')->value('id'),
            'address' => 'Tower B, Hinjewadi Phase 1, Pune',
            'website' => 'www.kaveri.example',
            'contact_name' => 'Kavita Rao',
            'contact_no' => '+91 98220 11111',
            'service_category_id' => ServiceCategory::where('name', 'Plant Rental')->value('id'),
            'approx_requirement' => '40 plants for 2 floors',
            'estimated_value' => '150000',
            'expected_closing_date' => '',
            'lead_rating' => 'Hot',
            'assigned_to' => $this->crmEmployee()->id,
            'next_action' => 'Call to fix site visit',
            'next_action_date' => '07/10/2026',
            'remarks' => '',
        ], $over);
    }

    protected function crmEnquiry(array $over = []): Enquiry
    {
        return app(EnquiryService::class)->create($this->enquiryPayload($over), $this->admin(), Unit::first());
    }

    /** Id (as the drop-down posts it) of a Tax master row seeded by the sales migration: GST 0%, 5%, 12%, 18%, 28%. */
    protected function taxId(string $name = 'GST 18%'): string
    {
        return (string) TaxStructure::where('name', $name)->valueOrFail('id');
    }

    /** What the Quotation page posts; makes an enquiry first when none is given. */
    protected function newQuotationPayload(array $over = []): array
    {
        $enquiryId = $over['enquiry_id'] ?? (Enquiry::orderBy('id')->value('id') ?? $this->crmEnquiry()->id);

        return array_merge([
            'enquiry_id' => $enquiryId,
            'contact_name' => 'Kavita Rao',
            'site' => 'Tower B lobby',
            'quotation_date' => '05/10/2026',
            'valid_until' => '04/11/2026',
            'sales_person_id' => $this->sales()->id,
            'currency_id' => Currency::defaultId(),
            'payment_terms' => '50% advance',
            'delivery_terms' => 'Within 10 days of PO',
            // GST / tax is a Tax master drop-down (23/10/2026); none chosen by default.
            'tax_structure_id' => '',
            'discount_amount' => '',
            'notes' => '',
            'lines' => [
                ['item_no' => 'BRK-101', 'description' => 'Front bracket', 'qty' => '10', 'uom' => 'Nos', 'rate' => '45.50', 'discount_percent' => '', 'tax_structure_id' => ''],
                ['item_no' => '', 'description' => '', 'qty' => '', 'uom' => '', 'rate' => '', 'discount_percent' => '', 'tax_structure_id' => ''],
            ],
            'cost' => ['material_cost' => '200', 'labour_cost' => '100', 'margin_percent' => '20'],
        ], $over);
    }
}
