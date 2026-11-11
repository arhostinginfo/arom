<?php

namespace Tests;

use App\Models\Activity;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\LeadSource;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use App\Services\ModuleSettings;
use App\Services\PageSetupService;
use Illuminate\Http\UploadedFile;

/**
 * Full CRM flow with 5 dummy entries (22/10/2026): prospect or customer → enquiry → follow-up → site visit →
 * quotation (BOQ + costing) → approval → sent → customer status → PO / agreement, with Admin > Page setup and the
 * optional parts (site visit, costing) changed on the way. The using class provides admin() and sales().
 * Every step goes through the app's own routes, as the pages post them.
 */
trait CrmFiveEntryFlow
{
    use CrmFixtures;

    /** Field => [visible, required]; fields not given keep their current choice. */
    protected function setPage(string $page, array $choices): void
    {
        $fields = [];
        foreach (app(PageSetupService::class)->fields($page) as $f => $cur) {
            [$vis, $req] = $choices[$f] ?? [$cur['visible'], $cur['required']];
            $fields[$f] = ['visible' => $vis ? '1' : '0', 'required' => $req ? '1' : '0'];
        }
        $this->actingAs($this->admin())->put("/admin/page-setup/$page", ['fields' => $fields])->assertSessionHasNoErrors()->assertRedirect();
        app()->forgetInstance(PageSetupService::class);
    }

    /** Puts every field of the page back to its default from config('nursery.page_fields'). */
    protected function resetPage(string $page): void
    {
        $this->setPage($page, collect(config("nursery.page_fields.$page.fields"))->map(fn ($f) => [true, (bool) $f['required']])->all());
    }

    protected function setModules(array $modules): void
    {
        // CRM is a switch too (01/11/2026), on by default; the Separate PO menu switch was removed (02/11/2026).
        $all = array_merge(['crm' => true, 'site_visit' => true, 'boq' => true, 'costing' => true], $modules);
        $this->actingAs($this->admin())->put('/admin/page-setup-modules', ['modules' => array_map(fn ($on) => $on ? '1' : '0', $all)])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/page-setup');
        app()->forgetInstance(ModuleSettings::class);
        app()->forgetInstance(PageSetupService::class);
    }

    protected function newProspect(array $data): Prospect
    {
        $this->actingAs($this->admin())->post('/crm/prospects', array_merge(['market' => 'Domestic'], $data))->assertSessionHasNoErrors()->assertRedirect();

        return Prospect::where('name', $data['name'])->firstOrFail();
    }

    protected function newEnquiry(array $over): Enquiry
    {
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload($over))->assertSessionHasNoErrors()->assertRedirect();

        return Enquiry::latest('id')->firstOrFail();
    }

    protected function newQuotation(Enquiry $e, array $lines, array $over = []): Quotation
    {
        $this->actingAs($this->admin())->post('/crm/quotations', $this->newQuotationPayload(array_merge(['enquiry_id' => $e->id, 'lines' => $lines], $over)))
            ->assertSessionHasNoErrors()->assertRedirect();

        return Quotation::latest('id')->firstOrFail();
    }

    protected function quotationAction(Quotation $q, string $action, array $data = []): Quotation
    {
        $this->actingAs($this->admin())->post("/crm/quotations/{$q->id}/$action", $data)->assertSessionHasNoErrors();

        return $q->fresh();
    }

    protected function newPo(Quotation $q, string $no, array $over = []): CustomerPo
    {
        $this->actingAs($this->admin())->post('/crm/pos', array_merge([
            'quotation_id' => $q->id, 'po_no' => $no, 'po_date' => now()->format('d/m/Y'), 'po_value' => (string) $q->fresh()->grand_total,
            'po_quantity' => '1', 'received_by' => $this->crmEmployee()->id, 'received_date' => now()->format('d/m/Y'),
        ], $over))->assertSessionHasNoErrors()->assertRedirect();

        return CustomerPo::latest('id')->firstOrFail();
    }

    protected function followUp(Enquiry $e, array $data): void
    {
        $this->actingAs($this->admin())->post("/crm/enquiries/{$e->id}/activities", array_merge(['activity_type' => 'Call', 'activity_date' => now()->format('d/m/Y')], $data))
            ->assertSessionHasNoErrors();
    }

    protected function source(string $name): int
    {
        return LeadSource::where('name', $name)->value('id');
    }

    protected function category(string $name): int
    {
        return ServiceCategory::where('name', $name)->value('id');
    }

    /** Runs the whole flow and returns the 5 enquiries, keyed 1 to 5. */
    protected function runFiveEntryFlow(): array
    {
        $admin = $this->admin();
        $today = now()->format('d/m/Y');
        $e = [];

        // ---- 1. Greenleaf Tech Park: Hot prospect, the whole default flow up to the PO ----
        $p1 = $this->newProspect(['name' => 'Greenleaf Tech Park', 'contact_person' => 'Meera Kulkarni', 'position' => 'Admin head',
            'phone_1' => '+91 98220 41001', 'email' => 'meera@greenleaf.example', 'industry_type' => 'Corporate', 'address' => 'Baner, Pune']);
        $e[1] = $this->newEnquiry(['prospect_id' => $p1->id, 'enquiry_date' => $today, 'lead_rating' => 'Hot', 'lead_source_id' => $this->source('Website'),
            'service_category_id' => $this->category('Plant Rental'), 'contact_name' => 'Typed, ignored', 'contact_no' => '9000000000',
            'next_action' => 'Call to fix site visit', 'next_action_date' => now()->addDay()->format('d/m/Y')]);
        $this->assertSame('Meera Kulkarni', $e[1]->contact_name);
        $this->assertSame('+91 98220 41001', $e[1]->contact_no);
        $this->assertMatchesRegularExpression('#^ENQ/\d{2}-\d{2}/\d{4}$#', $e[1]->enquiry_no);

        $this->followUp($e[1], ['details' => 'Spoke to Meera, visit agreed', 'followup_action' => 'Site visit', 'target_date' => now()->addDays(2)->format('d/m/Y')]);
        $this->assertSame('Site visit', $e[1]->fresh()->next_action);

        $this->actingAs($admin)->post('/crm/site-visits', [
            'enquiry_id' => $e[1]->id, 'visit_date' => $today, 'visit_time' => '11:00', 'status' => 'Done', 'poc' => 'Meera Kulkarni',
            'technical_employee_id' => $this->crmEmployee()->id, 'area_measurements' => 'Lobby 40 x 25 ft', 'floors' => 'Ground + 3',
            'indoor_outdoor_area' => 'Indoor', 'lighting_condition' => 'Bright indirect', 'water_availability' => 'Tap on each floor',
            'customer_requirement' => '60 plants for the lobby and floors', 'next_action' => 'Prepare BOQ',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $v1 = SiteVisit::latest('id')->firstOrFail();
        $this->actingAs($admin)->post("/crm/documents/site_visit/{$v1->id}", ['category' => 'Site photo', 'file' => UploadedFile::fake()->createWithContent('lobby.jpg', base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP/////////////////////////////////////////////////////////////////////////////////////wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='))])->assertSessionHasNoErrors();

        $q1 = $this->newQuotation($e[1], [['item_no' => 'PL-01', 'description' => 'Areca palm with planter', 'qty' => '20', 'uom' => 'NOS', 'rate' => '1450', 'discount_percent' => '', 'tax_structure_id' => $this->taxId('GST 18%')]]);
        $this->actingAs($admin)->post("/crm/documents/quotation/{$q1->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('boq.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        // below the approval limit: approved at once
        $this->assertSame('Approved', $this->quotationAction($q1, 'submit')->status->value);
        $this->assertSame('Sent', $this->quotationAction($q1, 'sent')->status->value);
        $this->quotationAction($q1, 'customer-status', ['status' => 'Customer Viewed/Feedback']);
        $this->assertSame('Accepted', $this->quotationAction($q1, 'customer-status', ['status' => 'Accepted'])->status->value);
        $po1 = $this->newPo($q1, 'GL/PO/2026/01', ['payment_terms' => '50% advance']);
        $this->assertSame('PO received', $e[1]->fresh()->status->value);
        $this->assertSame($p1->id, $po1->prospect_id);

        // ---- 2. Orchid Mall: Warm, above the limit: rejected, approved, revised, verbal win (PO pending) ----
        $p2 = $this->newProspect(['name' => 'Orchid Mall', 'contact_person' => 'Farhan Shaikh', 'phone_1' => '+91 98220 41002', 'industry_type' => 'Mall']);
        $e[2] = $this->newEnquiry(['prospect_id' => $p2->id, 'enquiry_date' => now()->subDays(10)->format('d/m/Y'), 'lead_rating' => 'Warm',
            'lead_source_id' => $this->source('BNI'), 'service_category_id' => $this->category('Vertical Garden / Green Wall'),
            'estimated_value' => '175000', 'next_action_date' => now()->subDay()->format('d/m/Y')]);
        $q2 = $this->newQuotation($e[2], [['item_no' => 'VG-01', 'description' => 'Green wall 200 sq ft', 'qty' => '1', 'uom' => 'SET', 'rate' => '150000', 'discount_percent' => '', 'tax_structure_id' => '']]);
        $this->assertSame('Internal Approval', $this->quotationAction($q2, 'submit')->status->value);
        $this->actingAs($admin)->post("/crm/quotations/{$q2->id}/reject", ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->assertSame('Draft', $this->quotationAction($q2, 'reject', ['remarks' => 'Margin too low'])->status->value);
        $this->quotationAction($q2, 'submit');
        $this->assertSame('Approved', $this->quotationAction($q2, 'approve', ['remarks' => 'OK'])->status->value);
        $this->quotationAction($q2, 'sent');
        $this->quotationAction($q2, 'customer-status', ['status' => 'Negotiation']);
        $this->quotationAction($q2, 'revise');
        $q2b = Quotation::latest('id')->firstOrFail();
        $this->assertSame($q2->id, $q2b->previous_revision_id);
        $this->assertSame('Draft', $q2b->status->value);
        $this->quotationAction($q2b, 'submit');
        $this->quotationAction($q2b, 'approve', ['remarks' => 'Revised price OK']);
        $this->quotationAction($q2b, 'sent');
        $this->assertSame('Accepted', $this->quotationAction($q2b, 'customer-status', ['status' => 'Accepted'])->status->value);
        $this->assertSame('Open', $e[2]->fresh()->status->value);

        // ---- 3. Sunrise Hospital: existing customer, Cold; the typed contact is kept; customer rejects ----
        $c3 = Party::create(['name' => 'Sunrise Hospital', 'type' => 'Customer', 'status' => 'Active', 'billing_address' => 'Aundh, Pune']);
        $e[3] = $this->newEnquiry(['prospect_id' => '', 'party_id' => $c3->id, 'prospect_type' => 'Existing Client', 'lead_rating' => 'Cold',
            'lead_source_id' => $this->source('Existing Client'), 'service_category_id' => $this->category('Garden Maintenance'),
            'contact_name' => 'Dr. Anita Desai', 'contact_no' => '+91 98220 41003', 'enquiry_date' => now()->subDays(40)->format('d/m/Y')]);
        $this->assertSame('Dr. Anita Desai', $e[3]->contact_name);
        $this->assertSame('+91 98220 41003', $e[3]->contact_no);
        $this->followUp($e[3], ['activity_type' => 'WhatsApp', 'details' => 'Sent the maintenance plan']);
        $q3 = $this->newQuotation($e[3], [['item_no' => 'GM-12', 'description' => 'Garden maintenance, 12 months', 'qty' => '12', 'uom' => 'NOS', 'rate' => '6500', 'discount_percent' => '5', 'tax_structure_id' => $this->taxId('GST 18%')]]);
        $this->quotationAction($q3, 'submit');
        $this->quotationAction($q3, 'sent');
        $this->actingAs($admin)->post("/crm/quotations/{$q3->id}/customer-status", ['status' => 'Rejected'])->assertSessionHasErrors('status_reason');
        $this->assertSame('Rejected', $this->quotationAction($q3, 'customer-status', ['status' => 'Rejected', 'status_reason' => 'Chose a cheaper vendor'])->status->value);

        // ---- 4. Lotus Hotels: every form under a changed page setup ----
        $this->setPage('enquiry', ['website' => [false, false], 'remarks' => [true, true], 'competitor_details' => [true, true]]);
        $this->setPage('enquiry-followup', ['details' => [false, false], 'next_action' => [true, true]]);
        $this->setPage('site-visit', ['floors' => [false, false], 'area_measurements' => [true, true]]);
        $this->setPage('quotation', ['site' => [true, true], 'notes' => [false, false]]);
        $this->setPage('customer-po', ['remarks' => [true, true], 'po_quantity' => [false, false]]);

        $p4 = $this->newProspect(['name' => 'Lotus Hotels', 'contact_person' => 'Rohan Mehta', 'phone_1' => '+91 98220 41004', 'industry_type' => 'Hotel']);
        $form = $this->actingAs($admin)->get('/crm/enquiries/create')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="website"', $form);
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="remarks"[^>]*required/', $form);
        $this->actingAs($admin)->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => $p4->id, 'remarks' => '', 'competitor_details' => '']))
            ->assertSessionHasErrors(['remarks', 'competitor_details']);
        $e[4] = $this->newEnquiry(['prospect_id' => $p4->id, 'lead_rating' => 'Hot', 'lead_source_id' => $this->source('Referral'), 'prospect_type' => 'Referral',
            'service_category_id' => $this->category('Landscape Design'), 'website' => 'https://not-saved.example', 'remarks' => 'Two lawns and a terrace',
            'competitor_details' => 'Local nursery quoted', 'enquiry_date' => now()->subDays(3)->format('d/m/Y'), 'expected_closing_date' => now()->endOfMonth()->format('d/m/Y')]);
        $this->assertNull($e[4]->website);
        $this->assertSame('Two lawns and a terrace', $e[4]->remarks);

        $this->actingAs($admin)->post("/crm/enquiries/{$e[4]->id}/activities", ['activity_type' => 'Call', 'activity_date' => $today])
            ->assertSessionHasErrorsIn('followup', ['followup_action', 'target_date']);
        $this->followUp($e[4], ['details' => 'ignored, hidden', 'followup_action' => 'Send design concept', 'target_date' => now()->addDays(3)->format('d/m/Y')]);
        $this->assertSame('', Activity::where('enquiry_id', $e[4]->id)->latest('id')->value('details'));
        $this->assertSame('Send design concept', $e[4]->fresh()->next_action);

        $this->actingAs($admin)->post('/crm/site-visits', ['enquiry_id' => $e[4]->id, 'visit_date' => $today, 'status' => 'Planned'])->assertSessionHasErrors('area_measurements');
        $this->actingAs($admin)->post('/crm/site-visits', ['enquiry_id' => $e[4]->id, 'visit_date' => $today, 'status' => 'Done',
            'area_measurements' => '2 lawns of 1,200 sq ft', 'floors' => 'not saved'])->assertSessionHasNoErrors();
        $this->assertNull(SiteVisit::latest('id')->value('floors'));

        $this->actingAs($admin)->post('/crm/quotations', $this->newQuotationPayload(['enquiry_id' => $e[4]->id, 'site' => '']))->assertSessionHasErrors('site');
        $q4 = $this->newQuotation($e[4], [['item_no' => 'LD-01', 'description' => 'Landscape design and development', 'qty' => '1', 'uom' => 'SET', 'rate' => '85000', 'discount_percent' => '', 'tax_structure_id' => $this->taxId('GST 18%')]],
            ['site' => 'Main lawn and terrace', 'notes' => 'not saved']);
        $this->assertNull($q4->notes);
        // 85,000 + 18% GST is above the 1,00,000 limit: it waits for an approver
        $this->assertSame('Internal Approval', $this->quotationAction($q4, 'submit')->status->value);
        $this->quotationAction($q4, 'approve', ['remarks' => 'Approved']);
        $this->quotationAction($q4, 'sent');
        $this->quotationAction($q4, 'customer-status', ['status' => 'Accepted']);
        $this->actingAs($admin)->post('/crm/pos', ['quotation_id' => $q4->id, 'po_no' => 'LH/AGR/09', 'po_date' => $today, 'po_value' => '85000',
            'received_by' => $this->crmEmployee()->id, 'received_date' => $today])->assertSessionHasErrors('remarks');
        $po4 = $this->newPo($q4, 'LH/AGR/09', ['remarks' => 'Signed agreement received', 'po_quantity' => '5']);
        $this->assertNull($po4->po_quantity);
        $this->assertSame('PO received', $e[4]->fresh()->status->value);

        foreach (['enquiry', 'enquiry-followup', 'site-visit', 'quotation', 'customer-po'] as $page) {
            $this->resetPage($page);
        }

        // ---- 5. Skyline Builders: quick-added prospect, site visit and costing switched off, customer puts it on hold ----
        $this->setModules(['site_visit' => false, 'costing' => false]);
        $this->actingAs($admin)->postJson('/crm/prospects/quick', ['name' => 'Skyline Builders', 'market' => 'Domestic', 'phone_1' => '+91 98220 41005',
            'contact_person' => 'Vikram Rane'])->assertCreated();
        $p5 = Prospect::where('name', 'Skyline Builders')->firstOrFail();
        $e[5] = $this->newEnquiry(['prospect_id' => $p5->id, 'lead_rating' => 'Hot', 'lead_source_id' => $this->source('Builder'),
            'service_category_id' => $this->category('Terrace Garden'), 'enquiry_date' => now()->subDays(15)->format('d/m/Y'),
            'expected_closing_date' => now()->endOfMonth()->format('d/m/Y')]);
        $this->assertSame('Vikram Rane', $e[5]->contact_name);
        $this->actingAs($admin)->get('/crm/site-visits')->assertNotFound();
        $this->actingAs($admin)->get('/crm/enquiries/'.$e[5]->id)->assertOk()->assertDontSee('2 Site visit');
        $this->actingAs($admin)->get('/crm/quotations/create?enquiry='.$e[5]->id)->assertOk()->assertDontSee('Cost details');
        $q5 = $this->newQuotation($e[5], [['item_no' => 'TG-01', 'description' => 'Terrace garden, 800 sq ft', 'qty' => '1', 'uom' => 'SET', 'rate' => '60000', 'discount_percent' => '', 'tax_structure_id' => $this->taxId('GST 18%')]],
            ['cost' => []]);
        $this->quotationAction($q5, 'submit');
        $this->quotationAction($q5, 'sent');
        $this->actingAs($admin)->post("/crm/quotations/{$q5->id}/customer-status", ['status' => 'Hold'])->assertSessionHasErrors('status_reason');
        $this->assertSame('Hold', $this->quotationAction($q5, 'customer-status', ['status' => 'Hold', 'status_reason' => 'Waiting for building permission'])->status->value);
        $this->setModules([]);
        $this->actingAs($admin)->get('/crm/site-visits')->assertOk();

        return $e;
    }
}
