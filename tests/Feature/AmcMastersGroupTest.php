<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/** (22/11/2026) The AMC masters grouped under "Masters", and the photo preview on the AMC Employee page. */
class AmcMastersGroupTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_admin_sees_all_five_masters_on_the_masters_page_and_tabs(): void
    {
        $admin = $this->admin();
        $this->amcMasters();
        $html = $this->actingAs($admin)->get(route('amc.masters.home'))->assertOk()->getContent();
        foreach (['verticals', 'planters', 'plants', 'types', 'employees'] as $k) {
            $this->assertStringContainsString('data-master="'.$k.'"', $html, $k);
        }

        // every master page has the tabs, with its own tab marked and the menu's Masters item open
        $page = $this->actingAs($admin)->get(route('amc.masters.planters.index'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="AMC masters"', $page);
        $this->assertMatchesRegularExpression('/class="tabl on" href="[^"]*masters\/planters"/', $page);
        preg_match('/<div class="tndd" id="tndd-amc".*?<\/div>/s', $page, $m);
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Masters</', $m[0]);
        $emp = $this->actingAs($admin)->get(route('amc.employees.create'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="AMC masters"', $emp);
        preg_match('/<div class="tndd" id="tndd-amc".*?<\/div>/s', $emp, $m);
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Masters</', $m[0]);
    }

    public function test_masters_shows_only_the_masters_a_user_may_view(): void
    {
        $user = $this->amcUser(['amc-plant.view', 'amc-employee.view']);
        $html = $this->actingAs($user)->get(route('amc.masters.home'))->assertOk()->getContent();
        $this->assertStringContainsString('data-master="plants"', $html);
        $this->assertStringContainsString('data-master="employees"', $html);
        foreach (['verticals', 'planters', 'types'] as $k) {
            $this->assertStringNotContainsString('data-master="'.$k.'"', $html, $k);
        }
        $this->assertStringNotContainsString('Planter master', $html);

        // without any master right: no menu item, and the page is refused
        $none = $this->amcUser(['amc-dashboard.view'], 'none@nursery.local');
        $this->actingAs($none)->get(route('amc.masters.home'))->assertForbidden();
        $this->assertStringNotContainsString('>Masters<', $this->actingAs($none)->get(route('amc.dashboard'))->getContent());
    }

    public function test_employee_photo_input_has_a_preview_box(): void
    {
        $html = $this->actingAs($this->admin())->get(route('amc.employees.create'))->assertOk()->getContent();
        $this->assertStringContainsString('<span id="photoPreview"', $html);
        $this->assertStringContainsString('data-photo-preview="#photoPreview"', $html);
        $js = file_get_contents(public_path('js/amc.js'));
        $this->assertStringContainsString('input[type=file][data-photo-preview]', $js);
        // test fixes 22/11/2026: a file that is not a readable image brings the current photo back (no broken icon),
        // and on a phone the current master's tab is scrolled into view
        $this->assertStringContainsString('img.onerror', $js);
        $this->assertStringContainsString("\$\$('.amcmtabs')", $js);
    }

    /** (22/11/2026) Every AMC form's save button reads "Submit"; the contract page is "AMC Tracker". */
    public function test_amc_forms_submit_with_a_submit_button_and_the_tracker_name(): void
    {
        $admin = $this->admin();
        $client = $this->amcClient();
        $c = $this->amcContract($client);
        $e = $this->amcEmployee('Gopal Gardener', 'Gardener');
        $pages = [route('amc.clients.create'), route('amc.clients.edit', $client), route('amc.contract.create'), route('amc.contract.show', $c),
            route('amc.employees.create'), route('amc.employees.edit', $e), route('amc.masters.plants.index'), route('amc.returns.create')];
        foreach ($pages as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#type="submit"><svg class="ico"><use href="\#i-(plus|check)"/></svg>Submit</button>#', $html, $url);
            $this->assertStringNotContainsString('Save changes', $html, $url);
        }
        $new = $this->actingAs($admin)->get(route('amc.contract.create'))->getContent();
        $this->assertStringContainsString('<h1>AMC Tracker</h1>', $new);
        $this->assertStringContainsString('<title>AMC Tracker', $new);
        preg_match('/<div class="tndd" id="tndd-amc".*?<\/div>/s', $new, $m);
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>AMC Tracker</', $m[0]);
        $this->assertStringNotContainsString('Customer PO / AMC', $new);
    }
}
