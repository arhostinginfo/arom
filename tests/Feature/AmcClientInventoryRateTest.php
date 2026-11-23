<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/**
 * (24/11/2026) AMC Client Inventory: each entry shows the plant's Rate from the Plant master (text, not typed) and
 * Amount = Rate x Plant qty, with a total per Location and for the customer. Nothing new is saved on the client.
 */
class AmcClientInventoryRateTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_rate_amount_and_totals_are_shown_from_the_plant_master(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $m['rose']->update(['rate' => '1200.50']);
        $c = $this->amcClient('Smart Work', [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => 4, 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 5],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_plant_id' => $m['money']->id, 'plant_qty' => 2],
            ['location_1' => 'Tower B', 'location_2' => 'Lobby', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 1],
        ]);

        $html = $this->actingAs($admin)->get(route('amc.clients.edit', $c))->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, '<th class="r" style="min-width:90px">Rate (₹)</th>')); // 2 Locations + the new-Location template
        $this->assertStringContainsString('data-rate="1200.50"', $html);
        $this->assertStringContainsString('<span data-inv-rate>1,200.50</span>', $html);
        $this->assertStringContainsString('<span data-inv-amount>6,002.50</span>', $html);   // 1200.50 x 5
        $this->assertStringContainsString('<span data-inv-amount></span>', $html);           // Money Plant has no rate
        $this->assertStringContainsString('<b data-inv-loctotal>6,002.50</b>', $html);       // Tower A
        $this->assertStringContainsString('<b data-inv-loctotal>1,200.50</b>', $html);       // Tower B
        $this->assertStringContainsString('<span id="amcInvTotal">7,203.00</span>', $html);
        // only display: no rate or amount field is posted
        $this->assertStringNotContainsString('[rate]', $html);
        $this->assertStringNotContainsString('[amount]', $html);
    }

    public function test_new_client_page_and_refused_save_show_rates(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $m['rose']->update(['rate' => '10']);

        $html = $this->actingAs($admin)->get(route('amc.clients.create'))->assertOk()->getContent();
        $this->assertStringContainsString('<span id="amcInvTotal">0.00</span>', $html);
        $tpl = substr($html, strpos($html, 'id="amcRowTpl"'));
        $tpl = substr($tpl, 0, strpos($tpl, '</template>'));
        $this->assertStringContainsString('data-rate="10.00"', $tpl);
        $this->assertStringContainsString('<span data-inv-amount></span>', $tpl);

        // a refused save (no customer) keeps the typed entries and shows their amounts
        $this->actingAs($admin)->from(route('amc.clients.create'))->post(route('amc.clients.store'), [
            'lines' => [['location_1' => 'A', 'location_2' => 'B', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '3']],
        ])->assertSessionHasErrors();
        $html = $this->actingAs($admin)->get(route('amc.clients.create'))->getContent();
        $this->assertStringContainsString('<span data-inv-amount>30.00</span>', $html);
        $this->assertStringContainsString('<span id="amcInvTotal">30.00</span>', $html);
    }
}
