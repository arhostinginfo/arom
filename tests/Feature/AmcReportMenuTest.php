<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/**
 * (22/11/2026, test fix) The AMC Report is marked in the menu it was opened from: Reports (no ?in) or AMC (?in=amc),
 * like the CRM and Sales reports. Before the fix, opened from Reports, no menu item was marked.
 */
class AmcReportMenuTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    /** The marked menu links, as "class:text". */
    private function onLinks(string $html): array
    {
        preg_match_all('/<a class="(it|repl|pg) on"[^>]*>(.*?)<\/a>/s', $html, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => $x[1].':'.trim(preg_replace('/\s+/', ' ', strip_tags($x[2]))), $m);
    }

    public function test_amc_report_is_marked_under_reports_or_under_amc(): void
    {
        $admin = $this->admin();

        $fromReports = $this->actingAs($admin)->get(route('reports.amc.report'))->assertOk()->assertSee('<h1>AMC Report</h1>', false)->getContent();
        $on = $this->onLinks($fromReports);
        $this->assertContains('it:Reports', $on);
        $this->assertContains('repl:AMC Report', $on);
        $this->assertNotContains('it:AMC', $on);

        $fromAmc = $this->actingAs($admin)->get(route('reports.amc.report', ['in' => 'amc', 'status' => 'Active']))->assertOk()->getContent();
        $on = $this->onLinks($fromAmc);
        $this->assertContains('it:AMC', $on);
        $this->assertContains('repl:AMC Report', $on);
        $this->assertNotContains('it:Reports', $on);
        // the filter form stays on this page and in this menu; Clear keeps the menu
        $this->assertStringContainsString('action="'.route('reports.amc.report').'"', $fromAmc);
        $this->assertStringContainsString('<input type="hidden" name="in" value="amc">', $fromAmc);
        $this->assertStringContainsString('href="'.route('reports.amc.report').'?in=amc">Clear</a>', $fromAmc);

        // the Reports page lists it; the AMC dashboard tiles open it in the AMC menu
        $this->assertStringContainsString(e(route('reports.amc.report')), $this->actingAs($admin)->get(route('app.page', ['page' => 'reports']))->getContent());
        $this->assertStringContainsString(e(route('reports.amc.report', ['renewal' => 1, 'in' => 'amc'])), $this->actingAs($admin)->get(route('amc.dashboard'))->getContent());

        // a user without amc-contract.view cannot open it
        $this->actingAs($this->amcUser([]))->get(route('reports.amc.report'))->assertForbidden();
    }
}
