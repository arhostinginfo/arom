<?php

namespace Tests\Feature;

use App\Models\Amc\AmcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\AmcFixtures;
use Tests\TestCase;

/** (22/11/2026) AMC Client Inventory: Customer name is a dropdown of the active customers of the Party master. */
class AmcClientPartyTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_customer_name_lists_only_active_customer_parties_not_yet_in_the_master(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $both = $this->amcParty('Both Ways Ltd', 'Both');
        $supplier = $this->amcParty('Supplier Only Ltd', 'Supplier');
        $inactive = $this->amcParty('Sleeping Ltd', 'Customer', 'Inactive');
        $this->amcClient('Smart Work');

        $html = $this->actingAs($admin)->get(route('amc.clients.create'))->assertOk()->getContent();
        $box = substr($html, strpos($html, 'id="party_id"'), 3000);
        $box = substr($box, 0, strpos($box, '</select>'));
        $this->assertStringContainsString('>Both Ways Ltd<', $box);
        $this->assertStringContainsString('>Rich Next<', $box);
        foreach (['Supplier Only Ltd', 'Sleeping Ltd', 'Smart Work<'] as $no) {
            $this->assertStringNotContainsString('>'.$no, $box, $no);
        }
        // the type-ahead gives the same list
        $labels = collect($this->actingAs($admin)->getJson(route('amc.lookup.parties', ['q' => 'S']))->assertOk()->json())->pluck('label')->all();
        $this->assertNotContains('Smart Work', $labels);
        $this->assertNotContains('Supplier Only Ltd', $labels);
        $this->assertNotContains('Sleeping Ltd', $labels);

        // saved with the party; the name is the party's
        $base = ['amc_vertical_id' => $m['amc']->id];
        $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['party_id' => $both->id])->assertSessionHasNoErrors();
        $c = AmcClient::where('party_id', $both->id)->firstOrFail();
        $this->assertSame('Both Ways Ltd', $c->name);

        // refused: none, a supplier, an inactive party, one already in the master
        $this->actingAs($admin)->post(route('amc.clients.store'), $base)->assertSessionHasErrors(['party_id' => 'Choose the customer from the Party master.']);
        foreach ([$supplier, $inactive] as $p) {
            $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['party_id' => $p->id])->assertSessionHasErrors(['party_id' => 'Choose an active customer from the Party master.']);
        }
        $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['party_id' => $both->id])->assertSessionHasErrors(['party_id' => 'This customer is already in the AMC Client Inventory.']);
        $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['name' => 'Nobody Ltd'])->assertSessionHasErrors(['party_id' => '"Nobody Ltd" is not an active customer in the Party master.']);

        // the client's own party stays selectable after it is made inactive
        $both->update(['status' => 'Inactive']);
        $this->actingAs($admin)->get(route('amc.clients.edit', $c))->assertOk()->assertSee('<option value="'.$both->id.'" selected>Both Ways Ltd</option>', false);
        $this->actingAs($admin)->put(route('amc.clients.update', $c), $base + ['party_id' => $both->id])->assertSessionHasNoErrors();
    }

    /** (22/11/2026, test fix) A wrong customer gives one message, not a second one about the name. */
    public function test_wrong_customer_is_reported_once(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $both = $this->amcParty('Both Ways Ltd', 'Both');
        $base = ['amc_vertical_id' => $m['amc']->id];
        $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['party_id' => $both->id])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('amc.clients.store'), $base)->assertSessionHasErrors('party_id')->assertSessionDoesntHaveErrors('name');
        $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['party_id' => $both->id])->assertSessionHasErrors('party_id')->assertSessionDoesntHaveErrors('name');
        $this->actingAs($admin)->post(route('amc.clients.store'), $base + ['name' => 'Nobody Ltd'])->assertSessionHasErrors('party_id')->assertSessionDoesntHaveErrors('name');
    }

    public function test_import_needs_an_active_customer_party(): void
    {
        $admin = $this->admin();
        $this->amcMasters();
        $csv = "Customer name,Business segment\nnobody ltd,AMC\n";
        $path = tempnam(sys_get_temp_dir(), 'amc');
        file_put_contents($path, $csv);
        $this->actingAs($admin)->from(route('amc.clients.index'))->post(route('amc.clients.import'), ['file' => new UploadedFile($path, 'c.csv', 'text/csv', null, true)])->assertSessionHasErrors('file');
        $this->assertStringContainsString('customer "nobody ltd" is not an active customer in the Party master', session('errors')->first('file'));

        file_put_contents($path, "Customer name,Business segment\nrich next,AMC\n");
        $this->actingAs($admin)->post(route('amc.clients.import'), ['file' => new UploadedFile($path, 'c.csv', 'text/csv', null, true)])->assertSessionHasNoErrors();
        $c = AmcClient::firstOrFail();
        $this->assertSame('Rich Next', $c->name);
        $this->assertNotNull($c->party_id);
    }

    /** (23/11/2026) Every active customer already in the AMC Client Inventory: the page says so and where to add one. */
    public function test_no_free_customer_says_where_to_add_one(): void
    {
        $admin = $this->admin();
        $c = $this->amcClient('Only One');
        \App\Models\Party::whereKeyNot($c->party_id)->forceDelete(); // the fixtures add sample parties: keep only the client's

        $this->actingAs($admin)->get(route('amc.clients.create'))->assertOk()
            ->assertSee('id="amcNoParty"', false)->assertSee(route('admin.parties.create'), false);
        // the client that has the customer does not get the message on its edit page
        $this->actingAs($admin)->get(route('amc.clients.edit', $c))->assertOk()->assertDontSee('id="amcNoParty"', false);

        // a new active customer: offered, no message
        $this->amcParty('New Customer Ltd');
        $this->actingAs($admin)->get(route('amc.clients.create'))->assertOk()
            ->assertDontSee('id="amcNoParty"', false)->assertSee('>New Customer Ltd<', false);
    }
}
