<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcClientLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\AmcFixtures;
use Tests\TestCase;

/** AMC Client Master (21/11/2026): two location texts per line, edit keeping line ids, gardener, import and CSV download. */
class AmcClientTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'amccsv');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'clients.csv', 'text/csv', null, true);
    }

    public function test_client_is_added_and_edited_with_two_locations_per_line(): void
    {
        $m = $this->amcMasters();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('amc.clients.create'))->assertOk()->assertSee('Location 1')->assertSee('Location 2');

        $this->actingAs($admin)->post(route('amc.clients.store'), [
            'name' => ' Smart  Work ', 'amc_vertical_id' => $m['rental']->id,
            'lines' => [
                ['location_1' => 'Tower A', 'location_2' => 'Floor 5 - Lobby', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => '10', 'amc_plant_id' => $m['money']->id, 'plant_qty' => '10'],
                ['location_1' => '', 'location_2' => '', 'amc_planter_id' => '', 'planter_qty' => '', 'amc_plant_id' => '', 'plant_qty' => ''],
                ['location_1' => 'Tower A', 'location_2' => '', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '20'],
            ],
        ])->assertRedirect();
        $c = AmcClient::where('name', 'Smart Work')->firstOrFail();
        $lines = $c->lines()->get();
        $this->assertCount(2, $lines, 'the empty line is not saved');
        $this->assertSame(['Tower A', 'Floor 5 - Lobby', 10, 10], [$lines[0]->location_1, $lines[0]->location_2, $lines[0]->planter_qty, $lines[0]->plant_qty]);
        $this->assertNull($lines[1]->location_2);
        $this->assertTrue(ActivityLog::where('action', 'amc_client.created')->where('subject_id', $c->id)->exists());

        // Edit: the first line is changed in place (same id), the second removed, a new one added.
        $first = $lines[0]->id;
        $this->actingAs($admin)->put(route('amc.clients.update', $c), [
            'name' => 'Smart Work', 'amc_vertical_id' => $m['amc']->id,
            'lines' => [
                ['id' => $first, 'location_1' => 'Tower B', 'location_2' => 'Reception', 'amc_plant_id' => $m['money']->id, 'plant_qty' => '12'],
                ['location_1' => 'ASTP', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => '5'],
            ],
        ])->assertRedirect(route('amc.clients.edit', $c));
        $lines = $c->lines()->get();
        $this->assertSame([$first, 'Tower B', 'Reception'], [$lines[0]->id, $lines[0]->location_1, $lines[0]->location_2]);
        $this->assertSame('ASTP', $lines[1]->location_1);
        $this->assertSame(2, AmcClientLine::where('amc_client_id', $c->id)->count());
        $this->assertSame($m['amc']->id, $c->fresh()->amc_vertical_id);

        // Customer name is unique.
        $this->actingAs($admin)->post(route('amc.clients.store'), ['name' => 'smart work', 'amc_vertical_id' => $m['amc']->id])
            ->assertSessionHasErrors('name');
        // Quantities are whole numbers.
        $this->actingAs($admin)->put(route('amc.clients.update', $c), ['name' => 'Smart Work', 'amc_vertical_id' => $m['amc']->id, 'lines' => [['location_1' => 'X', 'plant_qty' => '2.5']]])
            ->assertSessionHasErrors('lines.0.plant_qty');
    }

    public function test_gardener_is_shown_from_the_active_contract(): void
    {
        $c = $this->amcClient();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('amc.clients.edit', $c))->assertOk()->assertSee('placeholder="No Active contract"', false);

        $g = $this->amcEmployee('Mahesh Gardener', 'Gardener');
        $this->amcContract($c, ['gardener_id' => $g->id]);
        $this->actingAs($admin)->get(route('amc.clients.edit', $c))->assertOk()->assertSee('value="Mahesh Gardener ('.$g->code.')"', false);
        $this->actingAs($admin)->get(route('amc.clients.index'))->assertOk()->assertSee('Mahesh Gardener');

        // An Inactive contract no longer names the gardener.
        $c->contracts()->update(['status' => 'Inactive']);
        $this->actingAs($admin)->get(route('amc.clients.edit', $c))->assertOk()->assertDontSee('value="Mahesh Gardener', false);
    }

    public function test_import_groups_rows_by_customer_and_matches_masters_by_name(): void
    {
        $this->amcMasters();
        $admin = $this->admin();
        $file = $this->csv("\xEF\xBB\xBFCustomer name,Business segment,Location 1,Location 2,Planter,Planter qty,Plant,Plant qty\n"
            ."Smart Work,AMC + Rental,Tower A,Floor 5 - Lobby,ceramic round 12 inch,10,Money Plant,10\n"
            ."smart work,AMC + Rental,Tower A,,,,Rose,20\n"
            ."Rich Next,amc,ASTP,,,,Rose,10\n"
            ."Empty Lines Ltd,AMC,,,,,,\n");
        $this->actingAs($admin)->post(route('amc.clients.import'), ['file' => $file])->assertRedirect(route('amc.clients.index'))->assertSessionHas('toast', '3 AMC client(s) imported');

        $smart = AmcClient::where('name', 'Smart Work')->firstOrFail();
        $this->assertSame(2, $smart->lines()->count());
        $this->assertSame('Floor 5 - Lobby', $smart->lines()->first()->location_2);
        $this->assertSame(1, AmcClient::where('name', 'Rich Next')->firstOrFail()->lines()->count());
        $this->assertSame(0, AmcClient::where('name', 'Empty Lines Ltd')->firstOrFail()->lines()->count());
        $this->assertTrue(ActivityLog::where('action', 'amc_client.imported')->exists());
    }

    public function test_import_checks_the_whole_file_and_saves_nothing_on_an_error(): void
    {
        $this->amcMasters();
        $this->amcClient('Already There');
        $admin = $this->admin();
        $file = $this->csv("Customer name,Business segment,Location 1,Location 2,Planter,Planter qty,Plant,Plant qty\n"
            ."Good One,AMC,Tower A,,,,Rose,5\n"
            ."Bad Segment,Rental only,Tower A,,,,Rose,5\n"
            ."Bad Plant,AMC,Tower A,,,,Tulip,5\n"
            ."Bad Qty,AMC,Tower A,,,,Rose,many\n"
            ."Already There,AMC,Tower C,,,,Rose,1\n"
            .",AMC,Tower A,,,,Rose,5\n");
        $this->actingAs($admin)->from(route('amc.clients.index'))->post(route('amc.clients.import'), ['file' => $file])->assertSessionHasErrors('file');
        $errors = session('errors')->get('file');
        $this->assertCount(5, $errors);
        $this->assertStringContainsString('Row 3: business segment "Rental only" is not in the business segment master', $errors[0]);
        $this->assertStringContainsString('Row 4: plant "Tulip"', $errors[1]);
        $this->assertStringContainsString('Row 5', $errors[2]);
        $this->assertStringContainsString('Row 6: customer "Already There" is already in the AMC Client Master', $errors[3]);
        $this->assertStringContainsString('Row 7', $errors[4]);
        $this->assertNull(AmcClient::where('name', 'Good One')->first(), 'nothing is saved');

        // Columns missing.
        $this->actingAs($admin)->post(route('amc.clients.import'), ['file' => $this->csv("Name,Plant\nX,Rose\n")])->assertSessionHasErrors('file');
    }

    public function test_csv_download_and_template_use_the_import_layout(): void
    {
        $this->amcClient();
        $admin = $this->admin();
        $res = $this->actingAs($admin)->get(route('amc.clients.export'))->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));
        $this->assertSame(['Customer name', 'Business segment', 'Location 1', 'Location 2', 'Planter', 'Planter qty', 'Plant', 'Plant qty'], $rows[0]);
        $this->assertSame(['Smart Work', 'AMC + Rental', 'Tower A', 'Floor 5 - Lobby', 'Ceramic round 12 inch', '10', 'Money Plant', '10'], $rows[1]);
        $this->assertSame(['Smart Work', 'AMC + Rental', 'Tower A', 'Floor 6', '', '', 'Rose', '20'], $rows[2]);

        $tpl = $this->actingAs($admin)->get(route('amc.clients.template'))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Rich Next",AMC,ASTP', $tpl);

        // A downloaded file imports into an empty master as the same clients and lines.
        AmcClientLine::query()->delete();
        AmcClient::query()->delete();
        $path = tempnam(sys_get_temp_dir(), 'amccsv');
        file_put_contents($path, $csv);
        $this->actingAs($admin)->post(route('amc.clients.import'), ['file' => new UploadedFile($path, 'back.csv', 'text/csv', null, true)])->assertSessionHasNoErrors();
        $this->assertSame(2, AmcClient::where('name', 'Smart Work')->firstOrFail()->lines()->count());
    }

    public function test_import_needs_the_import_right(): void
    {
        $u = $this->amcUser(['amc-client.view', 'amc-client.add']);
        $this->actingAs($u)->post(route('amc.clients.import'), ['file' => $this->csv("Customer name,Business segment\nX,AMC\n")])->assertForbidden();
        $this->actingAs($u)->get(route('amc.clients.template'))->assertForbidden();
        $this->actingAs($u)->get(route('amc.clients.export'))->assertOk();

        $u2 = $this->amcUser(['amc-client.view', 'amc-client-import.add'], 'amc.import@nursery.local');
        $this->actingAs($u2)->get(route('amc.clients.index'))->assertOk()->assertSee('#importLayer', false);
    }
}
