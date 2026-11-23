<?php

namespace Tests\Feature;

use App\Models\Amc\AmcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/** (22/11/2026) AMC Client Inventory: the gardener is picked from the active Gardeners of the AMC Employee master. */
class AmcClientGardenerTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_add_client_offers_only_active_gardeners_and_saves_the_pick(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $g = $this->amcEmployee('Gopal Gardener', 'Gardener');
        $mgr = $this->amcEmployee('Meera Manager', 'Operation Manager');
        $left = $this->amcEmployee('Lalit Left', 'Gardener', ['status' => 'Inactive']);

        $html = $this->actingAs($admin)->get(route('amc.clients.create'))->assertOk()->getContent();
        $box = substr($html, strpos($html, 'id="gardener_id"'), 800);
        $this->assertStringContainsString('>Gopal Gardener ('.$g->code.')<', $box);
        $this->assertStringNotContainsString('Meera Manager', $box);
        $this->assertStringNotContainsString('Lalit Left', $box);

        $base = ['name' => 'Green Tower', 'amc_vertical_id' => $m['rental']->id, 'lines' => [['location_1' => 'Lobby', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '5']]];
        foreach ([$mgr, $left] as $e) {
            $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['gardener_id' => $e->id])
                ->assertSessionHasErrors(['gardener_id' => 'Choose an active AMC employee whose role is Gardener.']);
        }
        $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['gardener_id' => $g->id])->assertSessionHasNoErrors();
        $client = AmcClient::where('name', 'Green Tower')->firstOrFail();
        $this->assertSame($g->id, $client->gardener_id);

        // shown on the list and selected on the form; the contract and Return pages get it from the client's info
        $this->actingAs($admin)->get(route('amc.clients.index'))->assertSee('Gopal Gardener');
        $this->actingAs($admin)->get(route('amc.clients.edit', $client))->assertSee('<option value="'.$g->id.'" selected>', false);
        $this->assertSame($g->id, $this->actingAs($admin)->getJson(route('amc.clients.info', $client))->json('gardener_id'));

        // a gardener made inactive since may stay on the client; clearing it is allowed
        $g->update(['status' => 'Inactive']);
        $this->actingAs($admin)->put(route('amc.clients.update', $client), $base + ['gardener_id' => $g->id])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('amc.clients.update', $client), $base + ['gardener_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($client->fresh()->gardener_id);
    }

    /** Test fix: a client's gardener made Inactive (or moved to another role) is not offered to a new contract / Return. */
    public function test_client_info_offers_only_an_active_gardener(): void
    {
        $admin = $this->admin();
        $g = $this->amcEmployee('Gopal Gardener', 'Gardener');
        $client = $this->amcClient();
        $client->update(['gardener_id' => $g->id]);
        $this->assertSame($g->id, $this->actingAs($admin)->getJson(route('amc.clients.info', $client))->json('gardener_id'));

        $g->update(['status' => 'Inactive']);
        $this->actingAs($admin)->getJson(route('amc.clients.info', $client))->assertJsonPath('gardener_id', null)->assertJsonPath('gardener', null);

        $g->update(['status' => 'Active', 'role' => 'Operation Manager']);
        $this->actingAs($admin)->getJson(route('amc.clients.info', $client))->assertJsonPath('gardener_id', null);
        $this->assertSame($g->id, $client->fresh()->gardener_id); // still on the client itself
    }

    public function test_a_gardener_on_a_client_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $g = $this->amcEmployee('Gopal Gardener', 'Gardener');
        $this->amcClient()->update(['gardener_id' => $g->id]);
        $this->actingAs($admin)->delete(route('amc.employees.destroy', $g))->assertSessionHasErrors('delete');
        $this->assertNotNull($g->fresh());
    }

    public function test_a_user_who_may_edit_clients_can_search_gardeners(): void
    {
        $this->amcEmployee('Gopal Gardener', 'Gardener');
        $user = $this->amcUser(['amc-client.view', 'amc-client.edit']);
        $this->actingAs($user)->getJson(route('amc.lookup.employees', ['role' => 'Gardener', 'q' => 'Gop']))->assertOk()->assertSee('Gopal Gardener');
        $viewer = $this->amcUser(['amc-client.view'], 'viewer@nursery.local');
        $this->actingAs($viewer)->getJson(route('amc.lookup.employees', ['role' => 'Gardener', 'q' => 'Gop']))->assertForbidden();
    }
}
