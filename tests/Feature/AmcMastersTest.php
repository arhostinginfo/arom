<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Amc\AmcEmployee;
use App\Models\Amc\AmcPlant;
use App\Models\Amc\AmcVertical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\AmcFixtures;
use Tests\TestCase;

/** AMC masters (21/11/2026): Business segment, Planter, Plant, AMC type and the AMC Employee master with its photo. */
class AmcMastersTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_each_master_lists_adds_and_edits_with_a_unique_name(): void
    {
        $admin = $this->admin();
        foreach (['verticals' => 'AMC + Rental', 'planters' => 'Ceramic pot', 'plants' => 'Areca palm', 'types' => 'Indoor'] as $key => $name) {
            $this->actingAs($admin)->get(route("amc.masters.$key.index"))->assertOk()->assertSee(config("amc.masters.$key.title"));
            $this->actingAs($admin)->post(route("amc.masters.$key.store"), ['name' => '  '.$name.'  ', 'size' => '5 ft', 'is_active' => 1])
                ->assertRedirect(route("amc.masters.$key.index"));
            $model = config("amc.masters.$key.model");
            $row = $model::where('name', $name)->firstOrFail();
            $this->assertTrue($row->is_active);
            // The same name again is refused.
            $this->actingAs($admin)->from(route("amc.masters.$key.index"))->post(route("amc.masters.$key.store"), ['name' => $name, 'is_active' => 1])
                ->assertSessionHasErrors(['name' => 'This name is already in the list.']);
            $this->assertSame(1, $model::where('name', $name)->count());
            // Edit and make inactive.
            $this->actingAs($admin)->put(route("amc.masters.$key.update", $row->id), ['name' => $name.' 2', 'size' => '6 ft', 'is_active' => 0])->assertRedirect();
            $row->refresh();
            $this->assertSame($name.' 2', $row->name);
            $this->assertFalse($row->is_active);
            $this->actingAs($admin)->get(route("amc.masters.$key.index", ['q' => substr($name, 0, 3)]))->assertOk()->assertSee($name.' 2');
        }
        $this->assertSame('6 ft', AmcPlant::where('name', 'Areca palm 2')->value('size'));
        $this->assertTrue(ActivityLog::where('action', 'amc_master.created')->exists());
        $this->assertTrue(ActivityLog::where('action', 'amc_master.updated')->exists());
    }

    public function test_a_master_row_in_use_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $client = $this->amcClient();
        $vertical = AmcVertical::find($client->amc_vertical_id);
        $this->actingAs($admin)->from(route('amc.masters.verticals.index'))->delete(route('amc.masters.verticals.destroy', $vertical->id))
            ->assertSessionHasErrors('delete');
        $this->assertNotNull($vertical->fresh());
        $rose = AmcPlant::where('name', 'Rose')->first();
        $this->actingAs($admin)->delete(route('amc.masters.plants.destroy', $rose->id))->assertSessionHasErrors('delete');

        // A row nobody uses is deleted.
        $free = AmcVertical::create(['name' => 'Spare']);
        $this->actingAs($admin)->delete(route('amc.masters.verticals.destroy', $free->id))->assertRedirect(route('amc.masters.verticals.index'));
        $this->assertNull($free->fresh());
        $this->assertTrue(ActivityLog::where('action', 'amc_master.deleted')->exists());
    }

    public function test_master_rights_are_checked_per_master(): void
    {
        $u = $this->amcUser(['amc-plant.view']);
        $this->actingAs($u)->get(route('amc.masters.plants.index'))->assertOk()->assertDontSee('id="mform"', false);
        $this->actingAs($u)->post(route('amc.masters.plants.store'), ['name' => 'Fern'])->assertForbidden();
        $this->actingAs($u)->get(route('amc.masters.planters.index'))->assertForbidden();
        $this->assertNull(AmcPlant::where('name', 'Fern')->first());
    }

    public function test_employee_master_adds_with_photo_code_and_unique_name(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('amc.employees.create'))->assertOk()->assertSee('Blank = AE0001');
        $this->actingAs($admin)->post(route('amc.employees.store'), [
            'code' => '', 'name' => 'Suresh Patil', 'role' => 'Gardener', 'mobile' => '98765 43210', 'status' => 'Active',
            'photo' => $this->amcImage('suresh.png', 1500, 1000),
        ])->assertRedirect();
        $e = AmcEmployee::where('name', 'Suresh Patil')->firstOrFail();
        $this->assertSame('AE0001', $e->code);
        $this->assertSame('9876543210', $e->mobile);
        Storage::disk('local')->assertExists($e->photo_path);
        if (function_exists('imagecreatetruecolor')) {
            // Stored as a JPEG of at most 600 px.
            [$w, $h, $type] = getimagesizefromstring(Storage::disk('local')->get($e->photo_path));
            $this->assertSame(IMAGETYPE_JPEG, $type);
            $this->assertLessThanOrEqual(600, max($w, $h));
        }
        $this->actingAs($admin)->get(route('amc.employees.photo.show', $e))->assertOk();

        // Name and code cannot repeat; a non-image photo is refused.
        $this->actingAs($admin)->post(route('amc.employees.store'), ['code' => 'AE0001', 'name' => 'Suresh Patil', 'role' => 'Gardener', 'status' => 'Active'])
            ->assertSessionHasErrors(['code', 'name']);
        $this->actingAs($admin)->post(route('amc.employees.store'), ['name' => 'Asha', 'role' => 'Operation Manager', 'status' => 'Active', 'photo' => $this->amcPdf('x.pdf')])
            ->assertSessionHasErrors('photo');

        // List, search and role filter.
        $this->actingAs($admin)->get(route('amc.employees.index', ['q' => 'sur', 'role' => 'Gardener']))->assertOk()->assertSee('Suresh Patil');
        $this->actingAs($admin)->get(route('amc.employees.index', ['role' => 'Operation Manager']))->assertOk()->assertDontSee('Suresh Patil');
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $e = $this->amcEmployee('Kiran More', 'Gardener');
        $this->actingAs($admin)->put(route('amc.employees.update', $e), ['code' => $e->code, 'name' => $e->name, 'role' => 'Gardener', 'status' => 'Active', 'photo' => $this->amcImage('a.png')])->assertRedirect();
        $first = $e->fresh()->photo_path;
        Storage::disk('local')->assertExists($first);

        $this->actingAs($admin)->from(route('amc.employees.edit', $e))->post(route('amc.employees.photo', $e), ['photo' => $this->amcImage('b.jpg', 800, 800, 'jpg')])
            ->assertRedirect(route('amc.employees.edit', $e));
        $second = $e->fresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertExists($second);
        Storage::disk('local')->assertMissing($first);
        $this->assertTrue(ActivityLog::where('action', 'amc_employee.updated')->where('subject_id', $e->id)->get()->contains(fn ($l) => isset($l->getAttribute('changes')['Photo'])));
    }

    public function test_employee_on_a_contract_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $g = $this->amcEmployee('Ramesh', 'Gardener');
        $this->amcContract($this->amcClient(), ['gardener_id' => $g->id]);
        $this->actingAs($admin)->delete(route('amc.employees.destroy', $g))->assertSessionHasErrors('delete');
        $this->assertNotNull($g->fresh());

        $free = $this->amcEmployee('Spare person', 'Other');
        $this->actingAs($admin)->delete(route('amc.employees.destroy', $free))->assertRedirect(route('amc.employees.index'));
        $this->assertNull($free->fresh());
    }
}
