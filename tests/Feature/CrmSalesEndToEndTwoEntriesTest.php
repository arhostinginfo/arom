<?php

namespace Tests\Feature;

use App\Jobs\SendQuotationMail;
use App\Models\Company;
use App\Models\Currency;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\FinancialYear;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SiteVisit;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * CRM and Sales end to end with 2 entries (03/11/2026), every step through the app's own routes as the pages post them.
 *
 * CRM (menu order): Prospect → 1 Enquiry / Lead (+ follow-up) → 2 Site visit (+ photo) → 3 BOQ, costing, quotation
 * (BOQ upload, cost details) → 4 Approval → 5 Quotation sent (+ customer status) → 6 PO / Agreement (Part master
 * items, documents with the new PO and on the saved PO) → Create Sales Order.
 *   Entry 1  Greenfield Interiors, Pune (Maharashtra, same state as the company): starts as a Prospect, is added to the
 *            Party master and the enquiry is moved to the customer; quotation below the approval limit (approved at
 *            once), marked sent by hand.
 *   Entry 2  Bangalore Botanica, Bengaluru (Karnataka, other state): existing customer; quotation above the limit
 *            waits in 4 Approval and Admin approves it; sent by email.
 *
 * Sales: Sales order (made from the CRM PO, edited, sent for approval by the Sales user, approved by Admin) → Tax
 * invoice against that PO (PO no list, lines with balances, CGST + SGST for entry 1 and IGST for entry 2) → PDF → CSV
 * → invoice list → Sales reports; invoice date locked to today for a Sales user, a past date kept for Admin; Invoice
 * type drop-down; one credit note against entry 1's invoice.
 *
 * SQLite in memory only (phpunit.xml), or a throwaway MariaDB database whose name ends in _tmp, never "nursery".
 */
class CrmSalesEndToEndTwoEntriesTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->assertStringEndsWith('_tmp', DB::connection()->getDatabaseName(), 'Only a throwaway database may be used.');
        }
        Storage::fake('local');
        config(['nursery.documents_disk' => 'local']);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function sales(): User
    {
        if ($u = User::where('email', 'priya@company.example')->first()) {
            return $u;
        }
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822077777', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function d($date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
    }

    private function jpg(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP/////////////////////////////////////////////////////////////////////////////////////wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='));
    }

    /** Masters through Admin › Masters: company GSTIN (Maharashtra), the HSN code and the 4 parts. */
    private function masters(): array
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/masters/company', ['name' => 'Green Nursery Pvt Ltd', 'gstin' => '27AAACG1234F1Z5', 'pan' => 'AAACG1234F',
            'address' => "Plot 4, MIDC Chakan\nPune 410501"])->assertSessionHasNoErrors();
        $this->assertSame('27', Company::current()->stateCode());

        $gst18 = TaxStructure::where('name', 'GST 18%')->firstOrFail();
        $gst12 = TaxStructure::where('name', 'GST 12%')->firstOrFail();
        $this->actingAs($admin)->post('/admin/masters/lists/hsn-sac', ['code' => '0602', 'type' => 'HSN', 'description' => 'Live plants', 'tax_structure_id' => $gst18->id, 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $hsn = HsnSacCode::where('code', '0602')->firstOrFail();

        return ['gst18' => $gst18, 'gst12' => $gst12, 'hsn' => $hsn, 'nos' => Uom::where('code', 'NOS')->firstOrFail()];
    }

    private function newParty(array $data): Party
    {
        $this->actingAs($this->admin())->post('/admin/masters/parties', array_merge(['type' => 'Customer', 'status' => 'Active', 'currency_id' => Currency::defaultId()], $data))
            ->assertSessionHasNoErrors()->assertRedirect();

        return Party::where('name', $data['name'])->firstOrFail();
    }

    private function newItem(array $m, Party $party, string $no, string $desc, string $price, TaxStructure $tax): Item
    {
        $this->actingAs($this->admin())->post('/admin/masters/items', ['description' => $desc, 'item_no' => $no, 'type' => 'Sales', 'hsn_sac_code_id' => $m['hsn']->id,
            'uom_id' => $m['nos']->id, 'tax_structure_id' => $tax->id, 'status' => 'Active', 'party_id' => $party->id, 'price' => $price, 'currency_id' => Currency::defaultId()])
            ->assertSessionHasNoErrors();

        return Item::where('item_no', $no)->firstOrFail();
    }

    /** Quotation line as the Quotation page posts it. */
    private function qLine(Item $i, string $qty, string $rate, TaxStructure $tax): array
    {
        return ['item_no' => $i->item_no, 'description' => $i->description, 'qty' => $qty, 'uom' => 'NOS', 'rate' => $rate, 'discount_percent' => '', 'tax_structure_id' => (string) $tax->id];
    }

    private function poLine(Item $i, string $qty, string $rate): array
    {
        return ['item_id' => (string) $i->id, 'description' => '', 'quantity' => $qty, 'uom_id' => '', 'rate' => $rate];
    }

    private function invPayload(Party $party, SalesOrder $so, array $lines, array $over = []): array
    {
        return array_merge(['_form' => 'invoice', 'invoice_type' => 'Sales', 'invoice_date' => today()->format('d/m/Y'), 'party_id' => $party->id, 'sales_order_id' => $so->id,
            'shipping_address' => $party->shipping_address ?? $party->billing_address, 'mode_of_transport' => 'Road', 'transporter_id' => '', 'vehicle_no' => 'MH 14 GN 2026',
            'distance_km' => '35', 'pf_charges' => '', 'lines' => $lines], $over);
    }

    /** Order line id of the part on the order. */
    private function soLine(SalesOrder $so, Item $i): int
    {
        return (int) $so->lines()->where('item_id', $i->id)->value('id');
    }

    public function test_two_entries_go_through_crm_and_sales_end_to_end(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $sales = $this->sales();
        $m = $this->masters();
        $today = today()->format('d/m/Y');
        $emp = $this->crmEmployee();

        // =====================================================================================================
        // CRM, entry 1: Greenfield Interiors (Prospect → customer, Maharashtra), below the approval limit
        // =====================================================================================================
        $this->actingAs($sales)->post('/crm/prospects', ['name' => 'Greenfield Interiors', 'market' => 'Domestic', 'contact_person' => 'Anjali Deshmukh',
            'position' => 'Facility manager', 'phone_1' => '+91 98220 61001', 'email' => 'anjali@greenfield.example', 'address' => 'Kharadi, Pune'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $p1 = Prospect::where('name', 'Greenfield Interiors')->firstOrFail();
        $this->actingAs($sales)->get('/crm/prospects/'.$p1->id)->assertOk()->assertSee('Greenfield Interiors');

        // 1 Enquiry / Lead, for the prospect: the contact comes from the prospect
        $this->actingAs($sales)->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => $p1->id, 'party_id' => '', 'enquiry_date' => $today,
            'lead_rating' => 'Hot', 'contact_name' => '', 'contact_no' => '', 'estimated_value' => '55000', 'approx_requirement' => 'Office plants for 2 floors',
            'next_action' => 'Call to fix site visit', 'next_action_date' => $this->d(today()->addDay())]))->assertSessionHasNoErrors()->assertRedirect();
        $e1 = Enquiry::latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('#^ENQ/\d{2}-\d{2}/\d{4}$#', $e1->enquiry_no);
        $this->assertSame('Anjali Deshmukh', $e1->contact_name);
        $this->assertSame('Open', $e1->status->value);

        // follow-up
        $this->actingAs($sales)->post("/crm/enquiries/{$e1->id}/activities", ['activity_type' => 'Call', 'activity_date' => $today,
            'details' => 'Visit agreed for tomorrow', 'followup_action' => 'Site visit', 'target_date' => $this->d(today()->addDay())])->assertSessionHasNoErrors();
        $this->assertSame('Site visit', $e1->fresh()->next_action);

        // the prospect becomes a customer: added to the Party master, the enquiry is moved to it
        $c1 = $this->newParty(['name' => 'Greenfield Interiors Pvt Ltd', 'gstin' => '27AAFCG5678K1Z2', 'billing_address' => "Kharadi\nPune 411014",
            'shipping_address' => 'Tower 2, EON IT Park, Kharadi, Pune', 'payment_terms' => '30 days']);
        $this->assertSame('27', $c1->stateCode());
        $this->actingAs($sales)->put('/crm/enquiries/'.$e1->id, $this->enquiryPayload(['prospect_id' => '', 'party_id' => $c1->id, 'prospect_type' => 'New',
            'enquiry_date' => $today, 'lead_rating' => 'Hot', 'contact_name' => 'Anjali Deshmukh', 'contact_no' => '+91 98220 61001', 'estimated_value' => '55000',
            'next_action' => 'Site visit', 'next_action_date' => $this->d(today()->addDay())]))->assertSessionHasNoErrors();
        $this->assertSame([$c1->id, null], [$e1->fresh()->party_id, $e1->fresh()->prospect_id]);

        // parts of the customer (Part master)
        $areca = $this->newItem($m, $c1, 'GF-ARC-5', 'Areca palm 5 ft with pot', '1450', $m['gst18']);
        $planter = $this->newItem($m, $c1, 'GF-PLT-14', 'Ceramic planter 14 in', '850', $m['gst12']);

        // 2 Site visit, with a site photo
        $this->actingAs($sales)->post('/crm/site-visits', ['enquiry_id' => $e1->id, 'visit_date' => $today, 'visit_time' => '11:00', 'status' => 'Done',
            'poc' => 'Anjali Deshmukh', 'technical_employee_id' => $emp->id, 'area_measurements' => 'Two floors, 8,000 sq ft each', 'floors' => '5th and 6th',
            'indoor_outdoor_area' => 'Indoor', 'lighting_condition' => 'Bright indirect', 'water_availability' => 'Pantry on each floor',
            'customer_requirement' => '20 palms with planters', 'next_action' => 'Prepare BOQ'])->assertSessionHasNoErrors()->assertRedirect();
        $v1 = SiteVisit::latest('id')->firstOrFail();
        $this->actingAs($sales)->post("/crm/documents/site_visit/{$v1->id}", ['category' => 'Site photo', 'file' => $this->jpg('floor5.jpg')])->assertSessionHasNoErrors();
        $this->actingAs($sales)->get('/crm/site-visits/'.$v1->id)->assertOk()->assertSee('floor5.jpg');

        // 3 BOQ, costing, quotation: lines, cost details, BOQ file
        $this->actingAs($sales)->get('/crm/quotations/create?enquiry='.$e1->id)->assertOk()->assertSee('Cost details');
        $this->actingAs($sales)->post('/crm/quotations', $this->newQuotationPayload(['enquiry_id' => $e1->id, 'contact_name' => 'Anjali Deshmukh', 'site' => 'EON IT Park, Tower 2',
            'quotation_date' => $today, 'valid_until' => $this->d(today()->addDays(30)), 'sales_person_id' => $sales->id,
            'lines' => [$this->qLine($areca, '20', '1450', $m['gst18']), $this->qLine($planter, '20', '850', $m['gst12'])],
            'cost' => ['material_cost' => '30000', 'labour_cost' => '6000', 'margin_percent' => '25']]))->assertSessionHasNoErrors()->assertRedirect();
        $q1 = Quotation::latest('id')->firstOrFail();
        $this->assertSame($c1->id, $q1->party_id);
        // 20 × 1,450 + 18% = 34,220; 20 × 850 + 12% = 19,040
        $this->assertSame('53260.00', $q1->grand_total);
        $this->assertSame('36000.00', $q1->cost->total_cost);
        $this->assertSame('45000.00', $q1->cost->proposed_selling_price);
        $this->actingAs($sales)->post("/crm/documents/quotation/{$q1->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('greenfield-boq.pdf', 20, 'application/pdf')])
            ->assertSessionHasNoErrors();

        // 4 Approval: below 1,00,000, approved at once
        $this->actingAs($sales)->post("/crm/quotations/{$q1->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('Approved', $q1->fresh()->status->value);

        // 5 Quotation sent (by hand), customer feedback, accepted
        $this->actingAs($sales)->post("/crm/quotations/{$q1->id}/sent")->assertSessionHasNoErrors();
        $this->assertSame('Sent', $q1->fresh()->status->value);
        $this->actingAs($sales)->get('/crm/quotations/list?view=sent')->assertOk()->assertSee($q1->quotation_no);
        $this->actingAs($sales)->post("/crm/quotations/{$q1->id}/customer-status", ['status' => 'Customer Viewed/Feedback'])->assertSessionHasNoErrors();
        $this->actingAs($sales)->post("/crm/quotations/{$q1->id}/customer-status", ['status' => 'Accepted'])->assertSessionHasNoErrors();
        $this->assertSame('Accepted', $q1->fresh()->status->value);

        // 6 PO / Agreement: Part master items, a PO and an agreement chosen with the new PO, one more uploaded after
        $this->actingAs($sales)->get('/crm/pos/create?quotation='.$q1->id)->assertOk();
        $this->actingAs($sales)->post('/crm/pos', ['quotation_id' => $q1->id, 'po_no' => 'GFI/PO/2026/118', 'po_date' => $today, 'po_value' => '1',
            'valid_from' => $today, 'valid_to' => $this->d(today()->addMonths(3)), 'payment_terms' => '30 days', 'tax_terms' => 'GST extra',
            'contact_name' => 'Anjali Deshmukh', 'received_by' => $emp->id, 'received_date' => $today, 'remarks' => 'PO by email',
            'lines_json' => json_encode([$this->poLine($areca, '20', '1450'), $this->poLine($planter, '20', '')]),
            'doc_category' => 'Customer PO', 'doc_files' => [UploadedFile::fake()->create('GFI-PO-118.pdf', 30, 'application/pdf'), $this->jpg('GFI-PO-118-signed.jpg')],
        ])->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('toast', 'PO / agreement GFI/PO/2026/118 saved with 2 documents');
        $po1 = CustomerPo::latest('id')->with('lines')->firstOrFail();
        // with items the PO value is their sum (the typed 1 is ignored); a blank rate is the item price
        $this->assertSame('46000.00', $po1->po_value);
        $this->assertSame([$areca->id, $planter->id], $po1->lines->pluck('item_id')->all());
        $this->assertEquals([1450, 850], $po1->lines->pluck('rate')->map(fn ($r) => (float) $r)->all());
        $this->assertSame($c1->id, $po1->party_id);
        $this->actingAs($sales)->post("/crm/documents/customer_po/{$po1->id}", ['category' => 'Agreement', 'file' => UploadedFile::fake()->create('GFI-AMC-agreement.pdf', 25, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $this->assertSame(3, Document::where('documentable_type', $po1->getMorphClass())->where('documentable_id', $po1->id)->count());
        $this->actingAs($sales)->get('/crm/pos/'.$po1->id)->assertOk()->assertSee('GFI/PO/2026/118')->assertSee('GF-ARC-5')->assertSee('GF-PLT-14')
            ->assertSee('GFI-PO-118.pdf')->assertSee('GFI-PO-118-signed.jpg')->assertSee('GFI-AMC-agreement.pdf')->assertSee('Create Sales Order');
        $this->assertSame('PO received', $e1->fresh()->status->value);

        // =====================================================================================================
        // CRM, entry 2: Bangalore Botanica (existing customer, Karnataka), above the approval limit
        // =====================================================================================================
        $c2 = $this->newParty(['name' => 'Bangalore Botanica LLP', 'gstin' => '29AAKFB4321M1Z8', 'billing_address' => "Whitefield\nBengaluru 560066",
            'shipping_address' => 'Prestige Tech Park, Whitefield, Bengaluru', 'payment_terms' => '45 days']);
        $this->assertSame('29', $c2->stateCode());
        $vg = $this->newItem($m, $c2, 'BB-VG-M1', 'Vertical garden module 1 x 1 m', '9500', $m['gst18']);
        $drip = $this->newItem($m, $c2, 'BB-DRIP-K', 'Drip irrigation kit', '6000', $m['gst18']);

        $this->actingAs($sales)->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => '', 'party_id' => $c2->id, 'prospect_type' => 'Existing Client',
            'lead_source_id' => \App\Models\LeadSource::where('name', 'Existing Client')->value('id'), 'enquiry_date' => $today, 'lead_rating' => 'Warm',
            'contact_name' => 'Ravi Shankar', 'contact_no' => '+91 98450 22002', 'estimated_value' => '120000', 'approx_requirement' => 'Green wall at reception',
            'service_category_id' => \App\Models\ServiceCategory::where('name', 'Vertical Garden / Green Wall')->value('id'),
            'next_action' => 'Site visit', 'next_action_date' => $this->d(today()->addDays(2))]))->assertSessionHasNoErrors()->assertRedirect();
        $e2 = Enquiry::latest('id')->firstOrFail();
        $this->assertSame(['Ravi Shankar', $c2->id], [$e2->contact_name, $e2->party_id]);
        $this->actingAs($sales)->post("/crm/enquiries/{$e2->id}/activities", ['activity_type' => 'WhatsApp', 'activity_date' => $today,
            'details' => 'Shared green wall photos', 'followup_action' => 'Measure the wall', 'target_date' => $this->d(today()->addDays(2))])->assertSessionHasNoErrors();
        $this->assertSame('Measure the wall', $e2->fresh()->next_action);

        $this->actingAs($sales)->post('/crm/site-visits', ['enquiry_id' => $e2->id, 'visit_date' => $today, 'visit_time' => '15:30', 'status' => 'Done',
            'poc' => 'Ravi Shankar', 'technical_employee_id' => $emp->id, 'area_measurements' => 'Reception wall 5 m x 2 m', 'floors' => 'Ground',
            'indoor_outdoor_area' => 'Indoor', 'lighting_condition' => 'Artificial', 'water_availability' => 'Tap 10 m away',
            'customer_requirement' => '10 sq m green wall with drip', 'next_action' => 'Prepare BOQ'])->assertSessionHasNoErrors()->assertRedirect();
        $v2 = SiteVisit::latest('id')->firstOrFail();
        $this->actingAs($sales)->post("/crm/documents/site_visit/{$v2->id}", ['category' => 'Site photo', 'file' => $this->jpg('reception-wall.jpg')])->assertSessionHasNoErrors();

        $this->actingAs($sales)->post('/crm/quotations', $this->newQuotationPayload(['enquiry_id' => $e2->id, 'contact_name' => 'Ravi Shankar', 'site' => 'Prestige Tech Park reception',
            'quotation_date' => $today, 'valid_until' => $this->d(today()->addDays(15)), 'sales_person_id' => $sales->id,
            'lines' => [$this->qLine($vg, '10', '9500', $m['gst18']), $this->qLine($drip, '2', '6000', $m['gst18'])],
            'cost' => ['material_cost' => '70000', 'labour_cost' => '15000', 'margin_percent' => '20']]))->assertSessionHasNoErrors()->assertRedirect();
        $q2 = Quotation::latest('id')->firstOrFail();
        // 1,07,000 + 18% IGST (other state)
        $this->assertSame('126260.00', $q2->grand_total);
        $this->assertSame('102000.00', $q2->cost->proposed_selling_price);
        $this->actingAs($sales)->post("/crm/documents/quotation/{$q2->id}", ['category' => 'BOQ', 'file' => UploadedFile::fake()->create('botanica-boq.pdf', 20, 'application/pdf')])
            ->assertSessionHasNoErrors();

        // 4 Approval: above 1,00,000 it waits; the Sales user cannot approve, Admin does
        $this->actingAs($sales)->post("/crm/quotations/{$q2->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('Internal Approval', $q2->fresh()->status->value);
        $this->actingAs($admin)->get('/crm/quotations/list?view=approval')->assertOk()->assertSee($q2->quotation_no)->assertDontSee($q1->quotation_no);
        $this->actingAs($sales)->post("/crm/quotations/{$q2->id}/approve")->assertForbidden();
        $this->actingAs($admin)->post("/crm/quotations/{$q2->id}/approve", ['remarks' => 'Margin OK'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $q2->fresh()->status->value);

        // 5 Quotation sent by email (queued), negotiation, accepted
        $this->actingAs($sales)->post("/crm/quotations/{$q2->id}/email", ['to' => 'ravi@botanica.example', 'cc' => '', 'message' => 'Please find our quotation.'])
            ->assertSessionHasNoErrors();
        Queue::assertPushed(SendQuotationMail::class);
        $this->assertSame('Sent', $q2->fresh()->status->value);
        $this->actingAs($sales)->get('/crm/quotations/list?view=sent')->assertOk()->assertSee($q2->quotation_no);
        $this->actingAs($sales)->post("/crm/quotations/{$q2->id}/customer-status", ['status' => 'Negotiation'])->assertSessionHasNoErrors();
        $this->actingAs($sales)->post("/crm/quotations/{$q2->id}/customer-status", ['status' => 'Accepted'])->assertSessionHasNoErrors();

        // 6 PO / Agreement with items and an agreement file
        $this->actingAs($sales)->post('/crm/pos', ['quotation_id' => $q2->id, 'po_no' => 'BB/WO/7781', 'po_date' => $today, 'po_value' => '',
            'valid_from' => '', 'valid_to' => '', 'payment_terms' => '45 days', 'contact_name' => 'Ravi Shankar', 'received_by' => $emp->id, 'received_date' => $today,
            'lines_json' => json_encode([$this->poLine($vg, '10', '9500'), $this->poLine($drip, '2', '6000')]),
            'doc_category' => 'Agreement', 'doc_files' => [UploadedFile::fake()->create('BB-WO-7781.pdf', 40, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $po2 = CustomerPo::latest('id')->firstOrFail();
        $this->assertSame('107000.00', $po2->po_value);
        $this->actingAs($sales)->post("/crm/documents/customer_po/{$po2->id}", ['category' => 'Customer PO', 'file' => $this->jpg('BB-WO-7781-stamp.jpg')])->assertSessionHasNoErrors();
        $this->actingAs($sales)->get('/crm/pos/'.$po2->id)->assertOk()->assertSee('BB-VG-M1')->assertSee('BB-WO-7781.pdf')->assertSee('BB-WO-7781-stamp.jpg');
        $this->assertSame('PO received', $e2->fresh()->status->value);

        // lists, reports
        $this->actingAs($sales)->get('/crm/pos/list')->assertOk()->assertSee('GFI/PO/2026/118')->assertSee('BB/WO/7781');
        $this->actingAs($sales)->get('/crm/enquiries/list')->assertOk()->assertSee($e1->enquiry_no)->assertSee($e2->enquiry_no);
        Cache::flush();
        $year = FinancialYear::forDate(now());
        $range = ['from' => $year->starts_on->format('d/m/Y'), 'to' => $year->ends_on->format('d/m/Y')];
        $lead = $this->actingAs($sales)->get('/reports/crm/lead-report/download?'.http_build_query($range + ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Greenfield Interiors Pvt Ltd', $lead);
        $this->assertStringContainsString('Bangalore Botanica LLP', $lead);
        $this->assertStringContainsString('GFI/PO/2026/118', $lead);

        // ---- Create Sales Order from each CRM PO ----
        $this->actingAs($sales)->post("/crm/pos/{$po1->id}/sales-order")->assertSessionHasNoErrors();
        $so1 = SalesOrder::where('customer_po_id', $po1->id)->with('lines')->firstOrFail();
        $this->actingAs($sales)->post("/crm/pos/{$po2->id}/sales-order")->assertSessionHasNoErrors();
        $so2 = SalesOrder::where('customer_po_id', $po2->id)->with('lines')->firstOrFail();
        $this->assertSame([$c1->id, 'GFI/PO/2026/118', 'Draft', '46000.00'], [$so1->party_id, $so1->po_number, $so1->status, $so1->total_value]);
        $this->assertSame([$c2->id, 'BB/WO/7781', 'Draft', '107000.00'], [$so2->party_id, $so2->po_number, $so2->status, $so2->total_value]);
        $this->assertSame($this->d(today()->addMonths(3)), $so1->end_date->format('d/m/Y'));
        $this->actingAs($sales)->get('/crm/pos/'.$po1->id)->assertOk()->assertSee('Open sales order')->assertSee($so1->so_no);
        $this->actingAs($sales)->post("/crm/pos/{$po1->id}/sales-order")->assertSessionHasErrors('sales_order');

        // =====================================================================================================
        // Sales: sales order (edit, send for approval, Admin approves)
        // =====================================================================================================
        $this->actingAs($sales)->get('/sales/orders/'.$so1->id)->assertOk()->assertSee('GFI/PO/2026/118')->assertSee('From PO');
        // the Sales user checks the order and adds the revision before sending it
        $this->actingAs($sales)->put('/sales/orders/'.$so1->id, ['party_id' => $c1->id, 'po_number' => 'GFI/PO/2026/118', 'start_date' => $today,
            'end_date' => $this->d(today()->addMonths(3)), 'revision_no' => '0', 'revision_date' => $today,
            'lines' => $so1->lines->map(fn ($l) => ['id' => $l->id, 'item_id' => $l->item_id, 'quantity' => (string) (float) $l->quantity, 'rate' => (string) (float) $l->rate,
                'delivery_date' => $this->d(today()->addDays(7))])->all()])->assertSessionHasNoErrors();
        $this->assertSame('0', $so1->fresh()->revision_no);
        foreach ([$so1, $so2] as $so) {
            $this->actingAs($sales)->post("/sales/orders/{$so->id}/submit")->assertSessionHasNoErrors();
            $this->assertSame('Pending approval', $so->fresh()->status);
            $this->actingAs($sales)->post("/sales/orders/{$so->id}/approve")->assertForbidden();
            $this->actingAs($admin)->post("/sales/orders/{$so->id}/approve")->assertSessionHasNoErrors();
            $this->assertSame('Approved', $so->fresh()->status);
        }
        $this->actingAs($sales)->get('/sales/orders/list')->assertOk()->assertSee($so1->so_no)->assertSee($so2->so_no);

        // =====================================================================================================
        // Sales: tax invoice, entry 1 (Sales user, same state: CGST + SGST)
        // =====================================================================================================
        // invoice type is a drop-down; the date is today and read-only for a Sales user
        $html = $this->actingAs($sales)->get('/sales/invoices/create')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<select class="inp\s*" id="invoice_type" name="invoice_type" aria-required="true">\s*<option value="Sales"\s+selected>Sales</option><option value="Credit"\s*>Credit</option><option value="Debit"\s*>Debit</option>\s*</select>#', $html);
        $this->assertMatchesRegularExpression('#id="invoice_date" name="invoice_date"\s+value="'.preg_quote($today, '#').'"[^>]*readonly#', $html);
        $this->assertStringContainsString("Today's date, set automatically. Only an Admin can change it.", $html);

        // PO no list for the party, and the order's lines with their balances
        $label1 = 'PO GFI/PO/2026/118 · '.$so1->so_no;
        $this->assertSame([['id' => $so1->id, 'label' => $label1]], $this->actingAs($sales)->getJson("/sales/parties/{$c1->id}/info")->assertOk()->json('orders'));
        $this->actingAs($sales)->get('/sales/invoices/create?order='.$so1->id)->assertOk()->assertSee($label1, false);
        $lines = $this->actingAs($sales)->getJson("/sales/orders/{$so1->id}/po-lines")->assertOk()->json();
        $this->assertSame([$areca->id, $planter->id], array_column($lines, 'item_id'));
        $this->assertEquals([20, 20], array_column($lines, 'balance'));
        $this->assertEquals([1450, 850], array_column($lines, 'rate'));

        // the Sales user sends a past date: the invoice is dated today all the same
        $this->actingAs($sales)->post('/sales/invoices', $this->invPayload($c1, $so1, [
            ['sales_order_line_id' => $this->soLine($so1, $areca), 'quantity' => '12'],
            ['sales_order_line_id' => $this->soLine($so1, $planter), 'quantity' => '20'],
        ], ['invoice_date' => $this->d(today()->subDays(5)), 'pf_charges' => '500']))->assertSessionHasNoErrors()->assertRedirect();
        $inv1 = SalesInvoice::latest('id')->firstOrFail();
        $this->assertSame(today()->toDateString(), $inv1->invoice_date->toDateString());
        $this->assertMatchesRegularExpression('#^INV/#', $inv1->invoice_no);
        // 12 × 1,450 = 17,400 (18%) + 20 × 850 = 17,000 (12%) + P&F 500 (taxed at 18%)
        $this->assertSame(['Intra-state', '34900.00', '2631.00', '2631.00', '0.00', '40162.00'],
            [$inv1->supply_type, $inv1->taxable_value, $inv1->cgst_amount, $inv1->sgst_amount, $inv1->igst_amount, $inv1->grand_total]);
        $this->assertEquals([8, 0], array_column($this->actingAs($sales)->getJson("/sales/orders/{$so1->id}/po-lines")->json(), 'balance'));
        // more than the PO balance is refused
        $this->actingAs($sales)->post('/sales/invoices', $this->invPayload($c1, $so1, [['sales_order_line_id' => $this->soLine($so1, $areca), 'quantity' => '9']]))
            ->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        $this->assertSame(1, SalesInvoice::count());
        // the browser follows the redirect back to the form, which shows the error once (and uses up the flashed entries)
        $this->actingAs($sales)->get('/sales/invoices/create')->assertOk()->assertSee('only 8 is left on the PO', false);

        // =====================================================================================================
        // Sales: tax invoice, entry 2 (Admin, past date, other state: IGST)
        // =====================================================================================================
        $past = $year->starts_on->copy()->max(today()->subDay());
        $html = $this->actingAs($admin)->get('/sales/invoices/create?order='.$so2->id)->assertOk()->assertSee('PO BB/WO/7781 · '.$so2->so_no, false)->getContent();
        $this->assertDoesNotMatchRegularExpression('#id="invoice_date"[^>]*readonly#', $html);
        $this->actingAs($admin)->post('/sales/invoices', $this->invPayload($c2, $so2, [
            ['sales_order_line_id' => $this->soLine($so2, $vg), 'quantity' => '10'],
            ['sales_order_line_id' => $this->soLine($so2, $drip), 'quantity' => '1'],
        ], ['invoice_date' => $past->format('d/m/Y'), 'vehicle_no' => 'KA 01 AB 7781', 'distance_km' => '840']))->assertSessionHasNoErrors()->assertRedirect();
        $inv2 = SalesInvoice::latest('id')->firstOrFail();
        $this->assertSame($past->toDateString(), $inv2->invoice_date->toDateString());
        // 10 × 9,500 + 1 × 6,000 = 1,01,000; IGST 18% = 18,180
        $this->assertSame(['Inter-state', '101000.00', '0.00', '0.00', '18180.00', '119180.00'],
            [$inv2->supply_type, $inv2->taxable_value, $inv2->cgst_amount, $inv2->sgst_amount, $inv2->igst_amount, $inv2->grand_total]);
        $this->assertEquals([0, 1], array_column($this->actingAs($admin)->getJson("/sales/orders/{$so2->id}/po-lines")->json(), 'balance'));
        // a future date is refused even for Admin
        $this->actingAs($admin)->post('/sales/invoices', $this->invPayload($c2, $so2, [['sales_order_line_id' => $this->soLine($so2, $drip), 'quantity' => '1']],
            ['invoice_date' => $this->d(today()->addDay())]))->assertSessionHasErrorsIn('invoice', 'invoice_date');

        // =====================================================================================================
        // Credit note against entry 1's invoice: 2 palms returned
        // =====================================================================================================
        $this->actingAs($sales)->post('/sales/invoices', $this->invPayload($c1, $so1, [['sales_order_line_id' => $this->soLine($so1, $areca), 'quantity' => '13']],
            ['invoice_type' => 'Credit']))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        $this->actingAs($sales)->post('/sales/invoices', $this->invPayload($c1, $so1, [['sales_order_line_id' => $this->soLine($so1, $areca), 'quantity' => '2']],
            ['invoice_type' => 'Credit']))->assertSessionHasNoErrors();
        $cn = SalesInvoice::latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('#^CN/#', $cn->invoice_no);
        // 2 × 1,450 = 2,900; CGST 261 + SGST 261
        $this->assertSame(['Credit', '2900.00', '261.00', '261.00', '0.00', '3422.00'],
            [$cn->invoice_type, $cn->taxable_value, $cn->cgst_amount, $cn->sgst_amount, $cn->igst_amount, $cn->grand_total]);
        $this->assertSame('2.000', $so1->lines()->where('item_id', $areca->id)->value('credited_quantity'));

        // =====================================================================================================
        // PDF, CSV, list, reports
        // =====================================================================================================
        Cache::flush();
        foreach ([$inv1, $inv2, $cn] as $i) {
            $this->actingAs($sales)->get('/sales/invoices/'.$i->id)->assertOk()->assertSee($i->invoice_no)->assertSee(route('sales.invoices.pdf', $i), false);
            $res = $this->actingAs($sales)->get("/sales/invoices/{$i->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $res->getContent());
        }
        $render = fn ($i) => view('pdf.sales-invoice', ['inv' => $i->fresh(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']), 'company' => Company::current(), 'logo' => null])->render();
        $h = $render($inv1);
        foreach (['TAX INVOICE', 'CGST %', 'Maharashtra (27)', '27AAFCG5678K1Z2', 'GFI/PO/2026/118', '40,162.00'] as $s) {
            $this->assertStringContainsString($s, $h, "invoice 1 PDF: $s");
        }
        $h = $render($inv2);
        foreach (['TAX INVOICE', 'IGST %', 'Karnataka (29)', '29AAKFB4321M1Z8', 'BB/WO/7781', '1,19,180.00'] as $s) {
            $this->assertStringContainsString($s, $h, "invoice 2 PDF: $s");
        }
        $this->assertStringNotContainsString('CGST %', $h);
        $this->assertStringContainsString('CREDIT NOTE', $render($cn));

        $csv = $this->actingAs($sales)->get('/sales/invoices/export')->assertOk()->streamedContent();
        foreach ([$inv1->invoice_no, $inv2->invoice_no, $cn->invoice_no, 'GF-ARC-5', 'BB-VG-M1', '40162.00', '119180.00', '3422.00', 'Inter-state'] as $s) {
            $this->assertStringContainsString($s, $csv, "CSV: $s");
        }
        $this->assertSame(1 + 2 + 2 + 1, count(array_filter(explode("\n", trim($csv))))); // header + 2 + 2 + 1 lines

        $this->actingAs($sales)->get('/sales/invoices/list')->assertOk()->assertSee($inv1->invoice_no)->assertSee($inv2->invoice_no)->assertSee($cn->invoice_no);
        $this->actingAs($sales)->get('/sales/invoices/list?view=Credit')->assertOk()->assertSee($cn->invoice_no)->assertDontSee($inv1->invoice_no);
        $this->actingAs($sales)->get('/sales/invoices/list?view=Sales')->assertOk()->assertSee($inv2->invoice_no)->assertDontSee($cn->invoice_no);

        Cache::flush();
        foreach (['orders', 'sales', 'export', 'credit-debit'] as $r) {
            $this->actingAs($sales)->get('/reports/sales/'.$r)->assertOk();
        }
        $get = fn ($r, $extra = []) => $this->actingAs($sales)->get("/reports/sales/$r/download?".http_build_query($range + ['format' => 'csv'] + $extra))->assertOk()->streamedContent();
        $orders = $get('orders');
        foreach ([$so1->so_no, $so2->so_no, 'GFI/PO/2026/118', 'BB/WO/7781'] as $s) {
            $this->assertStringContainsString($s, $orders, "Sales order report: $s");
        }
        $salesRep = $get('sales');
        foreach ([$inv1->invoice_no, $inv2->invoice_no, '40162.00', '119180.00'] as $s) {
            $this->assertStringContainsString($s, $salesRep, "Sales report: $s");
        }
        $this->assertStringNotContainsString($cn->invoice_no, $salesRep);
        $this->assertStringContainsString($inv2->invoice_no, $get('sales', ['detail' => 'detailed']));
        $this->assertStringNotContainsString($inv1->invoice_no, $get('export'));
        $cd = $get('credit-debit');
        $this->assertStringContainsString($cn->invoice_no, $cd);
        $this->assertStringContainsString('3422.00', $cd);
        $this->assertStringNotContainsString($inv1->invoice_no, $cd);
        Cache::flush();
        foreach (['orders', 'sales', 'credit-debit'] as $r) {
            $this->actingAs($sales)->get("/reports/sales/$r/download?".http_build_query($range + ['format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        }

        // what the two entries left
        $this->assertSame([2, 2, 2, 2, 2, 3], [Enquiry::count(), SiteVisit::count(), Quotation::count(), CustomerPo::count(), SalesOrder::count(), SalesInvoice::count()]);
    }

    /**
     * Bug found in the browser run (03/11/2026): against a PO the page lists every part of the order with an empty
     * quantity and no remove button, and posts all of them (lines_json). A part left empty used to stop the save with
     * "Item 2: enter the quantity.", so a credit note (or invoice) for only some of the PO's parts could not be saved.
     */
    public function test_a_po_part_left_empty_on_the_invoice_page_is_skipped(): void
    {
        $m = $this->masters();
        $sales = $this->sales();
        $party = $this->newParty(['name' => 'Skip Line Traders', 'gstin' => '27AAFCS1111K1Z2', 'billing_address' => 'Hadapsar, Pune']);
        $a = $this->newItem($m, $party, 'SK-A', 'Fern', '100', $m['gst18']);
        $b = $this->newItem($m, $party, 'SK-B', 'Pot', '50', $m['gst12']);
        $this->actingAs($sales)->post('/sales/orders', ['party_id' => $party->id, 'po_number' => 'SK/1', 'start_date' => today()->format('d/m/Y'), 'end_date' => '',
            'revision_no' => '0', 'revision_date' => '', 'lines' => [
                ['item_id' => $a->id, 'quantity' => '10', 'rate' => '', 'delivery_date' => today()->format('d/m/Y')],
                ['item_id' => $b->id, 'quantity' => '10', 'rate' => '', 'delivery_date' => today()->format('d/m/Y')],
            ]])->assertSessionHasNoErrors();
        $so = SalesOrder::latest('id')->firstOrFail();
        $this->actingAs($sales)->post("/sales/orders/{$so->id}/submit");
        $this->actingAs($this->admin())->post("/sales/orders/{$so->id}/approve")->assertSessionHasNoErrors();
        // as the page sends it: both PO parts, the second one left empty
        $page = fn (string $type, string $qa, string $qb) => $this->invPayload($party, $so, [], ['invoice_type' => $type, 'lines_json' => json_encode([
            ['sales_order_line_id' => (string) $this->soLine($so, $a), 'item_id' => (string) $a->id, 'quantity' => $qa, 'rate' => '100'],
            ['sales_order_line_id' => (string) $this->soLine($so, $b), 'item_id' => (string) $b->id, 'quantity' => $qb, 'rate' => '50'],
        ])]);

        $this->actingAs($sales)->post('/sales/invoices', $page('Sales', '4', ''))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->firstOrFail();
        $this->assertSame([1, '400.00', '472.00'], [$inv->lines()->count(), $inv->taxable_value, $inv->grand_total]);
        $this->actingAs($sales)->post('/sales/invoices', $page('Credit', '1', ''))->assertSessionHasNoErrors();
        $cn = SalesInvoice::latest('id')->firstOrFail();
        $this->assertSame(['Credit', 1, '118.00'], [$cn->invoice_type, $cn->lines()->count(), $cn->grand_total]);
        // nothing typed at all: still refused, with the usual message
        $this->actingAs($sales)->post('/sales/invoices', $page('Sales', '', ''))->assertSessionHasErrorsIn('invoice', ['lines' => 'Add at least one item with a quantity.']);
        // a typed quantity on the second part is still checked against its PO balance
        $this->actingAs($sales)->post('/sales/invoices', $page('Sales', '', '11'))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        $this->assertSame(2, SalesInvoice::count());
    }
}
