<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\IndustryType;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enquiry: picking a Prospect no longer fills its details (22/10/2026); an existing customer still fills.
 * With a Prospect, Contact name / no are hidden and copied from the Prospect when saved.
 */
class EnquiryProspectNoAutofillTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function prospectWithDetails(): Prospect
    {
        return Prospect::create(['name' => 'Kaveri Precision', 'market' => 'Domestic', 'contact_person' => 'Prospect Person', 'phone_1' => '9822012345',
            'address' => 'Peenya, Bengaluru', 'industry_type' => IndustryType::first()->name]);
    }

    public function test_prospect_client_info_is_not_served(): void
    {
        $p = $this->prospectWithDetails();
        $this->crmEnquiry(['prospect_id' => $p->id]);

        $this->actingAs($this->admin())->getJson('/crm/enquiries/client-info?prospect='.$p->id)->assertNotFound();
        $this->getJson('/crm/enquiries/client-info?prospect=999999')->assertNotFound();
        $this->getJson('/crm/enquiries/client-info')->assertNotFound();
    }

    public function test_customer_client_info_still_fills_the_customers_details(): void
    {
        $c = Party::create(['name' => 'Apex Auto', 'type' => 'Customer', 'status' => 'Active', 'billing_address' => 'Chakan, Pune']);
        Contact::create(['party_id' => $c->id, 'name' => 'Ravi Shah', 'mobile' => '9822099999', 'is_decision_maker' => true]);
        $city = City::where('is_active', true)->first();
        $type = IndustryType::first();
        // an earlier enquiry of the customer gives industry, website, industry type and city
        $this->crmEnquiry(['prospect_id' => '', 'party_id' => $c->id, 'industry' => 'Auto parts', 'website' => 'https://apex.example',
            'industry_type_id' => $type->id, 'city_id' => $city->id]);

        $res = $this->actingAs($this->admin())->getJson('/crm/enquiries/client-info?party='.$c->id)->assertOk()
            ->assertJson(['contact_name' => 'Ravi Shah', 'contact_no' => '9822099999', 'address' => 'Chakan, Pune', 'industry' => 'Auto parts',
                'website' => 'https://apex.example', 'industry_type_id' => (string) $type->id, 'city' => ['id' => (string) $city->id, 'label' => $city->name]]);
        $this->assertArrayNotHasKey('phone_locked', $res->json());
        $this->getJson('/crm/enquiries/client-info?party=999999')->assertNotFound();
        // both given: only the customer counts
        $this->getJson('/crm/enquiries/client-info?party='.$c->id.'&prospect=1')->assertOk()->assertJsonPath('contact_name', 'Ravi Shah');
    }

    public function test_an_enquiry_with_a_prospect_takes_the_contact_from_the_prospect(): void
    {
        $p = $this->prospectWithDetails();
        // the boxes are hidden and not sent with a Prospect (22/10/2026); a typed pair is ignored, even an invalid one
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload([
            'prospect_id' => $p->id, 'contact_no' => 'abc', 'contact_name' => 'Typed Person', 'address' => 'Typed address',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $e = Enquiry::latest('id')->first();
        $this->assertSame($p->id, $e->prospect_id);
        $this->assertSame('9822012345', $e->contact_no);
        $this->assertSame('Prospect Person', $e->contact_name);
        $this->assertSame('Typed address', $e->address);

        // a Prospect without a contact person leaves the name empty, with no error
        $bare = Prospect::create(['name' => 'Bare Prospect', 'market' => 'Domestic', 'phone_1' => '9822033333']);
        $this->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => $bare->id]))->assertSessionHasNoErrors();
        $e = Enquiry::latest('id')->first();
        $this->assertSame('9822033333', $e->contact_no);
        $this->assertNull($e->contact_name);

        // a customer enquiry still saves the typed contact, and it is validated
        $c = Party::create(['name' => 'Apex Auto', 'type' => 'Customer', 'status' => 'Active']);
        $this->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => '', 'party_id' => $c->id, 'contact_name' => 'Ravi Shah', 'contact_no' => '9822099999']))
            ->assertSessionHasNoErrors();
        $e = Enquiry::latest('id')->first();
        $this->assertSame('Ravi Shah', $e->contact_name);
        $this->assertSame('9822099999', $e->contact_no);
        $this->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => '', 'party_id' => $c->id, 'contact_no' => 'abc']))->assertSessionHasErrors('contact_no');
    }

    public function test_the_form_hides_the_contact_while_a_prospect_is_selected(): void
    {
        $p = $this->prospectWithDetails();
        $e = $this->crmEnquiry(['prospect_id' => $p->id]);

        $html = $this->actingAs($this->admin())->get('/crm/enquiries/'.$e->id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$p->id.'"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/id="contactPair"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="contact_no"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="contact_name"[^>]*disabled/', $html);

        // also after a failed save with a Prospect (old input)
        $html = $this->from('/crm/enquiries/create')->followingRedirects()
            ->post('/crm/enquiries', $this->enquiryPayload(['prospect_id' => $p->id, 'lead_rating' => '']))->getContent();
        $this->assertMatchesRegularExpression('/id="contactPair"\s+hidden/', $html);

        // no Prospect yet: the pair shows and is sent; the create page still wires the customer fill
        $html = $this->get('/crm/enquiries/create')->assertSee('data-client-info=', false)->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="contactPair"\s+hidden/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="contact_no"[^>]*disabled/', $html);
    }

    public function test_an_existing_enquiry_keeps_its_stored_contact_and_address(): void
    {
        $p = $this->prospectWithDetails();
        $e = $this->crmEnquiry(['prospect_id' => $p->id, 'address' => 'Tower B, Hinjewadi Phase 1, Pune']);
        // stored earlier, when the contact was typed with a Prospect
        Enquiry::whereKey($e->id)->update(['contact_name' => 'Kavita Rao', 'contact_no' => '+91 98220 11111']);
        $p->update(['phone_1' => '9111111111', 'contact_person' => 'Someone Else', 'address' => 'New prospect address']);

        $html = $this->actingAs($this->admin())->get('/crm/enquiries/'.$e->id)->assertOk()->getContent();
        $this->assertStringContainsString('Tower B, Hinjewadi Phase 1, Pune', $html);
        $this->assertStringNotContainsString('New prospect address', $html);

        // saving with the same Prospect keeps the stored contact, not the Prospect's new one
        $this->put('/crm/enquiries/'.$e->id, $this->enquiryPayload(['prospect_id' => $p->id, 'address' => 'Tower B, Hinjewadi Phase 1, Pune']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $e->refresh();
        $this->assertSame('+91 98220 11111', $e->contact_no);
        $this->assertSame('Kavita Rao', $e->contact_name);
        $this->assertSame('Tower B, Hinjewadi Phase 1, Pune', $e->address);

        // picking another Prospect copies its contact, and the change is logged
        $other = Prospect::create(['name' => 'Shree Castings', 'market' => 'Domestic', 'contact_person' => 'Anil Joshi', 'phone_1' => '9822077777']);
        $this->put('/crm/enquiries/'.$e->id, $this->enquiryPayload(['prospect_id' => $other->id]))->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame('Anil Joshi', $e->contact_name);
        $this->assertSame('9822077777', $e->contact_no);
        $log = \App\Models\ActivityLog::where('action', 'enquiry.updated')->latest('id')->first();
        $this->assertSame(['old' => 'Kavita Rao', 'new' => 'Anil Joshi'], $log->changes['Contact name']);
    }
}
