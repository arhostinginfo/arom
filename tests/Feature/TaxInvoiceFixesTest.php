<?php

namespace Tests\Feature;

use App\Http\Controllers\Sales\SalesInvoiceController;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\TaxStructure;
use App\Models\Transporter;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tax invoice fixes (02/11/2026), from the client's sheet for sales/invoices/create:
 * 1. invoice date filled in automatically, only an Admin can change it;
 * 2. PO no lists the party's approved sales orders (page after an error, and the JSON the script uses), with a clear
 *    empty state;
 * 3. Generate PDF works (JPG logo drawn, PNG logo skipped without GD, a hint before the invoice is saved);
 * 4. standard GST layout: fixed header and footer on every page, totals once on the last page, 60 lines paginate;
 * plus: Invoice type is a dropdown instead of radio buttons.
 * Test database only.
 */
class TaxInvoiceFixesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** A real 300 x 120 baseline JPEG (from CompanyLogoTest); dompdf draws JPG without GD. */
    private const JPG = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAeAEsAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A4GiiirPmgooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigC/pGkXet34tLQICFMkssjbY4Yx953b+FR3P4ckgVf/4RDVf7e/snZFu8vz/tXmf6P5HXz/M6eXjnP4YzxWj4YvdNtvBfiqDUH3ef9k2W6XCxSy7ZGJ2EhunBPynj06109hruhp4a0fTorpLUXmm6lYhbi4WQ20ksiFDMyqNqnaedvAI7AmkdEKUGld+f42scfp3gq61i4to9M1TTbtZ5mgMiPIoicRtIAysgbDKjYIBGVIyKmt/hx4jlt5pri2SyWK6itcXJKl3eRUBXAOVBYfN0I6ZxWl4GsLXQfFel6jqesabC3nSoIku45AE8l8uzqxVBuKqATkkn050vCfiuO902ZNTubK1a1bSbWAFwheKG4JLHceSASSRwB6UFwp02lzaPX8jh9V0KLSZLqGTWdPmuraQxPbwrPu3BtpALRheOe/bjNY9df4zN5d6hqdx5uiyWP2ySWJ7WSz851LkKT5Z8xuDk5z6npmuQoOeokpWRpavotzov2D7S8T/bbOO8j8sk4R84ByBzx7/WrOn+Gp7ywhvrm+stOtribybeS9dlEzfxFdqn5V4BY4UE4z1rd8U2f9tQ+HpbC902RYNFtoJQ+owRski7iVKs4ORkdqhIg8R+DfD+nW19ZW1zpk08dwt7cLANsr7lkUsfmUYIOPmB7HINBp7NKT/Dz2MtfCWpRfbm1IxaXDZSCGWa83BTIcYRdgYuSDu+UEbec4IyR+Fbma5mEV/p72UFutzNqCzEwRow+UNxuDk/KEK7ie2Oa6/xPr2m+MLHWbPTbqKKb+1I72L7ZIsCzxrAsJ2s5AzkbsMQdp9cgMuJfD8el6r4f0yWytLvUNNs5HxclrYXMbtJJGJWZgCQQB823IwSDQW6UL6bfnucPq2i3OkfZpJHintbqPzLe6gJaKUdwCQCCp4KkAg9R0rNrqfE93bR+HPDuhx3EVxdadHO9w8DiSJTK4cIHHDEDrjIzwCea5agwqJKVkFFFFMgKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooA/9k=';

    /** A real PNG (from CompanyLogoTest): dompdf needs GD for it. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAMgAAADICAYAAACtWK6eAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAE70lEQVR4nO3dTW7cRhSF0ZssxOvI/kdZhzfiDAJCgoR+6mYXWX/nLMAu0vhwixrICQAAAAAAAADw4a/eB9jNv79+/Xn3z/jn92//bjfxoi/QIoKzxNOWl/mmnjE8SzTneXEvmiGInwjmeV7UE1aI4hGx1LycB1aO4hGxfOeFfLJjFI+I5X9eQoRR2T2UrR9eGM/bNZQtH1oY5+0WylYPK4x2dglli4cUxnVWD2XphxPGfVYNZcmHSsTRw4qRLPdAwuhvpVCWeZBEHCNZJZIlHkIY45o9lKkPn4hjBjNHMu3BhTGfGUOZ7sCJOGY2WyRTHTYRxwpmimSagybiWMkskUxxSGGsa/RQhj5cIo4djBzJsAdLxLGTUSMZ8lCJOHY0YiTDHSgRx85Gi2SowyTiYKxIhjlIIg4+jBLJEIdIxMF3I0TS/QCJOHisdyTdAxEHP+kZyd+9/mKYQdcFsR48q9eKdAtEHLyqRyRdAhEHZ90dye2BiIN33RmJj3Qo3Log1oNW7lqR2wIRB63dEYkrFhRuWRDrwVWuXpHLAxEHV7syElcsKFy6INaDu1y1IpcFIg7udkUkrlhQuGRBrAe9tF4RCwKF5gtiPeit5YpYECg0XRDrwSharYgFgUKzBbEejKbFilgQKDRZEOvBqN5dEQsChbcXxHowundWxIJAQSBQeOuK5XrFLM5esywIFAQChdNXLNcrZnPmmmVBoCAQKJy6YrleMatXr1kWBAoCgYJAoPDyN4jvD2b3yneIBYGCQKAgECgIBAovfaT7QGcVz36oWxAoCAQKAoGCQKAgECgIBApP/5jXj3hZzTM/6rUgUBAIFAQCBYFAQSBQEAgUBAIFgUBBIFAQCBQEAgWBQEEgUBAIFAQCBYFAwe/FYkt+LxY0IBAoCAQKAoGCQKAgECj4PwrZjv+jEBoRCBQEAgWBQOHlj/TEhzrzeuUDPbEgUBIIFAQChVPfIInvEObz6vdHYkGgJBAonL5iJa5ZzOPM9SqxIFASCBTeumIlrlmM7+z1KrEgUBIIFN6+YiWuWYzrnetVYkGg1GRBEivCeN5dj8SCQKnZgiRWhHG0WI/EgkCp6YIkVoT+Wq1HYkGg1HxBEitCPy3XI7EgULpkQRIrwv1ar0diQaB02YIkVoT7XLEeycWBJCLhelfFkbhiQenyBUmsCNe5cj2SmwJJREJ7V8eRuGJB6bYFSawI7dyxHsnNgSQi4X13xZG4YkHp9gVJrAjn3bkeSadAEpHwurvjSDoGkoiE5/WII+kcSCISftYrjsRHOpS6L0hiRXis53okgwSSiITveseRDBRIIhI+jBBHMlggiUgYJ45kwEASkexspDiSQQNJRLKj0eJIBg4kEclORowjGTyQRCQ7GDWOZIJAEpGsbOQ4kkkCOQhlHaOHcZjikJ+JZH6zxJFMGEgikpnNFEcyaSCJSGY0WxzJxIEchDK+GcM4THvwz0QyrpnjSBYJ5CCUccwexmGJh/hMJP2tEkeyYCAHodxvpTAOyz3QZyK5z4pxJIsHchDKdVYN47D0w30llHZWD+OwxUN+JZTzdgnjsNXDfiWU5+0WxmHLh/5KKI/tGsZh64f/Sigfdg/j4CU8sGMsovjOC3nCyrGIoublvGiFWETxPC/qTTMEI4jzvLgL9IxGDG15mTdrEY8IAAAAAAAAAC70H7NhrQ+rKA/cAAAAAElFTkSuQmCC';

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
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822312345', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function d(string $date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
    }

    /** Company in Maharashtra, customers in Maharashtra / Karnataka / Germany (EUR), parts for each. */
    private function setUpSales(): array
    {
        Company::create(['name' => 'Nursery Components Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'pan' => 'AAACN1234F',
            'address' => "Plot 21, MIDC Chakan\nPune 410501", 'phone' => '020 2345 6789', 'email' => 'accounts@nursery.example']);
        $mh = Party::create(['name' => 'Apex Auto Components', 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => "Gat 12, Chakan\nPune", 'shipping_address' => 'Gate 2, Chakan, Pune', 'status' => 'Active', 'currency_id' => Currency::defaultId(), 'payment_terms' => '30 days']);
        $ka = Party::create(['name' => 'Kaveri Precision', 'gstin' => '29AABCK1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Peenya, Bengaluru', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
        $ex = Party::create(['name' => 'Helix Motors GmbH', 'type' => 'Customer', 'billing_address' => 'Stuttgart', 'status' => 'Active', 'currency_id' => Currency::where('code', 'EUR')->first()->id]);
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $gst12 = TaxStructure::where('name', 'GST 12%')->first();
        $hsn = HsnSacCode::create(['code' => '8708', 'type' => 'HSN', 'description' => 'Parts of motor vehicles', 'tax_structure_id' => $gst18->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $mk = fn ($party, $no, $price, $tax) => Item::create(['party_id' => $party->id, 'description' => 'Part '.$no, 'item_no' => $no, 'type' => 'Sales', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => $nos->id, 'tax_structure_id' => $tax->id, 'price' => $price, 'status' => 'Active']);

        return ['mh' => $mh, 'ka' => $ka, 'ex' => $ex,
            'bracket' => $mk($mh, 'BRK-2210', 42.50, $gst18), 'plate' => $mk($mh, 'BRK-2214', 61, $gst12),
            'pin' => $mk($ka, 'HNG-0907', 8.20, $gst18), 'housing' => $mk($ex, 'HSG-5001', 4.80, $gst18)];
    }

    private function payload(Party $party, array $lines, array $over = []): array
    {
        return array_merge(['_form' => 'invoice', 'invoice_type' => 'Sales', 'invoice_date' => today()->format('d/m/Y'), 'party_id' => $party->id, 'sales_order_id' => '',
            'shipping_address' => 'Gate 2, Chakan, Pune', 'mode_of_transport' => 'Road', 'transporter_id' => '', 'vehicle_no' => 'MH 14 AB 1234',
            'distance_km' => '42', 'pf_charges' => '', 'lines' => $lines], $over);
    }

    private function order(array $d, string $status = 'approve', string $po = '4500123'): SalesOrder
    {
        $this->actingAs($this->sales())->post('/sales/orders', ['party_id' => $d['mh']->id, 'po_number' => $po, 'start_date' => today()->format('d/m/Y'), 'end_date' => '',
            'revision_no' => '0', 'revision_date' => '', 'lines' => [['item_id' => $d['bracket']->id, 'quantity' => '1200', 'rate' => '42.50', 'delivery_date' => today()->format('d/m/Y')]]])
            ->assertSessionHasNoErrors();
        $so = SalesOrder::latest('id')->first();
        if ($status === 'approve') {
            $this->actingAs($this->sales())->post("/sales/orders/{$so->id}/submit")->assertSessionHasNoErrors();
            $this->actingAs($this->admin())->post("/sales/orders/{$so->id}/approve")->assertSessionHasNoErrors();
        }

        return $so->fresh();
    }

    /** An invoice of $n lines (alternating parts) for the Maharashtra customer, saved by Admin. */
    private function bigInvoice(array $d, int $n, array $over = []): SalesInvoice
    {
        $lines = [];
        for ($i = 0; $i < $n; $i++) {
            $lines[] = ['item_id' => ($i % 2 ? $d['plate'] : $d['bracket'])->id, 'quantity' => (string) (10 + $i), 'rate' => ''];
        }
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['mh'], $lines, $over))->assertSessionHasNoErrors();

        return SalesInvoice::latest('id')->first();
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    // ---- 1. Invoice date ----

    public function test_invoice_date_is_today_and_only_admin_can_change_it(): void
    {
        $d = $this->setUpSales();
        $year = FinancialYear::forDate(today());
        $past = $year->starts_on->copy()->max(today()->subDays(3))->min(today());
        $line = [['item_id' => $d['bracket']->id, 'quantity' => '5']];

        // Sales user: read-only, today, hint; the server dates a new invoice today whatever is sent
        $html = $this->actingAs($this->sales())->get('/sales/invoices/create')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<input class="inp num\s+ro[^"]*" id="invoice_date" name="invoice_date"\s+value="'.preg_quote(today()->format('d/m/Y'), '#').'"[^>]*readonly#', $html);
        $this->assertStringContainsString("Today's date, set automatically. Only an Admin can change it.", $html);
        $this->actingAs($this->sales())->post('/sales/invoices', $this->payload($d['mh'], $line, ['invoice_date' => '01/01/2020']))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->first();
        $this->assertSame(today()->toDateString(), $inv->invoice_date->toDateString());

        // Sales user editing: the saved date stays
        $inv->forceFill(['invoice_date' => $past])->saveQuietly();
        $this->actingAs($this->sales())->put("/sales/invoices/{$inv->id}", $this->payload($d['mh'], $line, ['invoice_date' => today()->format('d/m/Y')]))->assertSessionHasNoErrors();
        $this->assertSame($past->toDateString(), $inv->fresh()->invoice_date->toDateString());
        $this->actingAs($this->sales())->get("/sales/invoices/{$inv->id}")->assertOk()->assertSee('As saved. Only an Admin can change it.');

        // Admin: editable, a past date in the year is kept; future and outside the year are refused (S-6)
        $html = $this->actingAs($this->admin())->get('/sales/invoices/create')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#id="invoice_date"[^>]*readonly#', $html);
        $this->assertStringContainsString('As Admin you can enter a past date in '.$year->label().', not after today.', $html);
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['mh'], $line, ['invoice_date' => $past->format('d/m/Y')]))->assertSessionHasNoErrors();
        $this->assertSame($past->toDateString(), SalesInvoice::latest('id')->first()->invoice_date->toDateString());
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['mh'], $line, ['invoice_date' => today()->addDay()->format('d/m/Y')]))->assertSessionHasErrorsIn('invoice', 'invoice_date');
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['mh'], $line, ['invoice_date' => $year->starts_on->copy()->subDay()->format('d/m/Y')]))->assertSessionHasErrorsIn('invoice', 'invoice_date');
        $this->assertSame(2, SalesInvoice::count());
    }

    // ---- 2. PO no drop-down ----

    public function test_po_dropdown_lists_the_partys_approved_orders(): void
    {
        $d = $this->setUpSales();
        $so = $this->order($d);
        $draft = $this->order($d, 'draft', 'DRAFT-1');
        $label = 'PO 4500123 · '.$so->so_no;

        // the JSON the script loads when the party is chosen
        $json = $this->actingAs($this->sales())->getJson("/sales/parties/{$d['mh']->id}/info")->assertOk()->json();
        $this->assertSame([['id' => $so->id, 'label' => $label]], $json['orders']);
        $this->assertSame([], $this->actingAs($this->sales())->getJson("/sales/parties/{$d['ka']->id}/info")->assertOk()->json('orders'));

        // server-rendered after a failed save: the party's approved order, not its draft
        $this->actingAs($this->sales())->from('/sales/invoices/create')->post('/sales/invoices', $this->payload($d['mh'], []))->assertSessionHasErrorsIn('invoice', 'lines');
        $html = $this->actingAs($this->sales())->get('/sales/invoices/create')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<option value="'.$so->id.'"\s*>'.preg_quote(e($label), '#').'</option>#', $html);
        $select = substr($html, strpos($html, 'id="inv_sales_order_id"'));
        $this->assertStringNotContainsString('DRAFT-1', substr($select, 0, strpos($select, '</select>')));
        $this->assertMatchesRegularExpression('#id="invPoNone"\s+hidden#', $html);

        // from "Make tax invoice"
        $this->actingAs($this->sales())->get('/sales/invoices/create?order='.$so->id)->assertOk()->assertSee($label)->assertViewHas('poOptions', fn ($o) => $o->has($so->id) && ! $o->has($draft->id));

        // a party without an approved order says so, in the select and the hint
        $this->actingAs($this->sales())->from('/sales/invoices/create')->post('/sales/invoices', $this->payload($d['ka'], []))->assertSessionHasErrorsIn('invoice', 'lines');
        $html = $this->actingAs($this->sales())->get('/sales/invoices/create')->assertOk()->getContent();
        $this->assertStringContainsString('<option value="">No approved sales order for this party.</option>', $html);
        $this->assertDoesNotMatchRegularExpression('#id="invPoNone"\s+hidden#', $html);

        // and the order can be billed
        $this->actingAs($this->sales())->post('/sales/invoices', $this->payload($d['mh'], [['sales_order_line_id' => $so->lines()->first()->id, 'quantity' => '10']], ['sales_order_id' => $so->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($so->id, SalesInvoice::latest('id')->first()->sales_order_id);
    }

    // ---- Invoice type drop-down ----

    public function test_invoice_type_is_a_dropdown(): void
    {
        $d = $this->setUpSales();
        $html = $this->actingAs($this->sales())->get('/sales/invoices/create')->assertOk()->getContent();
        $this->assertStringContainsString('<label for="invoice_type">Invoice type<span class="req">*</span></label>', $html);
        $this->assertMatchesRegularExpression('#<select class="inp\s*" id="invoice_type" name="invoice_type" aria-required="true">\s*<option value="Sales"\s+selected>Sales</option><option value="Credit"\s*>Credit</option><option value="Debit"\s*>Debit</option>\s*</select>#', $html);
        $this->assertStringNotContainsString('name="invoice_type" value=', $html);
        $this->assertStringContainsString('Sales = tax invoice (INV), Credit = credit note (CN), Debit = debit note (DN).', $html);

        // after a failed save the typed type stays chosen, with the error marked
        $this->actingAs($this->sales())->from('/sales/invoices/create')->post('/sales/invoices', $this->payload($d['mh'], [], ['invoice_type' => 'Credit']));
        $html = $this->actingAs($this->sales())->get('/sales/invoices/create')->getContent();
        $this->assertMatchesRegularExpression('#<option value="Credit"\s+selected>Credit</option>#', $html);
        $this->actingAs($this->sales())->from('/sales/invoices/create')->post('/sales/invoices', $this->payload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '1']], ['invoice_type' => 'Other']))
            ->assertSessionHasErrorsIn('invoice', 'invoice_type');
        $this->assertMatchesRegularExpression('#<select class="inp\s+err\s*" id="invoice_type"#', $this->actingAs($this->sales())->get('/sales/invoices/create')->getContent());

        // a saved invoice shows its type as text
        $this->actingAs($this->sales())->post('/sales/invoices', $this->payload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '1']]))->assertSessionHasNoErrors();
        $html = $this->actingAs($this->sales())->get('/sales/invoices/'.SalesInvoice::latest('id')->value('id'))->getContent();
        $this->assertStringNotContainsString('id="invoice_type"', $html);
        $this->assertStringContainsString('<div class="kvline"><b>Sales</b> · Tax invoice</div>', $html);
        // the script reads the select
        $js = file_get_contents(public_path('js/sales.js'));
        $this->assertStringContainsString("select[name=invoice_type]", $js);
        $this->assertStringNotContainsString("input[name=invoice_type]", $js);
    }

    // ---- 3. Generate PDF ----

    public function test_pdf_generates_with_a_jpg_logo_and_without_a_png_one_when_gd_is_off(): void
    {
        $d = $this->setUpSales();
        // before saving: a hint instead of the button; without a GSTIN every user is told why nothing saves
        $html = $this->actingAs($this->sales())->get('/sales/invoices/create')->getContent();
        $this->assertStringContainsString('Save the invoice to generate its PDF.', $html);
        $inv = $this->bigInvoice($d, 3);
        $this->actingAs($this->sales())->get("/sales/invoices/{$inv->id}")->assertSee(route('sales.invoices.pdf', $inv))->assertDontSee('Save the invoice to generate its PDF.')
            // the PDF and CSV controls are download links, not buttons that open nothing (the cause of issue 3)
            ->assertDontSee('data-open="invoice"', false)->assertSee('<a class="btn iob iob-pdf" href="'.route('sales.invoices.pdf', $inv).'"', false)->assertSee('<a class="btn iob iob-csv" href="'.route('sales.invoices.export').'"', false);

        $company = Company::current();
        foreach (['logo.jpg' => self::JPG, 'logo.png' => self::PNG] as $file => $b64) {
            Storage::disk('local')->put('company/'.$file, base64_decode($b64));
            $company->forceFill(['logo_path' => 'company/'.$file])->save();
            $res = $this->actingAs($this->sales())->get("/sales/invoices/{$inv->id}/pdf");
            $res->assertOk();
            $this->assertSame('application/pdf', $res->headers->get('content-type'));
            $this->assertStringStartsWith('%PDF', $res->getContent());
            $html = view('pdf.sales-invoice', ['inv' => $inv->fresh(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']), 'company' => $company->fresh(), 'logo' => $company->fresh()->pdfLogo()])->render();
            $drawn = $file === 'logo.jpg' || function_exists('imagecreatefrompng');
            $this->assertSame($drawn, str_contains($html, '<img src="data:image/'), $file);
        }
        // a logo file gone missing: still a PDF
        Storage::disk('local')->delete('company/logo.jpg');
        $company->forceFill(['logo_path' => 'company/logo.jpg'])->save();
        $this->actingAs($this->sales())->get("/sales/invoices/{$inv->id}/pdf")->assertOk();

        Company::query()->update(['gstin' => null]);
        $this->actingAs($this->sales())->get('/sales/invoices/create')->assertSee("Your company's GSTIN is not entered yet, so invoices cannot be saved.", false);
    }

    // ---- 4. Standard layout, pagination ----

    public function test_pdf_layout_header_footer_and_totals_once_on_the_last_page(): void
    {
        $d = $this->setUpSales();
        $t = Transporter::create(['name' => 'Sai Logistics', 'is_active' => true]);
        $so = $this->order($d);
        $inv = $this->bigInvoice($d, 60, ['pf_charges' => '250', 'transporter_id' => $t->id]);
        $inv->load(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']);
        $html = view('pdf.sales-invoice', ['inv' => $inv, 'company' => Company::current(), 'logo' => null])->render();

        // (11/11/2026) the header is in the flow, first in the body, so it prints on page 1 only; the footer is not in the
        // view (a fixed element repeats on every page): the controller draws it on the last page only
        $this->assertDoesNotMatchRegularExpression('/position:fixed/', $html);
        $this->assertStringNotContainsString('pdf-footer"', $html);
        $this->assertStringNotContainsString('computer generated', $html);
        $this->assertMatchesRegularExpression('/\.items thead\{display:table-header-group\}/', $html);
        $this->assertMatchesRegularExpression('/\.items tr\{page-break-inside:avoid\}/', $html);
        $header = strpos($html, '<div id="pdf-header">');
        $items = strpos($html, '<table class="items" id="pdf-items">');
        $this->assertTrue($header < strpos($html, 'Bill to (recipient)') && $header < $items);
        foreach (['TAX INVOICE', 'Original for recipient', 'Nursery Components Pvt Ltd', 'GSTIN</b> 27AAACN1234F1Z5', 'Maharashtra (27)', 'PAN</b> AAACN1234F',
            $inv->invoice_no, today()->format('d/m/Y'), 'Place of supply', 'Reverse charge</b><br>No'] as $s) {
            $this->assertStringContainsString($s, substr($html, $header, $items - $header), $s);
        }
        // first page: parties and transport
        foreach (['Bill to (recipient)', 'Ship to (consignee)', 'Apex Auto Components', 'Gate 2, Chakan, Pune', '27AABCA1234F1Z5', 'Sai Logistics', 'MH 14 AB 1234', '42 km'] as $s) {
            $this->assertStringContainsString($s, $html, $s);
        }
        // the item table: 60 rows, CGST / SGST columns
        $this->assertSame(60, substr_count($html, '<tr class="ln">'));
        $this->assertStringContainsString('Description of goods', $html);
        $this->assertStringContainsString('CGST %', $html);
        // totals once, after the last item
        $this->assertSame(1, substr_count($html, 'id="pdf-totals"'));
        $this->assertSame(1, substr_count($html, 'Grand total'));
        $this->assertSame(1, substr_count($html, 'Amount in words:'));
        $this->assertSame(1, substr_count($html, 'Authorised signatory'));
        $totals = strpos($html, 'id="pdf-totals"');
        $this->assertGreaterThan(strrpos($html, '<tr class="ln">'), $totals);
        $tail = substr($html, $totals);
        foreach (['Taxable value', 'P&amp;F charges', 'CGST', 'SGST', 'Total GST', \App\Support\Money::indian($inv->grand_total, 2), 'Rupees', 'HSN/SAC', '8708',
            'Declaration:', 'For <b>Nursery Components Pvt Ltd</b>', 'Payment terms:'] as $s) {
            $this->assertStringContainsString($s, $tail, $s);
        }

        // the PDF itself: several pages, small memory
        $before = memory_get_peak_usage(true);
        $res = $this->actingAs($this->sales())->get("/sales/invoices/{$inv->id}/pdf");
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertGreaterThanOrEqual(2, $this->pageCount($res->getContent()));
        $this->assertLessThan(160 * 1024 * 1024, memory_get_peak_usage(true) - $before);

        // against a PO: its number and date in the header
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['mh'], [['sales_order_line_id' => $so->lines()->first()->id, 'quantity' => '10']], ['sales_order_id' => $so->id]))->assertSessionHasNoErrors();
        $po = SalesInvoice::latest('id')->first()->load(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']);
        $html = view('pdf.sales-invoice', ['inv' => $po, 'company' => Company::current(), 'logo' => null])->render();
        $this->assertStringContainsString('PO 4500123', 'PO '.$po->salesOrder->po_number);
        $this->assertStringContainsString('4500123 · '.$so->so_no, $html);
    }

    public function test_pdf_igst_export_and_credit_note_variants(): void
    {
        $d = $this->setUpSales();
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['ka'], [['item_id' => $d['pin']->id, 'quantity' => '1000']], ['shipping_address' => 'Peenya']))->assertSessionHasNoErrors();
        $ka = SalesInvoice::latest('id')->first();
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['ex'], [['item_id' => $d['housing']->id, 'quantity' => '100', 'rate' => '4.80']], ['shipping_address' => 'Stuttgart']))->assertSessionHasNoErrors();
        $ex = SalesInvoice::latest('id')->first();
        $this->bigInvoice($d, 2);
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '1']], ['invoice_type' => 'Credit']))->assertSessionHasNoErrors();
        $cn = SalesInvoice::latest('id')->first();
        $render = fn ($i) => view('pdf.sales-invoice', ['inv' => $i->load(['lines.item.uom', 'party', 'salesOrder', 'transporter', 'currency', 'unit']), 'company' => Company::current(), 'logo' => null])->render();

        $h = $render($ka);
        $this->assertStringContainsString('IGST %', $h);
        $this->assertStringNotContainsString('CGST %', $h);
        $this->assertStringContainsString('Karnataka (29)', $h);
        $h = $render($ex);
        $this->assertStringContainsString('Supply meant for export under LUT without payment of integrated tax.', $h);
        $this->assertStringContainsString('Outside India (export)', $h);
        $this->assertStringContainsString('EUR 480.00', $h);
        $this->assertStringContainsString('EUR Four Hundred Eighty Only', $h);
        $h = $render($cn);
        $this->assertStringContainsString('CREDIT NOTE', $h);
        foreach ([$ka, $ex, $cn] as $i) {
            $this->actingAs($this->admin())->get("/sales/invoices/{$i->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    /** Writes docs/sales/sample-tax-invoice.pdf (fake data, 40 lines) when TAX_INVOICE_SAMPLE_PDF=1; skipped otherwise. */
    public function test_write_sample_pdf(): void
    {
        if (! getenv('TAX_INVOICE_SAMPLE_PDF')) {
            $this->markTestSkipped('Set TAX_INVOICE_SAMPLE_PDF=1 to write the sample PDF.');
        }
        $d = $this->setUpSales();
        $t = Transporter::create(['name' => 'Sai Logistics', 'is_active' => true]);
        Storage::disk('local')->put('company/logo.jpg', base64_decode(self::JPG));
        Company::query()->update(['logo_path' => 'company/logo.jpg']);
        $so = $this->order($d);
        $inv = $this->bigInvoice($d, 1);
        $lines = [];
        for ($i = 0; $i < 40; $i++) {
            $lines[] = ['item_id' => ($i % 2 ? $d['plate'] : $d['bracket'])->id, 'quantity' => (string) (25 * ($i + 1)), 'rate' => ''];
        }
        $this->actingAs($this->admin())->post('/sales/invoices', $this->payload($d['mh'], $lines, ['pf_charges' => '250', 'transporter_id' => $t->id]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->first();
        $bytes = SalesInvoiceController::invoicePdf($inv)->output();
        file_put_contents(base_path('docs/sales/sample-tax-invoice.pdf'), $bytes);
        $this->assertGreaterThanOrEqual(2, $this->pageCount($bytes));
    }
}
