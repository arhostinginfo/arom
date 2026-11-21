<?php

namespace Tests\Feature;

use App\Models\FinancialYear;
use App\Services\Amc\AmcDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\AmcFixtures;
use Tests\TestCase;

/** AMC dashboard figures (21/11/2026): active clients, monthly revenue spread over each period, renewals, overdue, new orders. */
class AmcDashboardTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_figures(): void
    {
        Carbon::setTestNow('2026-11-21 10:00:00');
        $a = $this->amcClient('Smart Work');
        $b = $this->amcClient('Rich Next', []);
        $c = $this->amcClient('Third Co', []);
        // 12 months (Apr 2026 to Mar 2027), 12,000: 1,000 a month.
        $this->amcContract($a, ['start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'amount' => 12000]);
        // A second contract of the same client still counts the client once; 6 months Oct 2026 to Mar 2027 of 3,000 = 500 a month.
        $this->amcContract($a, ['start_date' => '2026-10-01', 'end_date' => '2027-03-31', 'amount' => 3000]);
        // Ends in 20 days (renewal + red): Dec 2025 to Dec 2026 touches 13 months, 13,000 = 1,000 a month.
        $this->amcContract($b, ['start_date' => '2025-12-11', 'end_date' => '2026-12-11', 'amount' => 13000]);
        // Overdue (Active, ended): not an active client today; still counts its months in the year.
        $this->amcContract($c, ['start_date' => '2026-05-01', 'end_date' => '2026-10-31', 'amount' => 600]);
        // Inactive: left out of revenue and counts.
        $this->amcContract($c, ['start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'amount' => 99999, 'status' => 'Inactive']);

        $fy = FinancialYear::forDate(now());
        $d = app(AmcDashboard::class)->figures($fy);
        $this->assertSame(2, $d['activeClients']);
        // November 2026: 1,000 + 500 + 1,000.
        $this->assertEqualsWithDelta(2500, $d['thisMonth'], 0.001);
        $months = collect($d['revenue'])->pluck('amount', 'month');
        $this->assertCount(12, $months);
        $this->assertSame('Apr 2026', array_key_first($months->all()));
        $this->assertEqualsWithDelta(1000 + 1000, $months['Apr 2026'], 0.001);
        $this->assertEqualsWithDelta(1000 + 1000 + 100, $months['May 2026'], 0.001);
        $this->assertEqualsWithDelta(1000 + 500 + 1000 + 100, $months['Oct 2026'], 0.001);
        $this->assertEqualsWithDelta(1000 + 500, $months['Jan 2027'], 0.001);
        // 12,000 + 3,000 + 9 of 13 months of 13,000 (Apr to Dec 2026) + 600.
        $this->assertEqualsWithDelta(12000 + 3000 + 9000 + 600, $d['revenueTotal'], 0.001);

        $this->assertSame(1, $d['renewalCount']);
        $this->assertSame('Rich Next', $d['renewals'][0]->client->name);
        $this->assertSame(20, $d['renewals'][0]->daysLeft());
        $this->assertSame(1, $d['overdueCount']);
        $this->assertSame('Third Co', $d['overdue'][0]->client->name);
        $this->assertSame(5, $d['newCount']);

        $html = $this->actingAs($this->admin())->get(route('amc.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Total active clients', $html);
        $this->assertStringContainsString('₹2,500', $html);
        $this->assertStringContainsString('24,600.00', $html);
        $this->assertStringContainsString('20 day(s) left', $html);
        $this->assertStringContainsString('Ended 31/10/2026', $html);
        $this->assertStringContainsString('FY 2026-27', $html);
    }

    public function test_new_orders_count_only_the_last_60_days(): void
    {
        Carbon::setTestNow('2026-11-21 10:00:00');
        $client = $this->amcClient();
        $old = $this->amcContract($client);
        $old->forceFill(['created_at' => now()->subDays(61)])->save();
        $this->amcContract($client);
        $d = app(AmcDashboard::class)->figures(FinancialYear::forDate(now()));
        $this->assertSame(1, $d['newCount']);
        $this->assertCount(1, $d['newOrders']);
    }

    public function test_dashboard_needs_its_right(): void
    {
        $u = $this->amcUser(['amc-contract.view']);
        $this->actingAs($u)->get(route('amc.dashboard'))->assertForbidden();
        $u2 = $this->amcUser(['amc-dashboard.view'], 'amc.dash@nursery.local');
        $this->actingAs($u2)->get(route('amc.dashboard'))->assertOk()->assertSee('No contract ends in the next 60 days.');
    }
}
