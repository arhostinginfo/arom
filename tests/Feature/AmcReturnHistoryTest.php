<?php

namespace Tests\Feature;

use App\Models\Amc\AmcReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/**
 * (24/11/2026) An entry can be returned many times: the History page of a customer lists, per Location and per entry,
 * every return (newest first) with its dates, status, quantities, rate, amount and quality. Linked from each row of the
 * Return & Replace list, from the edit page and from each returned entry on the form.
 */
class AmcReturnHistoryTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_history_lists_every_return_of_each_entry_per_location(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $m['rose']->update(['rate' => '100']);
        $c = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 2],
            ['location_1' => 'Tower B', 'location_2' => 'Lobby', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 1],
        ]);
        $l = $c->lines()->orderBy('line_no')->get();
        $post = fn ($loc, $date, $replace, $rows) => $this->actingAs($admin)->post(route('amc.returns.store'), [
            'amc_client_id' => $c->id, 'location_1' => $loc, 'request_date' => $date, 'replace_date' => $replace, 'items' => $rows,
        ])->assertSessionHasNoErrors();
        $post('Tower A', '01/10/2026', '05/10/2026', [['amc_client_line_id' => $l[0]->id, 'plant_qty' => '2', 'quality' => 'Dead']]);
        $post('Tower A', '01/11/2026', null, [['amc_client_line_id' => $l[0]->id, 'plant_qty' => '3', 'quality' => 'Repairable']]);
        $post('Tower B', '02/11/2026', null, [['amc_client_line_id' => $l[2]->id, 'plant_qty' => '1', 'quality' => 'Reusable']]);
        [$r1, $r2, $r3] = AmcReturn::orderBy('id')->get()->all();

        $html = $this->actingAs($admin)->get(route('amc.returns.history', ['client' => $c->id, 'return' => $r2->id]))->assertOk()->getContent();
        $this->assertStringContainsString('Return &amp; Replace history: Smart Work', $html);
        $this->assertStringContainsString('3 returns', $html);
        // both Locations, every entry; Floor 6 never returned
        $this->assertStringContainsString('<h2>Tower A</h2>', $html);
        $this->assertStringContainsString('<h2>Tower B</h2>', $html);
        $this->assertStringContainsString('Not returned yet.', $html);
        // Floor 5: two returns, newest first, with status, amount and the marked return
        $e = substr($html, strpos($html, 'id="e-'.$l[0]->id.'"'));
        $e = substr($e, 0, strpos($e, 'id="e-'.$l[1]->id.'"'));
        $this->assertLessThan(strpos($e, $r1->number()), strpos($e, $r2->number()));
        $this->assertStringContainsString('Replaced 1× (2 qty), last 05/10/2026 · 1 open request since 01/11/2026', $e);
        $this->assertStringContainsString('>Open</span>', $e);
        $this->assertStringContainsString('>Replaced</span>', $e);
        $this->assertStringContainsString('300.00', $e);   // 3 x 100
        $this->assertStringContainsString('500.00', $e);   // total of the entry
        $this->assertMatchesRegularExpression('#<tr class="mark">\s*<td data-label="Return no" class="num"><a href="[^"]+"><b>'.$r2->number().'#', $e);

        // one Location only
        $one = $this->actingAs($admin)->get(route('amc.returns.history', ['client' => $c->id, 'location' => 'Tower B']))->getContent();
        $this->assertStringNotContainsString('<h2>Tower A</h2>', $one);
        $this->assertStringContainsString($r3->number(), $one);

        // links: each row of the list, the edit page, and the returned entry on the form
        $list = $this->actingAs($admin)->get(route('amc.returns.index'))->getContent();
        $this->assertStringContainsString(e(route('amc.returns.history', ['client' => $c->id, 'return' => $r1->id])), $list);
        $edit = $this->actingAs($admin)->get(route('amc.returns.edit', $r3))->getContent();
        $this->assertStringContainsString(e(route('amc.returns.history', ['client' => $c->id, 'return' => $r3->id])), $edit);
        $this->assertStringContainsString('History (2)</a>', $edit); // Floor 5 on the Tower A rows (hidden under Tower B)
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', $c))->json('lines');
        $this->assertSame([2, 0, 1], array_column(array_column($info, 'replaced'), 'count'));
    }

    /** (24/11/2026, test fix) "All locations" posts an empty location: every Location shows, not only the rows with none */
    public function test_location_filter_all_none_unknown_and_entry_removed(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $c = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5],
            ['location_1' => null, 'location_2' => 'Gate', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 1],
            ['location_1' => '123', 'location_2' => 'Roof', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 1],
        ]);
        $l = $c->lines()->orderBy('line_no')->get();
        $this->actingAs($admin)->post(route('amc.returns.store'), [
            'amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '01/11/2026', 'items' => [['amc_client_line_id' => $l[0]->id, 'plant_qty' => '1', 'quality' => 'Dead']],
        ])->assertSessionHasNoErrors();
        $get = fn ($q) => $this->actingAs($admin)->get(route('amc.returns.history', ['client' => $c->id] + $q))->assertOk()->getContent();

        foreach ([[], ['location' => ''], ['location' => 'Nowhere'], ['location' => ['x']]] as $q) {
            $html = $get($q);
            $this->assertStringContainsString('<h2>Tower A</h2>', $html, json_encode($q));
            $this->assertStringContainsString('<h2>(no Location)</h2>', $html, json_encode($q));
        }
        $none = $get(['location' => \App\Http\Controllers\Amc\AmcReturnController::NO_LOCATION]);
        $this->assertStringContainsString('<h2>(no Location)</h2>', $none);
        $this->assertStringNotContainsString('<h2>Tower A</h2>', $none);
        // a Location that is a number stays chosen in the picker
        $num = $get(['location' => '123']);
        $this->assertStringContainsString('<option value="123" selected', $num);
        $this->assertStringNotContainsString('<h2>Tower A</h2>', $num);

        // the entry removed from the inventory: its row moves to "Rows without an entry" under the return's Location
        $l[0]->delete();
        $html = $get(['location' => 'Tower A']);
        $this->assertStringContainsString('Rows without an entry', $html);
        $this->assertStringContainsString(AmcReturn::first()->number(), $html);
    }

    /** (24/11/2026, test fix) on the edit page of an entry's only return, History (1) shows: the count is of every return */
    public function test_edit_page_history_count_includes_the_return_being_edited(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $c = $this->amcClient('Smart Work', [['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5]]);
        $line = $c->lines()->first();
        $this->actingAs($admin)->post(route('amc.returns.store'), [
            'amc_client_id' => $c->id, 'location_1' => 'Tower A', 'request_date' => '01/11/2026', 'items' => [['amc_client_line_id' => $line->id, 'plant_qty' => '1', 'quality' => 'Dead']],
        ])->assertSessionHasNoErrors();
        $r = AmcReturn::first();

        $edit = $this->actingAs($admin)->get(route('amc.returns.edit', $r))->getContent();
        $this->assertStringContainsString('Not replaced yet</span> <a class="amchlink"', $edit); // status still without this return
        $this->assertStringContainsString('History (1)</a>', $edit);
        $st = $this->actingAs($admin)->getJson(route('amc.clients.info', ['client' => $c->id, 'except' => $r->id]))->json('lines.0.replaced');
        $this->assertSame(['count' => 1, 'state' => 'none', 'text' => 'Not replaced yet'], $st);
        // a new return: counts it, with its status
        $this->assertStringContainsString('1 open request since 01/11/2026', $this->actingAs($admin)->getJson(route('amc.clients.info', $c))->json('lines.0.replaced.text'));
    }

    public function test_history_queries_do_not_grow_with_the_returns(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $c = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5],
            ['location_1' => 'Tower B', 'location_2' => 'Lobby', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 5],
        ]);
        $l = $c->lines()->orderBy('line_no')->get();
        $count = function () use ($admin, $c) {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            $this->actingAs($admin)->get(route('amc.returns.history', $c))->assertOk();
            $n = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $n;
        };
        $post = fn ($loc, $line) => $this->actingAs($admin)->post(route('amc.returns.store'), [
            'amc_client_id' => $c->id, 'location_1' => $loc, 'request_date' => '01/11/2026', 'items' => [['amc_client_line_id' => $line->id, 'plant_qty' => '1', 'quality' => 'Dead']],
        ])->assertSessionHasNoErrors();
        $post('Tower A', $l[0]);
        $count(); // warm the per-request caches (permissions, settings)
        $one = $count();
        $post('Tower A', $l[0]);
        $post('Tower B', $l[1]);
        $post('Tower B', $l[1]);
        $this->assertSame($one, $count());
    }

    public function test_history_needs_the_return_view_right(): void
    {
        $m = $this->amcMasters();
        $c = $this->amcClient('Smart Work', [['location_1' => 'A', 'location_2' => 'B', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 1]]);
        $this->actingAs($this->amcUser([], 'no.rights@nursery.local'))->get(route('amc.returns.history', $c))->assertForbidden();
        $this->actingAs($this->amcUser(['amc-return.view']))->get(route('amc.returns.history', $c))->assertOk()->assertSee('Not returned yet.');
    }
}
