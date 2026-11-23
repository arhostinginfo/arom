<?php

namespace Tests\Feature;

use App\Models\Amc\AmcReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/**
 * (23/11/2026) Return & Replace: the entries of the chosen Location show every detail from the AMC Client Inventory
 * and what has been replaced so far for each, so a customer with several Locations shows which are replaced and which not.
 */
class AmcReturnReplacedStatusTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_each_entry_shows_its_details_and_replaced_so_far(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $c = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => 4, 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 2],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 7'],
            ['location_1' => 'Tower B', 'location_2' => 'Lobby'],
        ]);
        $l = $c->lines()->orderBy('line_no')->get();
        $base = ['amc_client_id' => $c->id, 'amc_plant_id' => $m['rose']->id, 'quality' => 'Reusable'];
        // Floor 5 replaced twice, Floor 6 requested but not replaced, Floor 7 nothing; one return for Tower B without an entry
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower A', 'amc_client_line_id' => $l[0]->id, 'request_date' => '01/11/2026', 'replace_date' => '03/11/2026', 'qty' => '2'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower A', 'amc_client_line_id' => $l[0]->id, 'request_date' => '10/11/2026', 'replace_date' => '12/11/2026', 'qty' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower A', 'amc_client_line_id' => $l[1]->id, 'request_date' => '18/11/2026', 'qty' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower B', 'request_date' => '19/11/2026', 'replace_date' => '20/11/2026', 'qty' => '3'])->assertSessionHasNoErrors();

        // the customer info used when the customer is chosen
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', $c))->assertOk()->json();
        $this->assertSame(['done', 'Replaced 2× (3 qty), last 12/11/2026'], [$info['lines'][0]['replaced']['state'], $info['lines'][0]['replaced']['text']]);
        $this->assertSame(['open', '1 open request since 18/11/2026'], [$info['lines'][1]['replaced']['state'], $info['lines'][1]['replaced']['text']]);
        $this->assertSame(['none', 'Not replaced yet'], [$info['lines'][2]['replaced']['state'], $info['lines'][2]['replaced']['text']]);
        $this->assertSame('Replaced 1× (3 qty), last 20/11/2026', $info['location_returns']['Tower B']['text']);
        $this->assertSame(['Ceramic', 4, 5], [$info['lines'][0]['planter'] !== null ? 'Ceramic' : null, $info['lines'][0]['planter_qty'], $info['lines'][0]['plant_qty']]);

        // the edit page of the open request: every column, and the request itself is not counted for its own entry
        $open = AmcReturn::whereHas('items', fn ($q) => $q->where('amc_client_line_id', $l[1]->id))->first();
        $html = $this->actingAs($admin)->get(route('amc.returns.edit', $open))->assertOk()->getContent();
        foreach (['<th>Location 2</th>', '<th>Planter</th>', '<th class="r">Planter qty</th>', '<th>Plant</th>', '<th class="r">Plant qty</th>', '<th>Replaced so far</th>', 'id="amcLocSummary"'] as $s) {
            $this->assertStringContainsString($s, $html, $s);
        }
        $this->assertStringContainsString('<span class="badge b-ok">Replaced 2× (3 qty), last 12/11/2026</span>', $html);
        // Floor 6 (the request being edited is left out), Floor 7, and Lobby (its return was for the Location only)
        $this->assertSame(3, substr_count($html, '<span class="badge b-neu">Not replaced yet</span>'));
        $this->assertMatchesRegularExpression("/data-loc-returns='([^']*)'/", $html);
        preg_match("/data-loc-returns='([^']*)'/", $html, $mm);
        $this->assertSame('Replaced 1× (3 qty), last 20/11/2026', json_decode(html_entity_decode($mm[1]), true)['Tower B']['text']);
    }

    /** (23/11/2026, test fix) picking the customer again on the edit page must still leave the edited return out. */
    public function test_edit_page_leaves_its_own_return_out_also_when_the_customer_is_picked_again(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $loc = "Tow'er \"A\" <b>x</b>";
        $c = $this->amcClient('Smart Work', [['location_1' => $loc, 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5], ['location_1' => 'B', 'location_2' => 'Lobby']]);
        $line = $c->lines()->orderBy('line_no')->first();
        $base = ['amc_client_id' => $c->id, 'amc_plant_id' => $m['rose']->id, 'quality' => 'Reusable', 'qty' => '1'];
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => $loc, 'amc_client_line_id' => $line->id, 'request_date' => '18/11/2026'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => $loc, 'request_date' => '19/11/2026'])->assertSessionHasNoErrors();
        $r = AmcReturn::whereHas('items', fn ($q) => $q->where('amc_client_line_id', $line->id))->firstOrFail();

        // the edit page asks the customer info without its own return; the create page without any
        $html = $this->actingAs($admin)->get(route('amc.returns.edit', $r))->assertOk()->getContent();
        $this->assertStringContainsString('/info?except='.$r->id.'"', $html);
        $this->assertStringNotContainsString('except=', $this->actingAs($admin)->get(route('amc.returns.create'))->getContent());
        $this->assertSame('none', $this->actingAs($admin)->getJson(route('amc.clients.info', ['client' => $c, 'except' => $r->id]))->json('lines.0.replaced.state'));
        $this->assertSame('open', $this->actingAs($admin)->getJson(route('amc.clients.info', $c))->json('lines.0.replaced.state'));

        // a Location name with quotes and tags is escaped in the table and in data-loc-returns
        $this->assertStringNotContainsString('<b>x</b>', $html);
        preg_match("/data-loc-returns='([^']*)'/", $html, $mm);
        $this->assertSame('1 open request since 19/11/2026', json_decode(html_entity_decode($mm[1]), true)[$loc]['text']);
    }
}
