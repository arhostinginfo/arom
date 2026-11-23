<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Amc\AmcReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/** Fixes after the AMC test report (21/11/2026): activity labels, the "Renewals due" tile's list, the Return & Replace gardener. */
class AmcReviewFixesTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_amc_actions_have_plain_labels_in_the_activity_log(): void
    {
        foreach (['amc_master', 'amc_employee', 'amc_contract', 'amc_return'] as $s) {
            foreach (['created', 'updated', 'deleted'] as $a) {
                $this->assertArrayHasKey("$s.$a", ActivityLog::ACTIONS);
            }
        }
        foreach (['amc_client.created', 'amc_client.updated', 'amc_client.imported', 'amc_file.uploaded', 'amc_file.deleted', 'amc_file.purged'] as $k) {
            $this->assertArrayHasKey($k, ActivityLog::ACTIONS);
        }
        // every action the AMC services write has a label
        foreach (glob(app_path('Services/Amc/*.php')) as $file) {
            preg_match_all("/ActivityLogger::log\(\s*'([a-z_]+\.[a-z_]+)'/", file_get_contents($file), $m);
            foreach ($m[1] as $action) {
                $this->assertArrayHasKey($action, ActivityLog::ACTIONS, $action);
            }
        }

        $admin = $this->admin();
        $this->amcContract($this->amcClient());
        $html = $this->actingAs($admin)->get(route('admin.activity.index'))->assertOk()->getContent();
        $this->assertStringContainsString('AMC added', $html);
        // the code is only an option value of the action filter, never shown as text
        $this->assertStringNotContainsString('>amc_contract.created<', $html);
    }

    public function test_renewals_due_tile_opens_the_contracts_ending_within_60_days(): void
    {
        $admin = $this->admin();
        $in10 = $this->amcContract($this->amcClient('Ten Days'), ['end_date' => today()->addDays(10)]);
        $in45 = $this->amcContract($this->amcClient('Forty Five'), ['end_date' => today()->addDays(45)]);
        $in60 = $this->amcContract($this->amcClient('Sixty Days'), ['end_date' => today()->addDays(60)]);
        $in61 = $this->amcContract($this->amcClient('Sixty One'), ['end_date' => today()->addDays(61)]);
        $over = $this->amcContract($this->amcClient('Overdue One'), ['end_date' => today()->subDay()]);
        $off = $this->amcContract($this->amcClient('Inactive One'), ['end_date' => today()->addDays(20), 'status' => 'Inactive']);

        $dash = $this->actingAs($admin)->get(route('amc.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString(e(route('reports.amc.report', ['renewal' => 1, 'in' => 'amc'])).'" data-tile="renewals"', $dash);

        $list = $this->actingAs($admin)->get(route('amc.contracts.list', ['renewal' => 1]))->assertOk()->getContent();
        foreach (['Ten Days', 'Forty Five', 'Sixty Days'] as $name) {
            $this->assertStringContainsString($name, $list, $name);
        }
        foreach (['Sixty One', 'Overdue One', 'Inactive One'] as $name) {
            $this->assertStringNotContainsString($name, $list, $name);
        }
        $this->assertStringContainsString('data-filter="renewal"', $list);
        // the same filter for the CSV, and it combines with the search
        $csv = $this->actingAs($admin)->get(route('amc.contracts.export', ['renewal' => 1]))->assertOk()->streamedContent();
        $this->assertSame(3, substr_count($csv, "\n") - 1);
        $this->assertStringNotContainsString('Ten Days', $this->actingAs($admin)->get(route('amc.contracts.list', ['renewal' => 1, 'q' => 'Forty']))->getContent());
        // the Red colour filter still means 30 days
        $red = $this->actingAs($admin)->get(route('amc.contracts.list', ['colour' => 'red']))->getContent();
        $this->assertStringContainsString('Ten Days', $red);
        $this->assertStringNotContainsString('Forty Five', $red);
    }

    public function test_one_upload_is_limited_to_40_mb_in_total(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('amc.files_disk'));
        $admin = $this->admin();
        $c = $this->amcContract($this->amcClient());
        $this->assertSame(40960, config('amc.max_total_kb'));

        // 5 PDFs of 9 MB each: every file is under the 10 MB limit, together 45 MB
        $big = fn ($n) => array_map(fn ($i) => \Illuminate\Http\UploadedFile::fake()->create("challan-$i.pdf", 9 * 1024, 'application/pdf'), range(1, $n));
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'challan_doc', 'files' => $big(5)])
            ->assertSessionHasErrors(['files' => 'The files of one upload can be at most 40 MB in total. Upload them in smaller groups.']);
        $this->assertSame(0, $c->files()->count());
        // 4 of them, 36 MB: accepted
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'challan_doc', 'files' => $big(4)])->assertSessionHasNoErrors();
        $this->assertSame(4, $c->files()->count());

        // the page says so and gives the limits to the browser check
        $html = $this->actingAs($admin)->get(route('amc.contract.show', $c))->assertOk()->getContent();
        $this->assertStringContainsString('max 10 MB each, 40 MB in total, up to 20 at a time.', $html);
        $this->assertStringContainsString('data-max-total-kb="40960" data-max-files="20"', $html);
    }

    public function test_return_gardener_must_be_an_active_gardener(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $client = $this->amcClient();
        $gardener = $this->amcEmployee('Gopal Gardener', 'Gardener');
        $manager = $this->amcEmployee('Meera Manager', 'Operation Manager');
        $left = $this->amcEmployee('Lalit Left', 'Gardener', ['status' => 'Inactive']);
        $base = ['amc_client_id' => $client->id, 'request_date' => '15/11/2026', 'amc_plant_id' => $m['rose']->id, 'qty' => '1', 'quality' => 'Reusable'];

        foreach ([$manager, $left] as $e) {
            $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['gardener_id' => $e->id])
                ->assertSessionHasErrors(['gardener_id' => 'Choose an active AMC employee whose role is Gardener.']);
        }
        $this->assertSame(0, AmcReturn::count());
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['gardener_id' => $gardener->id])->assertSessionHasNoErrors();
        $r = AmcReturn::firstOrFail();

        // a gardener who has since become inactive may stay on the entry they are already on
        $gardener->update(['status' => 'Inactive']);
        $this->actingAs($admin)->put(route('amc.returns.update', $r), array_merge($base, ['gardener_id' => $gardener->id, 'qty' => '2']))->assertSessionHasNoErrors();
        $this->assertSame(2, $r->fresh()->qty);
        // without a gardener is still allowed
        $this->actingAs($admin)->post(route('amc.returns.store'), $base)->assertSessionHasNoErrors();
    }
}
