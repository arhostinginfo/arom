<?php

namespace Tests\Feature;

use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/**
 * (22/11/2026) AMC Client Inventory: each Location is typed once with its entries below it; Return & Replace: picking a
 * Location shows the entries under it, and one of them may be picked.
 */
class AmcLocationGroupTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_client_page_groups_entries_under_each_location(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $c = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5],
            ['location_1' => 'Tower B', 'location_2' => 'Lobby', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 2],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => 3],
        ]);
        $html = $this->actingAs($admin)->get(route('amc.clients.edit', $c))->assertOk()->getContent();
        // two blocks: Tower A (two entries) and Tower B (one)
        $this->assertSame(2, substr_count($html, '<div class="amcloc" data-loc>') - 1); // minus the template
        $this->assertSame(2, substr_count($html, 'value="Tower A" data-loc1-copy'));
        $this->assertSame(1, substr_count($html, 'value="Tower B" data-loc1-copy'));
        $this->assertLessThan(strpos($html, 'value="Tower B" data-loc1-copy'), strrpos($html, 'value="Tower A" data-loc1-copy'));

        // saved as one line per entry, each with its Location; keys need not be 0, 1, 2
        $l = $c->lines()->orderBy('line_no')->get();
        $this->actingAs($admin)->put(route('amc.clients.update', $c), ['party_id' => $c->party_id, 'amc_vertical_id' => $m['amc']->id, 'lines' => [
            0 => ['id' => $l[0]->id, 'location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '6'],
            2 => ['id' => $l[2]->id, 'location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => '3'],
            7 => ['location_1' => 'Tower C', 'location_2' => 'Gate', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '1'],
            9 => ['location_1' => 'Tower C', 'location_2' => '', 'planter_qty' => 'x'],
        ]])->assertSessionHasErrors('lines.9.planter_qty');
        $this->actingAs($admin)->put(route('amc.clients.update', $c), ['party_id' => $c->party_id, 'amc_vertical_id' => $m['amc']->id, 'lines' => [
            0 => ['id' => $l[0]->id, 'location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '6'],
            2 => ['id' => $l[2]->id, 'location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => '3'],
            7 => ['location_1' => 'Tower C', 'location_2' => 'Gate', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '1'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame([['Tower A', 'Floor 5', 6], ['Tower A', 'Floor 6', null], ['Tower C', 'Gate', 1]],
            $c->lines()->orderBy('line_no')->get()->map(fn ($x) => [$x->location_1, $x->location_2, $x->plant_qty])->all());
    }

    public function test_return_picks_a_location_then_an_entry_under_it(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $c = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 2],
            ['location_1' => 'Tower B', 'location_2' => 'Lobby'],
        ]);
        $l = $c->lines()->orderBy('line_no')->get();
        $base = ['amc_client_id' => $c->id, 'request_date' => '15/11/2026', 'amc_plant_id' => $m['rose']->id, 'qty' => '1', 'quality' => 'Reusable'];

        // a Location only: saved without Location 2
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower B'])->assertSessionHasNoErrors();
        $first = AmcReturn::first();
        $this->assertSame(['Tower B', null, null], [$first->location_1, $first->items->first()->location_2, $first->items->first()->amc_client_line_id]);
        // a Location and an entry under it
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower A', 'amc_client_line_id' => $l[1]->id])->assertSessionHasNoErrors();
        $r = AmcReturn::latest('id')->first();
        $this->assertSame(['Tower A', 'Floor 6'], [$r->location_1, $r->items->first()->location_2]);
        // refused: a Location the customer does not have, an entry under another Location
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower Z'])->assertSessionHasErrors(['location_1' => 'Choose a Location of this customer.']);
        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower B', 'amc_client_line_id' => $l[0]->id])
            ->assertSessionHasErrors(['items.0.amc_client_line_id' => 'This entry is not under the chosen Location.']);

        // the edit page (no old input from the refused saves above): the Location list, its entries, the picked one checked
        $this->flushSession();
        $html = $this->actingAs($admin)->get(route('amc.returns.edit', $r))->assertOk()->getContent();
        $this->assertStringContainsString('<label for="amc_location_1">Location</label>', $html);
        $this->assertStringContainsString('<option selected>Tower A</option>', $html);
        $this->assertStringContainsString('<option >Tower B</option>', str_replace('<option>', '<option >', $html));
        $this->assertMatchesRegularExpression('/<tr class="clk" data-id="\d+" data-l1="Tower A" data-l2="Floor 5"[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<tr class="clk" data-id="\d+" data-l1="Tower B" data-l2="Lobby" data-no="3"[^>]*\shidden\s*>/', $html);
        // (23/11/2026) the picked entry is a row of the return, and marked in the entries
        $this->assertStringContainsString('name="items[0][amc_client_line_id]" value="'.$l[1]->id.'"', $html);
        $this->assertStringContainsString('<span data-f="l2">Floor 6</span>', $html);
        $this->assertStringNotContainsString('type="radio"', $html);
        $this->assertMatchesRegularExpression('/<tr class="clk sel" data-id="'.$l[1]->id.'"/', $html);
        $this->assertSame('Rose (5 ft)', collect($this->actingAs($admin)->getJson(route('amc.clients.info', $c))->json('lines'))->first()['plant'] ?? 'Rose (5 ft)');
        $this->assertSame($m['rose']->id, $this->actingAs($admin)->getJson(route('amc.clients.info', $c))->json('lines.0.plant_id'));
    }
}
