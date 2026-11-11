<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Unit;
use App\Models\User;
use App\Services\PageSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin > Page setup controls every form on the Enquiries page (22/10/2026): the follow-up box
 * ('enquiry-followup'), the enquiry form ('enquiry') and the contacts box ('contact'). Hidden fields are not
 * shown, not validated, not written on insert and left unchanged on update. Runs on SQLite in memory only.
 */
class EnquiryPageSetupTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function sales(): User
    {
        if ($u = User::where('email', 'priya@company.example')->first()) {
            return $u;
        }
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822055555', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    /** Field => [visible, required]; fields not given keep their current choice. */
    private function pageSetup(string $page, array $choices): void
    {
        $fields = [];
        foreach (app(PageSetupService::class)->fields($page) as $f => $cur) {
            [$vis, $req] = $choices[$f] ?? [$cur['visible'], $cur['required']];
            $fields[$f] = ['visible' => $vis ? '1' : '0', 'required' => $req ? '1' : '0'];
        }
        $this->actingAs($this->admin())->put("/admin/page-setup/$page", ['fields' => $fields])->assertSessionHasNoErrors()->assertRedirect();
        // a fresh service for the next request (the service keeps a per-request copy)
        app()->forgetInstance(PageSetupService::class);
    }

    private function followup(Enquiry $e, array $data)
    {
        return $this->actingAs($this->admin())->post("/crm/enquiries/{$e->id}/activities", array_merge(['activity_type' => 'Call', 'activity_date' => '06/10/2026'], $data));
    }

    // ---- Follow-up box ----

    public function test_enquiry_followup_is_listed_in_page_setup_and_its_edit_page_opens(): void
    {
        $this->actingAs($this->admin())->get('/admin/page-setup')->assertOk()->assertSee('Enquiry follow-up')->assertSee('/admin/page-setup/enquiry-followup', false);
        $this->actingAs($this->admin())->get('/admin/page-setup/enquiry-followup')->assertOk()->assertSee('Save page setup')
            ->assertSee('Follow-up details')->assertSee('Next action and target date');

        // activity type and date are fixed: cannot be hidden
        $this->pageSetup('enquiry-followup', ['activity_type' => [false, false], 'activity_date' => [false, false]]);
        $f = app(PageSetupService::class)->fields('enquiry-followup');
        $this->assertTrue($f['activity_type']['visible'] && $f['activity_type']['required']);
        $this->assertTrue($f['activity_date']['visible'] && $f['activity_date']['required']);
        $this->followup($this->crmEnquiry(), ['activity_type' => '', 'activity_date' => '', 'details' => 'x'])->assertSessionHasErrorsIn('followup', ['activity_type', 'activity_date']);
    }

    public function test_defaults_keep_todays_rules(): void
    {
        $e = $this->crmEnquiry();
        $html = $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="details"[^>]*required/', $html);
        $this->assertStringContainsString('id="f_next_action"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="f_next_action"[^>]*required/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="target_date"[^>]*required/', $html);

        $this->followup($e, [])->assertSessionHasErrorsIn('followup', ['details']);
        $this->followup($e, ['details' => 'Spoke', 'followup_action' => 'Send photos'])->assertSessionHasErrorsIn('followup', 'target_date');
        $this->followup($e, ['details' => 'Spoke', 'target_date' => '09/10/2026'])->assertSessionHasErrorsIn('followup', 'followup_action');
        $this->followup($e, ['details' => 'Spoke'])->assertSessionHasNoErrors();
        $this->assertSame('Call to fix site visit', $e->fresh()->next_action); // no next action given: unchanged
        $this->followup($e, ['details' => 'Spoke', 'followup_action' => 'Send photos', 'target_date' => '09/10/2026'])->assertSessionHasNoErrors();
        $this->assertSame('Send photos', $e->fresh()->next_action);
        $this->assertSame('2026-10-09', $e->fresh()->next_action_date->format('Y-m-d'));
        $this->assertSame(2, $e->activities()->count());
    }

    public function test_details_hidden_is_not_shown_not_required_and_saved_empty(): void
    {
        $e = $this->crmEnquiry();
        $this->followup($e, ['details' => 'Earlier call'])->assertSessionHasNoErrors();
        $this->pageSetup('enquiry-followup', ['details' => [false, false]]);

        $html = $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('id="details"', $html);
        $this->assertStringNotContainsString('<th>Details</th>', $html);
        $this->assertStringNotContainsString('Earlier call', $html);

        $this->followup($e, ['details' => 'sneaked in'])->assertSessionHasNoErrors();
        $a = Activity::latest('id')->first();
        $this->assertSame('', $a->details);
        $log = ActivityLog::where('action', 'enquiry.followup_added')->latest('id')->first();
        $this->assertSame('Call on 06/10/2026', $log->changes['Follow-up']['new']);
    }

    public function test_details_optional_blank_saves_empty_and_mandatory_is_required(): void
    {
        $e = $this->crmEnquiry();
        $this->pageSetup('enquiry-followup', ['details' => [true, false]]);
        $this->followup($e, ['details' => ''])->assertSessionHasNoErrors();
        $this->assertSame('', Activity::latest('id')->first()->details);

        $this->pageSetup('enquiry-followup', ['details' => [true, true]]);
        $this->followup($e, ['details' => ''])->assertSessionHasErrorsIn('followup', 'details');
    }

    public function test_next_action_hidden_leaves_the_enquiry_next_action_unchanged(): void
    {
        $e = $this->crmEnquiry();
        $this->pageSetup('enquiry-followup', ['next_action' => [false, false]]);

        $html = $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('id="f_next_action"', $html);
        $this->assertStringNotContainsString('id="target_date"', $html);

        // even a half-filled pair is ignored, not refused
        $this->followup($e, ['details' => 'Spoke', 'followup_action' => 'Sneaky', 'target_date' => 'bad'])->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame('Call to fix site visit', $e->next_action);
        $this->assertSame('2026-10-07', $e->next_action_date->format('Y-m-d'));
        $a = Activity::latest('id')->first();
        $this->assertNull($a->next_action);
        $this->assertNull($a->target_date);

        $html = $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->getContent();
        $this->assertStringNotContainsString('<th>Next action</th>', $html);
        $this->assertStringContainsString('<th>Details</th>', $html);
    }

    public function test_next_action_mandatory_needs_both_action_and_target_date(): void
    {
        $e = $this->crmEnquiry();
        $this->pageSetup('enquiry-followup', ['next_action' => [true, true]]);

        $html = $this->actingAs($this->admin())->get("/crm/enquiries/{$e->id}")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="f_next_action"[^>]*required/', $html);
        $this->assertMatchesRegularExpression('/id="target_date"[^>]*required/', $html);

        $this->followup($e, ['details' => 'Spoke'])->assertSessionHasErrorsIn('followup', ['followup_action', 'target_date']);
        $this->followup($e, ['details' => 'Spoke', 'followup_action' => 'Visit'])->assertSessionHasErrorsIn('followup', 'target_date');
        $this->followup($e, ['details' => 'Spoke', 'target_date' => '10/10/2026'])->assertSessionHasErrorsIn('followup', 'followup_action');
        $this->assertSame(0, $e->activities()->count());
        $this->followup($e, ['details' => 'Spoke', 'followup_action' => 'Visit', 'target_date' => '10/10/2026'])->assertSessionHasNoErrors();
        $this->assertSame('Visit', $e->fresh()->next_action);
    }

    // ---- Enquiry form ----

    public function test_enquiry_form_hidden_field_is_not_shown_saved_or_changed_and_mandatory_field_is_required(): void
    {
        $old = $this->crmEnquiry(); // made before: website www.kaveri.example
        $this->pageSetup('enquiry', ['website' => [false, false], 'remarks' => [true, true]]);

        $this->actingAs($this->admin())->get('/crm/enquiries/create')->assertOk()->assertDontSee('id="website"', false)
            ->assertSee('id="remarks"', false);
        $html = $this->actingAs($this->admin())->get("/crm/enquiries/{$old->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('id="website"', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="remarks"[^>]*required/', $html);

        // remarks mandatory
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload(['remarks' => '']))->assertSessionHasErrors('remarks');
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload(['remarks' => 'Wants rental']))->assertSessionHasNoErrors()->assertSessionDoesntHaveErrors('website');

        // website not written on insert, even when sent (and not validated: a bad value passes)
        $new = Enquiry::latest('id')->first();
        $this->assertNotSame($old->id, $new->id);
        $this->assertNull($new->website);
        $this->actingAs($this->admin())->post('/crm/enquiries', $this->enquiryPayload(['remarks' => 'x', 'website' => 'not a url !!']))->assertSessionHasNoErrors();

        // unchanged on update
        $this->actingAs($this->admin())->put("/crm/enquiries/{$old->id}", $this->enquiryPayload(['remarks' => 'Updated', 'website' => 'www.changed.example']))->assertSessionHasNoErrors();
        $old->refresh();
        $this->assertSame('Updated', $old->remarks);
        $this->assertStringContainsString('kaveri.example', (string) $old->website);
    }

    // ---- Contacts box ----

    public function test_contacts_box_on_the_enquiry_page_follows_contact_setup(): void
    {
        $prospect = $this->prospectFixture();
        $e = $this->crmEnquiry();
        $this->pageSetup('contact', ['email' => [false, false], 'designation' => [false, false]]);

        $this->actingAs($this->sales())->post('/crm/contacts', ['prospect_id' => $prospect->id, 'party_id' => '', 'name' => 'Kavita Rao',
            'mobile' => '9822011111', 'email' => 'kavita@kaveri.example', 'designation' => 'Admin head'])->assertSessionHasNoErrors()->assertRedirect();
        $c = Contact::firstOrFail();
        $this->assertNull($c->email);
        $this->assertNull($c->designation);

        $html = $this->actingAs($this->sales())->get("/crm/enquiries/{$e->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Contacts / POC', $html);
        $this->assertStringContainsString('Kavita Rao', $html);
        $this->assertStringContainsString('<th>Mobile</th>', $html);
        $this->assertStringNotContainsString('<th>Email</th>', $html);
        $this->assertStringNotContainsString('<th>Designation</th>', $html);

        // a value saved before it was hidden stays, but does not show in the box
        $c->forceFill(['email' => 'old@kaveri.example'])->save();
        $this->actingAs($this->sales())->get("/crm/enquiries/{$e->id}")->assertDontSee('old@kaveri.example');
        $this->actingAs($this->sales())->put("/crm/contacts/{$c->id}", ['prospect_id' => $prospect->id, 'name' => 'Kavita Rao', 'mobile' => '9822011111', 'email' => 'new@kaveri.example'])->assertSessionHasNoErrors();
        $this->assertSame('old@kaveri.example', $c->fresh()->email);
    }
}
