<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\CustomerPo;
use App\Models\Enquiry;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CRM count tiles on the dashboard (22/10/2026): each tile's number and link, and the page it opens shows the same
 * number. Known data: revised, soft-deleted and other-unit records, a quotation waiting for approval.
 */
class DashboardCrmCountsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFiveEntryFlow;

    protected $seed = true;

    private Unit $u1;

    private Unit $u2;

    protected function setUp(): void
    {
        parent::setUp();
        // Never run against the dev MySQL database.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->u1 = Unit::where('code', 'U1')->firstOrFail();
        $this->u2 = Unit::where('code', 'U2')->firstOrFail();
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
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822055555', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([$this->u1->id]);

        return $u;
    }

    private function switchUnit(User $u, Unit $unit): void
    {
        $this->actingAs($u)->post('/unit', ['unit' => $unit->id])->assertRedirect();
    }

    private function line(string $rate): array
    {
        return [['item_no' => 'PL-01', 'description' => 'Areca palm', 'qty' => '1', 'uom' => 'Nos', 'rate' => $rate, 'discount_percent' => '', 'tax_structure_id' => '']];
    }

    private function visit(Enquiry $e, string $status, string $date): SiteVisit
    {
        $this->actingAs($this->admin())->post('/crm/site-visits', [
            'enquiry_id' => $e->id, 'visit_date' => $date, 'visit_time' => '11:00', 'status' => $status, 'poc' => 'Meera',
            'technical_employee_id' => $this->crmEmployee()->id, 'area_measurements' => 'Lobby 40 x 25 ft', 'floors' => 'Ground',
            'indoor_outdoor_area' => 'Indoor', 'lighting_condition' => 'Bright', 'water_availability' => 'Tap',
            'customer_requirement' => '60 plants', 'next_action' => 'Prepare BOQ',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return SiteVisit::latest('id')->firstOrFail();
    }

    private function contact(Prospect $p, string $name, string $mobile): Contact
    {
        $this->actingAs($this->admin())->post('/crm/contacts', ['prospect_id' => $p->id, 'party_id' => '', 'name' => $name, 'mobile' => $mobile])
            ->assertSessionHasNoErrors()->assertRedirect();

        return Contact::latest('id')->firstOrFail();
    }

    /**
     * Unit 1: 6 enquiries (1 lost) + 1 deleted; 3 site visits (1 due) + 1 deleted due one; 4 current quotations
     * (draft, pending approval, revised to Rev 1, accepted) + 1 deleted; 1 PO; 2 contacts + 1 deleted.
     * Unit 2: 1 enquiry with 1 quotation pending approval and 1 due visit.
     */
    private function seedCrm(): array
    {
        $admin = $this->admin();
        $this->switchUnit($admin, $this->u1);
        $today = now()->format('d/m/Y');
        $p = $this->prospectFixture();
        $p2 = $this->newProspect(['name' => 'Orion Agro', 'phone_1' => '+91 98220 22222']);

        $e = [];
        foreach (range(1, 7) as $i) {
            $e[$i] = $this->newEnquiry(['enquiry_date' => $today]);
        }
        // E6: deleted; E7: lost (the "All" list still shows it).
        $this->actingAs($admin)->delete("/crm/enquiries/{$e[6]->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($e[6]);
        $e[7]->forceFill(['status' => 'Lost'])->save();

        // Site visits: done, due today, planned next week, and a due one deleted.
        $this->visit($e[1], 'Done', $today);
        $this->visit($e[2], 'Planned', $today);
        $this->visit($e[3], 'Planned', now()->addWeek()->format('d/m/Y'));
        $gone = $this->visit($e[4], 'Planned', $today);
        $this->actingAs($admin)->delete("/crm/site-visits/{$gone->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($gone);

        // Quotations.
        $qa = $this->newQuotation($e[1], $this->line('500'));                       // Draft
        $qb = $this->newQuotation($e[2], $this->line('150000'));                    // above the limit
        $this->assertSame('Internal Approval', $this->quotationAction($qb, 'submit')->status->value);
        $qc = $this->newQuotation($e[3], $this->line('500'));                       // sent, then revised
        $this->quotationAction($qc, 'submit');
        $this->assertSame('Sent', $this->quotationAction($qc, 'sent')->status->value);
        $this->quotationAction($qc, 'revise');
        $this->assertSame(2, Quotation::where('quotation_no', $qc->quotation_no)->count());
        $qd = $this->newQuotation($e[4], $this->line('500'));                       // accepted, PO
        $this->quotationAction($qd, 'submit');
        $this->quotationAction($qd, 'sent');
        $this->assertSame('Accepted', $this->quotationAction($qd, 'customer-status', ['status' => 'Accepted'])->status->value);
        $this->newPo($qd, 'PO-1001');
        $qe = $this->newQuotation($e[5], $this->line('150000'));                    // deleted
        $this->actingAs($admin)->delete("/crm/quotations/{$qe->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($qe);

        // Contacts (of the client, every unit).
        $this->contact($p, 'Kavita Rao', '+91 98220 33333');
        $this->contact($p2, 'Sanjay Patil', '+91 98220 44444');
        $c3 = $this->contact($p2, 'Gone Contact', '+91 98220 55555');
        $this->actingAs($admin)->delete("/crm/contacts/{$c3->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($c3);

        // Unit 2.
        $this->switchUnit($admin, $this->u2);
        $e2 = $this->newEnquiry(['enquiry_date' => $today]);
        $this->assertSame($this->u2->id, $e2->unit_id);
        $this->visit($e2, 'Planned', $today);
        $q2 = $this->newQuotation($e2, $this->line('150000'));
        $this->assertSame($this->u2->id, $q2->unit_id);
        $this->quotationAction($q2, 'submit');
        $this->switchUnit($admin, $this->u1);

        // The prospect count is cached for a minute; start from the real number.
        Cache::forget('crm.prospects.count');

        return ['prospects' => Prospect::count()];
    }

    private function tiles(User $u): array
    {
        $tiles = $this->actingAs($u)->get('/dashboard')->assertOk()->viewData('crmTiles');

        return collect($tiles)->keyBy('key')->all();
    }

    public function test_each_tile_count_and_link_match_the_page_it_opens(): void
    {
        $known = $this->seedCrm();
        $admin = $this->admin();
        $res = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $tiles = collect($res->viewData('crmTiles'))->keyBy('key');

        $this->assertSame(['enquiries', 'site-visits', 'site-visits-due', 'quotations', 'approval', 'sent', 'pos', 'prospects', 'contacts'], $tiles->keys()->all());
        $expected = [
            'enquiries' => [6, route('crm.enquiries.list')],
            'site-visits' => [3, route('crm.site-visits.list')],
            'site-visits-due' => [1, route('crm.site-visits.list', ['view' => 'due'])],
            'quotations' => [4, route('crm.quotations.list')],
            'approval' => [1, route('crm.quotations.list', ['view' => 'approval'])],
            'sent' => [1, route('crm.quotations.list', ['view' => 'sent'])],
            'pos' => [1, route('crm.customer-pos.index')],
            'prospects' => [$known['prospects'], route('crm.prospects.index')],
            'contacts' => [2, route('crm.contacts.index')],
        ];
        foreach ($expected as $key => [$count, $url]) {
            $this->assertSame($count, $tiles[$key]['count'], "count of $key");
            $this->assertSame($url, $tiles[$key]['url'], "url of $key");
        }
        $this->assertTrue($tiles['site-visits-due']['alert']);

        // Rendered: one link per tile, its spoken name gives the number and what it counts.
        $html = $res->getContent();
        $this->assertStringContainsString('aria-labelledby="dash-crm-h"', $html);
        $this->assertStringContainsString('href="'.e(route('crm.quotations.list', ['view' => 'approval'])).'" aria-label="1 quotation pending approval – open list" data-tile="approval"', $html);
        $this->assertStringContainsString('aria-label="6 enquiries / leads – open list"', $html);
        $this->assertStringContainsString('aria-label="1 site visit due – open list"', $html);
        $this->assertStringContainsString('aria-label="2 contacts – open list"', $html);

        // Follow each link: the page's own total equals the tile.
        foreach ($tiles as $key => $t) {
            $page = $this->actingAs($admin)->get($t['url'])->assertOk();
            $shown = match ($key) {
                'prospects' => (int) $page->viewData('total'),
                // The PO page is an entry page: its entry picker lists the unit's POs (up to 50).
                'pos' => $page->viewData('entries')->count(),
                default => $page->viewData('rows')->total(),
            };
            $this->assertSame($t['count'], $shown, "page of $key");
        }
        // The deleted quotation and the earlier revision are not listed.
        $rows = $this->actingAs($admin)->get(route('crm.quotations.list'))->viewData('rows');
        $this->assertTrue($rows->getCollection()->every(fn ($q) => $q->is_latest && $q->unit_id === $this->u1->id));
        $this->assertSame(1, $rows->getCollection()->where('revision_no', 1)->count());
    }

    public function test_switching_unit_switches_the_unit_counts(): void
    {
        $known = $this->seedCrm();
        $admin = $this->admin();
        $this->switchUnit($admin, $this->u2);
        $tiles = $this->tiles($admin);
        $this->assertSame(1, $tiles['enquiries']['count']);
        $this->assertSame(1, $tiles['site-visits']['count']);
        $this->assertSame(1, $tiles['site-visits-due']['count']);
        $this->assertSame(1, $tiles['quotations']['count']);
        $this->assertSame(1, $tiles['approval']['count']);
        $this->assertSame(0, $tiles['sent']['count']);
        $this->assertSame(0, $tiles['pos']['count']);
        // Prospects and contacts belong to the client, not to a unit.
        $this->assertSame($known['prospects'], $tiles['prospects']['count']);
        $this->assertSame(2, $tiles['contacts']['count']);
        foreach (['enquiries', 'site-visits', 'quotations', 'approval'] as $key) {
            $this->assertSame(1, $this->actingAs($admin)->get($tiles[$key]['url'])->viewData('rows')->total(), $key);
        }
        $this->switchUnit($admin, $this->u1);
        $this->assertSame(6, $this->tiles($admin)['enquiries']['count']);
    }

    public function test_site_visit_part_off_hides_the_site_visit_tiles(): void
    {
        $this->seedCrm();
        $this->setModules(['site_visit' => false]);
        $keys = array_keys($this->tiles($this->admin()));
        $this->assertNotContains('site-visits', $keys);
        $this->assertNotContains('site-visits-due', $keys);
        $this->assertContains('enquiries', $keys);
        $this->assertContains('quotations', $keys);
    }

    public function test_sales_role_sees_the_tiles_of_its_rights_only(): void
    {
        $this->seedCrm();
        // Sales has no quotation-approval.view, so no Pending approval tile, like the menu.
        $keys = array_keys($this->tiles($this->sales()));
        $this->assertSame(['enquiries', 'site-visits', 'site-visits-due', 'quotations', 'sent', 'pos', 'prospects', 'contacts'], $keys);
    }

    public function test_role_without_crm_rights_sees_no_crm_section(): void
    {
        $this->seedCrm();
        $u = User::create(['name' => 'Buyer', 'email' => 'buyer@company.example', 'mobile' => '9822000020', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Purchase');
        $u->units()->sync([$this->u1->id]);
        $r = $this->actingAs($u)->get('/dashboard')->assertOk();
        $this->assertSame([], $r->viewData('crmTiles'));
        $this->assertStringNotContainsString('dash-crm-h', $r->getContent());
        $this->assertStringNotContainsString('data-tile=', $r->getContent());
    }

    public function test_role_with_only_prospect_view_sees_only_the_prospects_tile(): void
    {
        $known = $this->seedCrm();
        Role::create(['name' => 'Prospect viewer', 'guard_name' => 'web'])->givePermissionTo(['dashboard.view', 'prospect.view']);
        $u = User::create(['name' => 'Viewer', 'email' => 'viewer@company.example', 'mobile' => '9822000021', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Prospect viewer');
        $u->units()->sync([$this->u1->id]);

        $r = $this->actingAs($u)->get('/dashboard')->assertOk();
        $tiles = $r->viewData('crmTiles');
        $this->assertSame(['prospects'], array_column($tiles, 'key'));
        $this->assertSame($known['prospects'], $tiles[0]['count']);
        $this->assertSame(1, substr_count($r->getContent(), 'data-tile='));
        $this->actingAs($u)->get($tiles[0]['url'])->assertOk();
    }

    /** The prospect tile shares the page badge's 60 s cache: right after an add both show the same, older number. */
    public function test_prospect_tile_and_page_badge_use_the_same_cached_count(): void
    {
        $known = $this->seedCrm();
        $admin = $this->admin();
        $this->assertSame($known['prospects'], $this->tiles($admin)['prospects']['count']);
        $this->newProspect(['name' => 'Fresh Prospect', 'phone_1' => '+91 98220 66666']);
        $tile = $this->tiles($admin)['prospects']['count'];
        $badge = (int) $this->actingAs($admin)->get(route('crm.prospects.index'))->viewData('total');
        $this->assertSame($badge, $tile);
        // Adding a prospect drops the cached count, so both show the new total at once.
        $this->assertSame($known['prospects'] + 1, $tile);
    }
}
