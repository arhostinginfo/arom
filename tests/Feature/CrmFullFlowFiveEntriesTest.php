<?php

namespace Tests\Feature;

use App\Models\CustomerPo;
use App\Models\Enquiry;
use App\Models\FinancialYear;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use App\Services\CrmAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The whole CRM with 5 dummy entries and Admin > Page setup (22/10/2026), see Tests\CrmFiveEntryFlow.
 * Runs on SQLite in memory, or on a throwaway MariaDB database whose name ends in _tmp, never on "nursery".
 */
class CrmFullFlowFiveEntriesTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFiveEntryFlow;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->assertStringEndsWith('_tmp', DB::connection()->getDatabaseName(), 'Only a throwaway database may be used.');
        }
        Storage::fake('local');
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
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    public function test_five_entries_go_through_the_whole_crm_with_page_setup(): void
    {
        $e = $this->runFiveEntryFlow();
        $admin = $this->admin();

        // ---- what the flow left ----
        $this->assertSame(5, Enquiry::count());
        $this->assertSame(4, Prospect::count());
        $this->assertSame(2, SiteVisit::count());
        $this->assertSame(6, Quotation::count()); // 5 and one revision
        $this->assertSame(2, CustomerPo::count());
        $this->assertSame(['PO received', 'Open', 'Lost', 'PO received', 'Open'], array_map(fn ($x) => $x->fresh()->status->value, array_values($e)));
        $this->assertSame(1, Quotation::poPending()->count()); // Orchid Mall: verbal win
        $this->assertSame(2, \App\Models\Document::count());

        // ---- every list and record page opens and shows its entries ----
        $list = $this->actingAs($admin)->get('/crm/enquiries/list')->assertOk()->getContent();
        foreach ($e as $x) {
            $this->assertStringContainsString($x->enquiry_no, $list);
            $this->actingAs($admin)->get('/crm/enquiries/'.$x->id)->assertOk()->assertSee($x->enquiry_no);
        }
        foreach (Quotation::all() as $q) {
            $this->actingAs($admin)->get('/crm/quotations/'.$q->id)->assertOk()->assertSee($q->quotation_no);
            $this->actingAs($admin)->get('/crm/quotations/'.$q->id.'/pdf')->assertOk();
        }
        foreach (SiteVisit::all() as $v) {
            $this->actingAs($admin)->get('/crm/site-visits/'.$v->id)->assertOk();
        }
        foreach (CustomerPo::all() as $po) {
            $this->actingAs($admin)->get('/crm/pos/'.$po->id)->assertOk()->assertSee($po->po_no);
        }
        foreach (Prospect::all() as $p) {
            $this->actingAs($admin)->get('/crm/prospects/'.$p->id)->assertOk()->assertSee($p->name);
        }
        foreach (['/crm/enquiries', '/crm/site-visits', '/crm/site-visits/list', '/crm/quotations', '/crm/quotations/list', '/crm/pos', '/crm/prospects', '/crm/contacts',
            '/crm/enquiries/create', '/crm/site-visits/create', '/crm/quotations/create', '/crm/pos/create', '/crm/prospects/create', '/crm/contacts/create',
            '/admin/page-setup', '/reports/crm/prospects', '/reports/crm/enquiries', '/reports/crm/lead-ageing', '/reports/crm/lead-report'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get('/crm/quotations/list?view=po-pending')->assertOk()->assertSee('Orchid Mall');

        // ---- dashboard and alerts ----
        $d = $this->actingAs($admin)->get('/dashboard')->assertOk()->viewData('d');
        $this->assertSame(5, $d['kpis']['enquiries']);
        $this->assertSame(2, $d['kpis']['open']); // Sunrise is lost, two have a PO
        $this->assertSame(1, $d['kpis']['hot']); // open leads only: Skyline
        $this->assertSame(1, $d['kpis']['closing']); // Skyline closes this month (Lotus has its PO)
        $this->assertEqualsCanonicalizing(['Website', 'BNI', 'Existing Client', 'Referral', 'Builder'], array_column($d['sources']['slices'], 'label'));
        $alerts = collect(app(CrmAlerts::class)->for($admin, Unit::first())['items'])->pluck('count', 'label');
        $this->assertSame(1, $alerts['PO pending']);
        $this->assertSame(0, $alerts['Quotations pending approval']);

        // ---- reports carry the 5 entries ----
        // the 6 quotation PDFs above used up most of the 10-a-minute download limit
        \Illuminate\Support\Facades\Cache::flush();
        $year = FinancialYear::forDate(now());
        $range = ['from' => $year->starts_on->format('d/m/Y'), 'to' => $year->ends_on->format('d/m/Y')];
        $all = ['Greenleaf Tech Park', 'Orchid Mall', 'Sunrise Hospital', 'Lotus Hotels', 'Skyline Builders'];
        // lead ageing lists the open leads only
        foreach (['enquiries' => $all, 'lead-report' => $all, 'lead-ageing' => ['Orchid Mall', 'Skyline Builders']] as $report => $clients) {
            $csv = $this->actingAs($admin)->get("/reports/crm/$report/download?".http_build_query($range + ['format' => 'csv']))->assertOk()->streamedContent();
            foreach ($all as $client) {
                in_array($client, $clients, true)
                    ? $this->assertStringContainsString($client, $csv, "$report report")
                    : $this->assertStringNotContainsString($client, $csv, "$report report");
            }
            $this->actingAs($admin)->get("/reports/crm/$report/download?".http_build_query($range + ['format' => 'pdf']))->assertOk();
        }
        $hot = $this->actingAs($admin)->get('/reports/crm/enquiries/download?'.http_build_query($range + ['format' => 'csv', 'rating' => 'Hot']))->streamedContent();
        $this->assertStringNotContainsString('Sunrise Hospital', $hot);

        // ---- page setup on the Prospect page, then back to defaults ----
        $this->setPage('prospect', ['email' => [false, false], 'contact_person' => [true, true]]);
        $this->actingAs($admin)->get('/crm/prospects/create')->assertOk()->assertDontSee('id="email"', false);
        $this->actingAs($admin)->post('/crm/prospects', ['name' => 'No Contact Ltd', 'market' => 'Domestic', 'phone_1' => '9822041006'])->assertSessionHasErrors('contact_person');
        $this->resetPage('prospect');
        $this->actingAs($admin)->get('/crm/prospects/create')->assertSee('id="email"', false);

        // ---- after the flow every page setup is back to its default ----
        foreach (['enquiry', 'enquiry-followup', 'site-visit', 'quotation', 'customer-po', 'prospect'] as $page) {
            foreach (app(\App\Services\PageSetupService::class)->fields($page) as $f => $cur) {
                $this->assertTrue($cur['visible'], "$page.$f visible");
                $this->assertSame((bool) config("nursery.page_fields.$page.fields.$f.required"), (bool) $cur['required'], "$page.$f required");
            }
        }

        // ---- a Sales user sees the entries of the unit but cannot approve or open Page setup ----
        $sales = $this->sales();
        $this->actingAs($sales)->get('/crm/enquiries/list')->assertOk()->assertSee($e[1]->enquiry_no);
        $this->actingAs($sales)->get('/admin/page-setup')->assertForbidden();
        $q = $this->newQuotation($e[5], [['item_no' => '', 'description' => 'Extra planters', 'qty' => '1', 'uom' => 'SET', 'rate' => '200000', 'discount_percent' => '', 'tax_structure_id' => '']]);
        $this->quotationAction($q, 'submit');
        $this->actingAs($sales)->post("/crm/quotations/{$q->id}/approve")->assertForbidden();
    }
}
