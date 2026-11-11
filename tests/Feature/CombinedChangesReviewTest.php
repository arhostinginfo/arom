<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Http\Controllers\Sales\SalesInvoiceController;
use App\Models\Company;
use App\Models\Currency;
use App\Models\CustomerPo;
use App\Models\FinancialYear;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\TaxStructure;
use App\Models\Transporter;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Combined review of three changes made in parallel on 02/11/2026 (03/11/2026, tester):
 * - the Separate PO menu removed (CRM › 6 PO / Agreement kept, with Part Master lines and Create Sales Order);
 * - tax invoice fixes (invoice date, PO no drop-down, PDF buttons, standard GST PDF, Invoice type drop-down);
 * - segmented radio groups turned into drop-downs.
 * Locks down the whole chain CRM PO → sales order → tax invoice → PDF → cancel, the invoice date rules on edit, the PDF
 * page layout (checked per page inside dompdf), the HSN summary per rate, missing optional data, and merge damage
 * (encoding, leftovers). SQLite :memory: only.
 */
class CombinedChangesReviewTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    /** The JPG / PNG logo fixtures of TaxInvoiceFixesTest (dompdf draws JPG without GD; PNG needs GD). */
    private function logo(string $type): string
    {
        return base64_decode((new \ReflectionClassConstant(TaxInvoiceFixesTest::class, $type))->getValue());
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['nursery.documents_disk' => 'local']);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function sales(): User
    {
        if ($u = User::where('email', 'priya@company.example')->first()) {
            return $u;
        }
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822000910', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function company(array $over = []): Company
    {
        return Company::create(array_merge(['name' => 'Nursery Components Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'pan' => 'AAACN1234F',
            'address' => "Plot 21, MIDC Chakan\nPune 410501", 'phone' => '020 2345 6789', 'email' => 'accounts@nursery.example'], $over));
    }

    private function customer(string $name = 'Apex Auto Components', array $over = []): Party
    {
        return Party::create(array_merge(['name' => $name, 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => "Gat 12, Chakan\nPune",
            'shipping_address' => 'Gate 2, Chakan, Pune', 'status' => 'Active', 'currency_id' => Currency::defaultId()], $over));
    }

    private function part(?Party $party, string $no, float $price, string $tax = 'GST 18%', string $hsn = '0602'): Item
    {
        $gst = TaxStructure::where('name', $tax)->firstOrFail();
        $code = HsnSacCode::firstOrCreate(['code' => $hsn], ['type' => 'HSN', 'description' => 'Goods '.$hsn, 'tax_structure_id' => $gst->id, 'is_active' => true]);

        return Item::create(['party_id' => $party?->id, 'description' => 'Part '.$no, 'item_no' => $no, 'type' => 'Sales', 'hsn_sac_code_id' => $code->id,
            'uom_id' => Uom::where('code', 'NOS')->value('id'), 'tax_structure_id' => $gst->id, 'price' => $price, 'currency_id' => Currency::defaultId(), 'status' => 'Active']);
    }

    private function invoicePayload(Party $party, array $lines, array $over = []): array
    {
        return array_merge(['_form' => 'invoice', 'invoice_type' => 'Sales', 'invoice_date' => today()->format('d/m/Y'), 'party_id' => $party->id, 'sales_order_id' => '',
            'shipping_address' => 'Gate 2, Chakan, Pune', 'mode_of_transport' => 'Road', 'transporter_id' => '', 'vehicle_no' => '', 'distance_km' => '', 'pf_charges' => '',
            'lines' => $lines], $over);
    }

    /** A sent quotation from a new enquiry of $party (a prospect's when null). */
    private function sentQuotation(?Party $party): Quotation
    {
        $e = $this->crmEnquiry($party ? ['party_id' => $party->id, 'prospect_id' => ''] : []);
        $this->actingAs($this->admin())->post(route('crm.quotations.store'), $this->newQuotationPayload(['enquiry_id' => $e->id]))->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('crm.quotations.submit', $q));
        $this->actingAs($this->admin())->post(route('crm.quotations.sent', $q));

        return $q->refresh();
    }

    /** Render the invoice PDF and record, per page, every text drawn with its position (pt from the page top). */
    private function pages(SalesInvoice $inv, ?array $logo = null): array
    {
        $inv->load(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']);
        $pdf = Pdf::loadView('pdf.sales-invoice', ['inv' => $inv, 'company' => Company::current(), 'logo' => $logo])->setPaper('a4', 'portrait');
        $texts = [];
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$texts) {
            $node = $frame->get_node();
            if ($node->nodeName === '#text' && trim($node->nodeValue) !== '') {
                $texts[] = ['page' => $canvas->get_page_number(), 'text' => trim($node->nodeValue), 'y' => (float) $frame->get_position('y'), 'h' => (float) $frame->get_margin_height()];
            }
        }]]);
        $pdf->render();

        return ['count' => $pdf->getDomPDF()->getCanvas()->get_page_count(), 'texts' => collect($texts)];
    }

    /** Pages on which $text is drawn (any case: dompdf applies text-transform to the text itself). */
    private function on(array $pages, string $text): array
    {
        return $pages['texts']->filter(fn ($t) => mb_stripos($t['text'], $text) !== false)->pluck('page')->unique()->sort()->values()->all();
    }

    // ---- 1. Separate PO menu removed: routes, redirects, leftovers ----

    public function test_po_menu_routes_are_gone_and_old_bookmarks_redirect_safely(): void
    {
        $this->assertSame([], collect(Route::getRoutes()->getRoutesByName())->keys()->filter(fn ($n) => str_starts_with($n, 'po.'))->values()->all());
        $this->assertArrayNotHasKey('po', config('nursery.modules'));
        foreach (['po.form', 'po.list', 'po.register'] as $v) {
            $this->assertFalse(view()->exists($v), $v);
        }
        foreach (['App\Support\PoContext', 'App\Http\Controllers\Po\CustomerPoController', 'App\Http\Controllers\Po\PoRegisterController', 'App\Http\Requests\Po\CustomerPoRequest'] as $c) {
            $this->assertFalse(class_exists($c), $c);
        }
        $this->assertTrue(Route::has('crm.customer-pos.sales-order'));

        $admin = $this->admin();
        // a guest signs in first
        $this->get('/po/1')->assertRedirect(route('login'));
        foreach (['/po', '/po/list', '/po/export', '/po/register', '/po/1/edit', '/po/abc', '/po/1abc'] as $url) {
            $this->actingAs($admin)->get($url)->assertNotFound();
        }
        $this->actingAs($admin)->get('/po/7')->assertStatus(301)->assertRedirect(route('crm.customer-pos.show', 7));
        $this->actingAs($admin)->get('/po/create?quotation=5')->assertStatus(301)->assertRedirect(route('crm.customer-pos.create').'?quotation=5');
        // no open redirect: the target is always this app's CRM page, whatever is in the query
        foreach (['/po/create?next=https://evil.example', '/po/create?url=//evil.example/x'] as $url) {
            $to = $this->actingAs($admin)->get($url)->assertStatus(301)->headers->get('Location');
            $this->assertStringStartsWith(route('crm.customer-pos.create').'?', $to);
            $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($to, PHP_URL_HOST));
        }
        $this->actingAs($admin)->get('//evil.example/po/1')->assertNotFound();

        // the menu: no PO top item, CRM keeps 6 PO / Agreement
        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('6 PO / Agreement', $html);
        $this->assertStringNotContainsString('Separate PO menu', $html);
        $this->assertStringNotContainsString('PO register', $html);
    }

    // ---- 2. Whole chain: CRM PO with items → sales order → tax invoice → PDF → cancel ----

    public function test_crm_po_to_sales_order_to_tax_invoice_pdf_and_cancel(): void
    {
        $this->company();
        $party = $this->customer();
        $rose = $this->part($party, 'ROSE-1', 40);
        $fern = $this->part(null, 'FERN-2', 25, 'GST 12%');
        $sales = $this->sales();

        // CRM › 6 PO / Agreement with Part Master lines, against a sent quotation
        $q = $this->sentQuotation($party);
        $this->actingAs($sales)->get(route('crm.customer-pos.create', ['quotation' => $q->id]))->assertOk()->assertSee('name="quotation_id"', false);
        $this->actingAs($sales)->post(route('crm.customer-pos.store'), ['quotation_id' => $q->id, 'po_no' => 'PO-7788', 'po_date' => '10/10/2026', 'po_value' => '',
            'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026', 'valid_from' => '10/10/2026', 'valid_to' => '31/03/2027',
            'lines_json' => json_encode([
                ['item_id' => (string) $rose->id, 'description' => '', 'quantity' => '100', 'uom_id' => '', 'rate' => '45'],
                ['item_id' => (string) $fern->id, 'description' => '', 'quantity' => '40', 'uom_id' => '', 'rate' => '30'],
            ])])->assertSessionHasNoErrors();
        $po = CustomerPo::latest('id')->firstOrFail();
        $this->assertSame(2, $po->lines()->count());
        $this->assertSame((int) $party->id, (int) $po->party_id);
        $this->assertSame(EnquiryStatus::PoReceived, $po->enquiry->fresh()->status);
        $this->assertSame(\App\Enums\QuotationStatus::Accepted, $q->fresh()->status);

        // Create Sales Order: a Draft with the PO no and lines
        $this->actingAs($sales)->get(route('crm.customer-pos.show', $po))->assertOk()->assertSee('Create Sales Order')->assertDontSee('id="poMakeSo" disabled', false);
        $this->actingAs($sales)->post(route('crm.customer-pos.sales-order', $po))->assertSessionHasNoErrors();
        $so = SalesOrder::where('customer_po_id', $po->id)->firstOrFail();
        $this->assertSame(SalesOrder::DRAFT, $so->status);
        $this->assertSame('PO-7788', $so->po_number);
        $this->assertEqualsCanonicalizing([[(int) $rose->id, 100.0, 45.0], [(int) $fern->id, 40.0, 30.0]],
            $so->lines->map(fn ($l) => [(int) $l->item_id, (float) $l->quantity, (float) $l->rate])->all());
        $this->actingAs($sales)->get(route('sales.orders.show', $so))->assertOk()->assertSee('From PO')->assertSee('PO-7788');

        // not in the PO no list while Draft; listed once approved
        $this->assertSame([], $this->actingAs($sales)->getJson("/sales/parties/{$party->id}/info")->assertOk()->json('orders'));
        $this->actingAs($sales)->post("/sales/orders/{$so->id}/submit")->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post("/sales/orders/{$so->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame(SalesOrder::APPROVED, $so->fresh()->status);
        $this->assertSame([['id' => $so->id, 'label' => 'PO PO-7788 · '.$so->so_no]], $this->actingAs($sales)->getJson("/sales/parties/{$party->id}/info")->json('orders'));
        $lines = collect($this->actingAs($sales)->getJson("/sales/orders/{$so->id}/po-lines")->assertOk()->json())->keyBy('item_id');
        $this->assertEquals(100, $lines[$rose->id]['balance']);
        $this->assertEquals(40, $lines[$fern->id]['balance']);

        // the invoice: a Sales user cannot date it; PO quantities are limited by the balance
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($party, [['sales_order_line_id' => $lines[$rose->id]['sales_order_line_id'], 'quantity' => '101']], ['sales_order_id' => $so->id]))
            ->assertSessionHasErrorsIn('invoice');
        $this->assertSame(0, SalesInvoice::count());
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($party, [
            ['sales_order_line_id' => $lines[$rose->id]['sales_order_line_id'], 'quantity' => '60'],
            ['sales_order_line_id' => $lines[$fern->id]['sales_order_line_id'], 'quantity' => '40'],
        ], ['sales_order_id' => $so->id, 'invoice_date' => '01/04/2026']))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->firstOrFail();
        $this->assertSame(today()->toDateString(), $inv->invoice_date->toDateString());
        $this->assertSame($so->id, $inv->sales_order_id);
        $this->assertEqualsWithDelta(60 * 45 + 40 * 30, (float) $inv->taxable_value, 0.001);
        $balances = collect($this->actingAs($sales)->getJson("/sales/orders/{$so->id}/po-lines")->json())->keyBy('item_id');
        $this->assertEquals(40, $balances[$rose->id]['balance']);
        $this->assertEquals(0, $balances[$fern->id]['balance']);

        // the PDF: a real download, with the PO in the header
        $res = $this->actingAs($sales)->get("/sales/invoices/{$inv->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString(str_replace('/', '-', $inv->invoice_no).'.pdf', (string) $res->headers->get('content-disposition'));
        $pages = $this->pages($inv);
        $this->assertNotEmpty($this->on($pages, 'PO-7788 · '.$so->so_no));
        $this->assertNotEmpty($this->on($pages, '10/10/2026')); // PO date = the customer PO's date

        // cancel: the balance comes back, the PDF says CANCELLED
        $this->actingAs($this->admin())->post("/sales/invoices/{$inv->id}/cancel", ['cancel_reason' => 'Wrong quantity'])->assertSessionHasNoErrors();
        $this->assertTrue($inv->fresh()->isCancelled());
        $this->assertEquals(100, collect($this->actingAs($sales)->getJson("/sales/orders/{$so->id}/po-lines")->json())->keyBy('item_id')[$rose->id]['balance']);
        $this->actingAs($sales)->get("/sales/invoices/{$inv->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertNotEmpty($this->on($this->pages($inv->fresh()), 'CANCELLED'));
    }

    public function test_prospect_po_hint_no_longer_points_to_a_customer_field(): void
    {
        $q = $this->sentQuotation(null);
        $this->actingAs($this->sales())->post(route('crm.customer-pos.store'), ['quotation_id' => $q->id, 'po_no' => 'PR-1', 'po_date' => '10/10/2026', 'po_value' => '455',
            'received_by' => $this->crmEmployee()->id, 'received_date' => '10/10/2026'])->assertSessionHasNoErrors();
        $po = CustomerPo::latest('id')->firstOrFail();
        $this->assertNull($po->party_id);
        $this->actingAs($this->sales())->get(route('crm.customer-pos.show', $po))->assertOk()
            ->assertSee('Add this client to the Party master first. The PO takes its customer from its quotation.', false)
            ->assertDontSee("choose it as the PO's customer", false)->assertSee('id="poMakeSo" disabled', false);
    }

    // ---- 3. Invoice date ----

    public function test_invoice_date_only_admin_changes_it_and_the_page_is_read_only_for_others(): void
    {
        $this->company();
        $party = $this->customer();
        $part = $this->part($party, 'BRK-1', 42.5);
        $line = [['item_id' => $part->id, 'quantity' => '5']];
        $year = FinancialYear::forDate(today());
        $past = $year->starts_on->copy()->max(today()->subDays(5))->min(today());
        $sales = $this->sales();

        // non-Admin: anything sent (malformed, future, earlier year) is ignored; a new invoice is dated today
        foreach (['not a date', today()->addDays(3)->format('d/m/Y'), '01/01/2020', ''] as $sent) {
            $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($party, $line, ['invoice_date' => $sent]))->assertSessionHasNoErrors();
            $this->assertSame(today()->toDateString(), SalesInvoice::latest('id')->first()->invoice_date->toDateString(), $sent);
        }
        $inv = SalesInvoice::latest('id')->first();
        $inv->forceFill(['invoice_date' => $past])->saveQuietly();
        // non-Admin edit keeps the saved date; the field is read-only with the saved date
        $this->actingAs($sales)->put("/sales/invoices/{$inv->id}", $this->invoicePayload($party, $line, ['invoice_date' => today()->format('d/m/Y')]))->assertSessionHasNoErrors();
        $this->assertSame($past->toDateString(), $inv->fresh()->invoice_date->toDateString());
        $html = $this->actingAs($sales)->get("/sales/invoices/{$inv->id}")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#id="invoice_date" name="invoice_date"\s+value="'.preg_quote($past->format('d/m/Y'), '#').'"[^>]*readonly#', $html);

        // Admin edit: a past date in the invoice's year is kept; future and an earlier year are refused
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get("/sales/invoices/{$inv->id}")->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#id="invoice_date"[^>]*readonly#', $html);
        $this->actingAs($admin)->put("/sales/invoices/{$inv->id}", $this->invoicePayload($party, $line, ['invoice_date' => $year->starts_on->format('d/m/Y')]))->assertSessionHasNoErrors();
        $this->assertSame($year->starts_on->toDateString(), $inv->fresh()->invoice_date->toDateString());
        $this->actingAs($admin)->put("/sales/invoices/{$inv->id}", $this->invoicePayload($party, $line, ['invoice_date' => today()->addDay()->format('d/m/Y')]))
            ->assertSessionHasErrorsIn('invoice', ['invoice_date' => 'The invoice date cannot be in the future.']);
        $this->actingAs($admin)->put("/sales/invoices/{$inv->id}", $this->invoicePayload($party, $line, ['invoice_date' => $year->starts_on->copy()->subDay()->format('d/m/Y')]))
            ->assertSessionHasErrorsIn('invoice', 'invoice_date');
        $this->assertSame($year->starts_on->toDateString(), $inv->fresh()->invoice_date->toDateString());
        // Admin: a refused date is shown again so it can be corrected
        $this->actingAs($admin)->from('/sales/invoices/create')->post('/sales/invoices', $this->invoicePayload($party, $line, ['invoice_date' => '31/12/2099']));
        $this->assertStringContainsString('value="31/12/2099"', $this->actingAs($admin)->get('/sales/invoices/create')->getContent());
    }

    // ---- 4. PDF ----

    public function test_pdf_header_on_the_first_page_footer_and_totals_on_the_last_page_only(): void
    {
        // a long company address and a JPG logo (11/11/2026: header on page 1 only, footer on the last page only)
        $this->company(['address' => str_repeat('Survey 12/3, Industrial Estate Road, ', 6)."\nChakan, Pune 410501"]);
        Storage::disk('local')->put('company/logo.jpg', $this->logo('JPG'));
        Company::query()->update(['logo_path' => 'company/logo.jpg']);
        $party = $this->customer();
        $a = $this->part($party, 'BRK-2210', 42.5, 'GST 18%', '8708');
        $b = $this->part($party, 'BRK-2214', 61, 'GST 12%', '8708');
        $t = Transporter::create(['name' => 'Sai Logistics', 'is_active' => true]);
        $lines = [];
        for ($i = 0; $i < 60; $i++) {
            $lines[] = ['item_id' => ($i % 2 ? $b : $a)->id, 'quantity' => (string) (10 + $i), 'rate' => ''];
        }
        $this->actingAs($this->admin())->post('/sales/invoices', $this->invoicePayload($party, $lines, ['pf_charges' => '250', 'transporter_id' => $t->id]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->firstOrFail();

        $logo = Company::current()->pdfLogo();
        $this->assertNotNull($logo);
        $p = $this->pages($inv, $logo);
        $n = $p['count'];
        $this->assertGreaterThanOrEqual(3, $n);
        $all = range(1, $n);
        // header: first page only (company, title, invoice no and date, place of supply)
        $this->assertSame([1], $this->on($p, 'Survey 12/3'));
        $this->assertSame([1, $n], $this->on($p, 'Nursery Components Pvt Ltd'), 'header, and "For …" at the signatory');
        $this->assertSame([1], $p['texts']->filter(fn ($x) => $x['text'] === 'TAX INVOICE')->pluck('page')->unique()->values()->all());
        $this->assertSame([1], $this->on($p, 'Place of supply'));
        $this->assertSame([1], $this->on($p, 'Original for recipient'));
        // the item table head repeats; parties and transport only on page 1
        $this->assertSame($all, $this->on($p, 'Qty'));
        $this->assertSame([1], $this->on($p, 'Bill to'));
        $this->assertSame([1], $this->on($p, 'Sai Logistics'));
        // totals, GST, words and signatory: once, on the last page
        foreach (['Grand total', 'Total GST', 'Amount in words:', 'Authorised signatory', 'Declaration:', 'P&F charges'] as $s) {
            $this->assertSame([$n], $this->on($p, $s), $s);
        }
        $this->assertSame(1, $p['texts']->filter(fn ($x) => $x['text'] === 'Grand total')->count());
        // the footer is not in the page body: the controller draws it on the last page only
        $this->assertSame([], $this->on($p, 'computer generated'));
        $drawn = [];
        $canvas = \Mockery::mock(\Dompdf\Canvas::class);
        $canvas->shouldReceive('get_width')->andReturn(595.28);
        $canvas->shouldReceive('get_height')->andReturn(841.89);
        $canvas->shouldReceive('line');
        $canvas->shouldReceive('text')->andReturnUsing(function ($x, $y, $text) use (&$drawn) {
            $drawn[] = ['y' => $y, 'text' => $text];
        });
        foreach ($all as $page) {
            \App\Http\Controllers\Sales\SalesInvoiceController::drawPdfFooter($canvas, $page, $n, $inv, Company::current(), 'f', 'b');
            if ($page < $n) {
                $this->assertSame([], $drawn, "no footer on page $page");
            }
        }
        $this->assertSame('This is a computer generated tax invoice.', $drawn[0]['text']);
        $this->assertStringStartsWith('Nursery Components Pvt Ltd · Survey 12/3', $drawn[1]['text']);
        // the footer sits in the bottom margin, below the body (A4 842pt less the bottom margin)
        preg_match('/@page\{margin:(\d+)pt 28pt (\d+)pt/', file_get_contents(resource_path('views/pdf/sales-invoice.blade.php')), $mg);
        $bottom = 842 - (float) $mg[2];
        $this->assertGreaterThanOrEqual($bottom, min(array_column($drawn, 'y')) - 4, 'the footer runs into the body');
        $this->assertLessThanOrEqual($bottom + 1, $p['texts']->max(fn ($x) => $x['y'] + $x['h']), 'the body runs into the footer');
        // every item row is drawn once: its HSN cell, 60 times, plus the 2 rows (18% and 12%) of the HSN summary
        $this->assertSame(62, $p['texts']->filter(fn ($x) => $x['text'] === '8708')->count());
        // the real download has the same number of pages
        $pdf = $this->actingAs($this->sales())->get("/sales/invoices/{$inv->id}/pdf")->assertOk()->getContent();
        $this->assertSame($n, preg_match_all('#/Type\s*/Page[^s]#', $pdf));
    }

    public function test_hsn_summary_has_one_row_per_hsn_and_rate(): void
    {
        $this->company();
        $party = $this->customer();
        $a = $this->part($party, 'A-18', 10, 'GST 18%', '8708');
        $b = $this->part($party, 'B-12', 10, 'GST 12%', '8708');
        $this->actingAs($this->admin())->post('/sales/invoices', $this->invoicePayload($party, [['item_id' => $a->id, 'quantity' => '100'], ['item_id' => $b->id, 'quantity' => '100']]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->firstOrFail()->load(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']);
        $this->assertSame(['9.00', '6.00'], $inv->lines->sortBy('line_no')->map(fn ($l) => number_format((float) $l->cgst_rate, 2))->values()->all());

        $html = view('pdf.sales-invoice', ['inv' => $inv, 'company' => Company::current(), 'logo' => null])->render();
        $sum = substr($html, strpos($html, '<table class="sum">'));
        $sum = substr($sum, 0, strpos($sum, '</table>'));
        // two rows for HSN 8708: 1,000 at 9% + 9% and 1,000 at 6% + 6%, adding up to the invoice's GST
        $this->assertSame(2, substr_count($sum, '<td>8708</td>'));
        $this->assertMatchesRegularExpression('#<td>8708</td><td class="r">1,000\.00</td>\s*<td class="r">9</td><td class="r">90\.00</td><td class="r">9</td><td class="r">90\.00</td>\s*<td class="r">180\.00</td>#', $sum);
        $this->assertMatchesRegularExpression('#<td>8708</td><td class="r">1,000\.00</td>\s*<td class="r">6</td><td class="r">60\.00</td><td class="r">6</td><td class="r">60\.00</td>\s*<td class="r">120\.00</td>#', $sum);
        $this->assertEqualsWithDelta(300, (float) $inv->cgst_amount + (float) $inv->sgst_amount, 0.001);
    }

    public function test_pdf_renders_without_optional_data_for_every_supply_and_type(): void
    {
        $this->company();
        $mh = $this->customer('Bare Customer', ['billing_address' => null, 'shipping_address' => null, 'payment_terms' => null]);
        $ka = $this->customer('Kaveri Precision', ['gstin' => '29AABCK1234F1Z5', 'billing_address' => null]);
        $ex = $this->customer('Helix Motors GmbH', ['gstin' => null, 'billing_address' => null, 'currency_id' => Currency::where('code', 'EUR')->value('id')]);
        $pm = $this->part($mh, 'M-1', 10);
        $pk = $this->part($ka, 'K-1', 10);
        $pe = $this->part($ex, 'E-1', 10);
        $admin = $this->admin();
        $make = function (Party $p, Item $i, string $type = 'Sales') use ($admin) {
            $this->actingAs($admin)->post('/sales/invoices', $this->invoicePayload($p, [['item_id' => $i->id, 'quantity' => '3', 'rate' => '10']], ['invoice_type' => $type, 'mode_of_transport' => '']))
                ->assertSessionHasNoErrors();

            return SalesInvoice::latest('id')->firstOrFail();
        };
        $invoices = ['intra' => $make($mh, $pm), 'inter' => $make($ka, $pk), 'export' => $make($ex, $pe), 'credit' => $make($mh, $pm, 'Credit'), 'debit' => $make($mh, $pm, 'Debit')];

        // the company loses its optional details after the invoices were saved; logo: a PNG (needs GD), then a missing file
        Company::query()->update(['gstin' => null, 'pan' => null, 'address' => null, 'phone' => null, 'email' => null]);
        // through the route once each (downloads are throttled to 10 a minute), with a PNG logo that cannot be drawn without GD
        Storage::disk('local')->put('company/logo.png', $this->logo('PNG'));
        Company::query()->update(['logo_path' => 'company/logo.png']);
        foreach ($invoices as $kind => $inv) {
            $res = $this->actingAs($admin)->get("/sales/invoices/{$inv->id}/pdf");
            $res->assertOk();
            $this->assertSame('application/pdf', $res->headers->get('content-type'), $kind);
            $this->assertStringStartsWith('%PDF', $res->getContent());
        }
        // no logo, and a logo file gone missing: the same PDF builder
        foreach ([null, 'company/gone.jpg'] as $logo) {
            Company::query()->update(['logo_path' => $logo]);
            foreach ($invoices as $kind => $inv) {
                $this->assertStringStartsWith('%PDF', SalesInvoiceController::invoicePdf($inv->fresh())->output(), "$kind $logo");
            }
        }
        $titles = ['intra' => 'TAX INVOICE', 'inter' => 'TAX INVOICE', 'export' => 'TAX INVOICE', 'credit' => 'CREDIT NOTE', 'debit' => 'DEBIT NOTE'];
        foreach ($invoices as $kind => $inv) {
            $p = $this->pages($inv->fresh(), Company::current()->pdfLogo());
            $this->assertSame(1, $p['count'], $kind);
            $this->assertSame([1], $this->on($p, $titles[$kind]), $kind);
            $this->assertSame([1], $this->on($p, 'Grand total'), $kind);
        }
        $html = view('pdf.sales-invoice', ['inv' => $invoices['export']->fresh(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']), 'company' => Company::current(), 'logo' => null])->render();
        $this->assertStringContainsString('Not applicable (export)', $html);
        $this->assertStringContainsString('Nil (LUT)', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    // ---- 5. Shared Sales page: links, not dead buttons; the Import dialog still opens ----

    public function test_sales_page_pdf_and_csv_are_links_and_sales_order_import_still_opens_its_dialog(): void
    {
        $this->company();
        $party = $this->customer();
        $part = $this->part($party, 'BRK-1', 10);
        $this->actingAs($this->admin())->post('/sales/invoices', $this->invoicePayload($party, [['item_id' => $part->id, 'quantity' => '1']]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->firstOrFail();
        $this->actingAs($this->sales())->post('/sales/orders', ['party_id' => $party->id, 'po_number' => 'X-1', 'start_date' => today()->format('d/m/Y'), 'end_date' => '',
            'revision_no' => '0', 'revision_date' => '', 'lines' => [['item_id' => $part->id, 'quantity' => '5', 'rate' => '10', 'delivery_date' => today()->format('d/m/Y')]]])->assertSessionHasNoErrors();
        $so = SalesOrder::latest('id')->firstOrFail();

        foreach (["/sales/invoices/{$inv->id}", "/sales/orders/{$so->id}", '/sales/invoices/create', '/sales/orders/create'] as $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-open="invoice"', $html, $url);
            $this->assertStringNotContainsString('data-open="order"', $html, $url);
            $this->assertStringContainsString('data-open="#soImport"', $html, $url);
            $this->assertStringContainsString('<a class="btn iob iob-csv" href="'.route('sales.invoices.export').'"', $html, $url);
            $this->assertStringContainsString('<a class="btn iob iob-csv" href="'.route('sales.orders.export').'"', $html, $url);
        }
        $this->assertStringContainsString('<a class="btn iob iob-pdf" href="'.route('sales.invoices.pdf', $inv).'"', $this->actingAs($this->sales())->get("/sales/invoices/{$inv->id}")->getContent());
    }

    // ---- 6. Merge damage: encoding, leftovers, radio groups ----

    public function test_source_files_are_clean_utf8_without_conflict_markers_or_po_menu_leftovers(): void
    {
        $bad = [];
        $leftovers = [];
        foreach (['app', 'config', 'routes', 'resources', 'public/js', 'public/css', 'database', 'tests'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (! preg_match('/\.(php|js|css)$/', $f->getFilename())) {
                    continue;
                }
                $src = file_get_contents($f->getPathname());
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(base_path()) + 1));
                if (! mb_check_encoding($src, 'UTF-8') || str_starts_with($src, "\xEF\xBB\xBF") || str_contains($src, "\0") || preg_match('/^(<<<<<<<|>>>>>>>) /m', $src)) {
                    $bad[] = $rel;
                }
                if ($rel !== 'tests/Feature/CombinedChangesReviewTest.php' && preg_match("/route\\(['\"]po\\.|PoContext|SeparatePo\\b|Controllers\\\\Po\\\\|Requests\\\\Po\\\\|view\\(['\"]po\\.|@include\\(['\"]po\\./", $src)) {
                    $leftovers[] = $rel;
                }
            }
        }
        $this->assertSame([], $bad, 'not clean UTF-8');
        $this->assertSame([], $leftovers, 'Separate PO menu leftovers');

        // no segmented radio group is left in the views or scripts
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $f) {
            $src = file_get_contents($f->getPathname());
            $this->assertDoesNotMatchRegularExpression('/type="radio"|role="radiogroup"|class="segc/', $src, $f->getFilename());
        }
        foreach (['sales.js', 'grn.js', 'preview.js'] as $js) {
            $this->assertStringNotContainsString('[name=invoice_type]:checked', file_get_contents(public_path('js/'.$js)), $js);
        }
    }

    // ---- 7. Radio groups to drop-downs: the one approval rule not covered elsewhere ----

    public function test_purchase_order_approval_rule_drop_down_saves(): void
    {
        $admin = $this->admin();
        \App\Models\ApprovalRule::for('purchase_order')->update(['is_required' => true]);
        $html = $this->actingAs($admin)->get('/admin/approval')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<label for="po_is_required">Approval required<span class="req">\*</span></label>\s*<select class="inp" id="po_is_required" name="is_required" aria-required="true">\s*<option value="1"\s+selected>Yes</option>#', $html);
        $this->actingAs($admin)->put('/admin/approval/purchase-order', ['_po' => '1', 'is_required' => '0', 'approve_above_amount' => ''])->assertSessionHasNoErrors();
        $this->assertFalse((bool) \App\Models\ApprovalRule::for('purchase_order')->is_required);
        $this->assertMatchesRegularExpression('#id="po_is_required" name="is_required" aria-required="true">\s*<option value="1"\s*>Yes</option>\s*<option value="0"\s+selected>No</option>#',
            $this->actingAs($admin)->get('/admin/approval')->getContent());
    }
}

