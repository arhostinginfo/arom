<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Enquiry;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\PageSetupService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Quotation UOM / Tax from the masters (23/10/2026): each item's Unit and Tax are drop-downs of the UOM and Tax
 * masters, the quotation's GST / tax is a Tax master drop-down, the Tax % is worked out on the server for the
 * supply type (CGST + SGST in the state, IGST in another state, none for export), and old quotations keep their data.
 * Masters used are the ones the sales migration seeds: UOM NOS, KG, MTR, SET, LTR, SQM; GST 0%, 5%, 12%, 18%, 28%.
 */
class QuotationMastersTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    protected function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    protected function sales(): User
    {
        $u = User::where('email', 'priya@company.example')->first();
        if (! $u) {
            $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822000010', 'password' => 'Secret2026', 'status' => 'Active']);
            $u->assignRole('Sales');
            $u->units()->sync([Unit::first()->id]);
        }

        return $u;
    }

    protected function line(array $over = []): array
    {
        return array_merge(['item_no' => 'PL-1', 'description' => 'Areca palm', 'qty' => '10', 'uom' => 'NOS', 'rate' => '100', 'discount_percent' => '', 'tax_structure_id' => $this->taxId('GST 18%')], $over);
    }

    protected function save(array $lines, array $over = [], ?Quotation $q = null)
    {
        $payload = $this->newQuotationPayload(array_merge(['lines' => $lines], $over));

        return $q
            ? $this->actingAs($this->admin())->put("/crm/quotations/{$q->id}", array_merge($payload, ['enquiry_id' => $q->enquiry_id]))
            : $this->actingAs($this->admin())->post('/crm/quotations', $payload);
    }

    protected function saved(array $lines, array $over = []): Quotation
    {
        $this->save($lines, $over)->assertSessionHasNoErrors()->assertRedirect();

        return Quotation::latest('id')->firstOrFail();
    }

    /** Our company in Maharashtra (27) and a customer with an enquiry; $gstin / $currency make the supply type. */
    protected function customerEnquiry(?string $gstin, string $currency = 'INR'): Enquiry
    {
        Company::firstOrCreate(['name' => 'Green Nursery Pvt Ltd'], ['gstin' => '27AAACG1234F1Z5', 'address' => 'Pune']);
        $party = Party::create(['name' => 'Client '.($gstin ?? $currency), 'gstin' => $gstin, 'type' => 'Customer', 'billing_address' => 'Somewhere', 'status' => 'Active',
            'currency_id' => Currency::where('code', $currency)->value('id')]);

        return $this->crmEnquiry(['prospect_type' => 'Existing Client', 'party_id' => $party->id, 'prospect_id' => '']);
    }

    protected function csv(array $head, array $rows): UploadedFile
    {
        $text = implode(',', $head)."\n".implode("\n", array_map(fn ($r) => implode(',', $r), $rows))."\n";

        return UploadedFile::fake()->createWithContent('q.csv', $text);
    }

    // ---- Form ----

    public function test_form_offers_unit_and_tax_from_the_masters_active_rows_only(): void
    {
        Uom::where('code', 'LTR')->update(['is_active' => false]);
        TaxStructure::where('name', 'GST 28%')->update(['is_active' => false]);
        $e = $this->crmEnquiry();

        $html = $this->actingAs($this->admin())->get("/crm/quotations/create?enquiry={$e->id}")->assertOk()->getContent();
        // item Unit: a select of the active UOM codes, no free-text box or old datalist
        $this->assertStringContainsString('<select class="inp " name="lines[0][uom]"', $html);
        $this->assertStringContainsString('<option value="NOS" title="Numbers">NOS</option>', $html);
        $this->assertStringContainsString('<option value="SQM" title="Square metre">SQM</option>', $html);
        $this->assertStringNotContainsString('<option value="LTR"', $html);
        $this->assertStringNotContainsString('id="uomList"', $html);
        // item Tax and the quotation's GST / tax: selects of the active tax structures, with the rate for the supply type
        $this->assertStringContainsString('name="lines[0][tax_structure_id]"', $html);
        $this->assertStringNotContainsString('name="lines[0][tax_percent]"', $html);
        $this->assertStringContainsString('<select class="inp " id="tax_structure_id" name="tax_structure_id"', $html);
        $this->assertStringNotContainsString('name="tax_terms"', $html);
        $this->assertStringContainsString('<option value="'.$this->taxId('GST 18%').'" data-rate="18">GST 18%</option>', $html);
        $this->assertStringContainsString('<option value="'.$this->taxId('GST 0%').'" data-rate="0">GST 0%</option>', $html);
        $this->assertStringNotContainsString('GST 28%', $html);
        // the new-row template has the same drop-downs
        $this->assertStringContainsString('name="lines[__i__][uom]"', $html);
        $this->assertStringContainsString('name="lines[__i__][tax_structure_id]"', $html);
        $this->assertStringContainsString('(this quotation: Intra-state)', $html);
    }

    // ---- Save ----

    public function test_save_stores_the_uom_code_the_structure_and_the_rate_worked_out_ignoring_a_posted_tax_percent(): void
    {
        $q = $this->saved([
            $this->line(['uom' => 'nos', 'tax_percent' => '50']),
            $this->line(['description' => 'Planter', 'uom' => 'Set', 'qty' => '2', 'rate' => '1000', 'discount_percent' => '10', 'tax_structure_id' => $this->taxId('GST 5%'), 'tax_percent' => '0']),
            $this->line(['description' => 'Delivery', 'uom' => '', 'qty' => '1', 'rate' => '500', 'tax_structure_id' => '']),
        ], ['tax_structure_id' => $this->taxId('GST 18%')]);

        $lines = $q->lines;
        $this->assertSame(['NOS', 'SET', null], $lines->pluck('uom')->all());
        $this->assertSame([(int) $this->taxId('GST 18%'), (int) $this->taxId('GST 5%'), null], $lines->pluck('tax_structure_id')->all());
        $this->assertSame(['18.00', '5.00', '0.00'], $lines->pluck('tax_percent')->all());
        // 10 × 100 + 18% = 1,180; 2 × 1000 − 10% + 5% = 1,890; 1 × 500 without tax = 500
        $this->assertSame(['1180.00', '1890.00', '500.00'], $lines->pluck('amount')->all());
        $this->assertSame('3570.00', $q->subtotal);
        $this->assertSame((int) $this->taxId('GST 18%'), $q->tax_structure_id);
        $this->assertNull($q->tax_terms);
        // a row with only a Unit or a Tax picked (the default tax) is an empty row and is dropped
        $q2 = $this->saved([$this->line(), ['item_no' => '', 'description' => '', 'qty' => '', 'uom' => 'NOS', 'rate' => '', 'discount_percent' => '', 'tax_structure_id' => $this->taxId('GST 18%')]]);
        $this->assertCount(1, $q2->lines);
    }

    public function test_tax_percent_follows_the_supply_type_in_state_other_state_and_export(): void
    {
        // a structure whose IGST differs from CGST + SGST shows which rate is taken; one with only IGST is used in the state too
        $split = TaxStructure::create(['name' => 'Test split', 'cgst_rate' => 2, 'sgst_rate' => 2, 'igst_rate' => 5, 'is_active' => true]);
        $igstOnly = TaxStructure::create(['name' => 'IGST only 12', 'cgst_rate' => 0, 'sgst_rate' => 0, 'igst_rate' => 12, 'is_active' => true]);
        $lines = [$this->line(['tax_structure_id' => (string) $split->id]), $this->line(['description' => 'Pots', 'tax_structure_id' => (string) $igstOnly->id])];

        $cases = [
            'in-state customer (27)' => [$this->customerEnquiry('27AABCA1234F1Z5'), ['4.00', '12.00'], ['1040.00', '1120.00']],
            'customer without GSTIN' => [$this->customerEnquiry(null), ['4.00', '12.00'], ['1040.00', '1120.00']],
            'other-state customer (29)' => [$this->customerEnquiry('29AABCB1234F1Z5'), ['5.00', '12.00'], ['1050.00', '1120.00']],
            'export customer (USD)' => [$this->customerEnquiry(null, 'USD'), ['0.00', '0.00'], ['1000.00', '1000.00']],
        ];
        foreach ($cases as $label => [$enquiry, $rates, $amounts]) {
            $q = $this->saved($lines, ['enquiry_id' => $enquiry->id]);
            $this->assertSame($rates, $q->lines->pluck('tax_percent')->all(), $label);
            $this->assertSame($amounts, $q->lines->pluck('amount')->all(), $label);
            $this->assertSame([$split->id, $igstOnly->id], $q->lines->pluck('tax_structure_id')->all(), $label);
        }
        // a prospect (no customer yet) is taken as in the company's state
        $q = $this->saved($lines, ['enquiry_id' => $this->crmEnquiry()->id]);
        $this->assertSame(['4.00', '12.00'], $q->lines->pluck('tax_percent')->all());

        // the form shows the rate of the supply type on each option, for the totals while typing
        $other = Enquiry::where('party_id', Party::where('gstin', '29AABCB1234F1Z5')->value('id'))->firstOrFail();
        $html = $this->actingAs($this->admin())->get("/crm/quotations/create?enquiry={$other->id}")->assertOk()->getContent();
        $this->assertStringContainsString('<option value="'.$split->id.'" data-rate="5">Test split (4%)</option>', $html);
        $this->assertStringContainsString('(this quotation: Inter-state)', $html);
    }

    public function test_unknown_or_inactive_unit_and_tax_are_refused_with_a_clear_message(): void
    {
        $this->save([$this->line(['uom' => 'Month'])])->assertSessionHasErrors(['lines.0.uom' => 'Item 1: choose a unit from the UOM master ("Month" is not in it).']);
        $this->save([$this->line(), $this->line(['tax_structure_id' => '99999'])])->assertSessionHasErrors(['lines.1.tax_structure_id' => 'Item 2: choose a tax from the Tax master.']);
        $this->save([$this->line(['tax_structure_id' => 'old:18'])])->assertSessionHasErrors(['lines.0.tax_structure_id' => 'Item 1: choose a tax from the Tax master.']);
        $this->save([$this->line()], ['tax_structure_id' => '99999'])->assertSessionHasErrors(['tax_structure_id' => 'Choose the GST / tax from the Tax master.']);
        $this->save([$this->line()], ['tax_structure_id' => 'abc'])->assertSessionHasErrors(['tax_structure_id' => 'Choose the GST / tax from the list.']);

        Uom::where('code', 'LTR')->update(['is_active' => false]);
        $gst28 = TaxStructure::where('name', 'GST 28%')->firstOrFail();
        $gst28->update(['is_active' => false]);
        $this->save([$this->line(['uom' => 'LTR'])])->assertSessionHasErrors(['lines.0.uom' => 'Item 1: choose a unit from the UOM master ("LTR" is not in it).']);
        $this->save([$this->line(['tax_structure_id' => (string) $gst28->id])])->assertSessionHasErrors('lines.0.tax_structure_id');
        $this->save([$this->line()], ['tax_structure_id' => (string) $gst28->id])->assertSessionHasErrors('tax_structure_id');
        $this->assertSame(0, Quotation::count());
    }

    public function test_a_quotation_using_a_master_row_made_inactive_later_still_opens_and_saves(): void
    {
        $gst28 = TaxStructure::where('name', 'GST 28%')->firstOrFail();
        $q = $this->saved([$this->line(['uom' => 'LTR', 'tax_structure_id' => (string) $gst28->id])], ['tax_structure_id' => (string) $gst28->id]);
        Uom::where('code', 'LTR')->update(['is_active' => false]);
        $gst28->update(['is_active' => false]);

        $html = $this->actingAs($this->admin())->get("/crm/quotations/{$q->id}")->assertOk()->getContent();
        $this->assertStringContainsString('<option value="LTR" selected>LTR (old)</option>', $html);
        $this->assertStringContainsString('<option value="'.$gst28->id.'" data-rate="28" selected>GST 28%</option>', $html);

        $this->save([$this->line(['uom' => 'LTR', 'tax_structure_id' => (string) $gst28->id])], ['tax_structure_id' => (string) $gst28->id], $q)->assertSessionHasNoErrors();
        $line = $q->fresh()->lines->first();
        $this->assertSame(['LTR', $gst28->id, '28.00'], [$line->uom, $line->tax_structure_id, $line->tax_percent]);
        $this->assertSame($gst28->id, $q->fresh()->tax_structure_id);
    }

    // ---- Old quotations ----

    public function test_old_quotation_with_unit_text_and_tax_percent_opens_with_old_options_and_saves_unchanged(): void
    {
        $q = $this->saved([$this->line(), $this->line(['description' => 'Visit', 'qty' => '2', 'rate' => '1000'])]);
        // as saved before the change: Unit "1" and "Job", Tax % only, GST / tax as text
        $l = $q->lines;
        DB::table('quotation_lines')->where('id', $l[0]->id)->update(['uom' => '1', 'tax_structure_id' => null, 'tax_percent' => 18, 'amount' => 1180]);
        DB::table('quotation_lines')->where('id', $l[1]->id)->update(['uom' => 'Job', 'tax_structure_id' => null, 'tax_percent' => 15, 'amount' => 2300]);
        DB::table('quotations')->where('id', $q->id)->update(['tax_structure_id' => null, 'tax_terms' => 'GST extra as applicable', 'subtotal' => 3480, 'grand_total' => 3480]);

        $html = $this->actingAs($this->admin())->get("/crm/quotations/{$q->id}")->assertOk()->getContent();
        $this->assertStringContainsString('<option value="1" selected>1 (old)</option>', $html);
        $this->assertStringContainsString('<option value="Job" selected>Job (old)</option>', $html);
        // 18% is a master rate, so the GST 18% row is picked; 15% is not, so it stays as an old option
        $this->assertStringContainsString('<option value="'.$this->taxId('GST 18%').'" data-rate="18" selected>GST 18%</option>', $html);
        $this->assertStringContainsString('<option value="old:15" data-rate="15" selected>15% (old)</option>', $html);
        $this->assertStringContainsString('Saved before as text: GST extra as applicable', $html);
        $this->assertStringContainsString('3,480.00', $html);

        // saved with what the form shows: nothing changes
        $lines = [
            $this->line(['uom' => '1', 'tax_structure_id' => $this->taxId('GST 18%')]),
            $this->line(['description' => 'Visit', 'qty' => '2', 'rate' => '1000', 'uom' => 'Job', 'tax_structure_id' => 'old:15']),
        ];
        $this->save($lines, [], $q)->assertSessionHasNoErrors();
        $q = $q->fresh();
        $this->assertSame(['1', 'Job'], $q->lines->pluck('uom')->all());
        $this->assertSame(['18.00', '15.00'], $q->lines->pluck('tax_percent')->all());
        $this->assertSame(['1180.00', '2300.00'], $q->lines->pluck('amount')->all());
        $this->assertSame([(int) $this->taxId('GST 18%'), null], $q->lines->pluck('tax_structure_id')->all());
        $this->assertSame('3480.00', $q->grand_total);
        $this->assertSame('GST extra as applicable', $q->tax_terms);
        $this->assertNull($q->tax_structure_id);

        // only the old values of this quotation are accepted, not any other text or rate
        $this->save([$lines[0], array_merge($lines[1], ['tax_structure_id' => 'old:16'])], [], $q)->assertSessionHasErrors('lines.1.tax_structure_id');
        $this->save([$lines[0], array_merge($lines[1], ['uom' => 'Visit'])], [], $q)->assertSessionHasErrors('lines.1.uom');
        $this->save([array_merge($lines[1], ['uom' => 'NOS'])])->assertSessionHasErrors('lines.0.tax_structure_id');
        $this->save([array_merge($lines[0], ['uom' => 'Job'])])->assertSessionHasErrors('lines.0.uom');
    }

    // ---- Revise ----

    public function test_revise_copies_the_tax_structures(): void
    {
        $q = $this->saved([$this->line(), $this->line(['description' => 'Pots', 'uom' => 'KG', 'tax_structure_id' => $this->taxId('GST 5%')])], ['tax_structure_id' => $this->taxId('GST 18%')]);
        $this->actingAs($this->admin())->post("/crm/quotations/{$q->id}/revise")->assertSessionHasNoErrors()->assertRedirect();
        $new = Quotation::where('previous_revision_id', $q->id)->firstOrFail();

        $this->assertSame(1, $new->revision_no);
        $this->assertSame((int) $this->taxId('GST 18%'), $new->tax_structure_id);
        $this->assertSame([(int) $this->taxId('GST 18%'), (int) $this->taxId('GST 5%')], $new->lines->pluck('tax_structure_id')->all());
        $this->assertSame(['NOS', 'KG'], $new->lines->pluck('uom')->all());
        $this->assertSame(['18.00', '5.00'], $new->lines->pluck('tax_percent')->all());
        $this->assertSame($q->grand_total, $new->grand_total);
    }

    // ---- CSV import / export ----

    public function test_csv_import_with_master_values_and_a_row_error_for_an_unknown_unit_or_tax(): void
    {
        $sales = $this->sales();
        $enq = $this->crmEnquiry();
        $head = array_values(QuotationService::IMPORT_COLUMNS);
        $this->assertContains('Tax', $head);
        $this->assertNotContains('GST / tax', $head);
        $this->assertNotContains('Tax %', $head);
        $row = fn ($unit, $tax, $desc = 'Areca palm') => ['1', $enq->enquiry_no, '05/10/2026', '', '', '', $sales->email, 'INR', '', '', '', '', '', $desc, '10', $unit, '100', '', $tax];

        // name or rate, any case
        $this->actingAs($sales)->post('/crm/quotations/import', ['file' => $this->csv($head, [$row('nos', 'gst 18%'), $row('KG', '5%', 'Pots'), $row('', '', 'Delivery')])])->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->assertSame(['NOS', 'KG', null], $q->lines->pluck('uom')->all());
        $this->assertSame([(int) $this->taxId('GST 18%'), (int) $this->taxId('GST 5%'), null], $q->lines->pluck('tax_structure_id')->all());
        $this->assertSame(['18.00', '5.00', '0.00'], $q->lines->pluck('tax_percent')->all());
        // the quotation's GST / tax is its first item's
        $this->assertSame((int) $this->taxId('GST 18%'), $q->tax_structure_id);

        $count = Quotation::count();
        $this->actingAs($sales)->post('/crm/quotations/import', ['file' => $this->csv($head, [$row('NOS', '18'), $row('Month', '18', 'Visit')])])
            ->assertSessionHasErrors(['file' => 'Rows 2 to 3 (group 1): row 3: unit "Month" is not in the UOM master (use a code such as NOS)']);
        $this->actingAs($sales)->post('/crm/quotations/import', ['file' => $this->csv($head, [$row('NOS', 'GST 15%')])])
            ->assertSessionHasErrors(['file' => 'Row 2 (group 1): row 2: tax "GST 15%" is not in the Tax master (use a name such as GST 0% or its rate)']);
        $this->assertSame($count, Quotation::count());
    }

    public function test_an_old_layout_csv_with_gst_tax_and_tax_percent_columns_still_imports(): void
    {
        $sales = $this->sales();
        $enq = $this->crmEnquiry();
        $head = ['Group', 'Enquiry no', 'Quotation date', 'Valid until', 'Contact', 'Site', 'Sales person login email', 'Currency', 'Payment terms', 'Delivery / service terms', 'GST / tax',
            'Discount', 'Notes', 'Item no', 'Item description', 'Qty', 'Unit', 'Rate', 'Discount %', 'Tax %'];
        $rows = [
            ['1', $enq->enquiry_no, '05/10/2026', '', '', '', $sales->email, 'INR', '', '', 'GST 18% included', '', '', 'PL-1', 'Areca palm', '20', 'Nos', '1450', '0', '18'],
            ['1', $enq->enquiry_no, '05/10/2026', '', '', '', $sales->email, 'INR', '', '', '', '', '', '', 'Planter', '2', 'SET', '500', '', '0'],
        ];
        $this->actingAs($sales)->post('/crm/quotations/import', ['file' => $this->csv($head, $rows)])->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->assertSame(['NOS', 'SET'], $q->lines->pluck('uom')->all());
        $this->assertSame([(int) $this->taxId('GST 18%'), (int) $this->taxId('GST 0%')], $q->lines->pluck('tax_structure_id')->all());
        $this->assertSame(['18.00', '0.00'], $q->lines->pluck('tax_percent')->all());
        $this->assertSame(['34220.00', '1000.00'], $q->lines->pluck('amount')->all());
        // the old GST / tax text column is not read
        $this->assertNull($q->tax_terms);
    }

    public function test_csv_export_and_template_use_the_master_values(): void
    {
        $q = $this->saved([$this->line(), $this->line(['description' => 'Pots', 'uom' => 'KG', 'tax_structure_id' => $this->taxId('GST 5%')])], ['tax_structure_id' => $this->taxId('GST 18%')]);
        $csv = $this->actingAs($this->admin())->get('/crm/quotations/export')->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", str_replace("\r", '', preg_replace('/^\xEF\xBB\xBF/', '', $csv))))));
        $head = array_flip($rows[0]);
        $this->assertArrayHasKey('Tax', $head);
        $this->assertArrayNotHasKey('GST / tax', $head);
        $this->assertSame(['NOS', 'GST 18%'], [$rows[1][$head['Unit']], $rows[1][$head['Tax']]]);
        $this->assertSame(['KG', 'GST 5%'], [$rows[2][$head['Unit']], $rows[2][$head['Tax']]]);

        $template = $this->actingAs($this->admin())->get('/crm/quotations/import-template')->assertOk()->streamedContent();
        $this->assertStringContainsString(',NOS,1450,0,"GST 18%"', $template);
        $this->assertStringNotContainsString(',Month,', $template);
    }

    // ---- PDF ----

    public function test_pdf_prints_the_tax_summary_by_structure_and_an_old_quotation_its_text(): void
    {
        $q = $this->saved([
            $this->line(),
            $this->line(['description' => 'Planter', 'qty' => '1', 'rate' => '1000', 'tax_structure_id' => $this->taxId('GST 5%')]),
            $this->line(['description' => 'Pots', 'qty' => '20', 'rate' => '50']),
        ], ['tax_structure_id' => $this->taxId('GST 18%')]);
        $render = fn (Quotation $q) => view('pdf.quotation', ['q' => $q->fresh(['lines', 'currency', 'unit', 'salesPerson', 'preparer', 'prospect'])])->render();

        // GST 18%: 1,000 + 1,000 at 18% = 360.00; GST 5%: 1,000 at 5% = 50.00
        $html = $render($q);
        $this->assertMatchesRegularExpression('/GST 18%: \S+ 360\.00/u', $html);
        $this->assertMatchesRegularExpression('/GST 5%: \S+ 50\.00/u', $html);
        $this->assertStringContainsString('3,410.00', $html);
        $this->actingAs($this->admin())->get("/crm/quotations/{$q->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        // an old quotation: lines without a structure are summed by their Tax %, none at all prints the saved text
        DB::table('quotation_lines')->where('quotation_id', $q->id)->update(['tax_structure_id' => null]);
        $this->assertMatchesRegularExpression('/Tax 18%: \S+ 360\.00/u', $render($q));
        DB::table('quotation_lines')->where('quotation_id', $q->id)->update(['tax_percent' => 0]);
        DB::table('quotations')->where('id', $q->id)->update(['tax_structure_id' => null, 'tax_terms' => 'GST extra as applicable']);
        $this->assertStringContainsString('GST extra as applicable', $render($q));
    }

    // ---- Page setup ----

    public function test_page_setup_shows_hides_and_makes_the_gst_tax_drop_down_mandatory(): void
    {
        $ps = app(PageSetupService::class);
        $set = function (bool $visible, bool $required) use ($ps) {
            $choices = [];
            foreach ($ps->fields('quotation') as $f => $cur) {
                $choices[$f] = $f === 'tax_terms' ? ['visible' => $visible, 'required' => $required] : ['visible' => $cur['visible'], 'required' => $cur['required']];
            }
            $ps->save('quotation', $choices, $this->admin());
        };
        $e = $this->crmEnquiry();

        $set(true, true);
        $this->actingAs($this->admin())->get("/crm/quotations/create?enquiry={$e->id}")->assertOk()
            ->assertSee('<select class="inp " id="tax_structure_id" name="tax_structure_id" required>', false);
        $this->save([$this->line()], ['tax_structure_id' => ''])->assertSessionHasErrors(['tax_structure_id' => 'Choose the GST / tax.']);
        $q = $this->saved([$this->line()], ['tax_structure_id' => $this->taxId('GST 18%')]);

        // hidden: not on the form, and what is saved stays as it was
        $set(false, false);
        $this->actingAs($this->admin())->get("/crm/quotations/{$q->id}")->assertOk()->assertDontSee('id="tax_structure_id"', false);
        $this->save([$this->line()], ['tax_structure_id' => $this->taxId('GST 5%')], $q)->assertSessionHasNoErrors();
        $this->assertSame((int) $this->taxId('GST 18%'), $q->fresh()->tax_structure_id);
    }

    // ---- Tax master ----

    public function test_a_tax_structure_used_by_a_quotation_cannot_be_deleted(): void
    {
        $onLine = TaxStructure::create(['name' => 'Line only 3%', 'cgst_rate' => 1.5, 'sgst_rate' => 1.5, 'igst_rate' => 3, 'is_active' => true]);
        $onHead = TaxStructure::create(['name' => 'Head only 3%', 'cgst_rate' => 1.5, 'sgst_rate' => 1.5, 'igst_rate' => 3, 'is_active' => true]);
        $unused = TaxStructure::create(['name' => 'Unused 3%', 'cgst_rate' => 1.5, 'sgst_rate' => 1.5, 'igst_rate' => 3, 'is_active' => true]);
        $this->saved([$this->line(['tax_structure_id' => (string) $onLine->id])], ['tax_structure_id' => (string) $onHead->id]);

        foreach ([$onLine, $onHead] as $t) {
            $this->actingAs($this->admin())->delete("/admin/masters/lists/tax-structures/{$t->id}")
                ->assertSessionHasErrors(['delete' => 'This tax structure is used on 1 record(s), so it cannot be deleted. Make it inactive instead.']);
            $this->assertDatabaseHas('tax_structures', ['id' => $t->id]);
        }
        $this->actingAs($this->admin())->delete("/admin/masters/lists/tax-structures/{$unused->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('tax_structures', ['id' => $unused->id]);
    }
}
