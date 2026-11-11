<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\Currency;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\TaxStructure;
use App\Models\Transporter;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\PageSetupService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Sales module (workbook SALES tab) and the masters it needs, 15/10/2026. Test database only. */
class SalesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function user(string $email, string $role): User
    {
        if ($u = User::where('email', $email)->first()) {
            return $u;
        }
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'mobile' => '98223'.random_int(10000, 99999), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function sales(): User
    {
        return $this->user('priya@company.example', 'Sales');
    }

    private function today(): string
    {
        return today()->format('d/m/Y');
    }

    /** Company in Maharashtra (27), a customer in Maharashtra, one in Karnataka (29), an export customer in EUR, and their parts. */
    private function setUpSales(): array
    {
        Company::create(['name' => 'Nursery Components Pvt Ltd', 'gstin' => '27AAACN1234F1Z5', 'address' => 'Plot 21, MIDC Chakan, Pune']);
        $eur = Currency::where('code', 'EUR')->first();
        $mh = Party::create(['name' => 'Apex Auto Components', 'gstin' => '27AABCA1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Chakan, Pune', 'shipping_address' => 'Gate 2, Chakan, Pune', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
        $ka = Party::create(['name' => 'Kaveri Precision', 'gstin' => '29AABCK1234F1Z5', 'type' => 'Customer', 'billing_address' => 'Peenya, Bengaluru', 'status' => 'Active', 'currency_id' => Currency::defaultId()]);
        $ex = Party::create(['name' => 'Helix Motors GmbH', 'type' => 'Customer', 'billing_address' => 'Stuttgart', 'status' => 'Active', 'currency_id' => $eur->id]);
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $gst12 = TaxStructure::where('name', 'GST 12%')->first();
        $hsn = HsnSacCode::create(['code' => '8708', 'type' => 'HSN', 'description' => 'Parts of motor vehicles', 'tax_structure_id' => $gst18->id, 'is_active' => true]);
        $nos = Uom::where('code', 'NOS')->first();
        $mk = fn ($party, $no, $price, $tax) => Item::create(['party_id' => $party->id, 'description' => 'Part '.$no, 'item_no' => $no, 'type' => 'Sales', 'hsn_sac_code_id' => $hsn->id,
            'uom_id' => $nos->id, 'tax_structure_id' => $tax->id, 'price' => $price, 'status' => 'Active', 'min_stock' => 100]);

        return [
            'mh' => $mh, 'ka' => $ka, 'ex' => $ex,
            'bracket' => $mk($mh, 'BRK-2210', 42.50, $gst18),
            'plate' => $mk($mh, 'BRK-2214', 61, $gst12),
            'pin' => $mk($ka, 'HNG-0907', 8.20, $gst18),
            'housing' => $mk($ex, 'HSG-5001', 4.80, $gst18),
        ];
    }

    private function orderPayload(Party $party, array $lines, array $over = []): array
    {
        return array_merge(['party_id' => $party->id, 'po_number' => '4500123', 'start_date' => $this->today(), 'end_date' => '', 'revision_no' => '0', 'revision_date' => '',
            'lines' => $lines], $over);
    }

    private function approvedOrder(array $d, array $lines = null): SalesOrder
    {
        $lines ??= [['item_id' => $d['bracket']->id, 'quantity' => '1200', 'rate' => '42.50', 'delivery_date' => $this->today()],
            ['item_id' => $d['plate']->id, 'quantity' => '600', 'rate' => '', 'delivery_date' => $this->today()]];
        $this->actingAs($this->sales())->post('/sales/orders', $this->orderPayload($d['mh'], $lines))->assertSessionHasNoErrors();
        $so = SalesOrder::latest('id')->first();
        $this->actingAs($this->sales())->post("/sales/orders/{$so->id}/submit")->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post("/sales/orders/{$so->id}/approve")->assertSessionHasNoErrors();

        return $so->fresh('lines');
    }

    private function invoicePayload(Party $party, array $lines, array $over = []): array
    {
        return array_merge(['invoice_type' => 'Sales', 'invoice_date' => $this->today(), 'party_id' => $party->id, 'sales_order_id' => '', 'shipping_address' => 'Gate 2, Chakan, Pune',
            'mode_of_transport' => 'Road', 'transporter_id' => '', 'vehicle_no' => 'mh 14 ab 1234', 'distance_km' => '42', 'pf_charges' => '', 'lines' => $lines], $over);
    }

    // ---- Masters ----

    public function test_tax_hsn_uom_category_transporter_masters(): void
    {
        $admin = $this->admin();
        $this->assertSame(5, TaxStructure::count());
        $this->assertTrue(Uom::where('code', 'NOS')->exists());
        $this->actingAs($admin)->post('/admin/masters/lists/tax-structures', ['name' => 'GST 3%', 'cgst_rate' => '1.5', 'sgst_rate' => '1', 'igst_rate' => '3', 'is_active' => '1'])->assertSessionHasErrors('igst_rate');
        $this->actingAs($admin)->post('/admin/masters/lists/tax-structures', ['name' => 'GST 3%', 'cgst_rate' => '1.5', 'sgst_rate' => '1.5', 'igst_rate' => '3', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/masters/lists/tax-structures', ['name' => 'GST 3%', 'cgst_rate' => '1.5', 'sgst_rate' => '1.5', 'igst_rate' => '3'])->assertSessionHasErrors('name');
        $tax = TaxStructure::where('name', 'GST 3%')->first();
        $this->actingAs($admin)->post('/admin/masters/lists/hsn-sac', ['code' => ' 7113 ', 'type' => 'HSN', 'description' => 'Jewellery', 'tax_structure_id' => $tax->id, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/masters/lists/hsn-sac', ['code' => '7113', 'type' => 'HSN', 'is_active' => '1'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/admin/masters/lists/hsn-sac', ['code' => '7113', 'type' => 'SAC', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/masters/lists/item-categories', ['name' => 'Pressings', 'type' => 'Sales', 'item_no_prefix' => 'prs', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('PRS', ItemCategory::first()->item_no_prefix);
        $this->actingAs($admin)->post('/admin/masters/lists/transporters', ['name' => 'Sai Logistics', 'gstin' => 'bad', 'is_active' => '1'])->assertSessionHasErrors('gstin');
        $this->actingAs($admin)->post('/admin/masters/lists/transporters', ['name' => 'Sai Logistics', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/admin/masters/lists/hsn-sac?q=71')->assertOk()->assertSee('Jewellery')->assertViewHas('rows', fn ($p) => $p->total() === 2);
        $this->actingAs($admin)->get('/admin/masters')->assertOk()->assertSee('Party master')->assertSee('Tax master')->assertSee('Transporter master');
        $this->assertSame(1, ActivityLog::where('action', 'master.created')->where('subject_type', 'transporter')->count());

        // in use: refused, inactive instead; Page setup hides the HSN description
        $d = $this->setUpSales();
        $gst18 = TaxStructure::where('name', 'GST 18%')->first();
        $this->actingAs($admin)->delete("/admin/masters/lists/tax-structures/{$gst18->id}")->assertSessionHasErrors('delete');
        $this->actingAs($admin)->delete("/admin/masters/lists/tax-structures/{$tax->id}")->assertSessionHasErrors('delete');
        $fields = [];
        foreach (app(PageSetupService::class)->fields('master-hsn-sac') as $f => $cur) {
            $fields[$f] = ['visible' => $f === 'description' ? '0' : '1', 'required' => '0'];
        }
        $this->actingAs($admin)->put('/admin/page-setup/master-hsn-sac', ['fields' => $fields])->assertSessionHasNoErrors();
        $hsn = HsnSacCode::where('code', '7113')->where('type', 'HSN')->first();
        $this->actingAs($admin)->put("/admin/masters/lists/hsn-sac/{$hsn->id}", ['code' => '7113', 'type' => 'HSN', 'description' => 'Changed', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertSame('Jewellery', $hsn->fresh()->description);
        $this->assertFalse($hsn->fresh()->is_active);

        // a role without Masters rights cannot add
        $this->actingAs($this->user('raj@company.example', 'Purchase'))->post('/admin/masters/lists/uoms', ['code' => 'BOX'])->assertForbidden();
    }

    public function test_party_master_form_import_export_and_delete_rules(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/masters/parties', ['name' => 'Apex', 'type' => 'Customer', 'status' => 'Active'])->assertSessionHasErrors('billing_address');
        $this->actingAs($admin)->post('/admin/masters/parties', ['name' => 'Apex', 'type' => 'Customer', 'status' => 'Active', 'billing_address' => 'Chakan', 'gstin' => '27ABC'])->assertSessionHasErrors('gstin');
        $this->actingAs($admin)->post('/admin/masters/parties', ['name' => 'Apex', 'type' => 'Customer', 'status' => 'Active', 'billing_address' => 'Chakan', 'gstin' => '27aabcz9999f1z5',
            'opening_balance' => '1,25,000', 'opening_balance_type' => 'DR', 'currency_id' => Currency::defaultId()])->assertSessionHasNoErrors();
        $p = Party::firstOrFail();
        $this->assertSame('27AABCZ9999F1Z5', $p->gstin);
        $this->assertSame('125000.00', $p->opening_balance);
        $this->assertSame('party.created', ActivityLog::where('subject_type', 'party')->value('action'));
        $this->actingAs($admin)->post('/admin/masters/parties', ['name' => 'Other', 'type' => 'Customer', 'status' => 'Active', 'billing_address' => 'X', 'gstin' => '27AABCZ9999F1Z5'])->assertSessionHasErrors('gstin');

        $csv = "Name,GST no,Type,Billing address,Shipping address,Payment terms,Opening balance,DR / CR,Currency,Status\n"
            ."Nova Fasteners,27AADCN7788Q1Z4,Both,Bhosari,,30 days,0,DR,INR,Active\nBad Co,,Vendor,Somewhere,,,,,XYZ,\n";
        $this->actingAs($admin)->post('/admin/masters/parties/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])->assertSessionHasErrors('file');
        $this->assertSame(1, Party::count());
        $csv = "Name,GST no,Type,Billing address,Shipping address,Payment terms,Opening balance,DR / CR,Currency,Status\nNova Fasteners,27AADCN7788Q1Z4,both,Bhosari,,30 days,0,DR,INR,\n";
        $this->actingAs($admin)->post('/admin/masters/parties/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])->assertSessionHasNoErrors();
        $this->assertSame('Both', Party::where('name', 'Nova Fasteners')->value('type'));
        $out = $this->actingAs($admin)->get('/admin/masters/parties/export')->streamedContent();
        $this->assertStringContainsString('"Nova Fasteners",27AADCN7788Q1Z4,Both', $out);
        $this->actingAs($admin)->get('/admin/masters/parties?type=Both')->assertOk()->assertViewHas('rows', fn ($r) => $r->total() === 1);

        // a party in use cannot be deleted
        $d = $this->setUpSales();
        $this->actingAs($admin)->delete("/admin/masters/parties/{$d['mh']->id}")->assertSessionHasErrors('delete');
        $this->actingAs($admin)->delete("/admin/masters/parties/{$p->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($p);
    }

    public function test_item_master_numbering_rules_import_and_export(): void
    {
        $admin = $this->admin();
        $d = $this->setUpSales();
        $cat = ItemCategory::create(['name' => 'Pressings', 'type' => 'Sales', 'item_no_prefix' => 'PRS', 'is_active' => true]);
        $base = ['description' => 'Bracket', 'type' => 'Sales', 'hsn_sac_code_id' => HsnSacCode::first()->id, 'uom_id' => Uom::first()->id,
            'tax_structure_id' => TaxStructure::first()->id, 'status' => 'Active', 'party_id' => $d['mh']->id];
        $this->actingAs($admin)->post('/admin/masters/items', $base)->assertSessionHasErrors('item_no');
        $this->actingAs($admin)->post('/admin/masters/items', $base + ['item_category_id' => $cat->id, 'price' => '1,250.50'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/masters/items', $base + ['item_category_id' => $cat->id])->assertSessionHasNoErrors();
        $this->assertSame(['PRS-0001', 'PRS-0002'], Item::where('item_category_id', $cat->id)->orderBy('id')->pluck('item_no')->all());
        $this->assertSame('1250.5000', Item::where('item_no', 'PRS-0001')->value('price'));
        $this->actingAs($admin)->post('/admin/masters/items', array_merge($base, ['item_no' => 'brk-2210']))->assertSessionHasErrors('item_no');
        $this->actingAs($admin)->post('/admin/masters/items', array_merge($base, ['item_no' => 'X-1', 'type' => 'Purchase']))->assertSessionHasErrors('type');
        $this->actingAs($admin)->post('/admin/masters/items', array_merge($base, ['item_no' => 'X-1', 'min_stock' => '10', 'max_stock' => '5']))->assertSessionHasErrors('max_stock');

        $csv = "Party name,Category,Item description,Item no,Internal item no,Type,HSN/SAC,UOM,Tax structure,Price,Currency,Min stock,Max stock,Weight / piece,Packaging qty,Status\n"
            ."Apex Auto Components,Pressings,Clip,,10-1,Sales,8708,NOS,GST 18%,1.10,INR,,,,,Active\n"
            ."Apex Auto Components,,Bad,,,Sales,9999,NOS,GST 18%,,,,,,,\n";
        $this->actingAs($admin)->post('/admin/masters/items/import', ['file' => UploadedFile::fake()->createWithContent('i.csv', $csv)])->assertSessionHasErrors('file');
        $csv = "Party name,Category,Item description,Item no,Internal item no,Type,HSN/SAC,UOM,Tax structure,Price,Currency,Min stock,Max stock,Weight / piece,Packaging qty,Status\n"
            ."Apex Auto Components,Pressings,Clip,,10-1,sales,8708,nos,gst 18%,1.10,INR,,,,,\n";
        $this->actingAs($admin)->post('/admin/masters/items/import', ['file' => UploadedFile::fake()->createWithContent('i.csv', $csv)])->assertSessionHasNoErrors();
        $this->assertSame('PRS-0003', Item::where('description', 'Clip')->value('item_no'));
        $this->assertStringContainsString('PRS-0003', $this->actingAs($admin)->get('/admin/masters/items/export')->streamedContent());
        $this->actingAs($admin)->get('/admin/masters/items?q=prs')->assertViewHas('rows', fn ($r) => $r->total() === 3);
    }

    public function test_company_master_is_saved_and_logged(): void
    {
        $this->actingAs($this->admin())->put('/admin/masters/company', ['name' => 'Nursery Components', 'gstin' => '27aaacn1234f1z5', 'pan' => 'bad'])->assertSessionHasErrors('pan');
        $this->actingAs($this->admin())->put('/admin/masters/company', ['name' => 'Nursery Components', 'gstin' => '27aaacn1234f1z5', 'pan' => 'aaacn1234f'])->assertSessionHasNoErrors();
        $this->assertSame('27', Company::current()->stateCode());
        $this->assertSame(1, ActivityLog::where('action', 'company.updated')->count());
        $this->actingAs($this->admin())->get('/admin/masters/company')->assertOk()->assertSee('Nursery Components');
    }

    // ---- Sales order ----

    public function test_sales_order_numbering_rates_validation_and_approval(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $line = fn ($item, $qty = '100', $rate = '') => ['item_id' => $item->id, 'quantity' => $qty, 'rate' => $rate, 'delivery_date' => $this->today()];

        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['mh'], []))->assertSessionHasErrorsIn('order', 'lines');
        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['mh'], [$line($d['pin'])]))->assertSessionHasErrorsIn('order', 'lines.0.item_id');
        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['mh'], [$line($d['bracket'])], ['end_date' => today()->subDay()->format('d/m/Y')]))->assertSessionHasErrorsIn('order', 'end_date');
        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['mh'], [$line($d['bracket'], '1,200'), $line($d['plate'], '600', '60')]))->assertSessionHasNoErrors();
        $so = SalesOrder::firstOrFail();
        $this->assertSame('SO/'.\App\Models\FinancialYear::forDate(today())->code.'/0001', $so->so_no);
        $this->assertSame('Draft', $so->status);
        $this->assertSame('42.5000', $so->lines[0]->rate);  // blank rate = Item master price (D14)
        $this->assertSame('87000.00', $so->total_value);   // 1200 × 42.50 + 600 × 60
        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['mh'], [$line($d['bracket'])]))->assertSessionHasErrorsIn('order', 'po_number');

        // approval: required for every order by default; Sales cannot approve; reject needs a reason
        $this->actingAs($sales)->post("/sales/orders/{$so->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('Pending approval', $so->fresh()->status);
        $this->actingAs($sales)->post("/sales/orders/{$so->id}/approve")->assertForbidden();
        $this->actingAs($this->admin())->post("/sales/orders/{$so->id}/reject")->assertSessionHasErrorsIn('order', 'remarks');
        $this->actingAs($this->admin())->post("/sales/orders/{$so->id}/reject", ['remarks' => 'Wrong rate'])->assertSessionHasNoErrors();
        $this->assertSame(['Rejected', 'Wrong rate'], [$so->fresh()->status, $so->fresh()->status_reason]);
        // a rejected order that is changed is a Draft again; then approved
        $this->actingAs($sales)->put("/sales/orders/{$so->id}", $this->orderPayload($d['mh'], [$line($d['bracket'], '1200')] + [1 => ['id' => $so->lines[1]->id] + $line($d['plate'], '600', '61')]))->assertSessionHasNoErrors();
        $this->assertSame('Draft', $so->fresh()->status);
        $this->actingAs($sales)->post("/sales/orders/{$so->id}/submit");
        $this->actingAs($this->admin())->post("/sales/orders/{$so->id}/approve", ['remarks' => 'OK'])->assertSessionHasNoErrors();
        $so->refresh();
        $this->assertSame('Approved', $so->status);
        $this->assertSame(['Submitted', 'Rejected', 'Submitted', 'Approved'], $so->approvals()->orderBy('id')->pluck('action')->all());
        // an approved order is locked except for Admin
        $this->actingAs($sales)->put("/sales/orders/{$so->id}", $this->orderPayload($d['mh'], [$line($d['bracket'])]))->assertSessionHasErrorsIn('order', 'form');

        // auto-approval at or below the rule's amount
        ApprovalRule::where('document_type', 'sales_order')->update(['approve_above_amount' => 100000]);
        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['ka'], [$line($d['pin'], '10')], ['po_number' => 'KPE/1']))->assertSessionHasNoErrors();
        $small = SalesOrder::latest('id')->first();
        $this->actingAs($sales)->post("/sales/orders/{$small->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('Approved', $small->fresh()->status);
        // an export customer's order always goes to an approver (amount limit is in rupees)
        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['ex'], [$line($d['housing'], '10', '4.80')], ['po_number' => 'HX-1']))->assertSessionHasNoErrors();
        $exp = SalesOrder::latest('id')->first();
        $this->actingAs($sales)->post("/sales/orders/{$exp->id}/submit");
        $this->assertSame('Pending approval', $exp->fresh()->status);
        $this->assertGreaterThanOrEqual(4, ActivityLog::where('action', 'sales_order.status_changed')->count());

        // Admin › Approval keeps the sales order rule
        $this->actingAs($this->admin())->put('/admin/approval/sales-order', ['is_required' => '1', 'approve_above_amount' => 'abc'])->assertSessionHasErrorsIn('sales_order', 'approve_above_amount');
        $this->actingAs($this->admin())->put('/admin/approval/sales-order', ['is_required' => '0', 'approve_above_amount' => ''])->assertSessionHasNoErrors();
        $this->assertFalse(ApprovalRule::for('sales_order')->is_required);
        $this->actingAs($this->admin())->get('/admin/approval')->assertOk()->assertSee('Save sales order rule');
    }

    public function test_sales_order_upload_po_import_export_and_list(): void
    {
        Storage::fake('local');
        $d = $this->setUpSales();
        $sales = $this->sales();
        $so = $this->approvedOrder($d);
        // the customer PO of an Approved order is part of what was approved: only an Admin can change it (review M4)
        $this->actingAs($sales)->post("/crm/documents/sales_order/{$so->id}", ['category' => 'Customer PO', 'file' => UploadedFile::fake()->create('po.pdf', 20, 'application/pdf')])->assertSessionHasErrors();
        $this->actingAs($this->admin())->post("/crm/documents/sales_order/{$so->id}", ['category' => 'Customer PO', 'file' => UploadedFile::fake()->create('po.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post("/crm/documents/sales_order/{$so->id}", ['category' => 'Customer PO', 'file' => UploadedFile::fake()->create('po.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])->assertSessionHasErrors('file');
        $this->actingAs($sales)->get("/sales/orders/{$so->id}")->assertOk()->assertSee('po.pdf')->assertSee('Make tax invoice');

        $t = $this->today();
        $csv = "Group,Party name,PO number,Start date,End date,Rev no,Rev date,Item no,Quantity,Rate,Delivery date\n"
            ."1,Kaveri Precision,KPE/77,$t,,0,,HNG-0907,4000,,$t\n2,Kaveri Precision,KPE/78,$t,,,,BRK-2210,10,,$t\n";
        $this->actingAs($sales)->post('/sales/orders/import', ['file' => UploadedFile::fake()->createWithContent('so.csv', $csv)])->assertSessionHasErrors('file');
        $csv = "Group,Party name,PO number,Start date,End date,Rev no,Rev date,Item no,Quantity,Rate,Delivery date\n"
            ."1,Kaveri Precision,KPE/77,$t,,0,,HNG-0907,4000,,$t\n1,Kaveri Precision,KPE/77,$t,,0,,HNG-0907,500,9,$t\n";
        $this->actingAs($sales)->post('/sales/orders/import', ['file' => UploadedFile::fake()->createWithContent('so.csv', $csv)])->assertSessionHasNoErrors();
        $imp = SalesOrder::where('po_number', 'KPE/77')->firstOrFail();
        $this->assertCount(2, $imp->lines);
        $this->assertSame('Draft', $imp->status);
        $this->assertSame('37300.00', $imp->total_value); // 4000 × 8.20 + 500 × 9
        $this->assertStringContainsString('KPE/77', $this->actingAs($sales)->get('/sales/orders/export')->streamedContent());
        $this->actingAs($sales)->get('/sales/orders/list?view=draft')->assertOk()->assertViewHas('rows', fn ($r) => $r->total() === 1);
        $this->actingAs($sales)->get('/sales/orders/list?view=open')->assertViewHas('rows', fn ($r) => $r->total() === 1);
        $this->actingAs($sales)->getJson('/sales/orders/lookup?q=KPE')->assertOk()->assertJsonCount(1);
    }

    // ---- Tax invoice ----

    public function test_invoice_gst_intra_inter_and_export_with_pf_charges(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $trn = Transporter::create(['name' => 'Sai Logistics', 'is_active' => true]);
        // Maharashtra customer: CGST + SGST; P&F taxed at the highest rate (18%)
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [
            ['item_id' => $d['bracket']->id, 'quantity' => '100', 'rate' => ''],
            ['item_id' => $d['plate']->id, 'quantity' => '10', 'rate' => '60'],
        ], ['pf_charges' => '250', 'transporter_id' => $trn->id]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::firstOrFail();
        $this->assertSame('INV/'.\App\Models\FinancialYear::forDate(today())->code.'/0001', $inv->invoice_no);
        $this->assertSame(['Intra-state', 'MH 14 AB 1234'], [$inv->supply_type, $inv->vehicle_no]);
        // 100 × 42.50 = 4250 @18% → 382.50 + 382.50; 10 × 60 = 600 @12% → 36 + 36; P&F 250 @18% → 22.50 + 22.50
        $this->assertSame(['5100.00', '441.00', '441.00', '0.00', '5982.00'], [$inv->taxable_value, $inv->cgst_amount, $inv->sgst_amount, $inv->igst_amount, $inv->grand_total]);
        $this->assertSame('8708', $inv->lines[0]->hsn_code);
        // Karnataka customer: IGST
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['ka'], [['item_id' => $d['pin']->id, 'quantity' => '1000', 'rate' => '']], ['shipping_address' => 'Peenya']))->assertSessionHasNoErrors();
        $ka = SalesInvoice::latest('id')->first();
        $this->assertSame(['Inter-state', '8200.00', '1476.00', '9676.00'], [$ka->supply_type, $ka->taxable_value, $ka->igst_amount, $ka->grand_total]);
        // export customer: no GST, in EUR
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['ex'], [['item_id' => $d['housing']->id, 'quantity' => '100', 'rate' => '4.80']], ['shipping_address' => 'Stuttgart']))->assertSessionHasNoErrors();
        $ex = SalesInvoice::latest('id')->first();
        $this->assertSame(['Export', '480.00', '480.00', 'EUR'], [$ex->supply_type, $ex->taxable_value, $ex->grand_total, $ex->currency->code]);
        // another customer's part is refused without a PO (A22)
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['pin']->id, 'quantity' => '1']]))->assertSessionHasErrorsIn('invoice', 'lines.0.item_id');
        // dates: not in the future, inside the year on the top bar (only an Admin can type the date, 02/11/2026)
        $this->actingAs($this->admin())->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '1']], ['invoice_date' => today()->addDay()->format('d/m/Y')]))->assertSessionHasErrorsIn('invoice', 'invoice_date');
        $this->actingAs($this->admin())->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '1']], ['invoice_date' => '01/01/2020']))->assertSessionHasErrorsIn('invoice', 'invoice_date');

        // PDF with the amount in words; page, list and CSV
        $pdf = $this->actingAs($sales)->get("/sales/invoices/{$inv->id}/pdf");
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertSame('Rupees Five Thousand Nine Hundred Eighty Two Only', Money::words($inv->grand_total));
        $this->actingAs($sales)->get("/sales/invoices/{$inv->id}")->assertOk()->assertSee('5,982.00')->assertSee('CGST');
        $this->actingAs($sales)->get('/sales/invoices/list?view=Sales')->assertOk()->assertViewHas('rows', fn ($r) => $r->total() === 3);
        $csv = $this->actingAs($sales)->get('/sales/invoices/export')->streamedContent();
        $this->assertStringContainsString('BRK-2210', $csv);
        $this->assertStringContainsString('5982.00', $csv);
    }

    public function test_invoice_against_po_locks_quantity_and_rate_and_cancel_returns_the_balance(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $so = $this->approvedOrder($d);
        [$l1, $l2] = [$so->lines[0], $so->lines[1]];
        $po = fn ($lines, $over = []) => $this->invoicePayload($d['mh'], $lines, ['sales_order_id' => $so->id] + $over);

        // more than the PO balance is refused; the SO rate is used whatever is posted
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l1->id, 'item_id' => $l1->item_id, 'quantity' => '1201']]))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l1->id, 'item_id' => $l1->item_id, 'quantity' => '800', 'rate' => '1'], ['sales_order_line_id' => $l2->id, 'quantity' => '250']]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::firstOrFail();
        $this->assertSame('42.5000', $inv->lines[0]->rate);
        $this->assertSame(['800.000', '250.000'], [$l1->fresh()->invoiced_quantity, $l2->fresh()->invoiced_quantity]);
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l1->id, 'quantity' => '401']]))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l1->id, 'quantity' => '400']]))->assertSessionHasNoErrors();
        $this->assertSame('1200.000', $l1->fresh()->invoiced_quantity);
        $this->actingAs($sales)->getJson("/sales/orders/{$so->id}/po-lines")->assertOk()->assertJsonPath('0.balance', 0)->assertJsonPath('1.balance', 350);

        // editing gives back the old quantity before checking: 800 → 700 is fine, and the balance follows
        $this->actingAs($sales)->put("/sales/invoices/{$inv->id}", $po([['sales_order_line_id' => $l1->id, 'quantity' => '700'], ['sales_order_line_id' => $l2->id, 'quantity' => '250']]))->assertSessionHasNoErrors();
        $this->assertSame('1100.000', $l1->fresh()->invoiced_quantity);
        // a credit note against the PO: not more than invoiced; it does not change the balance; stock comes back in
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l2->id, 'quantity' => '251']], ['invoice_type' => 'Credit']))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l2->id, 'quantity' => '50']], ['invoice_type' => 'Credit']))->assertSessionHasNoErrors();
        $cn = SalesInvoice::where('invoice_type', 'Credit')->firstOrFail();
        $this->assertStringStartsWith('CN/', $cn->invoice_no);
        $this->assertSame('250.000', $l2->fresh()->invoiced_quantity);
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l2->id, 'quantity' => '5']], ['invoice_type' => 'Debit']))->assertSessionHasNoErrors();
        $this->assertStringStartsWith('DN/', SalesInvoice::where('invoice_type', 'Debit')->value('invoice_no'));
        // stock: Sales out, Credit in, Debit none
        $this->assertSame(['in' => 50.0, 'out' => 1350.0], ['in' => (float) StockMovement::sum('quantity_in'), 'out' => (float) StockMovement::sum('quantity_out')]);

        // an order line already billed cannot be removed or reduced below what was billed
        $this->actingAs($this->admin())->put("/sales/orders/{$so->id}", $this->orderPayload($d['mh'], [['id' => $l1->id, 'item_id' => $l1->item_id, 'quantity' => '1000', 'rate' => '42.50', 'delivery_date' => $this->today()], ['id' => $l2->id, 'item_id' => $l2->item_id, 'quantity' => '600', 'rate' => '61', 'delivery_date' => $this->today()]]))
            ->assertSessionHasErrorsIn('order', 'lines.0.quantity');
        $this->actingAs($this->admin())->delete("/sales/orders/{$so->id}")->assertSessionHasErrorsIn('order', 'delete');

        // cancel keeps the number, returns the PO balance and removes the stock rows; cancelled is read-only
        $this->actingAs($this->user('raj@company.example', 'Purchase'))->post("/sales/invoices/{$inv->id}/cancel", ['cancel_reason' => 'Wrong quantity'])->assertForbidden();
        $this->actingAs($this->admin())->post("/sales/invoices/{$inv->id}/cancel")->assertSessionHasErrorsIn('invoice', 'cancel_reason');
        // a tax invoice cannot be cancelled while a credit note still gives back part of what it billed (review M1)
        $this->actingAs($this->admin())->post("/sales/invoices/{$inv->id}/cancel", ['cancel_reason' => 'Wrong quantity'])->assertSessionHasErrorsIn('invoice', 'cancel_reason');
        $this->actingAs($this->admin())->post("/sales/invoices/{$cn->id}/cancel", ['cancel_reason' => 'Raised in error'])->assertSessionHasNoErrors();
        $this->assertSame('0.000', $l2->fresh()->credited_quantity);
        $this->actingAs($this->admin())->post("/sales/invoices/{$inv->id}/cancel", ['cancel_reason' => 'Wrong quantity'])->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertSame(['Cancelled', 2], [$inv->status, $inv->lines()->count()]);
        $this->assertSame(['400.000', '0.000'], [$l1->fresh()->invoiced_quantity, $l2->fresh()->invoiced_quantity]);
        $this->assertSame(0, StockMovement::where('source_id', $inv->id)->where('source_type', 'sales_invoice')->count());
        $this->actingAs($this->admin())->put("/sales/invoices/{$inv->id}", $po([['sales_order_line_id' => $l1->id, 'quantity' => '1']]))->assertSessionHasErrorsIn('invoice', 'form');
        // the next invoice takes the next number: the cancelled one is never reused
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l1->id, 'quantity' => '10']]))->assertSessionHasNoErrors();
        $this->assertStringEndsWith('/0003', SalesInvoice::latest('id')->value('invoice_no'));
        $this->assertSame(2, ActivityLog::where('action', 'sales_invoice.cancelled')->count());
    }

    public function test_only_an_approved_order_of_the_same_party_can_be_billed(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '10', 'rate' => '', 'delivery_date' => $this->today()]], ['po_number' => 'DRAFT-1']))->assertSessionHasNoErrors();
        $draft = SalesOrder::firstOrFail();
        $line = $draft->lines()->first();
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['sales_order_line_id' => $line->id, 'quantity' => '1']], ['sales_order_id' => $draft->id]))->assertSessionHasErrorsIn('invoice', 'sales_order_id');
        $this->actingAs($sales)->getJson("/sales/orders/{$draft->id}/po-lines")->assertNotFound();
        $so = $this->approvedOrder($d, [['item_id' => $d['bracket']->id, 'quantity' => '10', 'rate' => '', 'delivery_date' => $this->today()]]);
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['ka'], [['sales_order_line_id' => $so->lines[0]->id, 'quantity' => '1']], ['sales_order_id' => $so->id, 'shipping_address' => 'X']))->assertSessionHasErrorsIn('invoice', 'sales_order_id');
        $this->actingAs($sales)->getJson("/sales/parties/{$d['mh']->id}/info")->assertOk()->assertJsonPath('shipping_address', 'Gate 2, Chakan, Pune')->assertJsonCount(1, 'orders');
        $this->actingAs($sales)->getJson('/sales/items?party='.$d['mh']->id.'&q=BRK')->assertOk()->assertJsonCount(2);
        // the invoice page opened from "Make tax invoice" has the party and PO filled in
        $this->actingAs($sales)->get('/sales/invoices/create?order='.$so->id)->assertOk()->assertSee('<option value="'.$so->id.'" selected>', false);
    }

    // ---- The Sales page, menu, rights and Page setup ----

    public function test_sales_page_menu_cards_error_bags_and_rights(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $page = $this->actingAs($sales)->get('/sales/invoices')->assertOk();
        $page->assertSee(route('sales.invoices.index'))->assertSee(route('sales.orders.index'))->assertSee('id="soCard"', false)->assertDontSee('id="soCard" open', false);
        $this->actingAs($sales)->get('/sales/orders')->assertOk()->assertSee('id="soCard"  open', false);
        $this->actingAs($sales)->get('/app/sales')->assertRedirect();

        // an invoice error shows on the invoice card, not on the sales order card
        $this->actingAs($sales)->from('/sales/invoices/create')->post('/sales/invoices', $this->invoicePayload($d['mh'], []))->assertSessionHasErrorsIn('invoice', 'lines');
        $html = $this->actingAs($sales)->get('/sales/invoices/create')->getContent();
        [$invoiceCard, $orderCard] = explode('id="soCard"', $html, 2);
        $this->assertStringContainsString('Add at least one item with a quantity.', $invoiceCard);
        $this->assertStringNotContainsString('Add at least one item with a quantity.', $orderCard);

        // rights: Purchase has no Sales pages; Accounts cannot see sales orders
        $purchase = $this->user('raj@company.example', 'Purchase');
        $this->actingAs($purchase)->get('/sales/invoices')->assertForbidden();
        $this->actingAs($purchase)->post('/sales/orders', [])->assertForbidden();
        $this->assertTrue($this->admin()->can('sales-order-approval.edit'));
        $this->assertFalse($sales->can('sales-order-approval.edit'));
    }

    public function test_sales_pages_follow_page_setup(): void
    {
        $d = $this->setUpSales();
        $admin = $this->admin();
        $set = function (string $page, array $choices) use ($admin) {
            $fields = [];
            foreach (app(PageSetupService::class)->fields($page) as $f => $cur) {
                [$vis, $req] = $choices[$f] ?? [$cur['visible'], $cur['required']];
                $fields[$f] = ['visible' => $vis ? '1' : '0', 'required' => $req ? '1' : '0'];
            }
            $this->actingAs($admin)->put("/admin/page-setup/$page", ['fields' => $fields])->assertSessionHasNoErrors();
        };
        $this->actingAs($admin)->get('/admin/page-setup')->assertSee('Sales')->assertSee('Tax invoice')->assertSee('Item master')->assertSee('Party master');

        $set('sales-order', ['end_date' => [true, true]]);
        $this->actingAs($this->sales())->post('/sales/orders', $this->orderPayload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '1', 'rate' => '', 'delivery_date' => $this->today()]]))->assertSessionHasErrorsIn('order', 'end_date');

        $this->actingAs($this->sales())->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '1']]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::firstOrFail();
        $set('tax-invoice', ['vehicle_no' => [false, false], 'pf_charges' => [false, false]]);
        $this->actingAs($this->sales())->get("/sales/invoices/{$inv->id}")->assertDontSee('id="vehicle_no"', false)->assertDontSee('id="pf_charges"', false);
        $this->actingAs($this->sales())->put("/sales/invoices/{$inv->id}", $this->invoicePayload($d['mh'], [['item_id' => $d['bracket']->id, 'quantity' => '2']], ['vehicle_no' => 'XX 1', 'pf_charges' => '999']))->assertSessionHasNoErrors();
        $this->assertSame(['MH 14 AB 1234', '0.00', '2.000'], [$inv->fresh()->vehicle_no, $inv->fresh()->pf_charges, $inv->fresh()->lines[0]->quantity]);

        $set('party', ['billing_address' => [true, false], 'gstin' => [true, true]]);
        $this->actingAs($admin)->post('/admin/masters/parties', ['name' => 'X', 'type' => 'Customer', 'status' => 'Active'])->assertSessionHasErrors('gstin')->assertSessionDoesntHaveErrors('billing_address');
        $set('item', ['internal_item_no' => [true, true]]);
        $this->actingAs($admin)->put("/admin/masters/items/{$d['bracket']->id}", ['description' => 'Part', 'item_no' => 'BRK-2210', 'type' => 'Sales', 'hsn_sac_code_id' => $d['bracket']->hsn_sac_code_id,
            'uom_id' => $d['bracket']->uom_id, 'tax_structure_id' => $d['bracket']->tax_structure_id, 'status' => 'Active'])->assertSessionHasErrors('internal_item_no');
    }

    // ---- Reports ----

    public function test_sales_reports_download_with_filters(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $so = $this->approvedOrder($d);
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['sales_order_line_id' => $so->lines[0]->id, 'quantity' => '700']], ['sales_order_id' => $so->id]))->assertSessionHasNoErrors();
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['ex'], [['item_id' => $d['housing']->id, 'quantity' => '100', 'rate' => '4.80']], ['shipping_address' => 'Stuttgart']))->assertSessionHasNoErrors();
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '10']]))->assertSessionHasNoErrors();
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '5']], ['invoice_type' => 'Credit']))->assertSessionHasNoErrors();
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '1']]))->assertSessionHasNoErrors();
        $cancel = SalesInvoice::latest('id')->first();
        $this->actingAs($this->admin())->post("/sales/invoices/{$cancel->id}/cancel", ['cancel_reason' => 'Test']);

        $year = \App\Models\FinancialYear::forDate(today());
        $range = ['from' => $year->starts_on->format('d/m/Y'), 'to' => $year->ends_on->format('d/m/Y'), 'format' => 'csv'];
        $get = fn (string $r, array $q = []) => $this->actingAs($sales)->get("/reports/sales/$r/download?".http_build_query($range + $q))->streamedContent();

        foreach (['orders', 'sales', 'export', 'credit-debit', 'stock'] as $r) {
            $this->actingAs($sales)->get("/reports/sales/$r")->assertOk()->assertSee('Download options');
        }
        $orders = $get('orders');
        $this->assertStringContainsString('BRK-2210', $orders);
        $this->assertStringContainsString('700.000,500.000', $orders);   // invoiced, balance
        $salesCsv = $get('sales');
        $this->assertSame(3, substr_count($salesCsv, 'INV/'));          // the cancelled invoice is left out
        $this->assertStringContainsString('Helix Motors GmbH', $salesCsv);
        $this->assertStringNotContainsString('Helix', $get('sales', ['party' => $d['mh']->id]));
        $export = $get('export', ['detail' => 'detailed']);
        $this->assertStringContainsString('HSG-5001', $export);
        $this->assertStringNotContainsString('BRK-2210', $export);
        $this->assertStringContainsString('CN/', $get('credit-debit'));
        $stock = $get('stock');
        $this->assertStringContainsString('BRK-2210,"Part BRK-2210",NOS,100.000,,0.000,0.000,700.000,-700.000,Yes', str_replace('"Part BRK-2210"', '"Part BRK-2210"', $stock) === $stock ? $stock : $stock);
        $below = $get('stock', ['below' => 1]);
        $this->assertStringContainsString('BRK-2214', $below);  // 5 back in from the credit note, still below 100
        $this->actingAs($sales)->get('/reports/sales/sales/download?'.http_build_query(['format' => 'pdf'] + $range))->assertOk();
        $this->actingAs($this->user('raj@company.example', 'Purchase'))->get('/reports/sales/sales')->assertForbidden();
    }

    // ---- Review fixes (18/10/2026) ----

    public function test_credit_notes_never_give_back_more_than_was_invoiced(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $so = $this->approvedOrder($d);
        $l1 = $so->lines[0];
        $po = fn ($lines, $over = []) => $this->invoicePayload($d['mh'], $lines, ['sales_order_id' => $so->id] + $over);
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l1->id, 'quantity' => '600']]))->assertSessionHasNoErrors();

        // with a PO: two credit notes of 600 against 600 invoiced, the second is refused and names the item
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l1->id, 'quantity' => '600']], ['invoice_type' => 'Credit']))->assertSessionHasNoErrors();
        $this->assertSame('600.000', $l1->fresh()->credited_quantity);
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $l1->id, 'quantity' => '1']], ['invoice_type' => 'Credit']))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        $this->assertStringContainsString('BRK-2210', session('errors')->getBag('invoice')->first('lines.0.quantity'));
        $this->assertSame(1, SalesInvoice::where('invoice_type', 'Credit')->count());
        $this->assertSame(600.0, (float) StockMovement::sum('quantity_in'));
        // editing the credit note gives its quantity back before checking again
        $cn = SalesInvoice::where('invoice_type', 'Credit')->first();
        $this->actingAs($sales)->put("/sales/invoices/{$cn->id}", $po([['sales_order_line_id' => $l1->id, 'quantity' => '500']], ['invoice_type' => 'Credit']))->assertSessionHasNoErrors();
        $this->assertSame('500.000', $l1->fresh()->credited_quantity);
        $first = SalesInvoice::where('invoice_type', 'Sales')->first();
        // the tax invoice cannot be lowered below what credit notes gave back
        $this->actingAs($sales)->put("/sales/invoices/{$first->id}", $po([['sales_order_line_id' => $l1->id, 'quantity' => '400']]))->assertSessionHasErrorsIn('invoice', 'lines');
        $this->assertSame('600.000', $l1->fresh()->invoiced_quantity);

        // without a PO: nothing invoiced means nothing can be credited
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '5']], ['invoice_type' => 'Credit']))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        // ... and at most the year's tax invoices of that customer, less earlier credit notes (lines of one note add up)
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '10']]))->assertSessionHasNoErrors();
        $free = SalesInvoice::latest('id')->first();
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '6'], ['item_id' => $d['plate']->id, 'quantity' => '5']], ['invoice_type' => 'Credit']))->assertSessionHasErrorsIn('invoice', 'lines.1.quantity');
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '6']], ['invoice_type' => 'Credit']))->assertSessionHasNoErrors();
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '5']], ['invoice_type' => 'Credit']))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        // another customer's tax invoices do not count
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['ka'], [['item_id' => $d['pin']->id, 'quantity' => '1']], ['invoice_type' => 'Credit', 'shipping_address' => 'Peenya']))->assertSessionHasErrorsIn('invoice', 'lines.0.quantity');
        // the tax invoice cannot be cancelled while its credit note stands
        $this->actingAs($this->admin())->post("/sales/invoices/{$free->id}/cancel", ['cancel_reason' => 'Wrong'])->assertSessionHasErrorsIn('invoice', 'cancel_reason');
        $this->assertSame('Active', $free->fresh()->status);
    }

    public function test_stock_balance_follows_every_invoice_and_feeds_the_stock_report(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $so = $this->approvedOrder($d);
        $po = fn ($lines, $over = []) => $this->invoicePayload($d['mh'], $lines, ['sales_order_id' => $so->id] + $over);
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $so->lines[0]->id, 'quantity' => '700'], ['sales_order_line_id' => $so->lines[1]->id, 'quantity' => '50']]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->first();
        $this->actingAs($sales)->post('/sales/invoices', $po([['sales_order_line_id' => $so->lines[0]->id, 'quantity' => '20']], ['invoice_type' => 'Credit']))->assertSessionHasNoErrors();
        $bal = fn ($item) => (float) DB::table('stock_balances')->where('item_id', $item->id)->value('quantity');
        $this->assertSame([-680.0, -50.0], [$bal($d['bracket']), $bal($d['plate'])]);
        $this->actingAs($sales)->put("/sales/invoices/{$inv->id}", $po([['sales_order_line_id' => $so->lines[0]->id, 'quantity' => '600'], ['sales_order_line_id' => $so->lines[1]->id, 'quantity' => '50']]))->assertSessionHasNoErrors();
        $this->assertSame(-580.0, $bal($d['bracket']));
        // the balance always equals the ledger
        foreach (DB::table('stock_balances')->get() as $b) {
            $ledger = (float) StockMovement::where('unit_id', $b->unit_id)->where('item_id', $b->item_id)->selectRaw('SUM(quantity_in - quantity_out) AS q')->value('q');
            $this->assertEqualsWithDelta($ledger, (float) $b->quantity, 0.0005);
        }

        $year = \App\Models\FinancialYear::forDate(today());
        $csv = fn (string $from, string $to) => $this->actingAs($sales)->get('/reports/sales/stock/download?'.http_build_query(['from' => $from, 'to' => $to, 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('BRK-2210,"Part BRK-2210",NOS,100.000,,0.000,20.000,600.000,-580.000,Yes', $csv($year->starts_on->format('d/m/Y'), $year->ends_on->format('d/m/Y')));
        // From after today: everything is opening stock, nothing in or out in the period
        if (today()->addDay()->lte($year->ends_on)) {
            $this->assertStringContainsString('BRK-2210,"Part BRK-2210",NOS,100.000,,-580.000,0.000,0.000,-580.000,Yes', $csv(today()->addDay()->format('d/m/Y'), $year->ends_on->format('d/m/Y')));
        }
        // To before today: what happened later is taken off the closing stock
        if (today()->subDay()->gte($year->starts_on)) {
            $this->assertStringContainsString('BRK-2210,"Part BRK-2210",NOS,100.000,,0.000,0.000,0.000,0.000,Yes', $csv($year->starts_on->format('d/m/Y'), today()->subDay()->format('d/m/Y')));
        }
        $this->actingAs($sales)->get('/reports/sales/stock')->assertSee('Stock received comes in with the Purchase module');

        $credit = SalesInvoice::where('invoice_type', 'Credit')->value('id');
        $this->actingAs($this->admin())->post("/sales/invoices/{$credit}/cancel", ['cancel_reason' => 'x'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post("/sales/invoices/{$inv->id}/cancel", ['cancel_reason' => 'x'])->assertSessionHasNoErrors();
        $this->assertSame([0.0, 0.0], [$bal($d['bracket']), $bal($d['plate'])]);
    }

    public function test_company_gstin_currency_rate_own_year_po_number_and_party_info_rules(): void
    {
        $d = $this->setUpSales();
        $sales = $this->sales();
        $year = \App\Models\FinancialYear::forDate(today());

        // a blank rate takes the Item master price only in the party's currency, on orders and invoices
        $this->actingAs($sales)->post('/sales/orders', $this->orderPayload($d['ex'], [['item_id' => $d['housing']->id, 'quantity' => '10', 'rate' => '', 'delivery_date' => $this->today()]], ['po_number' => 'HX-9']))
            ->assertSessionHasErrorsIn('order', 'lines.0.rate');
        $this->assertStringContainsString('priced in INR. Enter the rate in EUR', session('errors')->getBag('order')->first('lines.0.rate'));
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['ex'], [['item_id' => $d['housing']->id, 'quantity' => '10', 'rate' => '']], ['shipping_address' => 'Stuttgart']))
            ->assertSessionHasErrorsIn('invoice', 'lines.0.rate');

        // an edited invoice keeps the year of its number
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '1']]))->assertSessionHasNoErrors();
        $inv = SalesInvoice::latest('id')->first();
        $this->assertStringStartsWith('INV/', $inv->invoice_no);
        $this->actingAs($this->admin())->put("/sales/invoices/{$inv->id}", $this->invoicePayload($d['mh'], [['item_id' => $d['plate']->id, 'quantity' => '1']], ['invoice_date' => $year->starts_on->copy()->subDay()->format('d/m/Y')]))
            ->assertSessionHasErrorsIn('invoice', 'invoice_date');

        // a billed order line keeps its rate
        $so = $this->approvedOrder($d);
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['mh'], [['sales_order_line_id' => $so->lines[0]->id, 'quantity' => '10']], ['sales_order_id' => $so->id]))->assertSessionHasNoErrors();
        $l = $so->lines;
        $this->actingAs($this->admin())->put("/sales/orders/{$so->id}", $this->orderPayload($d['mh'], [
            ['id' => $l[0]->id, 'item_id' => $l[0]->item_id, 'quantity' => '1200', 'rate' => '45', 'delivery_date' => $this->today()],
            ['id' => $l[1]->id, 'item_id' => $l[1]->item_id, 'quantity' => '600', 'rate' => '61', 'delivery_date' => $this->today()]]))->assertSessionHasErrorsIn('order', 'lines.0.rate');

        // the same PO number is refused inside the save too, not only by the form check
        try {
            app(\App\Services\SalesOrderService::class)->create(['party_id' => $d['mh']->id, 'po_number' => '4500123', 'start_date' => $this->today(), 'end_date' => null,
                'revision_no' => '0', 'revision_date' => null, 'lines' => [['item_id' => $d['bracket']->id, 'quantity' => '1', 'rate' => '1', 'delivery_date' => $this->today()]]],
                $this->admin(), Unit::first(), $year);
            $this->fail('A second order with the same PO number was saved.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('po_number', $e->errors());
        }

        // party info: customers only, with the supply type and currency for the live totals
        $this->actingAs($this->admin())->getJson("/sales/parties/{$d['mh']->id}/info")->assertOk()->assertJsonPath('supply', 'Intra-state')->assertJsonPath('currency', 'INR');
        $this->actingAs($this->admin())->getJson("/sales/parties/{$d['ka']->id}/info")->assertJsonPath('supply', 'Inter-state');
        $supplier = Party::create(['name' => 'Steel Supplier', 'type' => 'Supplier', 'status' => 'Active']);
        $this->actingAs($this->admin())->getJson("/sales/parties/{$supplier->id}/info")->assertNotFound();
        $this->actingAs($sales)->getJson('/sales/items?party='.$d['mh']->id)->assertJsonPath('0.data.igst', '18');

        // no company GSTIN: no invoice can be saved
        Company::query()->update(['gstin' => null]);
        $this->actingAs($sales)->post('/sales/invoices', $this->invoicePayload($d['ka'], [['item_id' => $d['pin']->id, 'quantity' => '1']], ['shipping_address' => 'Peenya']))->assertSessionHasErrorsIn('invoice', 'form');
        $this->assertStringContainsString('GSTIN', session('errors')->getBag('invoice')->first('form'));
    }
}
