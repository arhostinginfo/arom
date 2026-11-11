<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sat's screen requests of 18/10/2026: select with search (paged lookups), menu fly-outs and lists that fit a phone. */
class UiBatchTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    public function test_lookups_page_twenty_at_a_time_in_name_order(): void
    {
        for ($i = 1; $i <= 45; $i++) {
            Prospect::create(['name' => sprintf('Paged Prospect %02d', $i), 'market' => 'Domestic', 'phone_1' => '98220'.sprintf('%05d', $i)]);
        }
        $p1 = $this->actingAs($this->admin())->getJson('/lookup/prospects?q=Paged')->assertOk()->json();
        $p2 = $this->getJson('/lookup/prospects?q=Paged&page=2')->json();
        $p3 = $this->getJson('/lookup/prospects?q=Paged&page=3')->json();

        $this->assertCount(20, $p1);
        $this->assertCount(20, $p2);
        $this->assertCount(5, $p3);
        $this->assertSame('Paged Prospect 01', $p1[0]['label']);
        $this->assertSame('Paged Prospect 21', $p2[0]['label']);
        $this->assertEmpty(array_intersect(array_column($p1, 'id'), array_column($p2, 'id')));
        // A phone search still finds the prospect; a name search does not look at phone numbers.
        $this->assertSame('Paged Prospect 07', $this->getJson('/lookup/prospects?q=9822000007')->json('0.label'));
        $this->assertCount(0, $this->getJson('/lookup/prospects?q=Paged&page=99')->json());
    }

    public function test_menu_shows_other_modules_pages_as_fly_outs(): void
    {
        $html = $this->actingAs($this->admin())->get('/crm/enquiries/list')->assertOk()->getContent();

        $this->assertStringContainsString('class="navfly" id="fly-sales"', $html);
        $this->assertStringContainsString('aria-controls="fly-sales" data-fly', $html);
        // The open module's pages stay in place, not in a fly-out.
        $this->assertStringNotContainsString('id="fly-crm"', $html);
    }

    public function test_crm_lists_fold_views_and_sort_into_a_filters_panel(): void
    {
        $this->crmEnquiry();
        foreach (['/crm/enquiries/list?view=open&q=ENQ&sort=no&dir=asc', '/crm/quotations/list', '/crm/site-visits/list', '/crm/contacts'] as $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('data-fold', $html, $url);
            $this->assertStringContainsString('<table class="atable rtable fit">', $html, $url);
        }
        $html = $this->get('/crm/enquiries/list?view=open&q=ENQ&sort=no&dir=asc')->getContent();
        // Search keeps the view and the sort; the view tabs keep the search.
        $this->assertStringContainsString('<input type="hidden" name="view" value="open">', $html);
        $this->assertMatchesRegularExpression('/<select class="inp" id="sort" name="sort" form="listSearch">.*<option value="no" selected/s', $html);
        $this->assertStringContainsString('view=open', $html);
        $this->assertStringContainsString('q=ENQ', $html);
        $this->assertStringContainsString('>Open</a>', $html);
    }

    public function test_master_dropdowns_have_no_hint_or_add_button_and_dates_get_the_picker(): void
    {
        // Sat 18/10/2026 (round 2): the hint and Add button under master dropdowns were removed.
        $html = $this->actingAs($this->admin())->get('/crm/enquiries/create')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-master-hint', $html);
        $this->assertStringNotContainsString('data-add-url', $html);
        $this->assertStringContainsString('js/datepicker.js', $html);
    }
}
