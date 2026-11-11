<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\Item;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Unit;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dates;
use App\Support\Gst;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tax invoices, credit notes and debit notes (SALES tab A17:A24). Rules:
 * - Against a PO (an Approved sales order of the same party), only that order's parts can be billed (A21), at the
 *   order's rate. A Sales invoice cannot bill more than the PO balance (qty lock, D6). A credit note cannot give back
 *   more than was invoiced on that line minus earlier credit notes; a debit note (value only) not more than invoiced.
 *   Without a PO, any of the customer's active parts (A22), at the rate typed or the Item master price (D14) when the
 *   item is priced in the party's currency; a credit note then cannot give back more of an item than the customer's
 *   active tax invoices of the year billed, minus earlier credit notes (review fixes 18/10/2026).
 * - The company GSTIN must be filled in (Admin › Masters › Company): it decides the GST split and is printed as the
 *   supplier's GSTIN on every invoice.
 * - GST per line from the item's tax structure: CGST + SGST in the company's state, IGST in another state, none for
 *   export customers (Gst). P&F charges are taxed at the highest line rate.
 * - Numbers INV / CN / DN per unit and year, unbroken (D16, C12): an invoice is cancelled, never deleted.
 * - Stock: a Sales invoice takes its quantity out, a credit note brings it back in; a debit note moves no stock.
 * Billed and credited quantities on the order lines, the stock rows and the stock balances change in the same
 * transaction, under row locks taken in id order, so two invoices against the same PO at the same moment cannot both
 * pass the balance check. The party row is locked for the whole save, so two credit notes of one customer queue.
 * The INV / CN / DN number is taken last, so the unit's series row is locked only for the end of the save.
 */
class SalesInvoiceService
{
    private const ATTEMPTS = 3;

    public function __construct(private readonly NumberSeries $numbers)
    {
    }

    public function create(array $data, User $actor, Unit $unit, FinancialYear $year): SalesInvoice
    {
        return DB::transaction(function () use ($data, $actor, $unit, $year) {
            $party = $this->lockParty((int) $data['party_id']);
            $company = self::company();
            // The date is typed dd/mm/yyyy, so it is set on its own below.
            $inv = new SalesInvoice(array_diff_key(array_intersect_key($data, array_flip(SalesInvoice::FIELDS)), ['invoice_date' => 1]));
            $inv->invoice_date = Dates::toDb($data['invoice_date']);
            $inv->unit_id = $unit->id;
            $inv->financial_year_id = $year->id;
            $inv->currency_id = $party->currency_id;
            $inv->supply_type = Gst::supplyType($party, $company);
            // A placeholder until the lines are checked and saved; the real number is taken at the end (short lock).
            $inv->invoice_no = '~'.Str::random(24);
            $inv->status = 'Active';
            $inv->created_by = $actor->id;
            $inv->updated_by = $actor->id;
            $inv->save();
            $this->saveLines($inv, $data['lines'], $party);
            $inv->invoice_no = $this->numbers->next(SalesInvoice::SERIES[$data['invoice_type']], $unit, $year);
            $inv->saveQuietly();
            ActivityLogger::log('sales_invoice.created', $inv, 'Added '.$inv->activityLabel().' for '.$party->name, [
                'fields' => $this->snapshot($inv),
                'items' => $this->lineSummary($inv),
            ]);

            return $inv;
        }, self::ATTEMPTS);
    }

    /** The invoice type and number stay as saved; everything else can change while the invoice is not cancelled. */
    public function update(SalesInvoice $inv, array $data, User $actor): SalesInvoice
    {
        return DB::transaction(function () use ($inv, $data, $actor) {
            $inv = SalesInvoice::whereKey($inv->id)->lockForUpdate()->firstOrFail();
            if ($inv->isCancelled()) {
                throw ValidationException::withMessages(['form' => $inv->invoice_no.' is cancelled, so it cannot be changed.']);
            }
            $party = $this->lockParty((int) $data['party_id']);
            $company = self::company();
            if (array_key_exists('invoice_date', $data)) {
                $this->checkOwnYear($inv, (string) $data['invoice_date']);
            }
            $inv->load('lines.item', 'party', 'salesOrder', 'transporter');
            $before = $this->snapshot($inv);
            $beforeLines = $this->lineSummary($inv);
            $oldParty = $inv->party_id;
            $oldItems = $inv->lines->pluck('item_id')->unique()->all();

            $touched = $this->undoEffects($inv);
            $fields = array_intersect_key($data, array_flip(SalesInvoice::FIELDS));
            unset($fields['invoice_type'], $fields['invoice_date']);
            $inv->fill($fields);
            if (array_key_exists('invoice_date', $data)) {
                $inv->invoice_date = Dates::toDb($data['invoice_date']);
            }
            $inv->currency_id = $party->currency_id;
            $inv->supply_type = Gst::supplyType($party, $company);
            $inv->updated_by = $actor->id;
            $inv->save();
            $this->saveLines($inv, $data['lines'], $party);
            // A smaller or moved tax invoice must still cover the credit notes already given against it.
            if ($inv->invoice_type === 'Sales') {
                $this->checkPoCredits($touched);
                $this->checkCustomerCredits($oldParty, $inv, $oldItems);
            }
            $inv->unsetRelations();

            $changes = ActivityLogger::diff($before, $this->snapshot($inv));
            if ($lineChanges = ActivityLogger::listDiff($beforeLines, $this->lineSummary($inv))) {
                $changes['items'] = $lineChanges;
            }
            if ($changes) {
                ActivityLogger::log('sales_invoice.updated', $inv, 'Updated '.$inv->activityLabel(), $changes);
            }

            return $inv;
        }, self::ATTEMPTS);
    }

    /** Cancel keeps the number (unbroken series); billed quantities go back to the PO and the stock rows are removed. */
    public function cancel(SalesInvoice $inv, string $reason, User $actor): SalesInvoice
    {
        return DB::transaction(function () use ($inv, $reason, $actor) {
            $inv = SalesInvoice::whereKey($inv->id)->lockForUpdate()->firstOrFail();
            if ($inv->isCancelled()) {
                throw ValidationException::withMessages(['cancel_reason' => $inv->invoice_no.' is already cancelled.']);
            }
            if ($inv->invoice_type === 'Sales') {
                $this->lockParty($inv->party_id);
            }
            // The lines stay on the cancelled invoice as a record; only their effects are undone.
            $touched = $this->undoEffects($inv, false);
            $inv->status = 'Cancelled';
            $inv->cancel_reason = $reason;
            $inv->updated_by = $actor->id;
            $inv->save();
            if ($inv->invoice_type === 'Sales') {
                $this->checkPoCredits($touched, true);
                $this->checkCustomerCredits($inv->party_id, $inv, $inv->lines()->pluck('item_id')->unique()->all(), true);
            }
            ActivityLogger::log('sales_invoice.cancelled', $inv, 'Cancelled '.$inv->activityLabel().': '.$reason, [
                'Status' => ['old' => 'Active', 'new' => 'Cancelled'],
            ]);

            return $inv;
        }, self::ATTEMPTS);
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(SalesInvoice $inv): array
    {
        $inv->loadMissing(['party', 'salesOrder', 'transporter']);

        return [
            'Invoice type' => $inv->invoice_type,
            'Invoice date' => Dates::show($inv->invoice_date),
            'Party name' => $inv->party?->name,
            'PO no' => $inv->salesOrder ? $inv->salesOrder->po_number.' ('.$inv->salesOrder->so_no.')' : null,
            'Shipping address' => $inv->shipping_address,
            'Mode of transport' => $inv->mode_of_transport,
            'Transporter' => $inv->transporter?->name,
            'Vehicle no' => $inv->vehicle_no,
            'Distance (km)' => $inv->distance_km,
            'P&F charges' => $inv->pf_charges,
            'Supply' => $inv->supply_type,
            'Taxable value' => $inv->taxable_value,
            'GST' => number_format((float) $inv->cgst_amount + (float) $inv->sgst_amount + (float) $inv->igst_amount, 2, '.', ''),
            'Grand total' => $inv->grand_total,
        ];
    }

    public function lineSummary(SalesInvoice $inv): array
    {
        return $inv->lines()->with('item:id,item_no')->get()
            ->map(fn (SalesInvoiceLine $l) => ($l->item?->item_no ?? '?').' × '.rtrim(rtrim((string) $l->quantity, '0'), '.').' @ '.rtrim(rtrim((string) $l->rate, '0'), '.'))->all();
    }

    /**
     * Save the lines with their GST, update the PO's billed quantities and the stock, and work out the totals.
     * $lines: [item_id, quantity, rate?, sales_order_line_id?]
     */
    private function saveLines(SalesInvoice $inv, array $lines, Party $party): void
    {
        $order = null;
        $orderLines = collect();
        if ($inv->sales_order_id) {
            $order = SalesOrder::whereKey($inv->sales_order_id)->sharedLock()->first();
            if (! $order || $order->status !== SalesOrder::APPROVED || $order->party_id !== $party->id || $order->unit_id !== $inv->unit_id) {
                throw ValidationException::withMessages(['sales_order_id' => 'Choose an approved sales order (PO) of this party.']);
            }
            // Locked in id order, so two invoices against the same PO wait for each other instead of deadlocking.
            $orderLines = SalesOrderLine::where('sales_order_id', $order->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        }
        $items = Item::with(['taxStructure', 'hsn', 'currency'])->whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');
        // Without a PO, a credit note gives back at most what the customer's tax invoices of the year billed, less
        // earlier credit notes (worked out once, then lowered as this note's lines use it).
        $creditRoom = ! $order && $inv->invoice_type === 'Credit'
            ? $this->customerCreditRoom($party->id, $inv, array_values(array_unique(array_map('intval', array_column($lines, 'item_id')))))
            : [];
        $partyCurrency = $party->currency?->code ?? 'INR';
        $stock = [];

        $taxable = 0.0;
        $tax = ['cgst_amount' => 0.0, 'sgst_amount' => 0.0, 'igst_amount' => 0.0];
        $highest = null;
        foreach (array_values($lines) as $i => $l) {
            $n = $i + 1;
            $qty = round((float) $l['quantity'], 3);
            $orderLine = null;
            if ($order) {
                $orderLine = $orderLines->get((int) ($l['sales_order_line_id'] ?? 0));
                if (! $orderLine) {
                    throw ValidationException::withMessages(["lines.$i.item_id" => "Item $n is not a part of the selected PO."]);
                }
                $item = $items->get($orderLine->item_id) ?? Item::withTrashed()->with(['taxStructure', 'hsn'])->find($orderLine->item_id);
                $name = self::lineName($n, $item);
                $rate = (float) $orderLine->rate;
                if ($inv->invoice_type === 'Sales') {
                    if ($qty > $orderLine->balance() + 0.0005) {
                        throw ValidationException::withMessages(["lines.$i.quantity" => "$name: only ".Money::qty($orderLine->balance()).' is left on the PO.']);
                    }
                    $orderLine->invoiced_quantity = round((float) $orderLine->invoiced_quantity + $qty, 3);
                    $orderLine->save();
                } elseif ($inv->invoice_type === 'Credit') {
                    if ($qty > $orderLine->creditRoom() + 0.0005) {
                        throw ValidationException::withMessages(["lines.$i.quantity" => "$name: only ".Money::qty($orderLine->creditRoom()).' can still be credited on this PO (invoiced '
                            .Money::qty($orderLine->invoiced_quantity).', already credited '.Money::qty($orderLine->credited_quantity).').']);
                    }
                    $orderLine->credited_quantity = round((float) $orderLine->credited_quantity + $qty, 3);
                    $orderLine->save();
                } elseif ($qty > (float) $orderLine->invoiced_quantity + 0.0005) {
                    throw ValidationException::withMessages(["lines.$i.quantity" => "$name: only ".Money::qty($orderLine->invoiced_quantity).' has been invoiced on this PO.']);
                }
            } else {
                $item = $items->get((int) $l['item_id']);
                if (! $item || $item->status !== 'Active' || ! in_array($item->type, ['Sales', 'Both'], true) || ($item->party_id && $item->party_id !== $party->id)) {
                    throw ValidationException::withMessages(["lines.$i.item_id" => "Item $n is not an active sales item of this customer."]);
                }
                $name = self::lineName($n, $item);
                if (($l['rate'] ?? '') === '' || $l['rate'] === null) {
                    // The Item master price only in the party's currency (code review M3).
                    $itemCurrency = $item->currency?->code ?? 'INR';
                    if ($itemCurrency !== $partyCurrency) {
                        throw ValidationException::withMessages(["lines.$i.rate" => "$name is priced in $itemCurrency. Enter the rate in $partyCurrency."]);
                    }
                    $rate = (float) $item->price;
                } else {
                    $rate = round((float) $l['rate'], 4);
                }
                if ($inv->invoice_type === 'Credit') {
                    $room = $creditRoom[$item->id] ?? 0.0;
                    if ($qty > $room + 0.0005) {
                        throw ValidationException::withMessages(["lines.$i.quantity" => $room > 0
                            ? "$name: only ".Money::qty($room).' can still be credited (this year\'s tax invoices of this customer, less earlier credit notes).'
                            : "$name: nothing can be credited. This customer has no active tax invoice for it this year, or it is already fully credited."]);
                    }
                    $creditRoom[$item->id] = round($room - $qty, 3);
                }
            }
            $ts = $item->taxStructure;
            $value = round($qty * $rate, 2);
            $gst = Gst::split($value, $inv->supply_type, (float) $ts?->cgst_rate, (float) $ts?->sgst_rate, (float) $ts?->igst_rate);
            SalesInvoiceLine::create([
                'sales_invoice_id' => $inv->id,
                'line_no' => $n,
                'sales_order_line_id' => $orderLine?->id,
                'item_id' => $item->id,
                'hsn_code' => $item->hsn?->code,
                'quantity' => $qty,
                'rate' => $rate,
                'taxable_value' => $value,
                'total' => round($value + $gst['cgst_amount'] + $gst['sgst_amount'] + $gst['igst_amount'], 2),
            ] + $gst);
            $taxable += $value;
            foreach ($tax as $k => $v) {
                $tax[$k] = $v + $gst[$k];
            }
            if ($ts && (! $highest || (float) $ts->igst_rate > (float) $highest->igst_rate)) {
                $highest = $ts;
            }
            $stock[$item->id] = ($stock[$item->id] ?? 0) + $qty;
        }
        $this->moveStock($inv, $stock);

        // P&F charges: part of the taxable value, at the highest GST rate of the invoice's parts.
        $pf = round((float) $inv->pf_charges, 2);
        if ($pf > 0 && $highest) {
            $pfTax = Gst::split($pf, $inv->supply_type, (float) $highest->cgst_rate, (float) $highest->sgst_rate, (float) $highest->igst_rate);
            foreach ($tax as $k => $v) {
                $tax[$k] = $v + $pfTax[$k];
            }
        }
        $inv->taxable_value = round($taxable + $pf, 2);
        $inv->cgst_amount = round($tax['cgst_amount'], 2);
        $inv->sgst_amount = round($tax['sgst_amount'], 2);
        $inv->igst_amount = round($tax['igst_amount'], 2);
        $inv->grand_total = round($inv->taxable_value + $inv->cgst_amount + $inv->sgst_amount + $inv->igst_amount, 2);
        $inv->saveQuietly();
    }

    /**
     * Stock out for a Sales invoice, back in for a credit note; a debit note moves no stock. One row per item, in item
     * order, so two invoices update the same stock balances in the same order.
     */
    private function moveStock(SalesInvoice $inv, array $qtyByItem): void
    {
        if ($inv->invoice_type === 'Debit') {
            return;
        }
        ksort($qtyByItem);
        $date = $inv->invoice_date instanceof \DateTimeInterface ? $inv->invoice_date->format('Y-m-d') : (string) $inv->invoice_date;
        foreach ($qtyByItem as $itemId => $qty) {
            if ($qty > 0) {
                $in = $inv->invoice_type === 'Credit' ? round($qty, 3) : 0.0;
                StockLedger::move($inv->unit_id, (int) $itemId, $date, $in, $in ? 0.0 : round($qty, 3), 'sales_invoice', $inv->id);
            }
        }
    }

    /**
     * Before a change or a cancel: give billed (or credited) quantities back to the PO lines and take the invoice's
     * stock rows off the ledger and the balances. Returns the ids of the PO lines it changed.
     */
    private function undoEffects(SalesInvoice $inv, bool $removeLines = true): array
    {
        $lines = SalesInvoiceLine::where('sales_invoice_id', $inv->id)->get();
        $ids = $lines->pluck('sales_order_line_id')->filter()->unique()->sort()->values();
        $column = ['Sales' => 'invoiced_quantity', 'Credit' => 'credited_quantity'][$inv->invoice_type] ?? null;
        if ($column && $ids->isNotEmpty()) {
            $orderLines = SalesOrderLine::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $l) {
                if ($l->sales_order_line_id && ($ol = $orderLines->get($l->sales_order_line_id))) {
                    $ol->{$column} = max(0, round((float) $ol->{$column} - (float) $l->quantity, 3));
                    $ol->save();
                }
            }
        }
        StockLedger::remove('sales_invoice', $inv->id);
        if ($removeLines) {
            SalesInvoiceLine::where('sales_invoice_id', $inv->id)->delete();
        }

        return $ids->all();
    }

    /** A tax invoice changed or cancelled: its PO lines must still have billed at least what was credited on them. */
    private function checkPoCredits(array $orderLineIds, bool $cancelling = false): void
    {
        $short = SalesOrderLine::with('item:id,item_no')->whereIn('id', $orderLineIds)->whereColumn('credited_quantity', '>', 'invoiced_quantity')->first();
        if ($short) {
            $what = $cancelling ? 'cancelled' : 'lowered';
            throw ValidationException::withMessages([$cancelling ? 'cancel_reason' : 'lines' => ($short->item?->item_no ?? 'An item').': '
                .Money::qty($short->credited_quantity).' has been given back by credit notes on this PO, so this invoice cannot be '.$what
                .' below that. Cancel those credit notes first.']);
        }
    }

    /** The same rule without a PO: per item, the customer's tax invoices of the year must cover its credit notes. */
    private function checkCustomerCredits(int $partyId, SalesInvoice $inv, array $itemIds, bool $cancelling = false): void
    {
        foreach ($this->customerCreditRoom($partyId, $inv, $itemIds, $cancelling) as $itemId => $room) {
            if ($room < -0.0005) {
                $no = Item::withTrashed()->whereKey($itemId)->value('item_no');
                throw ValidationException::withMessages([$cancelling ? 'cancel_reason' : 'lines' => $no.': credit notes this year gave back more than the tax invoices would bill after this change. Cancel those credit notes first.']);
            }
        }
    }

    /**
     * Per item: quantity the customer's active tax invoices of the invoice's unit and year billed, minus what active
     * credit notes gave back. This invoice's own lines are left out ($excludeSelf, for a cancel that keeps its lines).
     */
    private function customerCreditRoom(int $partyId, SalesInvoice $inv, array $itemIds, bool $excludeSelf = true): array
    {
        if (! $itemIds) {
            return [];
        }
        $sums = SalesInvoiceLine::query()->join('sales_invoices as i', 'i.id', '=', 'sales_invoice_lines.sales_invoice_id')
            ->where('i.party_id', $partyId)->where('i.unit_id', $inv->unit_id)->where('i.financial_year_id', $inv->financial_year_id)
            ->where('i.status', 'Active')->whereIn('i.invoice_type', ['Sales', 'Credit'])
            ->when($excludeSelf, fn ($q) => $q->where('i.id', '!=', $inv->id))
            ->whereIn('sales_invoice_lines.item_id', $itemIds)
            ->groupBy('sales_invoice_lines.item_id', 'i.invoice_type')
            ->selectRaw('sales_invoice_lines.item_id AS item_id, i.invoice_type AS t, SUM(sales_invoice_lines.quantity) AS qty')->get();
        $room = array_fill_keys($itemIds, 0.0);
        foreach ($sums as $r) {
            $room[(int) $r->item_id] += ($r->t === 'Sales' ? 1 : -1) * (float) $r->qty;
        }

        return array_map(fn ($q) => round($q, 3), $room);
    }

    /** "Item 2 (BRK-2214)": the row number and the item no, for messages under a line. */
    private static function lineName(int $n, ?Item $item): string
    {
        return 'Item '.$n.($item?->item_no ? ' ('.$item->item_no.')' : '');
    }

    /** The company row, which must carry a GSTIN before any invoice is saved (code review M2). */
    private static function company(): Company
    {
        $company = Company::current();
        if (! $company->gstin) {
            throw ValidationException::withMessages(['form' => 'Enter your company\'s GSTIN in Admin › Masters › Company first. It decides CGST + SGST or IGST and is printed on every invoice as the supplier\'s GSTIN.']);
        }

        return $company;
    }

    /** An edited invoice stays in the financial year of its number (database review SL-M2). */
    private function checkOwnYear(SalesInvoice $inv, string $date): void
    {
        $year = FinancialYear::find($inv->financial_year_id);
        $d = preg_match('#^\d{2}/\d{2}/\d{4}$#', $date) ? \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $date)->startOfDay() : null;
        if ($year && $d && ($d->lt($year->starts_on) || $d->gt($year->ends_on))) {
            throw ValidationException::withMessages(['invoice_date' => $inv->invoice_no.' is numbered in '.$year->label().', so its date must stay between '
                .$year->starts_on->format('d/m/Y').' and '.$year->ends_on->format('d/m/Y').'.']);
        }
    }

    /** The party row is locked for the save, so two credit notes (or a credit note and a cancel) of one customer queue. */
    private function lockParty(int $id): Party
    {
        $p = Party::with('currency')->whereKey($id)->lockForUpdate()->first();
        if (! $p) {
            throw ValidationException::withMessages(['party_id' => 'This party was deleted while you were working. Choose another party.']);
        }

        return $p;
    }
}
