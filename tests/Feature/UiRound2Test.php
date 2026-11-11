<?php

namespace Tests\Feature;

use App\Models\ApprovalRule;
use App\Models\City;
use App\Models\Contact;
use App\Models\IndustryType;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\User;
use App\Support\ApprovalNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sat's screen requests, round 2 (18/10/2026). */
class UiRound2Test extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    public function test_picking_a_customer_fills_its_known_details_and_a_prospect_fills_nothing(): void
    {
        $type = IndustryType::first();
        $p = Prospect::create(['name' => 'Kaveri Precision', 'market' => 'Domestic', 'contact_person' => 'Kavita Rao', 'phone_1' => '9822012345',
            'address' => 'Peenya, Bengaluru', 'industry_type' => $type->name]);
        // an earlier enquiry of the same prospect gives what the prospect does not hold
        $city = City::where('is_active', true)->first();
        $this->crmEnquiry(['prospect_id' => $p->id, 'industry' => 'Auto parts', 'website' => 'https://kaveri.example', 'city_id' => $city->id]);

        // a Prospect's details are no longer given to the enquiry (22/10/2026)
        $this->actingAs($this->admin())->getJson('/crm/enquiries/client-info?prospect='.$p->id)->assertNotFound();

        // a customer: its billing address, and its decision maker from Contacts
        $c = Party::create(['name' => 'Apex Auto', 'type' => 'Customer', 'status' => 'Active', 'billing_address' => 'Chakan, Pune']);
        Contact::create(['party_id' => $c->id, 'name' => 'Ravi Shah', 'mobile' => '9822099999', 'is_decision_maker' => true]);
        $this->getJson('/crm/enquiries/client-info?party='.$c->id)->assertOk()
            ->assertJson(['contact_name' => 'Ravi Shah', 'contact_no' => '9822099999', 'address' => 'Chakan, Pune', 'industry' => '', 'website' => '', 'city' => null]);

        $this->getJson('/crm/enquiries/client-info')->assertNotFound();
        $this->getJson('/crm/enquiries/client-info?party=999999')->assertNotFound();
        $this->get('/crm/enquiries/create')->assertSee('data-client-info=', false);
    }

    public function test_the_enquiry_keeps_its_typed_number_and_the_flow_shows_the_prospects_read_only(): void
    {
        $p = $this->prospectFixture();
        $p->update(['phone_1' => '9822012345']);
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => $p->id, 'contact_no' => '9000000000']))->assertSessionHasNoErrors();
        $e = \App\Models\Enquiry::latest('id')->first();
        // a typed number is ignored with a Prospect; the enquiry takes the Prospect's (22/10/2026)
        $this->assertSame('9822012345', $e->contact_no);
        $this->assertSame('9822012345', $e->clientPhone());

        // site visit and quotation start with the contact person only; the number is shown from the Prospect
        $this->get('/crm/site-visits/create?enquiry='.$e->id)->assertOk()->assertSee('Phone 9822012345 (from the Prospect)')->assertDontSee('value="Kavita Rao, ', false);
        $this->get('/crm/quotations/create?enquiry='.$e->id)->assertOk()->assertSee('Phone 9822012345 (from the Prospect)');
    }

    public function test_crm_menu_follows_the_enquiry_steps_and_option_rows_are_dropdowns(): void
    {
        $html = $this->actingAs($this->admin())->get('/crm/enquiries/create')->assertOk()->getContent();
        // PO / Agreement is back in CRM as step 6 (01/11/2026), and also has its own PO menu.
        $steps = ['>1 Enquiry / Lead<', '>2 Site visit<', '>3 ', '>4 Approval<', '>5 Quotation sent<', '>6 PO / Agreement<', '>Prospect<', '>Contacts<'];
        $at = array_map(fn ($s) => strpos($html, $s), $steps);
        $this->assertNotContains(false, $at);
        $sorted = $at;
        sort($sorted);
        $this->assertSame($sorted, $at);
        $this->assertStringContainsString(route('crm.quotations.list', ['view' => 'approval']), $html);

        // the filtered steps are marked when open, the broad Quotation item is not
        $html = $this->get('/crm/quotations/list?view=sent')->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>5 Quotation sent</', $html);
        $this->assertDoesNotMatchRegularExpression('/aria-current="page"\s*>3 /', $html);

        // option rows became dropdowns, with the same values and defaults
        $this->assertMatchesRegularExpression('/<select class="inp\s*" id="lead_rating" name="lead_rating"/', $this->get('/crm/enquiries/create')->getContent());
        $this->assertStringContainsString('<select class="inp ', $this->get('/crm/enquiries/create')->getContent());
        $visit = $this->get('/crm/site-visits/create')->getContent();
        $this->assertMatchesRegularExpression('/<select[^>]*id="status" name="status" required>\s*<option value="Planned" selected>/', $visit);
        $this->assertStringNotContainsString('role="radiogroup"', $this->get('/crm/prospects/create')->getContent());
    }

    public function test_submit_asks_with_the_amount_and_the_approval_limit(): void
    {
        $rule = new ApprovalRule(['is_required' => true, 'approve_above_amount' => 100000]);
        $this->assertSame('This quotation will go to an approver: its total ₹1,50,000.00 is above the approval limit of ₹1,00,000.00. Continue?',
            ApprovalNotice::message($rule, 150000, 'INR', 'quotation'));
        // re-test S1: within the limit, or with approval off, there is no question at all
        $this->assertNull(ApprovalNotice::message($rule, 50000, 'INR', 'sales order'));
        $this->assertStringContainsString('in USD always needs approval', ApprovalNotice::message($rule, 10, 'USD', 'quotation'));
        $this->assertStringContainsString('every quotation needs approval', ApprovalNotice::message(new ApprovalRule(['is_required' => true]), 10, 'INR', 'quotation'));
        $this->assertNull(ApprovalNotice::message(new ApprovalRule(['is_required' => false]), 10, 'INR', 'quotation'));

        // the fixed line is gone from the quotation page; the submit button carries the message instead
        $q = app(\App\Services\QuotationService::class);
        $this->assertStringNotContainsString('Approval needed above', file_get_contents(resource_path('views/crm/quotations/status.blade.php')));
        $this->assertStringNotContainsString('Approval needed above', file_get_contents(resource_path('views/sales/page.blade.php')));
    }

    public function test_retest_fixes_phone_width_prospect_hint_locked_phone_and_empty_file(): void
    {
        // B1: fields never grow wider than the screen for a long selected label
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.fgrid>.field,.picker{min-width:0}', $css);
        $this->assertStringContainsString('.pkbtn{min-width:0;max-width:100%}', $css);

        // S2: no hint line under Prospect; the Add button stays
        $p = $this->prospectFixture();
        $p->update(['phone_1' => '9822012345']);
        $html = $this->actingAs($this->admin())->get('/crm/enquiries/create')->getContent();
        $this->assertStringNotContainsString('Pick one. Type to search', $html);
        $this->assertStringContainsString('data-open="#prospectLayer"', $html);

        // S3 replaced (22/10/2026): with a Prospect the contact no is hidden, not read-only, and taken from the Prospect
        $this->getJson('/crm/enquiries/client-info?prospect='.$p->id)->assertNotFound();
        $this->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => $p->id]))->assertSessionHasNoErrors();
        $e = \App\Models\Enquiry::latest('id')->first();
        $this->assertSame('9822012345', $e->contact_no);
        $html = $this->get('/crm/enquiries/'.$e->id)->getContent();
        $this->assertMatchesRegularExpression('/id="contactPair"\s+hidden/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="contact_no"[^>]*readonly/', $html);

        // S6: an empty file says it is empty
        \Illuminate\Support\Facades\Storage::fake('local');
        $path = tempnam(sys_get_temp_dir(), 'empty');
        $this->post("/crm/documents/enquiry/{$e->id}", ['category' => 'Other', 'file' => new \Illuminate\Http\UploadedFile($path, 'empty.pdf', null, null, true)])
            ->assertSessionHasErrors(['file' => 'The file is empty.']);
        @unlink($path);
    }
}

