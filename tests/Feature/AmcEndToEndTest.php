<?php

namespace Tests\Feature;

use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcContract;
use App\Models\Amc\AmcEmployee;
use App\Models\Amc\AmcFile;
use App\Models\Amc\AmcPlant;
use App\Models\Amc\AmcPlanter;
use App\Models\Amc\AmcReturn;
use App\Models\Amc\AmcType;
use App\Models\Amc\AmcVertical;
use App\Models\FinancialYear;
use App\Models\Unit;
use App\Models\User;
use App\Services\Amc\AmcDashboard;
use App\Support\Money;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\AmcFixtures;
use Tests\TestCase;

/**
 * AMC module, tester's end-to-end run (21/11/2026): two full entries (Smart Work and Rich Next, as in the client's sample)
 * taken through the masters, AMC Client Inventory, Customer PO / AMC with files, alerts, AMC list, dashboard and
 * Return & Replace over HTTP, plus the edge cases: import with an error, date and role checks, colour boundaries,
 * the clean-up of old deployment images, and every AMC address refused without the right.
 */
class AmcEndToEndTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-11-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function gd(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    private function csvFile(string $content, string $name = 'clients.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'amce2e');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    /** A real temp file with text in it, whatever its name says (the content type is read from the content). */
    private function textFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'amce2e');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    /** Option texts of one <select id="..."> in the page, in order. */
    private function selectOptions(string $html, string $id): array
    {
        $this->assertSame(1, preg_match('/<select[^>]*id="'.$id.'"[^>]*>(.*?)<\/select>/s', $html, $m), 'select #'.$id.' missing');
        preg_match_all('/<option[^>]*>([^<]*)<\/option>/', $m[1], $o);

        return array_map(fn ($t) => html_entity_decode(trim($t)), $o[1]);
    }

    private function csvRows(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        return array_map('str_getcsv', array_values(array_filter(preg_split('/\r?\n/', $content), fn ($l) => $l !== '')));
    }

    /** The masters of the sample, added over HTTP; duplicate names refused, edit and in-use delete checked later. */
    private function mastersOverHttp(User $admin): array
    {
        $this->amcParties(); // (22/11/2026) AMC clients are customers of the Party master
        foreach (['AMC', 'AMC + Rental'] as $n) {
            $this->actingAs($admin)->post(route('amc.masters.verticals.store'), ['name' => $n, 'is_active' => 1])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->actingAs($admin)->post(route('amc.masters.planters.store'), ['name' => 'Ceramic round 12 inch', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.masters.plants.store'), ['name' => 'Money Plant', 'size' => '2 ft', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.masters.plants.store'), ['name' => 'Rose', 'size' => '5 ft', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.masters.types.store'), ['name' => 'Indoor maintenance', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.masters.types.store'), ['name' => 'Outdoor maintenance', 'is_active' => 1])->assertSessionHasNoErrors();

        return [
            'amc' => AmcVertical::where('name', 'AMC')->value('id'),
            'rental' => AmcVertical::where('name', 'AMC + Rental')->value('id'),
            'ceramic' => AmcPlanter::where('name', 'Ceramic round 12 inch')->value('id'),
            'money' => AmcPlant::where('name', 'Money Plant')->value('id'),
            'rose' => AmcPlant::where('name', 'Rose')->value('id'),
            'indoor' => AmcType::where('name', 'Indoor maintenance')->value('id'),
            'outdoor' => AmcType::where('name', 'Outdoor maintenance')->value('id'),
        ];
    }

    private function employeeOverHttp(User $admin, string $name, string $role, ?UploadedFile $photo = null, string $mobile = ''): AmcEmployee
    {
        $data = ['code' => '', 'name' => $name, 'role' => $role, 'mobile' => $mobile, 'status' => 'Active'];
        if ($photo) {
            $data['photo'] = $photo;
        }
        $this->actingAs($admin)->post(route('amc.employees.store'), $data)->assertRedirect()->assertSessionHasNoErrors();

        return AmcEmployee::where('name', $name)->firstOrFail();
    }

    // ------------------------------------------------------------------ 1. Masters

    public function test_masters_add_edit_duplicate_and_in_use(): void
    {
        $admin = $this->admin();
        $m = $this->mastersOverHttp($admin);

        // Duplicate names refused, any case, on every master.
        foreach (['verticals' => 'amc + rental', 'planters' => 'CERAMIC ROUND 12 INCH', 'plants' => 'rose', 'types' => ' Indoor   maintenance '] as $key => $dup) {
            $this->actingAs($admin)->from(route("amc.masters.$key.index"))->post(route("amc.masters.$key.store"), ['name' => $dup, 'size' => '1 ft', 'is_active' => 1])
                ->assertSessionHasErrors(['name' => 'This name is already in the list.']);
        }
        $this->assertSame(2, AmcVertical::count());
        $this->assertSame(2, AmcPlant::count());

        // Edit: rename, keep its own name, change size, make inactive.
        $this->actingAs($admin)->put(route('amc.masters.plants.update', $m['rose']), ['name' => 'Rose', 'size' => '4 ft'])->assertSessionHasNoErrors();
        $rose = AmcPlant::find($m['rose']);
        $this->assertSame(['Rose', '4 ft', false], [$rose->name, $rose->size, $rose->is_active]);
        $this->actingAs($admin)->put(route('amc.masters.plants.update', $m['rose']), ['name' => 'Rose', 'size' => '5 ft', 'is_active' => 1])->assertSessionHasNoErrors();
        // Renaming onto another name is refused.
        $this->actingAs($admin)->put(route('amc.masters.plants.update', $m['rose']), ['name' => 'money plant', 'is_active' => 1])->assertSessionHasErrors('name');
        $this->actingAs($admin)->get(route('amc.masters.plants.index', ['edit' => $m['rose']]))->assertOk()->assertSee('value="5 ft"', false);

        // A client and a contract make them "in use": delete refused; an unused one deletes.
        $client = $this->amcClient('In Use Co', [['location_1' => 'A', 'amc_planter_id' => $m['ceramic'], 'planter_qty' => 1, 'amc_plant_id' => $m['money'], 'plant_qty' => 1]]);
        $client->update(['amc_vertical_id' => $m['amc']]);
        $this->amcContract($client, ['amc_type_id' => $m['indoor']]);
        foreach (['verticals' => $m['amc'], 'planters' => $m['ceramic'], 'plants' => $m['money'], 'types' => $m['indoor']] as $key => $id) {
            $this->actingAs($admin)->from(route("amc.masters.$key.index"))->delete(route("amc.masters.$key.destroy", $id))->assertSessionHasErrors('delete');
        }
        $this->assertSame([2, 1, 2, 2], [AmcVertical::count(), AmcPlanter::count(), AmcPlant::count(), AmcType::count()]);
        $this->actingAs($admin)->delete(route('amc.masters.types.destroy', $m['outdoor']))->assertSessionHasNoErrors();
        $this->assertNull(AmcType::find($m['outdoor']));
    }

    public function test_employee_master_roles_photo_replace_and_in_use(): void
    {
        $admin = $this->admin();
        $disk = Storage::disk('local');
        $big = $this->gd() ? $this->amcImage('ram.jpg', 2000, 1500, 'jpg') : null;
        $om = $this->employeeOverHttp($admin, 'Anil Manager', 'Operation Manager', $big, '98765 43210');
        $this->assertSame(['AE0001', '9876543210'], [$om->code, $om->mobile]);
        $g = $this->employeeOverHttp($admin, 'Gopal Gardener', 'Gardener');
        $this->assertSame('AE0002', $g->code);

        if ($this->gd()) {
            $first = $om->photo_path;
            $disk->assertExists($first);
            // Photo resized to at most 600 px and served through the app.
            [$w, $h] = getimagesizefromstring($disk->get($first));
            $this->assertSame([600, 450], [$w, $h]);
            $this->actingAs($admin)->get(route('amc.employees.photo.show', $om))->assertOk();

            // Replace from the Employee master: the old file is deleted.
            $this->actingAs($admin)->put(route('amc.employees.update', $om), ['code' => 'AE0001', 'name' => 'Anil Manager', 'role' => 'Operation Manager', 'mobile' => '9876543210',
                'status' => 'Active', 'photo' => $this->amcImage('new.png', 300, 300)])->assertSessionHasNoErrors();
            $second = $om->fresh()->photo_path;
            $this->assertNotSame($first, $second);
            $disk->assertMissing($first);
            $disk->assertExists($second);
            // Not an image: refused, the photo kept.
            $txt = $this->textFile('photo.jpg', 'not an image');
            $this->actingAs($admin)->post(route('amc.employees.photo', $om), ['photo' => $txt])->assertSessionHasErrors('photo');
            $disk->assertExists($second);
        }

        // Duplicate name (any case) and duplicate code refused; mobile must be 10 digits; role from the list only.
        $this->actingAs($admin)->post(route('amc.employees.store'), ['name' => 'anil manager', 'role' => 'Gardener', 'status' => 'Active'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('amc.employees.store'), ['code' => 'ae0002', 'name' => 'New One', 'role' => 'Gardener', 'status' => 'Active'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post(route('amc.employees.store'), ['name' => 'New One', 'role' => 'Gardener', 'mobile' => '12345', 'status' => 'Active'])->assertSessionHasErrors('mobile');
        $this->actingAs($admin)->post(route('amc.employees.store'), ['name' => 'New One', 'role' => 'Driver', 'status' => 'Active'])->assertSessionHasErrors('role');
        $this->assertSame(2, AmcEmployee::count());

        // In use on a contract: cannot be deleted; an unused one can.
        $this->amcContract($this->amcClient(), ['gardener_id' => $g->id]);
        $this->actingAs($admin)->from(route('amc.employees.edit', $g))->delete(route('amc.employees.destroy', $g))->assertSessionHasErrors('delete');
        $spare = $this->employeeOverHttp($admin, 'Spare Person', 'Other');
        $this->actingAs($admin)->delete(route('amc.employees.destroy', $spare))->assertSessionHasNoErrors();
        $this->assertNull(AmcEmployee::find($spare->id));
    }

    // ------------------------------------------------------------------ 2 to 6. Two entries end to end

    public function test_two_entries_from_client_master_to_dashboard_and_return_replace(): void
    {
        $admin = $this->admin();
        $disk = Storage::disk('local');
        $m = $this->mastersOverHttp($admin);
        $anil = $this->employeeOverHttp($admin, 'Anil Manager', 'Operation Manager', $this->gd() ? $this->amcImage('anil.png', 400, 400) : null);
        $gopal = $this->employeeOverHttp($admin, 'Gopal Gardener', 'Gardener', $this->gd() ? $this->amcImage('gopal.png', 400, 400) : null);
        $suresh = $this->employeeOverHttp($admin, 'Suresh Gardener', 'Gardener');
        $old = $this->employeeOverHttp($admin, 'Old Gardener', 'Gardener');
        $old->update(['status' => 'Inactive']);

        // ---- AMC Client Inventory: Smart Work (AMC + Rental, Tower A / Floor 5 - Lobby, Money Plant 10, Rose 20) and Rich Next (AMC, ASTP, Rose 10).
        $this->actingAs($admin)->get(route('amc.clients.create'))->assertOk()->assertSee('name="lines[0][location_1]"', false)->assertSee('name="lines[0][location_2]"', false);
        $this->actingAs($admin)->post(route('amc.clients.store'), ['name' => '  Smart   Work ', 'amc_vertical_id' => $m['rental'], 'lines' => [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5 - Lobby', 'amc_planter_id' => $m['ceramic'], 'planter_qty' => '10', 'amc_plant_id' => $m['money'], 'plant_qty' => '10'],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_plant_id' => $m['rose'], 'plant_qty' => '20'],
            ['location_1' => '', 'location_2' => '', 'amc_planter_id' => '', 'planter_qty' => '', 'amc_plant_id' => '', 'plant_qty' => ''],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.clients.store'), ['name' => 'Rich Next', 'amc_vertical_id' => $m['amc'], 'lines' => [
            ['location_1' => 'ASTP', 'amc_plant_id' => $m['rose'], 'plant_qty' => '10'],
        ]])->assertSessionHasNoErrors();
        // Same name in another case refused; qty must be a whole number.
        $this->actingAs($admin)->post(route('amc.clients.store'), ['name' => 'smart work', 'amc_vertical_id' => $m['amc']])->assertSessionHasErrors('party_id');
        $this->actingAs($admin)->post(route('amc.clients.store'), ['name' => 'Qty Co', 'amc_vertical_id' => $m['amc'], 'lines' => [['location_1' => 'X', 'plant_qty' => '2.5']]])->assertSessionHasErrors('lines.0.plant_qty');
        $this->assertSame(2, AmcClient::count());

        $sw = AmcClient::where('name', 'Smart Work')->firstOrFail();
        $rn = AmcClient::where('name', 'Rich Next')->firstOrFail();
        $this->assertCount(2, $sw->lines);
        [$l1, $l2] = [$sw->lines[0], $sw->lines[1]];
        $this->assertSame(['Tower A', 'Floor 5 - Lobby', 10, 10], [$l1->location_1, $l1->location_2, $l1->planter_qty, $l1->plant_qty]);
        $this->assertSame(['Tower A', 'Floor 6', 20], [$l2->location_1, $l2->location_2, $l2->plant_qty]);

        // Edit: Location 2 of line 2 changes, line ids are kept.
        $this->actingAs($admin)->put(route('amc.clients.update', $sw), ['name' => 'Smart Work', 'amc_vertical_id' => $m['rental'], 'lines' => [
            ['id' => $l1->id, 'location_1' => 'Tower A', 'location_2' => 'Floor 5 - Lobby', 'amc_planter_id' => $m['ceramic'], 'planter_qty' => '10', 'amc_plant_id' => $m['money'], 'plant_qty' => '10'],
            ['id' => $l2->id, 'location_1' => 'Tower A', 'location_2' => 'Floor 6 - Cafeteria', 'amc_plant_id' => $m['rose'], 'plant_qty' => '20'],
        ]])->assertSessionHasNoErrors();
        $sw->refresh()->load('lines');
        $this->assertSame([$l1->id, $l2->id], $sw->lines->pluck('id')->all());
        $this->assertSame('Floor 6 - Cafeteria', $sw->lines[1]->location_2);
        $page = $this->actingAs($admin)->get(route('amc.clients.edit', $sw))->assertOk()->getContent();
        $this->assertStringContainsString('name="lines[0][location_1]" value="Tower A"', $page);
        $this->assertStringContainsString('name="lines[0][location_2]" value="Floor 5 - Lobby"', $page);
        // No contract yet and none picked: the gardener box is empty (22/11/2026: a dropdown of active Gardeners).
        $this->assertMatchesRegularExpression('/<select class="inp\s*" id="gardener_id" name="gardener_id">\s*<option value="">None<\/option>/', $page);
        $this->assertDoesNotMatchRegularExpression('/<option value="\d+" selected/', substr($page, strpos($page, 'id="gardener_id"'), 600));

        // List search and segment filter; CSV download, one row per line.
        $this->actingAs($admin)->get(route('amc.clients.index', ['q' => 'rich']))->assertOk()->assertSee('Rich Next')->assertDontSee('Smart Work');
        $this->actingAs($admin)->get(route('amc.clients.index', ['vertical' => $m['rental']]))->assertOk()->assertSee('Smart Work')->assertDontSee('Rich Next');
        $csv = $this->csvRows($this->actingAs($admin)->get(route('amc.clients.export'))->assertOk()->streamedContent());
        $this->assertSame(['Customer name', 'Business segment', 'Location 1', 'Location 2', 'Planter', 'Planter qty', 'Plant', 'Plant qty'], $csv[0]);
        $this->assertContains(['Smart Work', 'AMC + Rental', 'Tower A', 'Floor 5 - Lobby', 'Ceramic round 12 inch', '10', 'Money Plant', '10'], $csv);
        $this->assertContains(['Smart Work', 'AMC + Rental', 'Tower A', 'Floor 6 - Cafeteria', '', '', 'Rose', '20'], $csv);
        $this->assertContains(['Rich Next', 'AMC', 'ASTP', '', '', '', 'Rose', '10'], $csv);
        $this->assertCount(4, $csv);

        // ---- Customer PO / AMC: the form, customer details on select, validation.
        $form = $this->actingAs($admin)->get(route('amc.contract.create'))->assertOk()->getContent();
        $this->assertSame(['Monthly', 'Quarterly', 'Half-yearly', 'Yearly'], $this->selectOptions($form, 'billing_cycle'));
        $this->assertSame(['None', 'Anil Manager (AE0001)'], $this->selectOptions($form, 'operation_manager_id'));
        // Only active gardeners; the inactive one and the operation manager are not offered.
        $this->assertSame(['None', 'Gopal Gardener (AE0002)', 'Suresh Gardener (AE0003)'], $this->selectOptions($form, 'gardener_id'));
        $this->assertLessThan(strpos($form, 'id="amc_type_id"'), strpos($form, 'id="end_date"'), 'dates come before AMC type');
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', $sw))->assertOk()->json();
        $this->assertSame(['AMC + Rental', null], [$info['segment'], $info['contract']]);
        $this->assertSame(['Tower A', 'Floor 5 - Lobby', 'Ceramic round 12 inch', 10, 'Money Plant (2 ft)', 10],
            [$info['lines'][0]['location_1'], $info['lines'][0]['location_2'], $info['lines'][0]['planter'], $info['lines'][0]['planter_qty'], $info['lines'][0]['plant'], $info['lines'][0]['plant_qty']]);
        // The page opened for a customer shows its table too.
        $this->actingAs($admin)->get(route('amc.contract.create', ['client' => $rn->id]))->assertOk()->assertSee('ASTP')->assertSee('Rose (5 ft)');

        $base = ['amc_client_id' => $sw->id, 'amc_type_id' => $m['indoor'], 'start_date' => '01/04/2026', 'end_date' => '31/03/2027', 'billing_cycle' => 'Quarterly',
            'visit_plan' => 'Weekly, every Monday', 'amount' => '12,000', 'operation_manager_id' => $anil->id, 'gardener_id' => $gopal->id, 'status' => 'Active'];
        $bad = [
            'end_date' => ['end_date' => '31/03/2026'],                   // before the start
            'start_date' => ['start_date' => '2026-04-01'],               // not dd/mm/yyyy
            'end_date ' => ['end_date' => '31/02/2027'],                  // no such day
            'billing_cycle' => ['billing_cycle' => 'Weekly'],
            'amount' => ['amount' => 'abc'],
            'amount ' => ['amount' => '-5'],
            'amount  ' => ['amount' => '10.555'],
            'operation_manager_id' => ['operation_manager_id' => $gopal->id],
            'gardener_id' => ['gardener_id' => $anil->id],
            'gardener_id ' => ['gardener_id' => $old->id],                // inactive
            'amc_client_id' => ['amc_client_id' => 99999],
        ];
        foreach ($bad as $field => $over) {
            $this->actingAs($admin)->from(route('amc.contract.create'))->post(route('amc.contract.store'), array_merge($base, $over))->assertSessionHasErrors(trim($field));
        }
        $this->actingAs($admin)->from(route('amc.contract.create'))->post(route('amc.contract.store'), array_merge($base, ['end_date' => '31/03/2026']))
            ->assertSessionHasErrors(['end_date' => 'The end date must be on or after the start date.']);
        $this->assertSame(0, AmcContract::count());
        // End date equal to the start date is allowed (checked, then removed again).
        $this->actingAs($admin)->post(route('amc.contract.store'), array_merge($base, ['start_date' => '01/04/2026', 'end_date' => '01/04/2026']))->assertSessionHasNoErrors();
        AmcContract::query()->delete();

        // Old contracts, added in September (outside "new orders in the last 60 days").
        Carbon::setTestNow('2026-09-01 10:00:00');
        $this->actingAs($admin)->post(route('amc.contract.store'), array_merge($base, ['amc_client_id' => $rn->id, 'start_date' => '01/05/2026', 'end_date' => '31/10/2026',
            'billing_cycle' => 'Monthly', 'amount' => '600', 'operation_manager_id' => '', 'gardener_id' => $suresh->id]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.contract.store'), array_merge($base, ['start_date' => '01/04/2025', 'end_date' => '31/03/2026', 'amount' => '99,999', 'status' => 'Inactive']))->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-11-21 10:00:00');
        // The two current contracts.
        $this->actingAs($admin)->post(route('amc.contract.store'), $base)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.contract.store'), array_merge($base, ['amc_client_id' => $rn->id, 'amc_type_id' => $m['outdoor'], 'start_date' => '15/12/2025',
            'end_date' => '15/12/2026', 'billing_cycle' => 'Yearly', 'amount' => '13000', 'gardener_id' => $suresh->id]))->assertSessionHasNoErrors();
        [$rnOld, $swOld, $swNow, $rnNow] = AmcContract::orderBy('id')->get()->all();
        // Numbers follow the record number (the trial contract deleted above keeps its number unused).
        $this->assertSame(['AMC-0002', 'AMC-0003', 'AMC-0004', 'AMC-0005'], [$rnOld->number(), $swOld->number(), $swNow->number(), $rnNow->number()]);
        $this->assertSame(['2026-04-01', '2027-03-31', '12000.00', 'Quarterly', 'Weekly, every Monday'],
            [$swNow->start_date->toDateString(), $swNow->end_date->toDateString(), $swNow->amount, $swNow->billing_cycle, $swNow->visit_plan]);

        // Client master without its own gardener names the Active contract's (Smart Work: Gopal, Rich Next: Suresh of the one ending last).
        $this->actingAs($admin)->get(route('amc.clients.edit', $sw))->assertOk()->assertSee('The Active contract has Gopal Gardener (AE0002).', false);
        $this->actingAs($admin)->get(route('amc.clients.edit', $rn))->assertOk()->assertSee('The Active contract has Suresh Gardener (AE0003).', false);
        $this->assertSame($rnNow->id, $this->actingAs($admin)->getJson(route('amc.clients.info', $rn))->json('contract.id'));

        // ---- Files of Smart Work's contract.
        $pdf = $this->amcPdf('Smart Work PO.pdf');
        $pdfBytes = file_get_contents($pdf->getRealPath());
        $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'po', 'files' => [$pdf]])->assertRedirect()->assertSessionHasNoErrors();
        $po = AmcFile::where('category', 'po')->firstOrFail();
        $this->assertSame(['Smart Work PO.pdf', 'application/pdf'], [$po->original_name, $po->mime]);
        $dl = $this->actingAs($admin)->get(route('amc.contract.files.show', $po))->assertOk();
        $this->assertSame($pdfBytes, $dl->streamedContent());
        $this->assertStringContainsString('application/pdf', $dl->headers->get('Content-Type'));
        // Wrong types: a text file as PO, an image as challan document, a text file renamed .pdf.
        $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'po', 'files' => [$this->textFile('notes.txt', 'hello')]])->assertSessionHasErrors('files.0');
        $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'challan_doc', 'files' => [$this->amcPdf('dc.pdf'), UploadedFile::fake()->image('dc.jpg')]])->assertSessionHasErrors('files.1');
        $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'challan_doc', 'files' => [$this->textFile('fake.pdf', 'just text')]])->assertSessionHasErrors('files.0');
        $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'other', 'files' => [$this->amcPdf()]])->assertSessionHasErrors('category');
        $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'challan_doc', 'files' => [$this->amcPdf('DC-101.pdf')]])->assertSessionHasNoErrors();
        $this->assertSame(2, AmcFile::count());

        if ($this->gd()) {
            // Challan images, 2 chosen together: one collage JPEG, 2 columns of at most 800 px, 8 px gaps.
            $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'challan_image', 'files' => [
                $this->amcImage('dc1.png', 1200, 900), $this->amcImage('dc2.jpg', 1000, 800, 'jpg'),
            ]])->assertSessionHas('toast', 'Delivery challan · images: the images were combined into one collage');
            $col = AmcFile::where('category', 'challan_image')->sole();
            $bytes = $disk->get($col->path);
            $this->assertSame(['image/jpeg', 'Collage of 2 images.jpg'], [$col->mime, $col->original_name]);
            $this->assertNotFalse(imagecreatefromstring($bytes), 'collage opens');
            $info = getimagesizefromstring($bytes);
            // 2 × 800 + 3 × 8 wide; tallest tile (1000×800 at 800 wide = 640) + 2 × 8 high.
            $this->assertSame([1624, 656, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);

            // Deployment: one big image resized to at most 1,600 px; then 3 at once in one collage of 2 rows.
            $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'deployment', 'files' => [$this->amcImage('site.jpg', 3000, 2000, 'jpg')]])->assertSessionHasNoErrors();
            $one = AmcFile::where('category', 'deployment')->sole();
            $this->assertSame([1600, 1067], array_slice(getimagesizefromstring($disk->get($one->path)), 0, 2));
            $this->assertSame('site.jpg', $one->original_name);
            $this->actingAs($admin)->post(route('amc.contract.files.store', $swNow), ['category' => 'deployment', 'files' => [
                $this->amcImage('a.png', 1200, 900), $this->amcImage('b.png', 900, 1200), $this->amcImage('c.png', 640, 480),
            ]])->assertSessionHasNoErrors();
            $three = AmcFile::where('category', 'deployment')->latest('id')->first();
            // Row 1: 600 and 1,067 high tiles; row 2: 480. 8 + 1067 + 8 + 480 + 8.
            $this->assertSame([1624, 1571], array_slice(getimagesizefromstring($disk->get($three->path)), 0, 2));
            $this->assertSame(2, AmcFile::where('category', 'deployment')->count());
            $this->actingAs($admin)->get(route('amc.contract.files.show', $three))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

            // Photo replace from the contract page: back to the contract, old file gone.
            $before = $gopal->fresh()->photo_path;
            $this->actingAs($admin)->from(route('amc.contract.show', $swNow))->post(route('amc.employees.photo', $gopal), ['photo' => $this->amcImage('gopal2.jpg', 800, 800, 'jpg')])
                ->assertRedirect(route('amc.contract.show', $swNow));
            $disk->assertMissing($before);
            $disk->assertExists($gopal->fresh()->photo_path);
        }
        $show = $this->actingAs($admin)->get(route('amc.contract.show', $swNow))->assertOk()->getContent();
        foreach (['Customer PO / Agreement', 'Delivery challan · documents', 'Delivery challan · images', 'Deployment images', 'Smart Work PO.pdf', 'DC-101.pdf',
            'Anil Manager (AE0001)', 'Gopal Gardener (AE0002)', 'Replace photo'] as $text) {
            $this->assertStringContainsString(e($text), $show, $text);
        }
        // Deleting one file removes its row and its file.
        $dc = AmcFile::where('original_name', 'DC-101.pdf')->sole();
        $this->actingAs($admin)->delete(route('amc.contract.files.destroy', $dc))->assertRedirect();
        $this->assertNull(AmcFile::find($dc->id));
        $disk->assertMissing($dc->path);

        // ---- Alerts: overdue pop up, renewal banner, nothing for a contract far from its end.
        $this->actingAs($admin)->get(route('amc.contract.show', $rnOld))->assertOk()->assertSee('id="amcOverduePop"', false)->assertSee('AMC overdue')->assertSee('21 day(s) ago');
        $this->actingAs($admin)->get(route('amc.contract.show', $rnNow))->assertOk()->assertDontSee('id="amcOverduePop"', false)->assertSee('Renewal due')->assertSee('in 24 day(s)');
        $this->actingAs($admin)->get(route('amc.contract.show', $swNow))->assertOk()->assertDontSee('id="amcOverduePop"', false)->assertDontSee('Renewal due');
        $this->actingAs($admin)->get(route('amc.contract.show', $swOld))->assertOk()->assertDontSee('id="amcOverduePop"', false)->assertDontSee('Renewal due');

        // ---- AMC list: colours, legend, filters, CSV.
        $list = $this->actingAs($admin)->get(route('amc.contracts.list'))->assertOk()->getContent();
        foreach (['Green: Active, end date more than 30 days away', 'Red: due within 30 days', 'Black: overdue, end date passed', 'Grey: Inactive'] as $legend) {
            $this->assertStringContainsString($legend, $list);
        }
        foreach ([$swNow->id => 'green', $rnNow->id => 'red', $rnOld->id => 'black', $swOld->id => 'grey'] as $id => $colour) {
            $this->assertMatchesRegularExpression('/<tr class="clk c-'.$colour.'"[^>]*amc\/contract\/'.$id.'\'/', $list, "contract $id is $colour");
            $rows = $this->actingAs($admin)->get(route('amc.contracts.list', ['colour' => $colour]))->assertOk()->getContent();
            $this->assertSame(1, substr_count($rows, '<tr class="clk'), "only one $colour row");
            $this->assertStringContainsString(sprintf('AMC-%04d', $id), $rows);
        }
        $this->assertSame(1, substr_count($this->actingAs($admin)->get(route('amc.contracts.list', ['status' => 'Inactive']))->getContent(), '<tr class="clk'));
        $this->assertSame(2, substr_count($this->actingAs($admin)->get(route('amc.contracts.list', ['q' => 'Rich']))->getContent(), '<tr class="clk'));
        $this->assertSame(1, substr_count($this->actingAs($admin)->get(route('amc.contracts.list', ['q' => 'AMC-0002']))->getContent(), '<tr class="clk'));
        $csv = $this->csvRows($this->actingAs($admin)->get(route('amc.contracts.export'))->assertOk()->streamedContent());
        $this->assertCount(5, $csv);
        $byNo = collect(array_slice($csv, 1))->keyBy(0);
        $this->assertSame(['Smart Work', 'Indoor maintenance', '01/04/2026', '31/03/2027', 'Quarterly', 'Weekly, every Monday', '12,000.00', 'Anil Manager', 'Gopal Gardener', 'Active', 'Active', '130'],
            array_slice($byNo[$swNow->number()], 1));
        $this->assertSame(['Due within 30 days', '24'], array_slice($byNo[$rnNow->number()], 11));
        $this->assertSame(['Overdue (end date passed)', '-21'], array_slice($byNo[$rnOld->number()], 11));
        $this->assertSame('Inactive', $byNo[$swOld->number()][11]);
        $this->assertCount(2, $this->csvRows($this->actingAs($admin)->get(route('amc.contracts.export', ['colour' => 'red']))->streamedContent()));

        // ---- Dashboard by hand (21/11/2026, FY Apr 2026 to Mar 2027).
        // Smart Work 12,000 over Apr 2026 to Mar 2027 = 1,000 a month. Rich Next 13,000 over Dec 2025 to Dec 2026 (13 months) = 1,000 a month.
        // Rich Next old 600 over May to Oct 2026 = 100 a month. Smart Work old is Inactive: left out.
        $d = app(AmcDashboard::class)->figures(FinancialYear::forDate(now()));
        $this->assertSame(2, $d['activeClients']);
        $this->assertEqualsWithDelta(2000, $d['thisMonth'], 0.001);
        $months = collect($d['revenue'])->pluck('amount', 'month')->all();
        $expected = ['Apr 2026' => 2000, 'May 2026' => 2100, 'Jun 2026' => 2100, 'Jul 2026' => 2100, 'Aug 2026' => 2100, 'Sep 2026' => 2100, 'Oct 2026' => 2100,
            'Nov 2026' => 2000, 'Dec 2026' => 2000, 'Jan 2027' => 1000, 'Feb 2027' => 1000, 'Mar 2027' => 1000];
        $this->assertSame(array_keys($expected), array_keys($months));
        foreach ($expected as $k => $v) {
            $this->assertEqualsWithDelta($v, $months[$k], 0.001, $k);
        }
        $this->assertEqualsWithDelta(21600, $d['revenueTotal'], 0.001);
        $this->assertSame([1, $rnNow->id], [$d['renewalCount'], $d['renewals'][0]->id]);
        $this->assertSame([1, $rnOld->id], [$d['overdueCount'], $d['overdue'][0]->id]);
        // Added in the last 60 days: the two current contracts (the September ones are 81 days old).
        $this->assertSame(2, $d['newCount']);
        $this->assertEqualsCanonicalizing([$swNow->id, $rnNow->id], $d['newOrders']->pluck('id')->all());
        $dash = $this->actingAs($admin)->get(route('amc.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString(e(Money::rupees(2000)), $dash);
        $this->assertStringContainsString(Money::indian(21600, 2), $dash);
        $this->assertStringContainsString('AMC revenue · November 2026', $dash);

        // ---- Return & Replace: auto-fill data, quality list, save, edge cases, list, CSV.
        $info = $this->actingAs($admin)->getJson(route('amc.clients.info', $sw))->json();
        $this->assertSame(['Tower A · Floor 5 - Lobby', 'Tower A', 'Floor 5 - Lobby'], [$info['lines'][0]['label'], $info['lines'][0]['location_1'], $info['lines'][0]['location_2']]);
        $this->assertSame([$swNow->id, $m['indoor'], 'Indoor maintenance', $gopal->id], [$info['contract']['id'], $info['contract']['amc_type_id'], $info['contract']['type'], $info['contract']['gardener_id']]);
        $rf = $this->actingAs($admin)->get(route('amc.returns.create'))->assertOk()->getContent();
        $this->assertSame(['Select', 'Reusable', 'Needs Treatment', 'Repairable', 'Dead'], $this->selectOptions($rf, 'quality'));
        $this->assertMatchesRegularExpression('/<option value="'.$m['money'].'" data-size="2 ft"/', $rf);
        $this->assertStringContainsString('id="request_date" name="request_date" value="21/11/2026"', $rf);

        $ret = ['amc_client_id' => $sw->id, 'amc_client_line_id' => $l1->id, 'request_date' => '20/11/2026', 'replace_date' => '21/11/2026', 'reported_by_id' => $anil->id,
            'amc_type_id' => $m['indoor'], 'amc_plant_id' => $m['money'], 'qty' => '2', 'rate' => '150', 'gardener_id' => $gopal->id, 'return_remark' => 'Leaves yellow', 'quality' => 'Needs Treatment'];
        $this->actingAs($admin)->post(route('amc.returns.store'), array_merge($ret, ['amc_client_line_id' => $rn->lines()->value('id')]))->assertSessionHasErrors(['amc_client_line_id' => 'Choose a location of this customer.']);
        $this->actingAs($admin)->post(route('amc.returns.store'), array_merge($ret, ['quality' => 'Bad']))->assertSessionHasErrors('quality');
        $this->actingAs($admin)->post(route('amc.returns.store'), array_merge($ret, ['replace_date' => '19/11/2026']))->assertSessionHasErrors('replace_date');
        $this->actingAs($admin)->post(route('amc.returns.store'), array_merge($ret, ['qty' => '0']))->assertSessionHasErrors('qty');
        $this->assertSame(0, AmcReturn::count());
        $this->actingAs($admin)->post(route('amc.returns.store'), $ret)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('amc.returns.store'), array_merge($ret, ['amc_client_id' => $rn->id, 'amc_client_line_id' => $rn->lines()->value('id'), 'amc_plant_id' => $m['rose'],
            'qty' => '1', 'rate' => '', 'gardener_id' => $suresh->id, 'amc_type_id' => $m['outdoor'], 'quality' => 'Dead', 'replace_date' => '']))->assertSessionHasNoErrors();
        [$r1, $r2] = AmcReturn::orderBy('id')->get()->all();
        $this->assertSame(['RR-0001', 'Tower A', 'Floor 5 - Lobby', '2 ft', 'Needs Treatment', 2, '150.00'], [$r1->number(), $r1->location_1, $r1->location_2, $r1->plant_size, $r1->quality, $r1->qty, $r1->rate]);
        $this->assertSame(['ASTP', null, '5 ft', 'Dead', null], [$r2->location_1, $r2->location_2, $r2->plant_size, $r2->quality, $r2->replace_date]);
        $this->actingAs($admin)->get(route('amc.returns.edit', $r1))->assertOk()->assertSee('selected>Floor 5 - Lobby</option>', false)->assertSee('value="2 ft"', false);
        // Edit: quality changes.
        $this->actingAs($admin)->put(route('amc.returns.update', $r1), array_merge($ret, ['quality' => 'Reusable']))->assertSessionHasNoErrors();
        $this->assertSame('Reusable', $r1->fresh()->quality);
        // (One page view uses up the "saved" message, which also names RR-0001.)
        $this->actingAs($admin)->get(route('amc.returns.index'))->assertOk()->assertSee('RR-0001')->assertSee('RR-0002');

        $this->actingAs($admin)->get(route('amc.returns.index', ['q' => 'Rich']))->assertOk()->assertSee('RR-0002')->assertDontSee('RR-0001');
        $this->actingAs($admin)->get(route('amc.returns.index', ['quality' => 'Reusable']))->assertOk()->assertSee('RR-0001')->assertDontSee('RR-0002');
        $rcsv = $this->csvRows($this->actingAs($admin)->get(route('amc.returns.export'))->assertOk()->streamedContent());
        $this->assertCount(3, $rcsv);
        $this->assertSame(['RR-0001', 'Smart Work', 'Tower A', 'Floor 5 - Lobby', '20/11/2026', '21/11/2026', 'Anil Manager', 'Indoor maintenance', 'Money Plant', '2 ft', '2', '150.00', 'Gopal Gardener', 'Leaves yellow', 'Reusable'], $rcsv[1]);

        // A master in use by Return & Replace cannot be deleted either.
        $this->actingAs($admin)->delete(route('amc.masters.types.destroy', $m['outdoor']))->assertSessionHasErrors('delete');

        // Deleting a contract deletes its files from the disk.
        $paths = AmcFile::where('amc_contract_id', $swNow->id)->pluck('path')->all();
        $this->actingAs($admin)->delete(route('amc.contract.destroy', $swNow))->assertRedirect(route('reports.amc.report', ['in' => 'amc']));
        foreach ($paths as $p) {
            $disk->assertMissing($p);
        }
        $this->assertSame(0, AmcFile::where('amc_contract_id', $swNow->id)->count());
    }

    public function test_client_import_template_good_file_and_file_with_an_error(): void
    {
        $admin = $this->admin();
        $this->mastersOverHttp($admin);
        $tpl = $this->actingAs($admin)->get(route('amc.clients.template'))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $tpl);
        $this->assertSame(['Customer name', 'Business segment', 'Location 1', 'Location 2', 'Planter', 'Planter qty', 'Plant', 'Plant qty'], $this->csvRows($tpl)[0]);

        // A file with an error on the last row: nothing at all is saved, the row is named.
        $bad = "Customer name,Business segment,Location 1,Location 2,Planter,Planter qty,Plant,Plant qty\n"
            ."Smart Work,AMC + Rental,Tower A,Floor 5 - Lobby,Ceramic round 12 inch,10,Money Plant,10\n"
            ."Smart Work,AMC + Rental,Tower A,Floor 6,,,Rose,20\n"
            ."Rich Next,AMC,ASTP,,,,Tulip,ten\n";
        $this->actingAs($admin)->from(route('amc.clients.index'))->post(route('amc.clients.import'), ['file' => $this->csvFile($bad)])->assertSessionHasErrors('file');
        $errors = session('errors')->get('file');
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_starts_with($e, 'Row 4:') && str_contains($e, 'Tulip')), implode(' | ', $errors));
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_starts_with($e, 'Row 4:') && str_contains(strtolower($e), 'plant qty')), implode(' | ', $errors));
        $this->assertSame(0, AmcClient::count());

        // The template itself imports: 2 clients, Smart Work with 2 lines.
        $this->actingAs($admin)->post(route('amc.clients.import'), ['file' => $this->csvFile($tpl)])->assertSessionHasNoErrors()->assertSessionHas('toast', '2 AMC client(s) imported');
        $sw = AmcClient::where('name', 'Smart Work')->firstOrFail();
        $this->assertSame([['Tower A', 'Floor 5 - Lobby', 10, 10], ['Tower A', 'Floor 6 - Cafeteria', null, 20]],
            $sw->lines->map(fn ($l) => [$l->location_1, $l->location_2, $l->planter_qty, $l->plant_qty])->all());
        $this->assertSame('AMC', AmcClient::where('name', 'Rich Next')->first()->vertical->name);
        // Importing it again refuses the existing names and saves nothing.
        $this->actingAs($admin)->post(route('amc.clients.import'), ['file' => $this->csvFile($tpl)])->assertSessionHasErrors('file');
        $this->assertSame(2, AmcClient::count());
        $this->assertSame(3, AmcClient::withCount('lines')->get()->sum('lines_count'));
        // Not a CSV / missing columns.
        $this->actingAs($admin)->post(route('amc.clients.import'), ['file' => $this->csvFile("Name,Plant\nX,Rose\n")])->assertSessionHasErrors('file');
    }

    // ------------------------------------------------------------------ 4. Colour and alert boundaries

    public function test_colour_and_alert_boundaries(): void
    {
        $cases = [-1 => ['black', true, false], 0 => ['red', false, true], 30 => ['red', false, true], 31 => ['green', false, true], 60 => ['green', false, true], 61 => ['green', false, false]];
        $client = $this->amcClient();
        foreach ($cases as $days => [$colour, $overdue, $renew]) {
            $c = $this->amcContract($client, ['end_date' => today()->addDays($days)->toDateString()]);
            $c->refresh();
            $this->assertSame([$colour, $overdue, $renew], [$c->colour(), $c->isOverdue(), $c->needsRenewal()], "$days days");
            $this->assertTrue(AmcContract::ofColour($colour)->whereKey($c->id)->exists(), "$days days in the $colour filter");
            $c->update(['status' => 'Inactive']);
            $this->assertSame(['grey', false, false], [$c->colour(), $c->isOverdue(), $c->needsRenewal()]);
        }
    }

    // ------------------------------------------------------------------ 7. Clean-up of old deployment images

    public function test_purge_command_schedule_and_admin_button(): void
    {
        $admin = $this->admin();
        $disk = Storage::disk('local');
        $c = $this->amcContract($this->amcClient());
        $make = function (string $cat, int $daysAgo) use ($c, $disk) {
            $path = 'amc/contracts/'.$c->id.'/'.$cat.'-'.$daysAgo.'.jpg';
            $disk->put($path, 'x');
            $f = new AmcFile(['category' => $cat, 'path' => $path, 'original_name' => $cat.'.jpg', 'mime' => 'image/jpeg', 'size' => 1]);
            $f->amc_contract_id = $c->id;
            $f->created_at = now()->subDays($daysAgo);
            $f->save();

            return $f;
        };
        $oldDep = $make('deployment', 400);
        $edgeDep = $make('deployment', 366);
        $newDep = $make('deployment', 300);
        $oldPo = $make('po', 800);
        $oldDc = $make('challan_image', 800);
        $oldDoc = $make('challan_doc', 800);

        $this->artisan('amc:purge-old-images')->expectsOutput('2 deployment image(s) older than 1 year deleted.')->assertSuccessful();
        foreach ([$oldDep, $edgeDep] as $f) {
            $this->assertNull(AmcFile::find($f->id));
            $disk->assertMissing($f->path);
        }
        foreach ([$newDep, $oldPo, $oldDc, $oldDoc] as $f) {
            $this->assertNotNull(AmcFile::find($f->id));
            $disk->assertExists($f->path);
        }

        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'amc:purge-old-images'));
        $this->assertCount(1, $events);
        $this->assertSame('30 1 * * *', $events->first()->expression);

        // Admin button; a non-Admin with every AMC right is refused.
        $another = $make('deployment', 500);
        $u = $this->amcUser(['amc-contract.*']);
        $this->actingAs($u)->post(route('amc.contracts.purge'))->assertForbidden();
        $this->assertNotNull(AmcFile::find($another->id));
        $this->actingAs($u)->get(route('amc.contracts.list'))->assertOk()->assertDontSee('Delete deployment images older than 1 year');
        $this->actingAs($admin)->get(route('amc.contracts.list'))->assertOk()->assertSee('Delete deployment images older than 1 year');
        $this->actingAs($admin)->from(route('amc.contracts.list'))->post(route('amc.contracts.purge'))->assertRedirect(route('amc.contracts.list'))
            ->assertSessionHas('toast', '1 deployment image(s) older than 1 year deleted');
        $this->assertNull(AmcFile::find($another->id));
    }

    // ------------------------------------------------------------------ 8. Rights

    public function test_every_amc_address_is_refused_without_amc_rights(): void
    {
        $client = $this->amcClient();
        $contract = $this->amcContract($client);
        $e = $this->amcEmployee('Some Gardener', 'Gardener');
        $file = new AmcFile(['category' => 'po', 'path' => 'x.pdf', 'original_name' => 'x.pdf', 'mime' => 'application/pdf', 'size' => 1]);
        $file->amc_contract_id = $contract->id;
        $file->save();
        $ret = AmcReturn::create(['amc_client_id' => $client->id, 'request_date' => today(), 'amc_plant_id' => AmcPlant::first()->id, 'qty' => 1, 'quality' => 'Dead']);
        $params = ['client' => $client->id, 'contract' => $contract->id, 'employee' => $e->id, 'file' => $file->id, 'return' => $ret->id, 'id' => AmcVertical::first()->id];

        $u = User::create(['name' => 'CRM Sales Only', 'email' => 'crm.sales@nursery.local', 'mobile' => '9811100099', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'amc.')) {
                continue;
            }
            $uri = preg_replace_callback('/\{(\w+)\}/', fn ($mm) => (string) $params[$mm[1]], $route->uri());
            $method = collect($route->methods())->first(fn ($x) => $x !== 'HEAD');
            $this->actingAs($u)->call($method, '/'.$uri)->assertForbidden();
            $checked++;
        }
        $this->assertGreaterThanOrEqual(50, $checked);
        $html = $this->actingAs($u)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="tn-amc"', $html);
        $this->assertStringContainsString('id="tn-crm"', $html);
    }

    public function test_each_right_gates_its_action(): void
    {
        $admin = $this->admin();
        $m = $this->amcMasters();
        $client = $this->amcClient();
        $contract = $this->amcContract($client);
        $g = $this->amcEmployee('Some Gardener', 'Gardener');

        // Masters: View only.
        $v = $this->amcUser(['amc-vertical.view'], 'v.only@nursery.local');
        $this->actingAs($v)->get(route('amc.masters.verticals.index'))->assertOk()->assertDontSee('Add business segment');
        $this->actingAs($v)->post(route('amc.masters.verticals.store'), ['name' => 'New'])->assertForbidden();
        $this->actingAs($v)->put(route('amc.masters.verticals.update', $m['amc']), ['name' => 'X'])->assertForbidden();
        $this->actingAs($v)->delete(route('amc.masters.verticals.destroy', $m['amc']))->assertForbidden();
        $this->actingAs($v)->get(route('amc.masters.plants.index'))->assertForbidden();
        // Add but not Edit / Delete.
        $a = $this->amcUser(['amc-vertical.view', 'amc-vertical.add'], 'v.add@nursery.local');
        $this->actingAs($a)->post(route('amc.masters.verticals.store'), ['name' => 'New seg', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($a)->put(route('amc.masters.verticals.update', $m['amc']), ['name' => 'X'])->assertForbidden();
        // Edit but not Delete.
        $ed = $this->amcUser(['amc-vertical.view', 'amc-vertical.edit'], 'v.edit@nursery.local');
        $this->actingAs($ed)->put(route('amc.masters.verticals.update', AmcVertical::where('name', 'New seg')->value('id')), ['name' => 'New seg 2', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($ed)->delete(route('amc.masters.verticals.destroy', AmcVertical::where('name', 'New seg 2')->value('id')))->assertForbidden();
        $del = $this->amcUser(['amc-vertical.view', 'amc-vertical.delete'], 'v.del@nursery.local');
        $this->actingAs($del)->delete(route('amc.masters.verticals.destroy', AmcVertical::where('name', 'New seg 2')->value('id')))->assertSessionHasNoErrors();

        // Clients: View, Add, Edit, Import.
        $cv = $this->amcUser(['amc-client.view'], 'c.view@nursery.local');
        $this->actingAs($cv)->get(route('amc.clients.edit', $client))->assertOk()->assertDontSee('</svg>Submit</button>', false);
        $this->actingAs($cv)->put(route('amc.clients.update', $client), ['name' => 'X', 'amc_vertical_id' => $m['amc']->id])->assertForbidden();
        $this->actingAs($cv)->post(route('amc.clients.store'), ['name' => 'X', 'amc_vertical_id' => $m['amc']->id])->assertForbidden();
        $this->actingAs($cv)->get(route('amc.clients.template'))->assertForbidden();
        $this->actingAs($cv)->get(route('amc.clients.export'))->assertOk();
        $ce = $this->amcUser(['amc-client.view', 'amc-client.edit'], 'c.edit@nursery.local');
        $this->actingAs($ce)->put(route('amc.clients.update', $client), ['name' => 'Smart Work Pvt', 'amc_vertical_id' => $m['amc']->id])->assertSessionHasNoErrors();
        $this->actingAs($ce)->post(route('amc.clients.store'), ['name' => 'X', 'amc_vertical_id' => $m['amc']->id])->assertForbidden();
        $ci = $this->amcUser(['amc-client.view', 'amc-client-import.add'], 'c.imp@nursery.local');
        $this->actingAs($ci)->post(route('amc.clients.import'), ['file' => $this->csvFile("Customer name,Business segment\nImported Co,AMC\n")])->assertSessionHasNoErrors();
        $this->assertTrue(AmcClient::where('name', 'Imported Co')->exists());

        // Contracts: View only sees the list and the contract, cannot add, edit, upload or delete.
        $kv = $this->amcUser(['amc-contract.view'], 'k.view@nursery.local');
        $this->actingAs($kv)->get(route('amc.contract.index'))->assertRedirect(route('reports.amc.report', ['in' => 'amc']));
        $this->actingAs($kv)->get(route('amc.contract.show', $contract))->assertOk()->assertDontSee('</svg>Submit</button>', false)->assertDontSee('Upload')->assertDontSee('Delete contract');
        $this->actingAs($kv)->get(route('amc.contract.create'))->assertForbidden();
        $this->actingAs($kv)->put(route('amc.contract.update', $contract), [])->assertForbidden();
        $this->actingAs($kv)->post(route('amc.contract.files.store', $contract), ['category' => 'po', 'files' => [$this->amcPdf()]])->assertForbidden();
        $this->actingAs($kv)->post(route('amc.employees.photo', $g), [])->assertForbidden();
        $this->actingAs($kv)->delete(route('amc.contract.destroy', $contract))->assertForbidden();
        $ke = $this->amcUser(['amc-contract.view', 'amc-contract.edit'], 'k.edit@nursery.local');
        $this->actingAs($ke)->post(route('amc.contract.files.store', $contract), ['category' => 'po', 'files' => [$this->amcPdf()]])->assertSessionHasNoErrors();
        $f = AmcFile::sole();
        $this->actingAs($ke)->delete(route('amc.contract.files.destroy', $f))->assertForbidden();
        $this->actingAs($ke)->delete(route('amc.contract.destroy', $contract))->assertForbidden();
        $kd = $this->amcUser(['amc-contract.view', 'amc-contract.delete'], 'k.del@nursery.local');
        $this->actingAs($kd)->delete(route('amc.contract.files.destroy', $f))->assertRedirect();
        $this->assertNull(AmcFile::find($f->id));

        // Return & Replace: View only.
        $rv = $this->amcUser(['amc-return.view'], 'r.view@nursery.local');
        $this->actingAs($rv)->get(route('amc.returns.index'))->assertOk();
        $this->actingAs($rv)->get(route('amc.returns.create'))->assertForbidden();
        $this->actingAs($rv)->post(route('amc.returns.store'), [])->assertForbidden();

        // Employees: View only cannot change or replace a photo.
        $ev = $this->amcUser(['amc-employee.view'], 'e.view@nursery.local');
        $this->actingAs($ev)->get(route('amc.employees.edit', $g))->assertOk();
        $this->actingAs($ev)->put(route('amc.employees.update', $g), [])->assertForbidden();
        $this->actingAs($ev)->post(route('amc.employees.photo', $g), [])->assertForbidden();
        $this->actingAs($ev)->delete(route('amc.employees.destroy', $g))->assertForbidden();

        // Access rights lists every AMC row; the Admin opens the AMC pages.
        $roles = $this->actingAs($admin)->get(route('admin.roles.create'))->assertOk()->getContent();
        foreach (config('amc.pages') as $page) {
            $this->assertStringContainsString(e($page['label']), $roles, $page['label']);
        }
    }

    // ------------------------------------------------------------------ 9. CRM and Sales unaffected

    public function test_menu_keeps_crm_and_sales_and_adds_amc(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();
        preg_match_all('/<a class="tn(?: on)?" id="tn-([a-z]+)"/', $html, $mm);
        $this->assertSame(['dashboard', 'crm', 'sales', 'purchase', 'accounts', 'amc', 'admin', 'reports'], array_values(array_unique($mm[1])));
        foreach (['/crm/enquiries/list', '/crm/prospects', '/crm/quotations', '/crm/pos', '/sales/orders', '/sales/invoices'] as $url) {
            $page = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('css/amc.css', $page, $url);
            $this->assertStringNotContainsString('js/amc.js', $page, $url);
        }
    }
}
