<?php

namespace Tests\Feature;

use App\Models\Amc\AmcReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/** Fixes after the AMC end-to-end re-test (22/11/2026): Return & Replace Location / entry picker. */
class AmcEndToEndRetestFixesTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    /**
     * "No entry" sends an empty (or, from an old page, no) amc_client_line_id. An edit that moved the entry to
     * another Location without picking an entry used to keep the old entry and its Location 1 / Location 2.
     */
    public function test_edit_to_another_location_without_an_entry_clears_the_old_entry(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $client = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 1', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 2],
            ['location_1' => 'Tower B', 'location_2' => 'Lobby', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 3],
        ]);
        $lineA = $client->lines()->where('location_1', 'Tower A')->firstOrFail();
        $base = ['amc_client_id' => $client->id, 'request_date' => '15/11/2026', 'amc_plant_id' => $m['rose']->id, 'qty' => '1', 'quality' => 'Dead'];

        $this->actingAs($admin)->post(route('amc.returns.store'), $base + ['location_1' => 'Tower A', 'amc_client_line_id' => $lineA->id])
            ->assertSessionHasNoErrors();
        $r = AmcReturn::firstOrFail();
        $this->assertSame([$lineA->id, 'Tower A', 'Floor 1'], [$r->amc_client_line_id, $r->location_1, $r->location_2]);

        // Location changed to Tower B, no entry picked: the field is simply missing from the request.
        $this->actingAs($admin)->put(route('amc.returns.update', $r), $base + ['location_1' => 'Tower B'])
            ->assertSessionHasNoErrors()->assertRedirect(route('amc.returns.edit', $r));
        $r->refresh();
        $this->assertSame([null, 'Tower B', null], [$r->amc_client_line_id, $r->location_1, $r->location_2]);
    }

    /**
     * On a phone the entry rows are cards (display:grid) and the entries box is a .field (display:grid): both beat the
     * browser's own [hidden] rule, so every Location's entries showed. amc.css hides them again.
     */
    public function test_hidden_entries_stay_hidden_on_a_phone(): void
    {
        $css = preg_replace('/\s+/', '', file_get_contents(public_path('css/amc.css')));
        $this->assertStringContainsString('#amcLocRowstr[hidden]{display:none}', $css);
        $this->assertStringContainsString('#amcLocEntries[hidden]{display:none}', $css);
    }
}
