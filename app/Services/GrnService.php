<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Item;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dates;
use App\Support\Gst;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase › GRN entry (prototype "grn"): save, match, quality decision, cancel and delete of a supplier invoice
 * received. Every operation runs in one transaction with the GRN row locked and writes its activity log row in it.
 *
 * Match (PURCHASE A22): the lines total (qty × rate + GST, worked out here from the PO lines and masters) must equal
 * the supplier invoice amount within config('nursery.grn.match_tolerance'). Then the GRN gets its number (NumberSeries
 * 'grn') and date, and — for a Purchase invoice — the PO lines' received quantities go up through
 * PurchaseOrderService::receive() in this transaction. Otherwise it stays "Pending match" with no number. A matched GRN
 * must stay matched when it is changed; its supplier and invoice type are then fixed.
 *
 * Stock (architecture S7): only a Purchase invoice's ACCEPTED quantity comes into the GRN's unit, through
 * StockLedger::move() with source_type 'grn', when Quality approval is Approved. Rejected quantities move no stock and
 * are given back to the PO lines (pending again, for the supplier to replace). Cancel takes the PO quantities back
 * and removes the stock rows (StockLedger::remove) only while the unit still holds what was received.
 *
 * Lock order, the same in every path (and the same as PO and stock transfer code): GRN row → purchase order rows (in
 * id order) → their lines (receive(), in id order) → stock_balances rows (item order). PurchaseOrderService never
 * locks a GRN, and stock transfer never locks a PO, so no two paths wait on each other in opposite orders.
 */
class GrnService
{
    private const ATTEMPTS = 3;

    public function __construct(private readonly NumberSeries $numbers, private readonly PurchaseOrderService $orders)
    {
    }

    public function create(array $data, User $actor, Unit $unit, FinancialYear $year, bool $mustMatch = false): Grn
    {
        return DB::transaction(function () use ($data, $actor, $unit, $year, $mustMatch) {
            $party = $this->party((int) $data['party_id']);
            $grn = new Grn($this->attributes($data));
            $grn->unit_id = $unit->id;
            $grn->financial_year_id = $year->id;
            $grn->currency_id = $party->currency_id ?? Currency::defaultId();
            $grn->supply_type = PurchaseOrderService::supplyType($party);
            $grn->status = Grn::SAVED;
            $grn->match_status = Grn::PENDING_MATCH;
            $grn->quality_status = Grn::QA_PENDING;
            $grn->created_by = $actor->id;
            $grn->updated_by = $actor->id;
            $this->saveHeader($grn);
            $this->saveLines($grn, $data['lines']);

            ActivityLogger::log('grn.created', $grn, 'Added '.$grn->activityLabel().' from '.$party->name, [
                'fields' => $this->snapshot($grn),
                'items' => $this->lineSummary($grn),
            ]);
            $this->settleMatch($grn, $actor, $mustMatch, []);

            return $grn;
        }, self::ATTEMPTS);
    }

    public function update(Grn $grn, array $data, User $actor, bool $mustMatch = false): Grn
    {
        return DB::transaction(function () use ($grn, $data, $actor, $mustMatch) {
            $grn = $this->lock($grn);
            if (! $grn->isEditable()) {
                throw ValidationException::withMessages(['form' => $grn->label().' is '.strtolower($grn->stateLabel()).', so it cannot be changed.'.($grn->status === Grn::SAVED ? ' Cancel it and enter the invoice again.' : '')]);
            }
            $wasMatched = $grn->isMatched();
            if ($wasMatched && array_key_exists('party_id', $data) && (int) $data['party_id'] !== $grn->party_id) {
                throw ValidationException::withMessages(['party_id' => $grn->grn_no.' is matched, so its supplier cannot change. Cancel it and enter the invoice again.']);
            }
            if ($wasMatched && array_key_exists('invoice_type', $data) && $data['invoice_type'] !== $grn->invoice_type) {
                throw ValidationException::withMessages(['invoice_type' => $grn->grn_no.' is matched, so its invoice type cannot change.']);
            }
            $party = $this->party((int) ($data['party_id'] ?? $grn->party_id), $grn->party_id);
            $before = $this->snapshot($grn);
            $beforeLines = $this->lineSummary($grn);
            $oldEffects = $this->poEffects($grn);

            $grn->fill($this->attributes($data));
            $grn->currency_id = $party->currency_id ?? Currency::defaultId();
            $grn->supply_type = PurchaseOrderService::supplyType($party);
            $grn->updated_by = $actor->id;
            $this->saveHeader($grn);
            $this->saveLines($grn, $data['lines']);
            $grn->unsetRelations();

            $changes = ActivityLogger::diff($before, $this->snapshot($grn));
            if ($lineChanges = ActivityLogger::listDiff($beforeLines, $this->lineSummary($grn))) {
                $changes['items'] = $lineChanges;
            }
            if ($changes) {
                ActivityLogger::log('grn.updated', $grn, 'Updated '.$grn->activityLabel(), $changes);
            }
            if ($wasMatched) {
                if (! $this->amountMatches($grn)) {
                    throw ValidationException::withMessages(['invoice_amount' => $grn->grn_no.' is matched, so the lines total must still match the invoice amount. '.$this->difference($grn)]);
                }
                $this->applyReceive($grn, $this->delta($oldEffects, $this->poEffects($grn)), $actor);
            } else {
                $this->settleMatch($grn, $actor, $mustMatch, $oldEffects);
            }

            return $grn;
        }, self::ATTEMPTS);
    }

    /** Only a GRN still pending match (no number, nothing received, no stock) can be deleted; a matched one is cancelled. */
    public function delete(Grn $grn, User $actor): void
    {
        DB::transaction(function () use ($grn, $actor) {
            $grn = $this->lock($grn);
            if ($grn->status !== Grn::SAVED || $grn->isMatched()) {
                throw ValidationException::withMessages(['delete' => $grn->label().' is '.strtolower($grn->stateLabel()).', so it cannot be deleted.'.($grn->isMatched() && $grn->status === Grn::SAVED ? ' Cancel it instead.' : '')]);
            }
            ActivityLogger::log('grn.deleted', $grn, 'Deleted '.$grn->activityLabel(), ['fields' => $this->snapshot($grn), 'items' => $this->lineSummary($grn)], $actor);
            $grn->active_invoice_no = null;
            $grn->saveQuietly();
            $grn->delete();
        }, self::ATTEMPTS);
    }

    /**
     * Quality approval of a matched GRN: Approved (optionally with a rejected quantity per line, [grn_line_id => qty])
     * or Rejected (every quantity rejected). Accepted quantities of a Purchase invoice come into stock; rejected ones
     * are given back to their PO lines. The decision is final; to change it, cancel the GRN.
     */
    public function quality(Grn $grn, User $actor, string $decision, ?string $remarks, array $rejected = []): Grn
    {
        return DB::transaction(function () use ($grn, $actor, $decision, $remarks, $rejected) {
            $grn = $this->lock($grn);
            if ($grn->status !== Grn::SAVED || ! $grn->isMatched()) {
                throw ValidationException::withMessages(['quality' => $grn->label().' is '.strtolower($grn->stateLabel()).'; quality approval is given on a matched GRN.']);
            }
            if ($grn->quality_status !== Grn::QA_PENDING) {
                throw ValidationException::withMessages(['quality' => $grn->grn_no.' already has its quality approval result ('.$grn->quality_status.').']);
            }
            $this->decide($grn, $actor, $decision, $remarks, $rejected);

            return $grn;
        }, self::ATTEMPTS);
    }

    /**
     * Cancel a matched GRN with a reason; its number stays in the series. The PO quantities are taken back, and stock
     * that came in is removed only while the unit still holds it (checked under lock), as a stock transfer cancel.
     */
    public function cancel(Grn $grn, User $actor, string $reason): Grn
    {
        return DB::transaction(function () use ($grn, $actor, $reason) {
            $grn = $this->lock($grn);
            if ($grn->status === Grn::CANCELLED) {
                throw ValidationException::withMessages(['status' => $grn->label().' is already cancelled.']);
            }
            if (! $grn->isMatched()) {
                throw ValidationException::withMessages(['status' => 'This GRN is pending match and has no number yet, so delete it instead of cancelling.']);
            }
            // Read under the lock: once quality is approved or rejected (stock may have come in), only a quality approver cancels.
            if ($grn->isPurchase() && $grn->quality_status !== Grn::QA_PENDING && ! $actor->can('grn-quality.edit')) {
                throw ValidationException::withMessages(['status' => $grn->grn_no.' has its quality approval result, so only a quality approver can cancel it.']);
            }
            $hadStock = $grn->stock_posted;
            // PO rows and lines first (the lock order of every GRN path), then the stock balances.
            $this->applyReceive($grn, array_map(fn ($q) => -$q, $this->poEffects($grn)), $actor);
            if ($hadStock) {
                $accepted = $this->acceptedByItem($grn);
                $held = DB::table('stock_balances')->where('unit_id', $grn->unit_id)->whereIn('item_id', array_keys($accepted))
                    ->orderBy('item_id')->lockForUpdate()->pluck('quantity', 'item_id')->map(fn ($q) => (float) $q)->all();
                $items = Item::withTrashed()->with('uom:id,code')->whereIn('id', array_keys($accepted))->get()->keyBy('id');
                $short = [];
                foreach ($accepted as $itemId => $q) {
                    $have = $held[$itemId] ?? 0.0;
                    if ($have + 0.0005 < $q) {
                        $i = $items->get($itemId);
                        $short[] = ($i?->item_no ?? '?').': '.$grn->unit?->name.' holds '.Money::qty($have).' '.($i?->uom?->code ?? '').' of the '.Money::qty($q).' received';
                    }
                }
                if ($short) {
                    throw ValidationException::withMessages(['status' => 'The stock received has been used or moved on, so '.$grn->grn_no.' cannot be cancelled. '.implode('; ', $short).'.']);
                }
                StockLedger::remove(Grn::LEDGER_SOURCE, $grn->id);
                $grn->stock_posted = false;
            }
            $old = $grn->status;
            $grn->status = Grn::CANCELLED;
            $grn->status_reason = $reason;
            $grn->active_invoice_no = null;
            $grn->cancelled_by = $actor->id;
            $grn->cancelled_at = now();
            $grn->updated_by = $actor->id;
            $grn->save();
            ActivityLogger::log('grn.cancelled', $grn, 'Cancelled '.$grn->activityLabel().($hadStock ? ' and took its stock back out of '.$grn->unit?->name : '').': '.$reason,
                ['Status' => ['old' => $old, 'new' => Grn::CANCELLED]], $actor);

            return $grn;
        }, self::ATTEMPTS);
    }

    /** Lines total within the tolerance of the supplier invoice amount. */
    public function amountMatches(Grn $grn): bool
    {
        return round(abs((float) $grn->grand_total - (float) $grn->invoice_amount), 2) <= (float) config('nursery.grn.match_tolerance', 1) + 0.000001;
    }

    /** "Supplier invoice ₹1,00,000.00, lines total ₹99,120.00, difference ₹880.00." */
    public function difference(Grn $grn): string
    {
        $c = $grn->currencyCode() === 'INR' ? '₹' : $grn->currencyCode().' ';

        return 'Supplier invoice '.$c.Money::indian($grn->invoice_amount, 2).', lines total '.$c.Money::indian($grn->grand_total, 2)
            .', difference '.$c.Money::indian(round((float) $grn->invoice_amount - (float) $grn->grand_total, 2), 2).'.';
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(Grn $grn): array
    {
        $grn->loadMissing('party', 'transporter', 'customer', 'salesOrder');

        return [
            'Party name' => $grn->party?->name,
            'Invoice type' => $grn->invoice_type,
            'Invoice no' => $grn->invoice_no,
            'Invoice date' => Dates::show($grn->invoice_date),
            'Invoice amount' => $grn->invoice_amount,
            'Transporter' => $grn->transporter?->name,
            'Vehicle no' => $grn->vehicle_no,
            'E-way bill no' => $grn->eway_bill_no,
            'E-way bill date' => Dates::show($grn->eway_bill_date),
            'Customer name' => $grn->customer?->name,
            'SO no' => $grn->salesOrder?->so_no,
            'Remarks' => $grn->remarks,
            'Lines total' => $grn->grand_total,
        ];
    }

    /** One readable line per item, for the log. */
    public function lineSummary(Grn $grn): array
    {
        $n = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');

        return $grn->lines()->with(['item:id,item_no', 'uom:id,code', 'order:id,po_no'])->get()
            ->map(fn (GrnLine $l) => ($l->order ? $l->order->po_no.' · ' : '').($l->item?->item_no ?? '?').' × '.$n($l->quantity).' '.$l->uom?->code.' @ '.$n($l->rate))
            ->all();
    }

    // ---------------------------------------------------------------------------------------------------------------

    /**
     * After a save: match when the amounts agree (number, date, PO quantities, and with Quality approval hidden in
     * Page setup or for a supplier note, the quality step at once). "Save and generate GRN no" on a mismatch is refused.
     */
    private function settleMatch(Grn $grn, User $actor, bool $mustMatch, array $oldEffects): void
    {
        if (! $this->amountMatches($grn)) {
            if ($mustMatch) {
                throw ValidationException::withMessages(['invoice_amount' => 'Amount not matched. Fix it or save as pending match. '.$this->difference($grn)]);
            }

            return;
        }
        $grn->grn_no = $this->numbers->next('grn', Unit::findOrFail($grn->unit_id), $grn->financialYear);
        $grn->grn_date = $this->grnDate($grn);
        $grn->match_status = Grn::MATCHED;
        $grn->matched_by = $actor->id;
        $grn->matched_at = now();
        $grn->save();
        $this->applyReceive($grn, $this->delta($oldEffects, $this->poEffects($grn)), $actor);
        ActivityLogger::log('grn.matched', $grn, 'Amount matched: generated '.$grn->grn_no.' dated '.Dates::show($grn->grn_date).' for invoice '.$grn->invoice_no,
            ['Match' => ['old' => Grn::PENDING_MATCH, 'new' => Grn::MATCHED]], $actor);

        if (! $grn->isPurchase()) {
            $this->decide($grn, $actor, Grn::QA_APPROVED, 'Supplier '.strtolower($grn->invoice_type).' note: no goods to inspect, no stock moved', []);
        } elseif (! app(PageSetupService::class)->visible('grn', 'quality_status')) {
            $this->decide($grn, $actor, Grn::QA_APPROVED, 'Approved automatically (Quality approval is not used: hidden in Admin › Page setup)', []);
        }
    }

    /** The quality decision on a locked, matched GRN (see quality()). */
    private function decide(Grn $grn, User $actor, string $decision, ?string $remarks, array $rejected): void
    {
        $lines = GrnLine::where('grn_id', $grn->id)->orderBy('id')->get();
        $giveBack = [];
        foreach ($lines as $l) {
            $qty = (float) $l->quantity;
            $rej = $decision === Grn::QA_REJECTED ? $qty : round((float) ($rejected[$l->id] ?? 0), 3);
            if ($rej < 0 || $rej > $qty + 0.0005) {
                throw ValidationException::withMessages(['rejected.'.$l->id => 'Item '.$l->line_no.': the rejected quantity must be between 0 and '.Money::qty($qty).'.']);
            }
            $l->rejected_quantity = $rej;
            $l->accepted_quantity = round($qty - $rej, 3);
            $l->save();
            if ($grn->isPurchase() && $l->purchase_order_line_id && $rej > 0) {
                $giveBack[$l->purchase_order_line_id] = ($giveBack[$l->purchase_order_line_id] ?? 0) - $rej;
            }
        }
        // Rejected quantities go back to their PO lines (pending again), PO rows first, then the stock balances.
        $this->applyReceive($grn, $giveBack, $actor);
        $moved = false;
        if ($grn->isPurchase() && $decision === Grn::QA_APPROVED) {
            $date = $grn->grn_date->format('Y-m-d');
            foreach ($this->acceptedByItem($grn) as $itemId => $q) {
                StockLedger::move($grn->unit_id, (int) $itemId, $date, $q, 0.0, Grn::LEDGER_SOURCE, $grn->id);
                $moved = true;
            }
        }
        $old = $grn->quality_status;
        $grn->quality_status = $decision;
        $grn->quality_by = $actor->id;
        $grn->quality_at = now();
        $grn->quality_remarks = $remarks;
        $grn->stock_posted = $moved;
        $grn->updated_by = $actor->id;
        $grn->save();
        $rejectedTotal = $lines->sum(fn ($l) => (float) $l->rejected_quantity);
        $what = match (true) {
            $decision === Grn::QA_REJECTED => 'Quality rejected '.$grn->activityLabel().'; no stock received',
            $moved => 'Quality approved '.$grn->activityLabel().'; accepted quantity received into '.$grn->unit?->name.($rejectedTotal > 0 ? ' ('.Money::qty($rejectedTotal).' rejected)' : ''),
            default => 'Quality approved '.$grn->activityLabel(),
        };
        ActivityLogger::log('grn.quality', $grn, $what.($remarks ? ': '.$remarks : ''), ['Quality approval' => ['old' => $old, 'new' => $decision]], $actor);
    }

    /** GRN date: today, kept inside the GRN's financial year (a GRN of last year matched after 31 March gets 31 March). */
    private function grnDate(Grn $grn): string
    {
        $year = $grn->financialYear;
        $d = today();
        if ($d->gt($year->ends_on)) {
            $d = $year->ends_on->copy();
        } elseif ($d->lt($year->starts_on)) {
            $d = $year->starts_on->copy();
        }
        if ($grn->invoice_date && $d->lt($grn->invoice_date)) {
            $d = $grn->invoice_date->copy();
        }

        return $d->format('Y-m-d');
    }

    /** Save the header; the supplier invoice number is unique per supplier and year among live GRNs (index + friendly message). */
    private function saveHeader(Grn $grn): void
    {
        $grn->active_invoice_no = $grn->invoice_no;
        $dupe = Grn::where('party_id', $grn->party_id)->where('financial_year_id', $grn->financial_year_id)
            ->where('active_invoice_no', $grn->invoice_no)->when($grn->exists, fn ($q) => $q->whereKeyNot($grn->id))->first();
        if ($dupe) {
            throw ValidationException::withMessages(['invoice_no' => 'Invoice '.$grn->invoice_no.' of this supplier is already entered on '.$dupe->label().'.']);
        }
        try {
            $grn->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['invoice_no' => 'Invoice '.$grn->invoice_no.' of this supplier was entered by someone else just now.']);
        }
    }

    /**
     * Lines are written again on each save (no other document points at them). A line on a PO line takes that line's
     * item, UOM, tax and HSN, and its rate net of the PO discount unless a rate is typed; a line without a PO line takes
     * the Item master's. GST per line for the GRN's supply type, rounded to paise; totals are their sums.
     */
    private function saveLines(Grn $grn, array $lines): void
    {
        $poLines = PurchaseOrderLine::with('order:id,party_id,unit_id')->whereIn('id', array_filter(array_column($lines, 'purchase_order_line_id')))->get()->keyBy('id');
        $items = Item::withTrashed()->with('hsn:id,code')->whereIn('id', array_merge(array_filter(array_column($lines, 'item_id')), $poLines->pluck('item_id')->all()))->get()->keyBy('id');
        $taxes = TaxStructure::whereIn('id', array_merge($poLines->pluck('tax_structure_id')->all(), $items->pluck('tax_structure_id')->all()))->get()->keyBy('id');
        GrnLine::where('grn_id', $grn->id)->delete();
        $sum = ['taxable_value' => 0.0, 'cgst_amount' => 0.0, 'sgst_amount' => 0.0, 'igst_amount' => 0.0];
        foreach (array_values($lines) as $i => $l) {
            $n = $i + 1;
            $pl = ! empty($l['purchase_order_line_id']) ? $poLines->get((int) $l['purchase_order_line_id']) : null;
            if (! empty($l['purchase_order_line_id']) && (! $pl || $pl->order?->party_id !== $grn->party_id || $pl->order?->unit_id !== $grn->unit_id)) {
                throw ValidationException::withMessages(["lines.$i.purchase_order_line_id" => "Item $n: this PO line was changed while you were working. Choose it again."]);
            }
            $item = $items->get($pl ? $pl->item_id : (int) ($l['item_id'] ?? 0));
            if (! $item) {
                throw ValidationException::withMessages(["lines.$i.item_id" => "Choose the item of line $n."]);
            }
            $qty = round((float) $l['quantity'], 3);
            $typed = ($l['rate'] ?? '') !== '' && $l['rate'] !== null;
            $rate = $typed ? round((float) $l['rate'], 4)
                : ($pl ? round((float) $pl->rate * (100 - (float) $pl->discount_percent) / 100, 4) : (float) $item->price);
            $tax = $taxes->get($pl ? $pl->tax_structure_id : $item->tax_structure_id);
            $taxable = round($qty * $rate, 2);
            $gst = Gst::split($taxable, $grn->supply_type, (float) $tax?->cgst_rate, (float) $tax?->sgst_rate, (float) $tax?->igst_rate);
            $line = new GrnLine($gst + [
                'line_no' => $n,
                'purchase_order_id' => $pl?->purchase_order_id,
                'purchase_order_line_id' => $pl?->id,
                'item_id' => $item->id,
                'description' => $pl?->description ?: $item->description,
                'hsn_code' => $pl?->hsn_code ?: $item->hsn?->code,
                'quantity' => $qty,
                'uom_id' => $pl?->uom_id ?: $item->uom_id,
                'rate' => $rate,
                'taxable_value' => $taxable,
                'tax_structure_id' => $tax?->id,
                'total' => round($taxable + $gst['cgst_amount'] + $gst['sgst_amount'] + $gst['igst_amount'], 2),
            ]);
            $line->grn_id = $grn->id;
            $line->save();
            foreach ($sum as $k => $v) {
                $sum[$k] = $v + (float) $line->{$k};
            }
        }
        foreach ($sum as $k => $v) {
            $grn->{$k} = round($v, 2);
        }
        $grn->grand_total = round($sum['taxable_value'] + $sum['cgst_amount'] + $sum['sgst_amount'] + $sum['igst_amount'], 2);
        $grn->saveQuietly();
    }

    /**
     * What this GRN holds received on PO lines now: [purchase_order_line_id => quantity]. A live, matched Purchase GRN
     * holds its invoice quantity less what quality rejected (that was given back); anything else holds nothing.
     */
    private function poEffects(Grn $grn): array
    {
        if ($grn->status !== Grn::SAVED || ! $grn->isMatched() || ! $grn->isPurchase()) {
            return [];
        }
        $out = [];
        foreach (GrnLine::where('grn_id', $grn->id)->whereNotNull('purchase_order_line_id')->get(['purchase_order_line_id', 'quantity', 'rejected_quantity']) as $l) {
            $out[$l->purchase_order_line_id] = round(($out[$l->purchase_order_line_id] ?? 0) + (float) $l->quantity - (float) $l->rejected_quantity, 3);
        }

        return $out;
    }

    private function delta(array $old, array $new): array
    {
        $out = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $id) {
            $d = round(($new[$id] ?? 0) - ($old[$id] ?? 0), 3);
            if ($d != 0.0) {
                $out[$id] = $d;
            }
        }

        return $out;
    }

    /** Change PO received quantities by [purchase_order_line_id => ±qty], one receive() per PO in PO id order. */
    private function applyReceive(Grn $grn, array $delta, User $actor): void
    {
        $delta = array_filter($delta, fn ($q) => round((float) $q, 3) != 0.0);
        if (! $delta) {
            return;
        }
        $byPo = PurchaseOrderLine::whereIn('id', array_keys($delta))->pluck('purchase_order_id', 'id');
        $groups = [];
        foreach ($delta as $lineId => $q) {
            $groups[(int) $byPo[$lineId]][(int) $lineId] = $q;
        }
        ksort($groups);
        foreach ($groups as $poId => $qty) {
            ksort($qty);
            $this->orders->receive(PurchaseOrder::withTrashed()->findOrFail($poId), $qty, $actor);
        }
    }

    /** Accepted quantity per item id, in item order (the order stock balance rows are locked and written in). */
    private function acceptedByItem(Grn $grn): array
    {
        $qty = GrnLine::where('grn_id', $grn->id)->get(['item_id', 'accepted_quantity'])->groupBy('item_id')
            ->map(fn ($g) => round((float) $g->sum('accepted_quantity'), 3))->filter(fn ($q) => $q > 0)->all();
        ksort($qty);

        return $qty;
    }

    private function attributes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(Grn::FIELDS));
        foreach (['invoice_date', 'eway_bill_date'] as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = ($out[$f] ?? '') !== '' ? Dates::toDb($out[$f]) : null;
            }
        }
        foreach (['transporter_id', 'customer_id', 'sales_order_id', 'vehicle_no', 'eway_bill_no', 'remarks'] as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = ($out[$f] ?? '') !== '' ? $out[$f] : null;
            }
        }
        if (isset($out['vehicle_no'])) {
            $out['vehicle_no'] = strtoupper($out['vehicle_no']);
        }
        if (isset($out['invoice_no'])) {
            $out['invoice_no'] = trim($out['invoice_no']);
        }
        if (! empty($out['sales_order_id']) && empty($out['customer_id'])) {
            $out['sales_order_id'] = null;
        }

        return $out;
    }

    /** The supplier must still exist and be a Supplier or Both party (the GRN's own may since have become inactive). */
    private function party(int $id, ?int $current = null): Party
    {
        $p = Party::withTrashed()->find($id);
        if (! $p || ! in_array($p->type, ['Supplier', 'Both'], true) || ($id !== $current && ($p->trashed() || $p->status !== 'Active'))) {
            throw ValidationException::withMessages(['party_id' => 'This supplier was deleted or changed while you were working. Choose another supplier.']);
        }

        return $p;
    }

    private function lock(Grn $grn): Grn
    {
        return Grn::whereKey($grn->id)->lockForUpdate()->firstOrFail();
    }
}
