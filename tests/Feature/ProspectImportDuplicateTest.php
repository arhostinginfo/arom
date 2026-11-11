<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\User;
use App\Services\PageSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * CRM > Prospects import: the whole file is checked for duplicates by prospect name + mobile number
 * (against saved prospects and earlier rows of the file). Any duplicate stops the whole file.
 */
class ProspectImportDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const HEAD = "Prospect name,Domestic / Export,Contact no 1,Contact no 2\n";

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function import(string $csv)
    {
        return $this->actingAs($this->admin())
            ->post('/crm/prospects/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)]);
    }

    private function saved(string $name, string $phone1, ?string $phone2 = null): Prospect
    {
        return Prospect::create(['name' => $name, 'market' => 'Domestic', 'phone_1' => $phone1, 'phone_2' => $phone2]);
    }

    /** @return string[] the 'file' error messages in the session */
    private function fileErrors(): array
    {
        return session('errors')->get('file');
    }

    public function test_clean_file_is_imported(): void
    {
        $this->saved('Kaveri Precision', '9822012345');
        $csv = self::HEAD
            ."Orion Agro Equipment,Domestic,+91 98220 11111,\n"
            ."Shree Castings,Export,+44 20 7946 0000,\n";

        $this->import($csv)->assertSessionHasNoErrors()->assertRedirect('/crm/prospects');
        $this->assertSame(3, Prospect::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'prospect.imported']);
    }

    public function test_duplicate_of_saved_prospect_blocks_the_whole_file(): void
    {
        $this->saved('Orion Agro Equipment', '+91 98220 11111');
        $csv = self::HEAD
            ."Fresh One,Domestic,9822000001,\n"            // row 2: fine
            ."Orion Agro Equipment,Domestic,+91 98220 11111,\n"; // row 3: duplicate

        $this->import($csv)->assertSessionHasErrors('file');
        $this->assertSame(1, Prospect::count(), 'nothing is saved');
        $this->assertDatabaseMissing('prospects', ['name' => 'Fresh One']);
        $this->assertContains('Row 3: prospect "Orion Agro Equipment" with mobile +91 98220 11111 already exists.', $this->fileErrors());
        $this->assertCount(1, $this->fileErrors());
    }

    public function test_duplicate_on_contact_no_2_of_saved_prospect_is_found(): void
    {
        $this->saved('Orion Agro Equipment', '9822000099', '9822011111');
        $this->import(self::HEAD."Orion Agro Equipment,Domestic,9822011111,\n")->assertSessionHasErrors('file');
        $this->assertSame(1, Prospect::count());
    }

    public function test_duplicate_inside_the_file_blocks_everything_and_names_the_earlier_row(): void
    {
        $csv = self::HEAD
            ."Orion Agro Equipment,Domestic,9822011111,\n"     // row 2
            ."Shree Castings,Export,9822022222,\n"            // row 3
            ."Orion Agro Equipment,Domestic,9822011111,\n";   // row 4 = row 2

        $this->import($csv)->assertSessionHasErrors('file');
        $this->assertSame(0, Prospect::count());
        $this->assertSame(['Row 4: prospect "Orion Agro Equipment" with mobile 9822011111 is repeated in the file (same as row 2).'], $this->fileErrors());
    }

    public function test_repeat_inside_file_ignores_case_and_extra_spaces_and_matches_contact_no_2(): void
    {
        $csv = self::HEAD
            ."Orion Agro Equipment,Domestic,9822000001,9822011111\n"
            ."  orion   AGRO equipment ,Domestic,+91 98220 11111,\n";

        $this->import($csv)->assertSessionHasErrors('file');
        $this->assertSame(0, Prospect::count());
        $this->assertStringContainsString('Row 3:', $this->fileErrors()[0]);
        $this->assertStringContainsString('(same as row 2)', $this->fileErrors()[0]);
    }

    public function test_same_name_with_a_different_mobile_is_allowed(): void
    {
        $this->saved('Orion Agro Equipment', '9822011111');
        $csv = self::HEAD
            ."Orion Agro Equipment,Domestic,9822033333,\n"
            ."Orion Agro Equipment,Domestic,9822044444,\n";

        $this->import($csv)->assertSessionHasNoErrors()->assertRedirect('/crm/prospects');
        $this->assertSame(3, Prospect::where('name', 'Orion Agro Equipment')->count());
    }

    public function test_same_mobile_with_a_different_name_is_allowed(): void
    {
        $this->saved('Orion Agro Equipment', '9822011111');
        $csv = self::HEAD
            ."Kaveri Precision,Domestic,9822011111,\n"
            ."Shree Castings,Domestic,+91 98220 11111,\n";

        $this->import($csv)->assertSessionHasNoErrors()->assertRedirect('/crm/prospects');
        $this->assertSame(3, Prospect::count());
    }

    public function test_formatted_and_plain_mobile_are_the_same_number(): void
    {
        $this->saved('Orion Agro Equipment', '9822011111');
        $this->import(self::HEAD."Orion Agro Equipment,Domestic,+91 98220 11111,\n")->assertSessionHasErrors('file');
        $this->assertSame(['Row 2: prospect "Orion Agro Equipment" with mobile +91 98220 11111 already exists.'], $this->fileErrors());

        // and the other way round, plus a leading 0 and dashes
        $this->saved('Shree Castings', '+91 98220 22222');
        $this->import(self::HEAD."Shree Castings,Domestic,09822-022222,\n")->assertSessionHasErrors('file');
        $this->assertSame(2, Prospect::count());
    }

    public function test_deleted_prospect_does_not_count_as_a_duplicate(): void
    {
        $this->saved('Orion Agro Equipment', '9822011111')->delete();
        $this->import(self::HEAD."Orion Agro Equipment,Domestic,9822011111,\n")->assertSessionHasNoErrors();
        $this->assertSame(1, Prospect::count());
    }

    public function test_hidden_contact_no_2_is_not_read_but_contact_no_1_is_still_checked(): void
    {
        $setup = app(PageSetupService::class);
        $fields = [];
        foreach ($setup->fields('prospect') as $f => $cur) {
            $fields[$f] = ['visible' => $f === 'phone_2' ? '0' : ($cur['visible'] ? '1' : '0'), 'required' => $cur['required'] ? '1' : '0'];
        }
        $this->actingAs($this->admin())->put('/admin/page-setup/prospect', ['fields' => $fields])->assertSessionHasNoErrors();

        $this->saved('Orion Agro Equipment', '9822000001');
        // Contact no 2 in the file is ignored, so it does not make the row a repeat of row 2.
        $ok = "Prospect name,Domestic / Export,Contact no 1,Contact no 2\n"
            ."Kaveri Precision,Domestic,9822055555,9822066666\n"
            ."Kaveri Precision,Domestic,9822077777,9822066666\n";
        $this->import($ok)->assertSessionHasNoErrors();
        $this->assertSame(3, Prospect::count());
        $this->assertNull(Prospect::where('phone_1', '9822055555')->value('phone_2'));

        $this->import("Prospect name,Domestic / Export,Contact no 1\nOrion Agro Equipment,Domestic,9822000001\n")->assertSessionHasErrors('file');
        $this->assertSame(['Row 2: prospect "Orion Agro Equipment" with mobile 9822000001 already exists.'], $this->fileErrors());
    }

    public function test_the_error_page_shows_the_duplicate_messages(): void
    {
        $this->saved('Orion Agro Equipment', '9822011111');
        $this->import(self::HEAD."Orion Agro Equipment,Domestic,9822011111,\n")->assertRedirect();
        $this->actingAs($this->admin())->get('/crm/prospects')->assertOk()
            ->assertSee('The file was not imported. Nothing was saved.')
            ->assertSee('Row 2: prospect &quot;Orion Agro Equipment&quot; with mobile 9822011111 already exists.', false);
    }

    /**
     * Known gap: saved prospects are looked up with whereIn('name', ...) on the trimmed file name, so a
     * file name that differs from the saved one only in inner spaces is not found and the duplicate is imported.
     */
    public function test_saved_prospect_with_different_inner_spacing_is_a_duplicate(): void
    {
        $this->saved('Orion Agro Equipment', '9822011111');
        $this->import(self::HEAD."Orion  Agro   Equipment,Domestic,9822011111,\n")->assertSessionHasErrors('file');
        $this->assertSame(1, Prospect::count());
    }

    /** Case differences against saved prospects (works on MySQL's _ci collation; SQLite's = is case sensitive). */
    public function test_saved_prospect_with_different_case_is_a_duplicate(): void
    {
        $this->saved('Orion Agro Equipment', '9822011111');
        $this->import(self::HEAD."ORION agro equipment,Domestic,9822011111,\n")->assertSessionHasErrors('file');
        $this->assertSame(1, Prospect::count());
    }
}
