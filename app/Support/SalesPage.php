<?php

namespace App\Support;

use App\Http\Controllers\Crm\QuotationController;
use App\Http\Controllers\LookupController;
use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\Item;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Transporter;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Sales page (SALES A24: "Sales order and sales invoice must be on a single page"): the Tax invoice card first and
 * the Sales order card below it, hidden by default (D5) and opened from the menu's Sales Order item or a link. Each card
 * has its own entry picker and form. Entries are for the unit and financial year on the top bar (OTHER POINTS A22).
 */
final class SalesPage
{
    public static function render(Request $request, SalesInvoice $invoice, SalesOrder $order, string $open): View
    {
        $user = $request->user();
        $invoice->loadMissing(['lines.item.uom', 'lines.orderLine', 'party', 'salesOrder', 'transporter', 'creator', 'updater']);
        $order->loadMissing(['lines.item.uom', 'party', 'creator', 'updater', 'customerPo']);
        $fromOld = fn (string $form, string $f, $default) => old('_form') === $form ? old($f, $default) : $default;
        $invParty = (int) $fromOld('invoice', 'party_id', $invoice->party_id);
        $soParty = (int) $fromOld('order', 'party_id', $order->party_id);

        return view('sales.page', [
            'open' => $open,
            'invoice' => $invoice,
            'order' => $order,
            'old' => $fromOld,
            'invEntries' => self::invoiceSearch($request)->limit(50)->get(),
            'soEntries' => self::orderSearch($request)->limit(50)->get(),
            'invCustomers' => QuotationController::pickerOptions(Party::customers(), Party::class, $invParty ?: null),
            'soCustomers' => QuotationController::pickerOptions(Party::customers(), Party::class, $soParty ?: null),
            // The selected party's approved sales orders for "PO no" (A21), plus the one already on the invoice.
            'poOptions' => $invParty ? self::approvedOrders($invParty, $invoice->sales_order_id) : collect(),
            'transporters' => Transporter::forPicker($invoice->transporter_id)->orderBy('name')->get(),
            'invItems' => $invParty ? self::itemOptions($invParty) : collect(),
            'soItems' => $soParty ? self::itemOptions($soParty) : collect(),
            'company' => Company::current(),
            'rule' => ApprovalRule::for('sales_order'),
            'approvals' => $order->exists ? $order->approvals()->with('actor:id,name')->latest('id')->limit(10)->get() : collect(),
            'soDocs' => DocumentService::box($order),
            'invHistory' => ActivityLog::history($invoice),
            'soHistory' => ActivityLog::history($order),
            'unit' => WorkContext::unit($user),
            'q' => trim((string) $request->query('q')),
        ]);
    }

    /** Approved sales orders of a customer in the unit on the top bar, newest first: id => "PO 4500123 · SO/26-27/0001". */
    public static function approvedOrders(int $partyId, ?int $keep = null)
    {
        return SalesOrder::where('party_id', $partyId)->where('unit_id', WorkContext::unit()?->id)
            ->where(fn ($q) => $q->where('status', SalesOrder::APPROVED)->when($keep, fn ($w) => $w->orWhere('id', $keep)))
            ->latest('id')->limit(100)->get(['id', 'po_number', 'so_no'])
            ->mapWithKeys(fn ($o) => [$o->id => 'PO '.$o->po_number.' · '.$o->so_no]);
    }

    /** A customer's sales items (and items of no party) for the item pickers: all while there are at most 200. */
    public static function itemOptions(int $partyId)
    {
        $list = Item::forSale()->where(fn ($q) => $q->whereNull('party_id')->orWhere('party_id', $partyId))->orderBy('item_no')->limit(201)->get();

        return $list->count() > 200 ? collect() : $list->mapWithKeys(fn ($i) => [$i->id => $i->pickerLabel()]);
    }

    public static function invoiceSearch(Request $request)
    {
        $q = LookupController::term($request);

        return SalesInvoice::query()->where('unit_id', WorkContext::unit()?->id)->where('financial_year_id', WorkContext::financialYear()->id)
            ->with(['party:id,name', 'currency:id,code'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('invoice_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhereIn('party_id', Party::select('id')->where('name', 'like', LookupController::prefix($q)))))
            ->latest('id');
    }

    public static function orderSearch(Request $request)
    {
        $q = LookupController::term($request);

        return SalesOrder::query()->where('unit_id', WorkContext::unit()?->id)->where('financial_year_id', WorkContext::financialYear()->id)
            ->with('party:id,name')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('so_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('po_number', 'like', LookupController::prefix($q))
                ->orWhereIn('party_id', Party::select('id')->where('name', 'like', LookupController::prefix($q)))))
            ->latest('id');
    }
}
