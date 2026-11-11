<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SiteVisit;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Thu 22/10/2026: no Delete button anywhere in the CRM module (Prospect, Enquiry, Site visit, Quotation incl. its
 * lists, PO / Agreement, Contacts), including the documents row delete. The destroy routes, methods and permissions
 * stay, and the Sales order page keeps its document row delete.
 */
class CrmNoDeleteButtonTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->dir = sys_get_temp_dir().'/crm-nodelete-test-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    /** CrmFixtures::newQuotationPayload() needs a sales person. */
    private function sales(): User
    {
        if ($u = User::where('email', 'priya@company.example')->first()) {
            return $u;
        }
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822000010', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function pdf(string $name): UploadedFile
    {
        file_put_contents($this->dir.'/'.$name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        return new UploadedFile($this->dir.'/'.$name, $name, null, null, true);
    }

    private function assertNoDeleteControls(string $html): void
    {
        $this->assertStringNotContainsString('delForm', $html);
        $this->assertStringNotContainsString('Delete prospect', $html);
        $this->assertStringNotContainsString('Delete enquiry', $html);
        $this->assertStringNotContainsString('Delete contact', $html);
        $this->assertStringNotContainsString('Delete site visit', $html);
        $this->assertStringNotContainsString('Delete quotation', $html);
        $this->assertStringNotContainsString('Delete PO', $html);
        $this->assertStringNotContainsString('/destroy', $html);
        $this->assertDoesNotMatchRegularExpression('#action="[^"]*/crm/documents/\d+"#', $html);
        $this->assertStringNotContainsString('name="_method" value="DELETE"', $html);
        $this->assertDoesNotMatchRegularExpression('/aria-label="Delete /', $html);
    }

    public function test_admin_has_every_delete_permission_used_here(): void
    {
        $admin = $this->admin();
        foreach (['prospect.delete', 'enquiry.delete', 'enquiry.edit', 'site-visit.edit'] as $p) {
            $this->assertTrue($admin->can($p), $p);
        }
    }

    public function test_prospect_page_has_no_delete_button(): void
    {
        $p = $this->prospectFixture();
        $html = $this->actingAs($this->admin())->get(route('crm.prospects.show', $p))->assertOk()->assertSee('Save changes')->getContent();
        $this->assertNoDeleteControls($html);
        // the index page opens a prospect too
        $this->assertNoDeleteControls($this->actingAs($this->admin())->get(route('crm.prospects.index'))->assertOk()->getContent());
    }

    public function test_enquiry_page_with_a_document_has_no_delete_button_or_document_delete(): void
    {
        $e = $this->crmEnquiry();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('crm.documents.store', ['type' => 'enquiry', 'id' => $e->id]), ['category' => 'Other', 'file' => $this->pdf('brief.pdf')])->assertSessionHasNoErrors();
        $doc = Document::firstOrFail();

        $html = $this->actingAs($admin)->get(route('crm.enquiries.show', $e))->assertOk()
            ->assertSee('brief.pdf')->assertSee('Save changes')->assertSee('Upload')->getContent();
        $this->assertNoDeleteControls($html);
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.enquiries.index'))->assertOk()->getContent());
    }

    public function test_site_visit_page_has_no_delete_button_or_document_delete(): void
    {
        $e = $this->crmEnquiry();
        $admin = $this->admin();
        $visit = SiteVisit::forceCreate(['unit_id' => $e->unit_id, 'enquiry_id' => $e->id, 'visit_at' => now(), 'status' => 'Planned']);
        $this->actingAs($admin)->post(route('crm.documents.store', ['type' => 'site_visit', 'id' => $visit->id]), ['category' => 'Site photo', 'file' => $this->pdf('lobby.pdf')])->assertSessionHasNoErrors();

        $html = $this->actingAs($admin)->get(route('crm.site-visits.show', $visit))->assertOk()
            ->assertSee('lobby.pdf')->assertSee('Upload')->getContent();
        $this->assertNoDeleteControls($html);
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.site-visits.index'))->assertOk()->getContent());
        foreach (['all', 'due'] as $view) {
            $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.site-visits.list', ['view' => $view]))->assertOk()->getContent());
        }
    }

    public function test_contact_page_and_list_have_no_delete_button(): void
    {
        $admin = $this->admin();
        $p = $this->prospectFixture();
        $this->actingAs($admin)->post(route('crm.contacts.store'), ['prospect_id' => $p->id, 'party_id' => '', 'name' => 'Kavita Rao', 'mobile' => '9822011111'])->assertSessionHasNoErrors();
        $c = Contact::firstOrFail();

        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.contacts.show', $c))->assertOk()->assertSee('Kavita Rao')->getContent());
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.contacts.index'))->assertOk()->getContent());
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.contacts.create'))->assertOk()->getContent());
        // the prospect page lists the contact in its contacts box, also without a delete
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.prospects.show', $p))->assertOk()->assertSee('Kavita Rao')->getContent());
    }

    public function test_quotation_revisions_with_a_document_and_its_lists_have_no_delete(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('crm.quotations.store'), $this->newQuotationPayload())->assertSessionHasNoErrors();
        $q = Quotation::firstOrFail();
        $this->actingAs($admin)->post(route('crm.documents.store', ['type' => 'quotation', 'id' => $q->id]), ['category' => 'Quotation', 'file' => $this->pdf('quote.pdf')])->assertSessionHasNoErrors();

        $html = $this->actingAs($admin)->get(route('crm.quotations.show', $q))->assertOk()
            ->assertSee('quote.pdf')->assertSee('Upload')->assertSee('Remove item 1')->getContent();
        $this->assertNoDeleteControls($html);
        // the line "Remove item" (x) only drops an unsaved row in the browser: a plain button, not a form
        $this->assertStringContainsString('type="button" data-del-line', $html);

        // Rev 1 is the latest, Rev 0 read-only; neither shows a delete
        $this->actingAs($admin)->post(route('crm.quotations.revise', $q))->assertSessionHasNoErrors();
        $rev1 = Quotation::where('is_latest', true)->firstOrFail();
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.quotations.show', $rev1))->assertOk()->getContent());
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.quotations.show', $q))->assertOk()->getContent());

        // one pending approval (large amount) and one with the customer, so the list views have rows
        $this->actingAs($admin)->post(route('crm.quotations.store'), $this->newQuotationPayload(['lines' => [['description' => 'Large job', 'qty' => '1', 'rate' => '250000']]]))->assertSessionHasNoErrors();
        $big = Quotation::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('crm.quotations.submit', $big))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('crm.quotations.submit', $rev1))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('crm.quotations.sent', $rev1))->assertSessionHasNoErrors();

        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.quotations.list', ['view' => 'approval']))->assertOk()->assertSee($big->quotation_no)->getContent());
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.quotations.list', ['view' => 'sent']))->assertOk()->assertSee($rev1->quotation_no)->getContent());
        foreach (['all', 'po-pending'] as $view) {
            $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.quotations.list', ['view' => $view]))->assertOk()->getContent());
        }
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.quotations.index'))->assertOk()->getContent());
        // the status part of a quotation awaiting approval and of one with the customer
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.quotations.show', $big))->assertOk()->getContent());
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.quotations.show', $rev1))->assertOk()->getContent());
    }

    /** An accepted quotation and a PO against it. */
    private function customerPo(): CustomerPo
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('crm.quotations.store'), $this->newQuotationPayload())->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('crm.quotations.submit', $q))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('crm.quotations.sent', $q))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('crm.quotations.customer-status', $q), ['status' => 'Accepted'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('crm.customer-pos.store'), [
            'quotation_id' => $q->id, 'po_no' => 'KP/PO/77', 'po_date' => '10/10/2026', 'po_value' => '455', 'po_quantity' => '10', 'received_by' => $this->crmEmployee()->id,
            'received_date' => '10/10/2026', 'valid_from' => '01/11/2026', 'valid_to' => '31/10/2027', 'payment_terms' => '50% advance', 'contact_name' => 'Kavita Rao',
        ])->assertSessionHasNoErrors();

        return CustomerPo::latest('id')->firstOrFail();
    }

    public function test_customer_po_page_with_a_document_and_its_list_have_no_delete(): void
    {
        $admin = $this->admin();
        $po = $this->customerPo();
        $this->actingAs($admin)->post(route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]), ['category' => 'Agreement', 'file' => $this->pdf('agreement.pdf')])->assertSessionHasNoErrors();

        $html = $this->actingAs($admin)->get(route('crm.customer-pos.show', $po))->assertOk()
            ->assertSee('KP/PO/77')->assertSee('agreement.pdf')->assertSee('Upload')->getContent();
        $this->assertNoDeleteControls($html);
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.customer-pos.index'))->assertOk()->assertSee('KP/PO/77')->getContent());
        $this->assertNoDeleteControls($this->actingAs($admin)->get(route('crm.customer-pos.create'))->assertOk()->getContent());
    }

    public function test_sales_order_page_still_shows_the_document_row_delete(): void
    {
        $admin = $this->admin();
        Company::create(['name' => 'Nursery Components Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'address' => 'Plot 21, MIDC Chakan, Pune']);
        $party = Party::create(['name' => 'Apex Auto Components', 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Chakan, Pune', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $hsn = HsnSacCode::create(['code' => '8708', 'type' => 'HSN', 'description' => 'Parts of motor vehicles', 'tax_structure_id' => $gst18->id, 'is_active' => true]);
        $item = Item::create(['party_id' => $party->id, 'description' => 'Part BRK-2210', 'item_no' => 'BRK-2210', 'type' => 'Sales', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => Uom::where('code', 'NOS')->value('id'), 'tax_structure_id' => $gst18->id, 'price' => 42.50, 'status' => 'Active', 'min_stock' => 100]);
        $today = today()->format('d/m/Y');
        $this->actingAs($admin)->post('/sales/orders', ['party_id' => $party->id, 'po_number' => '4500123', 'start_date' => $today, 'end_date' => '', 'revision_no' => '0', 'revision_date' => '',
            'lines' => [['item_id' => $item->id, 'quantity' => '100', 'rate' => '42.50', 'delivery_date' => $today]]])->assertSessionHasNoErrors();
        $so = SalesOrder::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('crm.documents.store', ['type' => 'sales_order', 'id' => $so->id]), ['category' => 'Customer PO', 'file' => $this->pdf('po.pdf')])->assertSessionHasNoErrors();
        $doc = Document::firstOrFail();

        $this->actingAs($admin)->get("/sales/orders/{$so->id}")->assertOk()
            ->assertSee('po.pdf')
            ->assertSee('action="'.route('crm.documents.destroy', $doc).'"', false)
            ->assertSee('aria-label="Delete po.pdf"', false);
    }

    public function test_delete_routes_of_contact_site_visit_quotation_po_and_document_still_work(): void
    {
        $admin = $this->admin();

        $p = $this->prospectFixture();
        $this->actingAs($admin)->post(route('crm.contacts.store'), ['prospect_id' => $p->id, 'party_id' => '', 'name' => 'Spare Contact', 'mobile' => '9822012345'])->assertSessionHasNoErrors();
        $c = Contact::firstOrFail();
        $this->actingAs($admin)->delete(route('crm.contacts.destroy', $c))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull(Contact::find($c->id));

        $e = $this->crmEnquiry();
        $visit = SiteVisit::forceCreate(['unit_id' => $e->unit_id, 'enquiry_id' => $e->id, 'visit_at' => now(), 'status' => 'Planned']);
        $this->actingAs($admin)->post(route('crm.documents.store', ['type' => 'site_visit', 'id' => $visit->id]), ['category' => 'Site photo', 'file' => $this->pdf('lobby.pdf')])->assertSessionHasNoErrors();
        $doc = Document::firstOrFail();
        $this->actingAs($admin)->delete(route('crm.documents.destroy', $doc))->assertSessionHasNoErrors();
        $this->assertNull(Document::find($doc->id));
        $this->actingAs($admin)->delete(route('crm.site-visits.destroy', $visit))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull(SiteVisit::find($visit->id));

        $po = $this->customerPo();
        $q = $po->quotation;
        $this->actingAs($admin)->post(route('crm.documents.store', ['type' => 'customer_po', 'id' => $po->id]), ['category' => 'Agreement', 'file' => $this->pdf('agreement.pdf')])->assertSessionHasNoErrors();
        $poDoc = Document::latest('id')->firstOrFail();
        $this->actingAs($admin)->delete(route('crm.documents.destroy', $poDoc))->assertSessionHasNoErrors();
        $this->assertNull(Document::find($poDoc->id));
        $this->actingAs($admin)->delete(route('crm.customer-pos.destroy', $po))->assertSessionHasNoErrors()->assertRedirect(route('crm.customer-pos.index'));
        $this->assertNull(CustomerPo::find($po->id));
        $this->actingAs($admin)->delete(route('crm.quotations.destroy', $q))->assertSessionHasNoErrors()->assertRedirect(route('crm.quotations.index'));
        $this->assertNull(Quotation::find($q->id));
    }

    public function test_delete_routes_still_work(): void
    {
        $admin = $this->admin();
        $e = $this->crmEnquiry();
        $this->actingAs($admin)->delete(route('crm.enquiries.destroy', $e))->assertRedirect(route('crm.enquiries.index'));
        $this->assertNull(Enquiry::find($e->id));

        $p = Prospect::create(['name' => 'Spare Prospect', 'market' => 'Domestic', 'phone_1' => '9822099999']);
        $this->actingAs($admin)->delete(route('crm.prospects.destroy', $p))->assertRedirect(route('crm.prospects.index'));
        $this->assertNull(Prospect::find($p->id));

        // an enquiry document can still be deleted by the backend route
        $e2 = $this->crmEnquiry();
        $this->actingAs($admin)->post(route('crm.documents.store', ['type' => 'enquiry', 'id' => $e2->id]), ['category' => 'Other', 'file' => $this->pdf('x.pdf')])->assertSessionHasNoErrors();
        $doc = Document::firstOrFail();
        $this->actingAs($admin)->delete(route('crm.documents.destroy', $doc))->assertSessionHasNoErrors();
        $this->assertNull(Document::find($doc->id));
    }
}
