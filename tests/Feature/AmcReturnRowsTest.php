<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Amc\AmcReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/**
 * (23/11/2026) Return & Replace holds rows: each entry of the chosen Location clicked on the page becomes a row with its
 * planter, planter qty, plant, plant size (from the Plant master, not typed), plant qty and rate; Submit saves them all.
 */
class AmcReturnRowsTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    private function setUpClient(): array
    {
        $m = $this->amcMasters();
        $c = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => 4, 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 2],
            ['location_1' => 'Tower B', 'location_2' => 'Lobby', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => 1],
        ]);

        return [$m, $c, $c->lines()->orderBy('line_no')->get()];
    }

    public function test_several_rows_are_saved_with_master_values_copied(): void
    {
        $admin = $this->admin();
        [$m, $c, $l] = $this->setUpClient();
        $m['rose']->update(['rate' => '120.50']);
        $head = ['amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '20/11/2026'];

        // the page: the row template shows the planter, plant, size and rate as text; only the qtys and quality are fields
        $html = $this->actingAs($admin)->get(route('amc.returns.create'))->assertOk()->getContent();
        $tpl = substr($html, strpos($html, 'id="amcItemTpl"'));
        $tpl = substr($tpl, 0, strpos($tpl, '</template>'));
        foreach (['name="items[__N__][amc_client_line_id]"', 'name="items[__N__][planter_qty]"', 'name="items[__N__][plant_qty]"', 'name="items[__N__][quality]"',
            '<span data-f="planter">', '<span data-f="plant">', '<span data-f="size">', '<span data-f="rate">', '<span data-f="amount">'] as $want) {
            $this->assertStringContainsString($want, $tpl, $want);
        }
        foreach (['[amc_planter_id]', '[amc_plant_id]', '[rate]', '<select class="inp" name="items[__N__][amc'] as $no) {
            $this->assertStringNotContainsString($no, $tpl, $no);
        }
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', $c))->json('lines.0');
        $this->assertSame([$m['ceramic']->id, 'Rose', '5 ft', '120.50'], [$info['planter_id'], $info['plant_name'], $info['plant_size'], $info['plant_rate']]);

        // posted planter / plant / rate / size are ignored: they come from the entry and the Plant master
        $this->actingAs($admin)->post(route('amc.returns.store'), $head + ['items' => [
            3 => ['amc_client_line_id' => $l[0]->id, 'amc_planter_id' => $m['money']->id, 'planter_qty' => '2', 'amc_plant_id' => $m['money']->id, 'plant_qty' => '3', 'rate' => '9', 'quality' => 'Reusable'],
            7 => ['amc_client_line_id' => $l[1]->id, 'plant_qty' => '1', 'plant_size' => 'typed 99 ft', 'location_2' => 'typed', 'quality' => 'Dead'],
        ]])->assertSessionHasNoErrors();
        $r = AmcReturn::firstOrFail();
        $this->assertSame(['Tower A', null], [$r->location_1, $r->quality]);
        $this->assertSame([
            [1, $l[0]->id, 'Floor 5', $m['ceramic']->id, 2, $m['rose']->id, '5 ft', 3, '120.50', 'Reusable', 361.5],
            [2, $l[1]->id, 'Floor 6', null, null, $m['money']->id, '2 ft', 1, null, 'Dead', null],
        ], $r->items->map(fn ($i) => [$i->line_no, $i->amc_client_line_id, $i->location_2, $i->amc_planter_id, $i->planter_qty, $i->amc_plant_id, $i->plant_size, $i->plant_qty, $i->rate, $i->quality, $i->amount()])->all());
        $this->assertSame('Floor 5: Ceramic round 12 inch ×2, Rose (5 ft) ×3 @ ₹120.50 · Reusable', ActivityLog::where('action', 'amc_return.created')->first()->changes['fields']['Row 1']);

        // the edit page: rows as text with the qtys, their limits, amount and total; the entries in them marked
        $html = $this->actingAs($admin)->get(route('amc.returns.edit', $r))->assertOk()->getContent();
        $this->assertStringContainsString('name="items[1][amc_client_line_id]" value="'.$l[1]->id.'"', $html);
        $this->assertStringContainsString('<span data-f="l2">Floor 5</span>', $html);
        $this->assertStringContainsString('name="items[0][plant_qty]" value="3"', $html);
        $this->assertStringContainsString('data-max="5"', $html);
        $this->assertStringContainsString('<span data-f="amount">361.50</span>', $html);
        $this->assertStringContainsString('<b id="amcItemTotal">361.50</b>', $html);
        $this->assertMatchesRegularExpression('/name="items\[0\]\[quality\]".*?<option selected>Reusable<\/option>/s', $html);
        $this->assertStringNotContainsString('id="quality"', $html);
        $this->assertMatchesRegularExpression('/<tr class="clk sel" data-id="'.$l[0]->id.'"/', $html);
        $this->assertMatchesRegularExpression('/<tr class="clk" data-id="'.$l[2]->id.'"/', $html);

        // the list: rows, total qty, the rows' qualities; the quality filter looks at the rows
        $this->actingAs($admin)->get(route('amc.returns.index'))->assertOk()->assertSee('Floor 6: Money Plant (2 ft) ×1 · Dead')->assertSee('<td data-label="Qty" class="r num">4</td>', false);
        $this->actingAs($admin)->get(route('amc.returns.index', ['quality' => 'Dead']))->assertSee($r->number());
        $this->actingAs($admin)->get(route('amc.returns.index', ['quality' => 'Repairable']))->assertDontSee($r->number());
        $csv = $this->actingAs($admin)->get(route('amc.returns.export'))->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("
", substr($csv, 3))));
        $this->assertSame(['Rate', 'Amount'], array_slice($rows[0], 13, 2));
        $this->assertSame(['120.50', '361.50', 'Reusable'], [$rows[1][13], $rows[1][14], $rows[1][17]]);

        // a row taken out on edit goes
        $this->actingAs($admin)->put(route('amc.returns.update', $r), $head + ['items' => [
            ['amc_client_line_id' => $l[1]->id, 'plant_qty' => '2', 'quality' => 'Repairable'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame([[$l[1]->id, 2, 'Repairable']], $r->fresh()->items->map(fn ($i) => [$i->amc_client_line_id, $i->plant_qty, $i->quality])->all());

        // a planter or plant used on a row cannot be deleted from its master
        $c->lines()->update(['amc_plant_id' => null, 'amc_planter_id' => null]);
        $this->actingAs($admin)->delete(route('amc.masters.plants.destroy', $m['money']->id))->assertSessionHasErrors('delete');
    }

    public function test_rows_are_checked(): void
    {
        $admin = $this->admin();
        [$m, $c, $l] = $this->setUpClient();
        $other = $this->amcClient('Rich Next');
        $head = ['amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '20/11/2026'];
        $post = fn (array $items) => $this->actingAs($admin)->post(route('amc.returns.store'), $head + ['items' => $items]);
        $ok = ['quality' => 'Reusable'];

        $post([])->assertSessionHasErrors(['items' => 'Click an entry of the Location to add it to the return.']);
        $post([['amc_client_line_id' => $l[2]->id, 'planter_qty' => '1'] + $ok])
            ->assertSessionHasErrors(['items.0.amc_client_line_id' => 'This entry is not under the chosen Location.']);
        $post([['amc_client_line_id' => $other->lines()->value('id'), 'plant_qty' => '1'] + $ok])
            ->assertSessionHasErrors(['items.0.amc_client_line_id' => 'Choose an entry of this customer.']);
        $post([['amc_client_line_id' => $l[0]->id, 'plant_qty' => '1'] + $ok, ['amc_client_line_id' => $l[0]->id, 'plant_qty' => '1'] + $ok])
            ->assertSessionHasErrors(['items.1.amc_client_line_id' => 'This entry is already in the return.']);
        $post([['amc_client_line_id' => $l[0]->id] + $ok])->assertSessionHasErrors(['items.0.plant_qty' => 'Enter the planter qty or the plant qty.']);
        $post([['amc_client_line_id' => $l[0]->id, 'plant_qty' => '1']])->assertSessionHasErrors(['items.0.quality' => 'Choose the quality.']);
        // at most the entry's own qty (Floor 5: 4 planters, 5 plants); Floor 6 has no planter
        $post([['amc_client_line_id' => $l[0]->id, 'planter_qty' => '5', 'plant_qty' => '6'] + $ok])->assertSessionHasErrors([
            'items.0.planter_qty' => 'At most 4 (the planter qty in the AMC Client Inventory).',
            'items.0.plant_qty' => 'At most 5 (the plant qty in the AMC Client Inventory).',
        ]);
        $post([['amc_client_line_id' => $l[1]->id, 'planter_qty' => '1'] + $ok])->assertSessionHasErrors(['items.0.planter_qty' => 'This entry has no planter.']);
        $post([['amc_client_line_id' => $l[0]->id, 'plant_qty' => '0'] + $ok])->assertSessionHasErrors('items.0.plant_qty');
        $this->assertSame(0, AmcReturn::count());
        // equal to the entry's own qty is fine; an inactive plant of the entry still comes along
        $m['rose']->update(['is_active' => false]);
        $post([['amc_client_line_id' => $l[0]->id, 'planter_qty' => '4', 'plant_qty' => '5'] + $ok])->assertSessionHasNoErrors();
        $this->assertSame([4, 5, $m['rose']->id], [AmcReturn::first()->items[0]->planter_qty, AmcReturn::first()->items[0]->plant_qty, AmcReturn::first()->items[0]->amc_plant_id]);

        // a refused save keeps the rows typed, Location 2 read again from the entry
        $this->actingAs($admin)->from(route('amc.returns.create'))->post(route('amc.returns.store'), array_merge($head, ['request_date' => '', 'items' => [
            ['amc_client_line_id' => $l[0]->id, 'plant_qty' => '9'] + $ok,
        ]]))->assertSessionHasErrors(['request_date', 'items.0.plant_qty']);
        $html = $this->actingAs($admin)->get(route('amc.returns.create'))->getContent();
        $this->assertStringContainsString('name="items[0][plant_qty]" value="9"', $html);
        $this->assertStringContainsString('<span data-f="l2">Floor 5</span>', $html);
    }

    public function test_refused_save_keeps_row_keys_so_errors_show_on_their_row(): void
    {
        $admin = $this->admin();
        [$m, $c, $l] = $this->setUpClient();
        // row 1 was taken out on the page: rows 0 and 2 are posted, row 2 without its plant qty
        $this->actingAs($admin)->from(route('amc.returns.create'))->post(route('amc.returns.store'), [
            'amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '20/11/2026', 'quality' => 'Reusable', 'items' => [
                0 => ['amc_client_line_id' => $l[0]->id, 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '1'],
                2 => ['amc_client_line_id' => $l[1]->id, 'amc_plant_id' => $m['money']->id, 'plant_qty' => ''],
                5 => 'not a row',
            ]])->assertSessionHasErrors('items.2.plant_qty');
        $html = $this->actingAs($admin)->get(route('amc.returns.create'))->assertOk()->getContent();
        $this->assertStringContainsString('name="items[2][plant_qty]"', $html);
        $this->assertStringContainsString('id="items.2.plant_qty-err"', $html);
        $this->assertStringNotContainsString('name="items[1][', $html);
        // a row added next takes a new index, not one already on the page
        $this->assertStringContainsString('data-next="3"', $html);
    }

    /** Every row taken out on edit: the save is refused and the page shows no rows, not the saved ones again. */
    public function test_refused_save_with_no_rows_does_not_bring_saved_rows_back(): void
    {
        $admin = $this->admin();
        [$m, $c, $l] = $this->setUpClient();
        $head = ['amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '20/11/2026', 'quality' => 'Reusable'];
        $this->actingAs($admin)->post(route('amc.returns.store'), $head + ['items' => [['amc_client_line_id' => $l[0]->id, 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '1']]])->assertSessionHasNoErrors();
        $r = AmcReturn::firstOrFail();

        $this->actingAs($admin)->from(route('amc.returns.edit', $r))->put(route('amc.returns.update', $r), $head)->assertSessionHasErrors('items');
        $html = $this->actingAs($admin)->get(route('amc.returns.edit', $r))->getContent();
        $this->assertStringNotContainsString('name="items[0][amc_client_line_id]"', $html);
        $this->assertSame(1, $r->items()->count());
        // without old input the saved rows show
        $this->flushSession();
        $this->assertStringContainsString('name="items[0][amc_client_line_id]"', $this->actingAs($admin)->get(route('amc.returns.edit', $r))->getContent());
    }

    /** (23/11/2026) The Plant master has a Rate (₹): typed with commas or ₹, shown in the list, refused when not an amount. */
    public function test_plant_master_rate(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('amc.masters.plants.store'), ['name' => 'Areca Palm', 'size' => '4 ft', 'rate' => '₹ 1,250.5', 'is_active' => 1])->assertSessionHasNoErrors();
        $p = \App\Models\Amc\AmcPlant::where('name', 'Areca Palm')->firstOrFail();
        $this->assertSame('1250.50', $p->rate);
        $this->actingAs($admin)->get(route('amc.masters.plants.index'))->assertOk()->assertSee('<th>Rate (₹)</th>', false)->assertSee('1,250.50');
        foreach (['abc', '-1', '1.234'] as $bad) {
            $this->actingAs($admin)->post(route('amc.masters.plants.store'), ['name' => 'Bad '.$bad, 'rate' => $bad, 'is_active' => 1])->assertSessionHasErrors('rate');
        }
        $this->actingAs($admin)->post(route('amc.masters.plants.store'), ['name' => 'No Rate', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertNull(\App\Models\Amc\AmcPlant::where('name', 'No Rate')->value('rate'));
    }

    /** (24/11/2026, test fix) A row saved before the rows came from entries (no entry): re-saving keeps its Location 2. */
    public function test_row_without_an_entry_keeps_its_location_2_when_saved_again(): void
    {
        $admin = $this->admin();
        [$m, $c, $l] = $this->setUpClient();
        $r = AmcReturn::create(['amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '2026-11-20']);
        $r->items()->create(['line_no' => 1, 'location_2' => 'Old floor', 'amc_plant_id' => $m['rose']->id, 'plant_size' => '5 ft', 'plant_qty' => 2, 'quality' => 'Dead']);

        $html = $this->actingAs($admin)->get(route('amc.returns.edit', $r))->assertOk()->getContent();
        $this->assertStringContainsString('<span data-f="l2">Old floor</span>', $html);
        $this->assertStringContainsString('name="items[0][location_2]" value="Old floor"', $html);
        // the page posts the row back as it shows it
        $this->actingAs($admin)->put(route('amc.returns.update', $r), ['amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '20/11/2026', 'items' => [
            ['amc_client_line_id' => '', 'location_2' => 'Old floor', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '3', 'quality' => 'Dead'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['Old floor', 3], [$r->items()->first()->location_2, $r->items()->first()->plant_qty]);
        // a row of an entry never takes a posted Location 2
        $this->actingAs($admin)->put(route('amc.returns.update', $r), ['amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '20/11/2026', 'items' => [
            ['amc_client_line_id' => $l[0]->id, 'location_2' => 'typed', 'plant_qty' => '1', 'quality' => 'Dead'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('Floor 5', $r->items()->first()->location_2);
    }

    /** (24/11/2026, test fix) No qty on a row whose entry has only a planter: the message is under the planter qty (the plant qty box is disabled). */
    public function test_missing_qty_message_is_under_the_box_the_row_can_fill(): void
    {
        $admin = $this->admin();
        [$m, $c, $l] = $this->setUpClient();
        $this->actingAs($admin)->post(route('amc.returns.store'), ['amc_client_id' => $c->id, 'location_1' => 'Tower B', 'request_date' => '20/11/2026',
            'items' => [['amc_client_line_id' => $l[2]->id, 'quality' => 'Reusable']]])
            ->assertSessionHasErrors(['items.0.planter_qty' => 'Enter the planter qty or the plant qty.'])->assertSessionDoesntHaveErrors('items.0.plant_qty');
    }

    /** (24/11/2026) A saved return keeps its rate and plant size when the Plant master changes; a new row takes the new ones. */
    public function test_saved_rows_keep_their_rate_and_size(): void
    {
        $admin = $this->admin();
        [$m, $c, $l] = $this->setUpClient();
        $m['rose']->update(['rate' => '100']);
        $m['money']->update(['rate' => '40']);
        $head = ['amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '20/11/2026'];
        $this->actingAs($admin)->post(route('amc.returns.store'), $head + ['items' => [['amc_client_line_id' => $l[0]->id, 'plant_qty' => '2', 'quality' => 'Dead']]])->assertSessionHasNoErrors();
        $r = AmcReturn::firstOrFail();

        $m['rose']->update(['rate' => '150', 'size' => '6 ft']);
        $html = $this->actingAs($admin)->get(route('amc.returns.edit', $r))->getContent();
        $this->assertStringContainsString('<span data-f="rate">100.00</span>', $html);
        $this->assertStringContainsString('<span data-f="size">5 ft</span>', $html);
        $this->assertStringContainsString('<b id="amcItemTotal">200.00</b>', $html);

        // saved again with a row added: the old row keeps 100 / 5 ft, the new one takes the master's 40
        $this->actingAs($admin)->put(route('amc.returns.update', $r), $head + ['items' => [
            ['amc_client_line_id' => $l[0]->id, 'plant_qty' => '3', 'quality' => 'Dead'],
            ['amc_client_line_id' => $l[1]->id, 'plant_qty' => '1', 'quality' => 'Reusable'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame([['100.00', '5 ft', 3], ['40.00', '2 ft', 1]], $r->fresh()->items->map(fn ($i) => [$i->rate, $i->plant_size, $i->plant_qty])->all());
    }

    /**
     * (24/11/2026, e2e fix) On the edit page a saved row taken out and its entry clicked again is saved with the row's old
     * size and rate, so the entry (the page and the customer info re-read on the page) offers those, not the Plant master's.
     */
    public function test_entry_added_again_on_edit_shows_the_saved_rate_and_size(): void
    {
        $admin = $this->admin();
        [$m, $c, $l] = $this->setUpClient();
        $m['rose']->update(['rate' => '100']);
        $head = ['amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '20/11/2026'];
        $this->actingAs($admin)->post(route('amc.returns.store'), $head + ['items' => [['amc_client_line_id' => $l[0]->id, 'plant_qty' => '2', 'quality' => 'Dead']]])->assertSessionHasNoErrors();
        $r = AmcReturn::firstOrFail();
        $m['rose']->update(['rate' => '150', 'size' => '6 ft']);

        $html = $this->actingAs($admin)->get(route('amc.returns.edit', $r))->getContent();
        $this->assertMatchesRegularExpression('/data-id="'.$l[0]->id.'"[^>]*data-size="5 ft" data-rate="100.00"/', $html);
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', ['client' => $c, 'except' => $r->id]))->json('lines.0');
        $this->assertSame(['5 ft', '100.00'], [$info['plant_size'], $info['plant_rate']]);
        // elsewhere (a new return, here after a refused save) the entry offers the Plant master's
        $this->actingAs($admin)->from(route('amc.returns.create'))->post(route('amc.returns.store'), $head)->assertSessionHasErrors('items');
        $this->assertMatchesRegularExpression('/data-id="'.$l[0]->id.'"[^>]*data-size="6 ft" data-rate="150.00"/',
            $this->actingAs($admin)->get(route('amc.returns.create'))->getContent());
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', $c))->json('lines.0');
        $this->assertSame(['6 ft', '150.00'], [$info['plant_size'], $info['plant_rate']]);

        // taken out and added again: saved with the old 100 / 5 ft, as the page showed
        $this->actingAs($admin)->put(route('amc.returns.update', $r), $head + ['items' => [5 => ['amc_client_line_id' => $l[0]->id, 'plant_qty' => '1', 'quality' => 'Dead']]])->assertSessionHasNoErrors();
        $this->assertSame([['100.00', '5 ft', 1]], $r->fresh()->items->map(fn ($i) => [$i->rate, $i->plant_size, $i->plant_qty])->all());
    }
}
