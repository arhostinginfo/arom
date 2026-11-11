<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\User;
use App\Services\PageSetupService;
use App\Services\ProspectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Admin > Page setup for the Prospect page: form, validation, save, CSV download, template and import. Test database only. */
class ProspectPageSetupTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const FULL = [
        'name' => 'Kaveri Precision', 'market' => 'Domestic', 'contact_person' => 'Kavita Rao', 'position' => 'Buyer',
        'phone_1' => '9822012345', 'phone_2' => '9822054321', 'email' => 'buyer@kaveri.example', 'gstin' => '27AAACK1234F1Z5',
        'industry_type' => 'Engineering', 'address' => 'Hinjewadi, Pune',
    ];

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    /** $choices: field => [visible, required]; fields not given keep their current setting. */
    private function setUp_(array $choices): void
    {
        $fields = [];
        foreach (app(PageSetupService::class)->fields('prospect') as $f => $cur) {
            [$vis, $req] = $choices[$f] ?? [$cur['visible'], $cur['required']];
            $fields[$f] = ['visible' => $vis ? '1' : '0', 'required' => $req ? '1' : '0'];
        }
        $this->actingAs($this->admin())->put('/admin/page-setup/prospect', ['fields' => $fields])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function csv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = array_values(array_filter(preg_split('/\r?\n/', $content), fn ($l) => $l !== ''));

        return array_map('str_getcsv', $lines);
    }

    private function import(string $csv)
    {
        return $this->actingAs($this->admin())->post('/crm/prospects/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)]);
    }

    public function test_hidden_field_is_not_rendered_and_mandatory_field_gets_a_star(): void
    {
        $this->setUp_(['gstin' => [false, false], 'contact_person' => [true, true]]);

        $page = $this->actingAs($this->admin())->get('/crm/prospects/create')->assertOk();
        $page->assertDontSee('name="gstin"', false)->assertDontSee('>GSTIN', false);
        $page->assertSee('Contact person<span class="req">*</span>', false);
        $page->assertSee('name="email"', false);
        $page->assertSee('Mandatory: Prospect name, Domestic / Export, Contact person and Contact no 1.');

        // the Add prospect drawer on the enquiry page uses the same fields
        $enq = $this->actingAs($this->admin())->get('/crm/enquiries/create')->assertOk();
        $enq->assertDontSee('id="pd_gstin"', false)->assertSee('id="pd_contact_person"', false);
    }

    public function test_mandatory_field_is_required_on_store_and_quick_store(): void
    {
        $this->setUp_(['industry_type' => [true, true]]);
        $base = ['name' => 'Asha Interiors', 'market' => 'Domestic', 'phone_1' => '9822012000'];

        $this->actingAs($this->admin())->post('/crm/prospects', $base)->assertSessionHasErrors('industry_type');
        $this->actingAs($this->admin())->postJson('/crm/prospects/quick', $base)->assertJsonValidationErrors('industry_type');
        $this->assertSame(0, Prospect::count());

        $this->actingAs($this->admin())->post('/crm/prospects', $base + ['industry_type' => 'Retail'])->assertSessionHasNoErrors();
        $this->assertSame('Retail', Prospect::firstOrFail()->industry_type);
    }

    public function test_hidden_field_is_not_saved_on_store_and_kept_on_update(): void
    {
        $this->setUp_(['email' => [false, false], 'address' => [false, false]]);

        // a hidden field is not validated and not stored
        $this->actingAs($this->admin())->post('/crm/prospects', self::FULL)->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post('/crm/prospects', array_merge(self::FULL, ['name' => 'Other', 'email' => 'not-an-email']))->assertSessionHasNoErrors();
        $p = Prospect::where('name', 'Kaveri Precision')->firstOrFail();
        $this->assertNull($p->email);
        $this->assertNull($p->address);
        $this->assertSame('27AAACK1234F1Z5', $p->gstin);

        // stored values of a field hidden later stay unchanged on update
        $p->forceFill(['email' => 'old@kaveri.example', 'address' => 'Old address'])->save();
        $this->actingAs($this->admin())->put("/crm/prospects/{$p->id}", array_merge(self::FULL, ['email' => 'new@kaveri.example', 'address' => '', 'position' => 'Manager']))
            ->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('old@kaveri.example', $p->email);
        $this->assertSame('Old address', $p->address);
        $this->assertSame('Manager', $p->position);

        // updating without the hidden inputs at all keeps them too
        $this->actingAs($this->admin())->put("/crm/prospects/{$p->id}", ['name' => 'Kaveri Precision', 'market' => 'Export', 'phone_1' => '9822012345'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('old@kaveri.example', $p->email);
        $this->assertSame('Export', $p->market->value);

        // the edit page does not show the hidden value
        $this->actingAs($this->admin())->get("/crm/prospects/{$p->id}")->assertOk()->assertDontSee('old@kaveri.example');
    }

    public function test_export_headers_exclude_hidden_columns_and_rows_align(): void
    {
        app(ProspectService::class)->create(self::FULL, $this->admin(), null);
        $this->setUp_(['position' => [false, false], 'gstin' => [false, false]]);

        $rows = $this->csv($this->actingAs($this->admin())->get('/crm/prospects/export')->assertOk()->streamedContent());
        $expected = array_values(array_diff_key(ProspectService::COLUMNS, ['position' => 1, 'gstin' => 1]));
        $this->assertSame($expected, $rows[0]);
        $this->assertCount(2, $rows);
        $row = array_combine($rows[0], $rows[1]);
        $this->assertSame('Kaveri Precision', $row['Prospect name']);
        $this->assertSame('Domestic', $row['Domestic / Export']);
        $this->assertSame('Kavita Rao', $row['Contact person']);
        $this->assertSame('9822012345', $row['Contact no 1']);
        $this->assertSame('9822054321', $row['Contact no 2']);
        $this->assertSame('buyer@kaveri.example', $row['Email']);
        $this->assertSame('Engineering', $row['Industry type']);
        $this->assertSame('Hinjewadi, Pune', $row['Address']);

        // with everything shown, all ten columns come back
        $this->setUp_(['position' => [true, false], 'gstin' => [true, false]]);
        $rows = $this->csv($this->actingAs($this->admin())->get('/crm/prospects/export')->streamedContent());
        $this->assertSame(array_values(ProspectService::COLUMNS), $rows[0]);
        $this->assertSame('Buyer', array_combine($rows[0], $rows[1])['Position']);
        $this->assertSame('27AAACK1234F1Z5', array_combine($rows[0], $rows[1])['GSTIN']);
    }

    public function test_template_excludes_hidden_columns_and_sample_row_aligns(): void
    {
        $this->setUp_(['contact_person' => [false, false], 'phone_2' => [false, false], 'address' => [false, false]]);

        $rows = $this->csv($this->actingAs($this->admin())->get('/crm/prospects/import-template')->assertOk()->streamedContent());
        $this->assertSame(['Prospect name', 'Domestic / Export', 'Position', 'Contact no 1', 'Email', 'GSTIN', 'Industry type'], $rows[0]);
        $this->assertCount(count($rows[0]), $rows[1]);
        $row = array_combine($rows[0], $rows[1]);
        $this->assertSame('Orion Agro Equipment', $row['Prospect name']);
        $this->assertSame('Purchase manager', $row['Position']);
        $this->assertSame('+91 98220 11111', $row['Contact no 1']);
        $this->assertSame('sanjay@orion.example', $row['Email']);
        $this->assertSame('27AAACO1234F1Z5', $row['GSTIN']);
        $this->assertSame('Agriculture machinery', $row['Industry type']);

        // the template imports as it is
        $content = $this->actingAs($this->admin())->get('/crm/prospects/import-template')->streamedContent();
        $this->import($content)->assertSessionHasNoErrors()->assertRedirect('/crm/prospects');
        $this->assertSame('sanjay@orion.example', Prospect::firstOrFail()->email);
    }

    public function test_import_ignores_hidden_columns_and_requires_mandatory_ones(): void
    {
        $this->setUp_(['email' => [false, false], 'gstin' => [false, false], 'phone_2' => [false, false]]);

        // hidden columns in the file, even with values that would fail validation, are not read
        $csv = "Prospect name,Domestic / Export,Contact no 1,Contact no 2,Email,GSTIN,Address\n"
            ."Nilgiri Farms,domestic,9822013000,12,not-an-email,BAD,Ooty\n";
        $this->import($csv)->assertSessionHasNoErrors()->assertRedirect('/crm/prospects');
        $p = Prospect::where('name', 'Nilgiri Farms')->firstOrFail();
        $this->assertNull($p->email);
        $this->assertNull($p->gstin);
        $this->assertNull($p->phone_2);
        $this->assertSame('Ooty', $p->address);

        // duplicate check still works with Contact no 2 hidden
        $dup = "Prospect name,Domestic / Export,Contact no 1\nNilgiri Farms,Domestic,+91 98220 13000\n";
        $this->import($dup)->assertSessionHasErrors('file');
        $this->assertSame(1, Prospect::count());

        // a field made mandatory in Page setup is required on each row, also when its column is missing from the file
        $this->setUp_(['industry_type' => [true, true]]);
        $this->import("Prospect name,Domestic / Export,Contact no 1\nShree Castings,Export,9822014000\n")->assertSessionHasErrors('file');
        $this->import("Prospect name,Domestic / Export,Contact no 1,Industry type\nShree Castings,Export,9822014000,\n")->assertSessionHasErrors('file');
        $this->assertSame(1, Prospect::count());
        $this->import("Prospect name,Domestic / Export,Contact no 1,Industry type\nShree Castings,Export,9822014000,Foundry\n")->assertSessionHasNoErrors();
        $this->assertSame('Foundry', Prospect::where('name', 'Shree Castings')->value('industry_type'));
    }

    public function test_page_setup_changes_apply_at_once_within_the_same_request_cycle(): void
    {
        $setup = app(PageSetupService::class);
        $this->assertArrayHasKey('email', ProspectService::columns());
        $this->setUp_(['email' => [false, false]]);
        // the scoped service's memo is cleared on save, and the cache is rewritten
        $this->assertArrayNotHasKey('email', ProspectService::columns());
        $this->assertFalse($setup->visible('prospect', 'email'));
        $this->setUp_(['email' => [true, false]]);
        $this->assertArrayHasKey('email', ProspectService::columns());
    }
}
