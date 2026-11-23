<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Amc\AmcContract;
use App\Models\Amc\AmcFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\AmcFixtures;
use Tests\TestCase;

/**
 * Customer PO / AMC (21/11/2026): validation and dates, the customer's details, files and the collage, photo replace,
 * overdue pop up and renewal banner, AMC list colours and the clean-up of old deployment images.
 */
class AmcContractTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private ?int $clientId = null;

    private function payload(array $over = []): array
    {
        $m = $this->amcMasters();
        $this->clientId ??= $this->amcClient()->id;

        return array_merge([
            'amc_client_id' => $this->clientId, 'amc_type_id' => $m['type']->id, 'start_date' => '01/04/2026', 'end_date' => '31/03/2027',
            'billing_cycle' => 'Quarterly', 'visit_plan' => 'Weekly, every Monday', 'amount' => '5,000', 'status' => 'Active',
        ], $over);
    }

    private function gd(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    public function test_contract_is_added_with_dates_people_and_details_of_the_customer(): void
    {
        $admin = $this->admin();
        $om = $this->amcEmployee('Anil Manager', 'Operation Manager');
        $g = $this->amcEmployee('Gopal Gardener', 'Gardener');
        $this->actingAs($admin)->get(route('amc.contract.create'))->assertOk()
            ->assertSee('AMC start date')->assertSee('AMC end date')->assertSee('placeholder="dd/mm/yyyy"', false);

        $this->actingAs($admin)->post(route('amc.contract.store'), $this->payload(['operation_manager_id' => $om->id, 'gardener_id' => $g->id]))->assertRedirect();
        $c = AmcContract::firstOrFail();
        $this->assertSame(['2026-04-01', '2027-03-31', '5000.00', 'Quarterly'], [$c->start_date->toDateString(), $c->end_date->toDateString(), $c->amount, $c->billing_cycle]);
        $this->assertSame([$om->id, $g->id], [$c->operation_manager_id, $c->gardener_id]);
        $this->assertTrue(ActivityLog::where('action', 'amc_contract.created')->where('subject_id', $c->id)->exists());

        // The page shows the customer's segment and lines; info JSON gives them to the picker.
        $this->actingAs($admin)->get(route('amc.contract.show', $c))->assertOk()
            ->assertSee('AMC-0001')->assertSee('AMC + Rental')->assertSee('Floor 5 - Lobby')->assertSee('Money Plant (2 ft)')->assertSee('value="01/04/2026"', false);
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', $c->amc_client_id))->assertOk()->json();
        $this->assertSame('AMC + Rental', $info['segment']);
        $this->assertCount(2, $info['lines']);
        $this->assertSame(['Tower A', 'Floor 5 - Lobby'], [$info['lines'][0]['location_1'], $info['lines'][0]['location_2']]);
        $this->assertSame($g->id, $info['contract']['gardener_id']);

        // Edit.
        $this->actingAs($admin)->put(route('amc.contract.update', $c), $this->payload(['amc_client_id' => $c->amc_client_id, 'amount' => '6000.50', 'status' => 'Inactive']))->assertRedirect();
        $this->assertSame(['6000.50', 'Inactive'], [$c->fresh()->amount, $c->fresh()->status]);
    }

    public function test_validation_of_dates_roles_and_amount(): void
    {
        $admin = $this->admin();
        $g = $this->amcEmployee('Only Gardener', 'Gardener');
        $inactive = $this->amcEmployee('Old Manager', 'Operation Manager', ['status' => 'Inactive']);
        $this->actingAs($admin)->post(route('amc.contract.store'), $this->payload(['start_date' => '01/04/2026', 'end_date' => '31/03/2026']))
            ->assertSessionHasErrors(['end_date' => 'The end date must be on or after the start date.']);
        $this->actingAs($admin)->post(route('amc.contract.store'), $this->payload(['start_date' => '2026-04-01']))->assertSessionHasErrors(['start_date' => 'Enter the date as dd/mm/yyyy.']);
        // A gardener cannot be the operation manager; an inactive employee cannot be picked.
        $this->actingAs($admin)->post(route('amc.contract.store'), $this->payload(['operation_manager_id' => $g->id]))->assertSessionHasErrors('operation_manager_id');
        $this->actingAs($admin)->post(route('amc.contract.store'), $this->payload(['operation_manager_id' => $inactive->id]))->assertSessionHasErrors('operation_manager_id');
        $this->actingAs($admin)->post(route('amc.contract.store'), $this->payload(['amount' => 'abc', 'billing_cycle' => 'Weekly', 'amc_type_id' => '']))
            ->assertSessionHasErrors(['amount', 'billing_cycle', 'amc_type_id']);
        $this->assertSame(0, AmcContract::count());
        // Same start and end date is allowed.
        $this->actingAs($admin)->post(route('amc.contract.store'), $this->payload(['start_date' => '01/04/2026', 'end_date' => '01/04/2026']))->assertSessionHasNoErrors();
    }

    public function test_po_document_upload_download_and_wrong_type(): void
    {
        $admin = $this->admin();
        $c = $this->amcContract($this->amcClient());
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'po', 'files' => [$this->amcPdf('PO 123.pdf')]])->assertRedirect();
        $f = AmcFile::where('category', 'po')->firstOrFail();
        $this->assertSame(['PO 123.pdf', 'application/pdf', $admin->id], [$f->original_name, $f->mime, $f->uploaded_by]);
        Storage::disk('local')->assertExists($f->path);
        $this->assertStringStartsWith('amc/contracts/'.$c->id.'/', $f->path);
        $this->actingAs($admin)->get(route('amc.contract.files.show', $f))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin)->get(route('amc.contract.show', $c))->assertOk()->assertSee('PO 123.pdf');

        // A text file named .pdf, and an image in the PDF-only challan documents box, are refused.
        $tmp = tempnam(sys_get_temp_dir(), 'amc');
        file_put_contents($tmp, 'just text, not a PDF');
        $fake = new \Illuminate\Http\UploadedFile($tmp, 'fake.pdf', 'application/pdf', null, true);
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'po', 'files' => [$fake]])->assertSessionHasErrors('files.0');
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'challan_doc', 'files' => [$this->amcImage('a.png')]])->assertSessionHasErrors('files.0');
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'other', 'files' => [$this->amcPdf()]])->assertSessionHasErrors('category');
        $this->assertSame(1, AmcFile::count());

        // Delete a file: row and file gone, logged.
        $this->actingAs($admin)->delete(route('amc.contract.files.destroy', $f))->assertRedirect();
        Storage::disk('local')->assertMissing($f->path);
        $this->assertTrue(ActivityLog::where('action', 'amc_file.deleted')->exists());
    }

    public function test_several_images_together_become_one_collage(): void
    {
        if (! $this->gd()) {
            $this->markTestSkipped('GD is not available');
        }
        $admin = $this->admin();
        $c = $this->amcContract($this->amcClient());
        $files = [$this->amcImage('d1.png', 1600, 1200), $this->amcImage('d2.jpg', 1000, 1400, 'jpg'), $this->amcImage('d3.png', 900, 600)];
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'deployment', 'files' => $files])
            ->assertRedirect()->assertSessionHas('toast', 'Deployment images: the images were combined into one collage');

        $rows = AmcFile::where('category', 'deployment')->get();
        $this->assertCount(1, $rows, 'only the collage is stored');
        $this->assertSame(['Collage of 3 images.jpg', 'image/jpeg'], [$rows[0]->original_name, $rows[0]->mime]);
        $this->assertCount(1, Storage::disk('local')->allFiles('amc/contracts/'.$c->id));
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('local')->get($rows[0]->path));
        $this->assertSame(IMAGETYPE_JPEG, $type);
        // 2 columns of 800 px tiles with 8 px gaps; 2 rows.
        $this->assertSame(2 * 800 + 3 * 8, $w);
        $this->assertGreaterThan(600, $h);
        $this->assertSame($rows[0]->size, strlen(Storage::disk('local')->get($rows[0]->path)));

        // A single image is stored as a JPEG of at most 1600 px.
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'challan_image', 'files' => [$this->amcImage('big.png', 2400, 1800)]])->assertRedirect();
        $one = AmcFile::where('category', 'challan_image')->firstOrFail();
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('local')->get($one->path));
        $this->assertSame([IMAGETYPE_JPEG, 1600, 1200], [$type, $w, $h]);
        $this->assertSame('big.jpg', $one->original_name);
    }

    public function test_photos_of_the_people_are_replaced_from_the_contract_page(): void
    {
        $admin = $this->admin();
        $om = $this->amcEmployee('Anil Manager', 'Operation Manager');
        $g = $this->amcEmployee('Gopal Gardener', 'Gardener');
        $c = $this->amcContract($this->amcClient(), ['operation_manager_id' => $om->id, 'gardener_id' => $g->id]);
        $this->actingAs($admin)->get(route('amc.contract.show', $c))->assertOk()
            ->assertSee(route('amc.employees.photo', $om))->assertSee(route('amc.employees.photo', $g))->assertSee('Add photo');

        $this->actingAs($admin)->from(route('amc.contract.show', $c))->post(route('amc.employees.photo', $g), ['photo' => $this->amcImage('g1.png')])
            ->assertRedirect(route('amc.contract.show', $c));
        $first = $g->fresh()->photo_path;
        $this->actingAs($admin)->from(route('amc.contract.show', $c))->post(route('amc.employees.photo', $g), ['photo' => $this->amcImage('g2.png')]);
        $second = $g->fresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
        $this->actingAs($admin)->get(route('amc.contract.show', $c))->assertSee('Replace photo')->assertSee(route('amc.employees.photo.show', $g), false);

        // A contract editor without Employee master rights may replace a photo; a viewer may not.
        $editor = $this->amcUser(['amc-contract.view', 'amc-contract.edit'], 'amc.editor@nursery.local');
        $this->actingAs($editor)->post(route('amc.employees.photo', $om), ['photo' => $this->amcImage('om.png')])->assertRedirect();
        $this->assertNotNull($om->fresh()->photo_path);
        $viewer = $this->amcUser(['amc-contract.view'], 'amc.viewer@nursery.local');
        $this->actingAs($viewer)->post(route('amc.employees.photo', $om), ['photo' => $this->amcImage('om2.png')])->assertForbidden();
    }

    public function test_overdue_contract_shows_the_red_pop_up_and_one_ending_soon_the_renewal_banner(): void
    {
        $admin = $this->admin();
        $client = $this->amcClient();
        $overdue = $this->amcContract($client, ['start_date' => today()->subYear()->toDateString(), 'end_date' => today()->subDays(3)->toDateString()]);
        $html = $this->actingAs($admin)->get(route('amc.contract.show', $overdue))->assertOk()->getContent();
        $this->assertStringContainsString('id="amcOverduePop" data-autoopen', $html);
        $this->assertStringContainsString('role="alertdialog"', $html);
        $this->assertStringContainsString('3 day(s) ago, and is still Active', $html);

        // Inactive: no pop up.
        $overdue->update(['status' => 'Inactive']);
        $this->assertStringNotContainsString('amcOverduePop', $this->actingAs($admin)->get(route('amc.contract.show', $overdue))->getContent());

        $soon = $this->amcContract($client, ['end_date' => today()->addDays(45)->toDateString()]);
        $html = $this->actingAs($admin)->get(route('amc.contract.show', $soon))->getContent();
        $this->assertStringContainsString('Renewal due.', $html);
        $this->assertStringContainsString('in 45 day(s)', $html);
        $this->assertStringNotContainsString('amcOverduePop', $html);

        $later = $this->amcContract($client, ['end_date' => today()->addDays(61)->toDateString()]);
        $html = $this->actingAs($admin)->get(route('amc.contract.show', $later))->getContent();
        $this->assertStringNotContainsString('Renewal due.', $html);
        $this->assertStringNotContainsString('amcOverduePop', $html);
    }

    public function test_amc_list_colours_filters_and_csv(): void
    {
        $admin = $this->admin();
        $client = $this->amcClient();
        $green = $this->amcContract($client, ['end_date' => today()->addDays(31)->toDateString()]);
        $red = $this->amcContract($client, ['end_date' => today()->addDays(30)->toDateString()]);
        $redToday = $this->amcContract($client, ['end_date' => today()->toDateString()]);
        $black = $this->amcContract($client, ['end_date' => today()->subDay()->toDateString()]);
        $grey = $this->amcContract($client, ['end_date' => today()->subDay()->toDateString(), 'status' => 'Inactive']);
        $this->assertSame(['green', 'red', 'red', 'black', 'grey'], [$green->colour(), $red->colour(), $redToday->colour(), $black->colour(), $grey->colour()]);

        $html = $this->actingAs($admin)->get(route('amc.contracts.list'))->assertOk()->getContent();
        $this->assertStringContainsString('Green: Active, end date more than 30 days away', $html);
        $this->assertStringContainsString('Black: overdue, end date passed', $html);
        // (22/11/2026) the Status column, second, shows the colour with the contract's status
        $this->assertMatchesRegularExpression('/AMC no.*?<th>Status<\/th>\s*<th>Customer<\/th>/s', $html);
        // the status only, no colour name (the badge and dot carry the colour)
        $this->assertSame(4, substr_count($html, 'aria-hidden="true"></i>Active</span>'));
        $this->assertSame(1, substr_count($html, 'aria-hidden="true"></i>Inactive</span>'));
        foreach (['green' => 1, 'red' => 2, 'black' => 1, 'grey' => 1] as $k => $n) {
            $this->assertSame($n, substr_count($html, 'data-colour="'.$k.'"'), $k);
            $this->assertSame($n, substr_count($html, 'class="clk c-'.$k.'"'), $k);
            $page = $this->actingAs($admin)->get(route('amc.contracts.list', ['colour' => $k]))->assertOk()->getContent();
            $this->assertSame($n, substr_count($page, 'data-colour="'.$k.'"'), 'filter '.$k);
            $this->assertSame($n, AmcContract::ofColour($k)->count());
        }
        $this->assertSame(1, substr_count($this->actingAs($admin)->get(route('amc.contracts.list', ['status' => 'Inactive']))->getContent(), 'data-colour='));
        $this->assertStringContainsString($green->number(), $this->actingAs($admin)->get(route('amc.contracts.list', ['q' => 'smart']))->getContent());
        $this->assertStringContainsString('data-colour="black"', $this->actingAs($admin)->get(route('amc.contracts.list', ['q' => $black->number()]))->getContent());

        $csv = $this->actingAs($admin)->get(route('amc.contracts.export', ['colour' => 'red']))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));
        $this->assertSame('AMC no', $rows[0][0]);
        $this->assertCount(3, $rows);
        $this->assertSame('Due within 30 days', $rows[1][11]);
    }

    public function test_purge_deletes_old_deployment_images_only(): void
    {
        $admin = $this->admin();
        $c = $this->amcContract($this->amcClient());
        $mk = function (string $category, $at) use ($c, $admin) {
            $path = 'amc/contracts/'.$c->id.'/'.$category.'-'.$at->timestamp.'.jpg';
            Storage::disk('local')->put($path, 'x');
            $f = new AmcFile(['category' => $category, 'path' => $path, 'original_name' => basename($path), 'mime' => 'image/jpeg', 'size' => 1]);
            $f->amc_contract_id = $c->id;
            $f->uploaded_by = $admin->id;
            $f->created_at = $at;
            $f->save();

            return $f;
        };
        $old = $mk('deployment', now()->subYear()->subDay());
        $new = $mk('deployment', now()->subMonths(11));
        $oldPo = $mk('po', now()->subYears(2));
        $oldChallan = $mk('challan_image', now()->subYears(2));

        $this->artisan('amc:purge-old-images')->expectsOutput('1 deployment image(s) older than 1 year deleted.')->assertSuccessful();
        $this->assertNull($old->fresh());
        Storage::disk('local')->assertMissing($old->path);
        foreach ([$new, $oldPo, $oldChallan] as $kept) {
            $this->assertNotNull($kept->fresh());
            Storage::disk('local')->assertExists($kept->path);
        }
        $this->assertTrue(ActivityLog::where('action', 'amc_file.purged')->exists());

        // The command is on the daily schedule.
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertTrue($events->contains(fn ($e) => str_contains((string) $e->command, 'amc:purge-old-images')));

        // The Admin button runs the same clean-up; others may not.
        $older = $mk('deployment', now()->subYears(3));
        $deleter = $this->amcUser(['amc-contract.view', 'amc-contract.delete'], 'amc.deleter@nursery.local');
        $this->actingAs($deleter)->post(route('amc.contracts.purge'))->assertForbidden();
        $this->actingAs($admin)->get(route('amc.contracts.list'))->assertSee('Delete deployment images older than 1 year');
        $this->actingAs($deleter)->get(route('amc.contracts.list'))->assertDontSee('Delete deployment images older than 1 year');
        $this->actingAs($admin)->from(route('amc.contracts.list'))->post(route('amc.contracts.purge'))->assertRedirect(route('amc.contracts.list'))
            ->assertSessionHas('toast', '1 deployment image(s) older than 1 year deleted');
        $this->assertNull($older->fresh());
    }

    public function test_deleting_a_contract_deletes_its_files(): void
    {
        $admin = $this->admin();
        $c = $this->amcContract($this->amcClient());
        $this->actingAs($admin)->post(route('amc.contract.files.store', $c), ['category' => 'po', 'files' => [$this->amcPdf()]]);
        $path = AmcFile::firstOrFail()->path;
        $this->actingAs($admin)->delete(route('amc.contract.destroy', $c))->assertRedirect(route('reports.amc.report', ['in' => 'amc']));
        $this->assertNull($c->fresh());
        $this->assertSame(0, AmcFile::count());
        Storage::disk('local')->assertMissing($path);
    }
}
