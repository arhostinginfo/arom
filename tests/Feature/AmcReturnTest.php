<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Amc\AmcReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/** Return & Replace (21/11/2026): auto-fill from the client and its Active contract, plant size, quality, list and CSV. */
class AmcReturnTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_entry_is_saved_with_locations_and_plant_size_copied(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $client = $this->amcClient();
        $g = $this->amcEmployee('Gopal Gardener', 'Gardener');
        $rep = $this->amcEmployee('Reena Reporter', 'Other');
        $this->amcContract($client, ['amc_type_id' => $m['type2']->id, 'gardener_id' => $g->id]);
        $line = $client->lines()->first();

        // What the page fills in: the client's lines and, from its Active contract, the AMC type and gardener.
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', $client))->json();
        $this->assertSame([$line->id, 'Tower A · Floor 5 - Lobby'], [$info['lines'][0]['id'], $info['lines'][0]['label']]);
        $this->assertSame([$m['type2']->id, $g->id], [$info['contract']['amc_type_id'], $info['contract']['gardener_id']]);
        $html = $this->actingAs($admin)->get(route('amc.returns.create'))->assertOk()->getContent();
        $this->assertStringContainsString('data-size="5 ft"', $html);
        foreach (config('amc.qualities') as $q) {
            $this->assertMatchesRegularExpression('#<option\s*>'.$q.'</option>#', $html);
        }

        $this->actingAs($admin)->post(route('amc.returns.store'), [
            'amc_client_id' => $client->id, 'amc_client_line_id' => $line->id, 'request_date' => '15/11/2026', 'replace_date' => '18/11/2026',
            'reported_by_id' => $rep->id, 'amc_type_id' => $m['type2']->id, 'amc_plant_id' => $m['rose']->id, 'qty' => '4', 'rate' => '1,250.50',
            'gardener_id' => $g->id, 'return_remark' => 'Leaves yellow', 'quality' => 'Needs Treatment',
        ])->assertRedirect();
        $r = AmcReturn::firstOrFail();
        $this->assertSame(['Tower A', 'Floor 5 - Lobby', '5 ft', 4, '1250.50', 'Needs Treatment'], [$r->location_1, $r->location_2, $r->plant_size, $r->qty, $r->rate, $r->quality]);
        $this->assertSame(['2026-11-15', '2026-11-18'], [$r->request_date->toDateString(), $r->replace_date->toDateString()]);
        $this->assertTrue(ActivityLog::where('action', 'amc_return.created')->exists());
        $this->actingAs($admin)->get(route('amc.returns.edit', $r))->assertOk()->assertSee('<option selected>Tower A</option>', false)->assertSee('selected>Floor 5 - Lobby</option>', false)->assertSee('value="5 ft"', false);

        // The copied texts stay when the client's line changes later.
        $line->update(['location_1' => 'Tower Z']);
        $this->assertSame('Tower A', $r->fresh()->location_1);

        // Edit: other quality.
        $this->actingAs($admin)->put(route('amc.returns.update', $r), [
            'amc_client_id' => $client->id, 'amc_client_line_id' => $line->id, 'request_date' => '15/11/2026', 'amc_plant_id' => $m['money']->id, 'qty' => '2', 'quality' => 'Dead',
        ])->assertRedirect(route('amc.returns.edit', $r));
        $r->refresh();
        $this->assertSame(['Dead', '2 ft', 'Tower Z', null], [$r->quality, $r->plant_size, $r->location_1, $r->replace_date]);
    }

    public function test_validation(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $a = $this->amcClient('Client A');
        $b = $this->amcClient('Client B');
        $base = ['amc_client_id' => $a->id, 'request_date' => '15/11/2026', 'amc_plant_id' => $m['rose']->id, 'qty' => '1', 'quality' => 'Reusable'];
        // A location line of another client is refused.
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['amc_client_line_id' => $b->lines()->first()->id])
            ->assertSessionHasErrors(['amc_client_line_id' => 'Choose a location of this customer.']);
        $this->actingAs($admin)->post(route('amc.returns.store'), array_merge($base, ['replace_date' => '14/11/2026']))
            ->assertSessionHasErrors(['replace_date' => 'The replace date must be on or after the request date.']);
        $this->actingAs($admin)->post(route('amc.returns.store'), array_merge($base, ['quality' => 'Broken', 'qty' => '0', 'amc_plant_id' => '']))
            ->assertSessionHasErrors(['quality', 'qty', 'amc_plant_id']);
        $this->assertSame(0, AmcReturn::count());
        $this->actingAs($admin)->post(route('amc.returns.store'), $base)->assertSessionHasNoErrors();
    }

    public function test_list_search_quality_filter_csv_and_delete(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $a = $this->amcClient('Smart Work');
        $b = $this->amcClient('Rich Next', [['location_1' => 'ASTP', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 10]]);
        $mk = fn ($client, $q) => $this->actingAs($admin)->post(route('amc.returns.store'), [
            'amc_client_id' => $client->id, 'amc_client_line_id' => $client->lines()->first()->id, 'request_date' => '10/11/2026', 'amc_plant_id' => $m['rose']->id, 'qty' => '3', 'quality' => $q,
        ]);
        $mk($a, 'Reusable');
        $mk($b, 'Dead');
        $html = $this->actingAs($admin)->get(route('amc.returns.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Smart Work', $html);
        $this->assertStringContainsString('Rich Next', $html);
        $this->assertStringNotContainsString('Smart Work', $this->actingAs($admin)->get(route('amc.returns.index', ['quality' => 'Dead']))->getContent());
        $this->assertStringNotContainsString('Smart Work', $this->actingAs($admin)->get(route('amc.returns.index', ['q' => 'rich']))->getContent());

        $csv = $this->actingAs($admin)->get(route('amc.returns.export'))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));
        $this->assertSame(['Entry no', 'Customer', 'Location 1', 'Location 2', 'Request date', 'Replace date', 'Reported by', 'AMC type', 'Plant', 'Plant size', 'Qty', 'Rate', 'Gardener', 'Return remark', 'Quality'], $rows[0]);
        $this->assertSame(['RR-0002', 'Rich Next', 'ASTP', '', '10/11/2026'], array_slice($rows[2], 0, 5));

        $r = AmcReturn::first();
        $viewer = $this->amcUser(['amc-return.view']);
        $this->actingAs($viewer)->delete(route('amc.returns.destroy', $r))->assertForbidden();
        $this->actingAs($viewer)->get(route('amc.returns.edit', $r))->assertOk()->assertDontSee('Delete entry');
        $this->actingAs($admin)->delete(route('amc.returns.destroy', $r))->assertRedirect(route('amc.returns.index'));
        $this->assertSame(1, AmcReturn::count());
    }
}
