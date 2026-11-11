<?php

namespace App\Services;

use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase orders (PURCHASE tab): create, change, delete, cancel, the approval steps (D3) and receive() for GRN entry.
 * Numbers come from NumberSeries (PO/26-27/0001, D16). Each operation runs in one transaction with the PO row locked
 * (lockForUpdate) and writes its activity log row in that transaction. GST and every total are worked out here from
 * the masters, never taken from the page. A line already received on a GRN keeps its item and rate, and its quantity
 * cannot go below what was received (as the qty lock of sales orders, D6).
 */
class PurchaseOrderService
{
    private const ATTEMPTS = 3;

    public function __construct(private readonly NumberSeries $numbers)
    {
    }

    public function create(array $data, User $actor, Unit $unit, FinancialYear $year): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor, $unit, $year) {
            $party = $this->lockParty((int) $data['party_id']);
            $po = new PurchaseOrder($this->attributes($data));
            $po->unit_id = $unit->id;
            $po->financial_year_id = $year->id;
            // Always the supplier's currency (review fix): GST, Import and the approval limit follow the supplier.
            $po->currency_id = $party->currency_id ?? Currency::defaultId();
            $po->po_no = $this->numbers->next('purchase_order', $unit, $year);
            $po->status = PurchaseOrder::DRAFT;
            $po->supply_type = self::supplyType($party);
            $po->created_by = $actor->id;
            $po->updated_by = $actor->id;
            $po->save();
            $this->saveLines($po, $data['lines']);
            ActivityLogger::log('purchase_order.created', $po, 'Added '.$po->activityLabel().' for '.$party->name, [
                'fields' => $this->snapshot($po),
                'items' => $this->lineSummary($po),
            ]);

            return $po;
        }, self::ATTEMPTS);
    }

    public function update(PurchaseOrder $po, array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data, $actor) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            if (! $po->isEditableBy($actor)) {
                throw ValidationException::withMessages(['form' => $po->po_no.' is '.$po->status.', so '.(in_array($po->status, [PurchaseOrder::PENDING, PurchaseOrder::APPROVED, PurchaseOrder::PARTLY_RECEIVED], true) ? 'only an Admin can change it.' : 'it cannot be changed.')]);
            }
            $partyId = (int) ($data['party_id'] ?? $po->party_id);
            if ($partyId !== $po->party_id && $po->hasReceipts()) {
                throw ValidationException::withMessages(['party_id' => 'Goods are received against this PO, so its supplier cannot change.']);
            }
            $party = $this->lockParty($partyId);
            $po->load('lines.item', 'lines.uom', 'lines.taxStructure', 'party', 'currency', 'transporter');
            $before = $this->snapshot($po);
            $beforeLines = $this->lineSummary($po);

            $oldValue = (float) $po->taxable_value;
            $oldParty = $po->party_id;
            $po->fill($this->attributes($data));
            $po->currency_id = $party->currency_id ?? Currency::defaultId();
            $po->supply_type = self::supplyType($party);
            // A rejected PO that is changed starts again as a Draft.
            if ($po->status === PurchaseOrder::REJECTED) {
                $po->status = PurchaseOrder::DRAFT;
                $po->status_reason = null;
            }
            $po->updated_by = $actor->id;
            $po->save();
            $this->saveLines($po, $data['lines']);
            $po->unsetRelations();

            $changes = ActivityLogger::diff($before, $this->snapshot($po));
            if ($lineChanges = ActivityLogger::listDiff($beforeLines, $this->lineSummary($po))) {
                $changes['items'] = $lineChanges;
            }
            if ($changes) {
                ActivityLogger::log('purchase_order.updated', $po, 'Updated '.$po->activityLabel(), $changes);
            }

            if (in_array($po->status, [PurchaseOrder::APPROVED, PurchaseOrder::PARTLY_RECEIVED], true)) {
                // Admin changed an approved PO (review fix): a higher value or another supplier that the approval rule
                // still needs approval for goes back to Pending approval; otherwise the receipt status is worked out
                // again as receive() does (e.g. a line lowered to what was received makes the PO Received).
                $rule = ApprovalRule::for('purchase_order');
                $grew = (float) $po->taxable_value > $oldValue + 0.004 || $po->party_id !== $oldParty;
                if ($grew && $rule->needsApproval((float) $po->taxable_value, ! $party->isForeign())) {
                    $po->approved_by = null;
                    $po->approved_at = null;
                    $this->addApproval($po, 'Submitted', $actor, 'Changed after approval: value '.Money::rupees($oldValue, 2).' to '.Money::rupees($po->taxable_value, 2));
                    $this->changeStatus($po, PurchaseOrder::PENDING, null, $actor, 'Sent '.$po->activityLabel().' back for approval after a change by '.$actor->name);
                } else {
                    $this->refreshReceiptStatus($po, $actor);
                }
            }

            return $po;
        }, self::ATTEMPTS);
    }

    /** As a sales order: nothing received, and a Pending or Approved PO only by an Admin. The number is not reused. */
    public function delete(PurchaseOrder $po, User $actor): void
    {
        DB::transaction(function () use ($po, $actor) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            if ($po->hasReceipts()) {
                throw ValidationException::withMessages(['delete' => 'Goods are received against '.$po->po_no.', so it cannot be deleted.']);
            }
            // Only a Draft or Rejected PO, for everyone, Admin included (review fix); a sent or approved PO is cancelled.
            if (! in_array($po->status, [PurchaseOrder::DRAFT, PurchaseOrder::REJECTED], true)) {
                throw ValidationException::withMessages(['delete' => $po->po_no.' is '.$po->status.', so it cannot be deleted'.($po->status === PurchaseOrder::CANCELLED ? '; it is kept as a record.' : '. Cancel it with a reason instead.')]);
            }
            ActivityLogger::log('purchase_order.deleted', $po, 'Deleted '.$po->activityLabel(), ['fields' => $this->snapshot($po), 'items' => $this->lineSummary($po)]);
            $po->delete();
        }, self::ATTEMPTS);
    }

    /**
     * Cancel (for a PO already sent or approved): the PO stays as a record with its number and the reason, and is left
     * out of open POs. Not once anything is received on it (that is a short-close, built with GRN).
     */
    public function cancel(PurchaseOrder $po, User $actor, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor, $reason) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            // Pending or Approved only; a Draft or Rejected PO is deleted instead (review fix).
            if (! in_array($po->status, [PurchaseOrder::PENDING, PurchaseOrder::APPROVED], true)) {
                throw ValidationException::withMessages(['status' => $po->po_no.' is '.$po->status.', so it cannot be cancelled'.(in_array($po->status, [PurchaseOrder::DRAFT, PurchaseOrder::REJECTED], true) ? '; delete it instead.' : '.')]);
            }
            if ($po->hasReceipts()) {
                throw ValidationException::withMessages(['status' => 'Goods are received against '.$po->po_no.', so it cannot be cancelled.']);
            }
            // approvals.action has no "Cancelled" (enum); the activity log keeps who cancelled and why.
            $this->changeStatus($po, PurchaseOrder::CANCELLED, $reason, $actor, 'Cancelled '.$po->activityLabel().': '.$reason);

            return $po;
        }, self::ATTEMPTS);
    }

    /**
     * Send for approval (D3). At or below the rule's amount (taxable value, in rupees) the PO is approved at once;
     * otherwise it waits for someone with Purchase order approval · Edit. A PO in another currency always waits.
     */
    public function submit(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            if (! in_array($po->status, [PurchaseOrder::DRAFT, PurchaseOrder::REJECTED], true)) {
                throw ValidationException::withMessages(['status' => $po->po_no.' is already '.$po->status.'.']);
            }
            if (! $po->lines()->exists()) {
                throw ValidationException::withMessages(['status' => 'Add at least one item before sending for approval.']);
            }
            $rule = ApprovalRule::for('purchase_order');
            // In rupees or not by the supplier (Party master), never by a currency picked on the page (review fix).
            $po->loadMissing('party.currency');
            if ($rule->needsApproval((float) $po->taxable_value, ! $po->party?->isForeign())) {
                $this->addApproval($po, 'Submitted', $actor, null);
                $this->changeStatus($po, PurchaseOrder::PENDING, null, $actor, 'Sent '.$po->activityLabel().' for approval');
            } else {
                $why = $rule->is_required ? 'value at or below '.Money::rupees($rule->approve_above_amount, 2) : 'approval not required';
                $this->addApproval($po, 'Approved automatically', $actor, ucfirst($why));
                $po->approved_at = now();
                $this->changeStatus($po, PurchaseOrder::APPROVED, null, $actor, 'Approved '.$po->activityLabel().' automatically ('.$why.')');
            }

            return $po;
        }, self::ATTEMPTS);
    }

    public function approve(PurchaseOrder $po, User $actor, ?string $remarks): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor, $remarks) {
            $po = $this->lockPending($po);
            $this->addApproval($po, 'Approved', $actor, $remarks);
            $po->approved_by = $actor->id;
            $po->approved_at = now();
            $this->changeStatus($po, PurchaseOrder::APPROVED, null, $actor, 'Approved '.$po->activityLabel());
            // A PO sent back for approval after goods were received returns to its receipt status.
            $this->refreshReceiptStatus($po, $actor);

            return $po;
        }, self::ATTEMPTS);
    }

    public function reject(PurchaseOrder $po, User $actor, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor, $reason) {
            $po = $this->lockPending($po);
            $this->addApproval($po, 'Rejected', $actor, $reason);
            $this->changeStatus($po, PurchaseOrder::REJECTED, $reason, $actor, 'Rejected '.$po->activityLabel().': '.$reason);

            return $po;
        }, self::ATTEMPTS);
    }

    /**
     * GRN contract: add (or, for a GRN changed or cancelled, take back with a negative number) received quantities on
     * PO lines, [purchase_order_line_id => quantity]. Call it inside the GRN's own transaction. The PO row and then its
     * lines (in id order) are locked, so two GRNs at once cannot receive more than ordered. Receiving needs an Approved
     * or Partly received PO; a line can never go below 0 or above its quantity. The status follows: Partly received,
     * Received, or back to Approved when everything is taken back.
     */
    public function receive(PurchaseOrder $po, array $quantities, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $quantities, $actor) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            $adding = collect($quantities)->contains(fn ($q) => (float) $q > 0);
            if ($adding && ! in_array($po->status, PurchaseOrder::RECEIVABLE, true)) {
                throw ValidationException::withMessages(['purchase_order_id' => $po->po_no.' is '.$po->status.'; goods can be received only against an Approved PO.']);
            }
            $lines = PurchaseOrderLine::where('purchase_order_id', $po->id)->whereIn('id', array_map('intval', array_keys($quantities)))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($quantities as $lineId => $qty) {
                $line = $lines->get((int) $lineId);
                if (! $line) {
                    throw ValidationException::withMessages(['lines' => 'A line is not on '.$po->po_no.'.']);
                }
                $new = round((float) $line->received_quantity + (float) $qty, 3);
                if ($new < 0) {
                    throw ValidationException::withMessages(['lines' => 'Item '.$line->line_no.' of '.$po->po_no.': only '.Money::qty($line->received_quantity).' is received, so that much cannot be taken back.']);
                }
                if ($new > (float) $line->quantity) {
                    throw ValidationException::withMessages(['lines' => 'Item '.$line->line_no.' of '.$po->po_no.': only '.Money::qty($line->pending()).' is pending, so '.Money::qty($qty).' cannot be received.']);
                }
                $line->received_quantity = $new;
                $line->save();
            }
            $this->refreshReceiptStatus($po, $actor);

            return $po;
        }, self::ATTEMPTS);
    }

    /**
     * Status from the received quantities, for a PO that is Approved, Partly received or Received: Received when every
     * line is fully received, Partly received when something is, else Approved. Used by receive(), an Admin edit and
     * approve(). Other statuses are left as they are.
     */
    private function refreshReceiptStatus(PurchaseOrder $po, User $actor): void
    {
        if (! in_array($po->status, [PurchaseOrder::APPROVED, PurchaseOrder::PARTLY_RECEIVED, PurchaseOrder::RECEIVED], true)) {
            return;
        }
        $all = PurchaseOrderLine::where('purchase_order_id', $po->id)->get(['quantity', 'received_quantity']);
        $status = match (true) {
            $all->isNotEmpty() && $all->every(fn ($l) => (float) $l->received_quantity >= (float) $l->quantity) => PurchaseOrder::RECEIVED,
            $all->contains(fn ($l) => (float) $l->received_quantity > 0) => PurchaseOrder::PARTLY_RECEIVED,
            default => PurchaseOrder::APPROVED,
        };
        if ($status !== $po->status) {
            $this->changeStatus($po, $status, null, $actor, ucfirst($po->activityLabel()).' is '.strtolower($status));
        }
    }

    /** GST of the PO by the supplier (D14): Intra-state, Inter-state, or Import for a supplier in another currency. */
    public static function supplyType(Party $party): string
    {
        $type = Gst::supplyType($party, Company::current());

        return $type === Gst::EXPORT ? PurchaseOrder::IMPORT : $type;
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(PurchaseOrder $po): array
    {
        $po->loadMissing('party', 'currency', 'transporter');

        return [
            'Party name' => $po->party?->name,
            'PO date' => Dates::show($po->po_date),
            'Delivery date' => Dates::show($po->delivery_date),
            'Payment terms' => $po->payment_terms,
            'Currency' => $po->currency?->code,
            'Transporter' => $po->transporter?->name,
            'Billing address' => $po->billing_address,
            'Shipping address' => $po->shipping_address,
            'Remarks' => $po->remarks,
            'Taxable value' => $po->taxable_value,
            'Total' => $po->grand_total,
            'Status' => $po->status,
        ];
    }

    /** One readable line per item, for the log. */
    public function lineSummary(PurchaseOrder $po): array
    {
        $n = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');

        return $po->lines()->with(['item:id,item_no', 'uom:id,code', 'taxStructure:id,name'])->get()
            ->map(fn (PurchaseOrderLine $l) => ($l->item?->item_no ?? '?').' × '.$n($l->quantity).' '.$l->uom?->code.' @ '.$n($l->rate)
                .((float) $l->discount_percent ? ' less '.$n($l->discount_percent).'%' : '').' · '.$l->taxStructure?->name
                .($l->delivery_date ? ' by '.Dates::show($l->delivery_date) : ''))->all();
    }

    private function attributes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(PurchaseOrder::FIELDS));
        foreach (['po_date', 'delivery_date'] as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = Dates::toDb($out[$f]);
            }
        }
        foreach (['currency_id', 'transporter_id'] as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = $out[$f] ?: null;
            }
        }

        return $out;
    }

    /**
     * Lines are matched by id, so a line keeps its id (and its received quantity) when the PO is changed. Blank UOM,
     * tax, description or rate take the Item master's; the rate only when the item is priced in the PO's currency.
     * GST per line at the tax structure's rates for the PO's supply type, each rounded to paise; totals are their sums.
     */
    private function saveLines(PurchaseOrder $po, array $lines): void
    {
        $existing = PurchaseOrderLine::where('purchase_order_id', $po->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $items = Item::with(['currency:id,code', 'hsn:id,code'])->whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');
        $taxIds = array_merge(array_filter(array_column($lines, 'tax_structure_id')), $items->pluck('tax_structure_id')->all());
        $taxes = TaxStructure::whereIn('id', $taxIds)->get()->keyBy('id');
        $poCurrency = Currency::find($po->currency_id)?->code ?? 'INR';
        $keep = [];
        $sum = ['taxable_value' => 0.0, 'cgst_amount' => 0.0, 'sgst_amount' => 0.0, 'igst_amount' => 0.0];
        foreach (array_values($lines) as $i => $l) {
            $n = $i + 1;
            $item = $items->get((int) $l['item_id']);
            if (! $item) {
                throw ValidationException::withMessages(["lines.$i.item_id" => "Item $n was deleted while you were working. Choose another item."]);
            }
            $line = isset($l['id']) && $existing->has((int) $l['id']) ? $existing[(int) $l['id']] : new PurchaseOrderLine();
            $qty = round((float) $l['quantity'], 3);
            if (($l['rate'] ?? '') === '' || $l['rate'] === null) {
                $itemCurrency = $item->currency?->code ?? 'INR';
                if ($itemCurrency !== $poCurrency) {
                    throw ValidationException::withMessages(["lines.$i.rate" => "Item $n ({$item->item_no}) is priced in $itemCurrency. Enter the rate in $poCurrency."]);
                }
                $rate = (float) $item->price;
            } else {
                $rate = round((float) $l['rate'], 4);
            }
            // GRN entry: a line a live GRN is entered against keeps its item, even before anything is received (pending match).
            if ($line->exists && (int) $l['item_id'] !== $line->item_id
                && \App\Models\GrnLine::where('purchase_order_line_id', $line->id)->whereHas('grn', fn ($g) => $g->where('status', \App\Models\Grn::SAVED))->exists()) {
                throw ValidationException::withMessages(["lines.$i.item_id" => "Item $n is on a GRN, so its item cannot change."]);
            }
            if ($line->exists && (float) $line->received_quantity > 0) {
                if ((int) $l['item_id'] !== $line->item_id) {
                    throw ValidationException::withMessages(["lines.$i.item_id" => "Item $n is already received, so its item cannot change."]);
                }
                if (abs($rate - (float) $line->rate) > 0.00005) {
                    throw ValidationException::withMessages(["lines.$i.rate" => "Item $n is already received at ".rtrim(rtrim((string) $line->rate, '0'), '.').', so its rate cannot change.']);
                }
                if ($qty < (float) $line->received_quantity) {
                    throw ValidationException::withMessages(["lines.$i.quantity" => "Item $n: ".Money::indian($line->received_quantity, 3).' is already received, so the quantity cannot be lower.']);
                }
            }
            $tax = $taxes->get((int) (($l['tax_structure_id'] ?? '') ?: $item->tax_structure_id));
            if (! $tax) {
                throw ValidationException::withMessages(["lines.$i.tax_structure_id" => "Choose the tax of item $n."]);
            }
            $discount = round((float) ($l['discount_percent'] ?? 0), 2);
            $taxable = round($qty * $rate * (100 - $discount) / 100, 2);
            $gst = Gst::split($taxable, $po->supply_type, (float) $tax->cgst_rate, (float) $tax->sgst_rate, (float) $tax->igst_rate);
            $line->fill($gst + [
                'line_no' => $n,
                'item_id' => $item->id,
                'description' => ($l['description'] ?? '') !== '' ? $l['description'] : $item->description,
                'hsn_code' => $item->hsn?->code,
                'quantity' => $qty,
                'uom_id' => (int) (($l['uom_id'] ?? '') ?: $item->uom_id),
                'rate' => $rate,
                'discount_percent' => $discount,
                'taxable_value' => $taxable,
                'tax_structure_id' => $tax->id,
                'total' => round($taxable + $gst['cgst_amount'] + $gst['sgst_amount'] + $gst['igst_amount'], 2),
                'delivery_date' => ($l['delivery_date'] ?? '') !== '' ? Dates::toDb($l['delivery_date']) : $po->delivery_date?->format('Y-m-d'),
            ]);
            $line->purchase_order_id = $po->id;
            $line->save();
            $keep[] = $line->id;
            foreach ($sum as $k => $v) {
                $sum[$k] = $v + (float) $line->{$k};
            }
        }
        foreach ($existing->except($keep) as $gone) {
            if ((float) $gone->received_quantity > 0) {
                throw ValidationException::withMessages(['lines' => 'Item '.$gone->line_no.' is already received, so it cannot be removed.']);
            }
            // GRN lines point at PO lines (RESTRICT), also those of cancelled or deleted GRNs, which are kept as records.
            $onGrn = \App\Models\GrnLine::where('purchase_order_line_id', $gone->id)->with(['grn' => fn ($g) => $g->withTrashed()])->first();
            if ($onGrn) {
                throw ValidationException::withMessages(['lines' => 'Item '.$gone->line_no.' is on GRN '.($onGrn->grn?->grn_no ?? 'for invoice '.$onGrn->grn?->invoice_no).', so it cannot be removed.']);
            }
            $gone->delete();
        }
        foreach ($sum as $k => $v) {
            $po->{$k} = round($v, 2);
        }
        $po->grand_total = round($sum['taxable_value'] + $sum['cgst_amount'] + $sum['sgst_amount'] + $sum['igst_amount'], 2);
        $po->saveQuietly();
    }

    /** The party must still exist and be a supplier; its row is locked for the save. */
    private function lockParty(int $id): Party
    {
        $p = Party::with('currency')->whereKey($id)->lockForUpdate()->first();
        if (! $p || ! in_array($p->type, ['Supplier', 'Both'], true)) {
            throw ValidationException::withMessages(['party_id' => 'This supplier was deleted or changed while you were working. Choose another supplier.']);
        }

        return $p;
    }

    private function lockPending(PurchaseOrder $po): PurchaseOrder
    {
        $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
        if ($po->status !== PurchaseOrder::PENDING) {
            throw ValidationException::withMessages(['status' => $po->po_no.' is not waiting for approval (it is '.$po->status.').']);
        }

        return $po;
    }

    private function changeStatus(PurchaseOrder $po, string $status, ?string $reason, User $actor, string $description): void
    {
        $old = $po->status;
        $po->status = $status;
        $po->status_reason = $reason;
        $po->updated_by = $actor->id;
        $po->save();
        ActivityLogger::log('purchase_order.status_changed', $po, $description, ['Status' => ['old' => $old, 'new' => $status]]);
    }

    private function addApproval(PurchaseOrder $po, string $action, User $actor, ?string $remarks): void
    {
        $po->approvals()->create(['action' => $action, 'amount' => $po->taxable_value, 'remarks' => $remarks, 'acted_by' => $actor->id, 'acted_at' => now()]);
    }
}
