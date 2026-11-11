<?php

namespace App\Services;

use App\Models\ApprovalRule;
use App\Models\CustomerPo;
use App\Models\FinancialYear;
use App\Models\Item;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Unit;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales orders (SALES tab): create, change, delete, import, and the approval steps (D3). Numbers come from
 * NumberSeries (SO/26-27/0001, D16). Each operation writes its activity log row in the same transaction.
 * A line already billed keeps its part, and its quantity cannot go below what was billed (qty lock, D6).
 */
class SalesOrderService
{
    private const ATTEMPTS = 3;

    public function __construct(private readonly NumberSeries $numbers)
    {
    }

    /** $fromPo (02/11/2026): the customer PO the order is made from (Create Sales Order); see createFromCustomerPo(). */
    public function create(array $data, User $actor, Unit $unit, FinancialYear $year, ?string $number = null, ?CustomerPo $fromPo = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $actor, $unit, $year, $number, $fromPo) {
            $this->lockParty((int) $data['party_id']);
            $this->checkPoNumber((int) $data['party_id'], $data['po_number'] ?? null, null);
            $so = new SalesOrder($this->attributes($data));
            $so->customer_po_id = $fromPo?->id;
            $so->unit_id = $unit->id;
            $so->financial_year_id = $year->id;
            $so->so_no = $number ?? $this->numbers->next('sales_order', $unit, $year);
            $so->status = SalesOrder::DRAFT;
            $so->created_by = $actor->id;
            $so->updated_by = $actor->id;
            $so->save();
            $this->saveLines($so, $data['lines']);
            ActivityLogger::log('sales_order.created', $so, 'Added '.$so->activityLabel().' for '.$so->party->name, [
                'fields' => $this->snapshot($so),
                'items' => $this->lineSummary($so),
            ]);

            return $so;
        }, self::ATTEMPTS);
    }

    /**
     * Create Sales Order from a customer PO (02/11/2026). The order is a Draft with the PO's customer, PO no, dates and
     * items (quantity and rate as on the PO); the user checks it and sends it for approval as usual. The order's own
     * rules apply: an active customer, the PO no not on another order of the party (S-5, same message), and each part an
     * active sales item of the customer or of no party (S-4). One active order per PO: the PO row is locked, so two
     * clicks at once queue and the second is refused. Both records get an activity log row in the same transaction.
     */
    public function createFromCustomerPo(CustomerPo $po, User $actor, FinancialYear $year): SalesOrder
    {
        return DB::transaction(function () use ($po, $actor, $year) {
            $po = CustomerPo::whereKey($po->id)->lockForUpdate()->first();
            if (! $po) {
                throw ValidationException::withMessages(['sales_order' => 'This PO was deleted while you were working.']);
            }
            if ($so = SalesOrder::where('customer_po_id', $po->id)->first()) {
                throw ValidationException::withMessages(['sales_order' => 'Sales order '.$so->so_no.' is already made from this PO.']);
            }
            if ($why = self::whyNoOrderFrom($po)) {
                throw ValidationException::withMessages(['sales_order' => $why]);
            }
            $po->load(['party', 'lines.item', 'unit']);
            $party = $po->party;
            if ($party->trashed() || $party->status !== 'Active' || ! in_array($party->type, ['Customer', 'Both'], true)) {
                throw ValidationException::withMessages(['sales_order' => $party->name.' is not an active Customer (or Both) in the Party master, so a sales order cannot be made for it.']);
            }
            $ok = Item::whereIn('id', $po->lines->pluck('item_id'))->forSale()
                ->where(fn ($p) => $p->whereNull('party_id')->orWhere('party_id', $party->id))->pluck('id')->all();
            foreach ($po->lines as $l) {
                if (! in_array($l->item_id, $ok, true)) {
                    throw ValidationException::withMessages(['sales_order' => 'Part '.($l->item?->item_no ?? '#'.$l->item_id).' is not an active sales item of '.$party->name.', so the sales order cannot be made. Change the PO\'s items first.']);
                }
            }
            $start = $po->po_date ?? $po->valid_from ?? today();
            // The order's end date cannot be before its start date: a PO valid up to a day before its PO date starts then.
            if ($po->valid_to && $po->valid_to->lt($start)) {
                $start = $po->valid_from && $po->valid_from->lte($po->valid_to) ? $po->valid_from : $po->valid_to;
            }
            // Each order line needs a delivery date: the PO's end date, or else its start date, to be checked by the user.
            $delivery = $po->valid_to ?? $start;
            $so = $this->create([
                'party_id' => $party->id,
                'po_number' => $po->po_no,
                'start_date' => Dates::show($start),
                'end_date' => $po->valid_to ? Dates::show($po->valid_to) : null,
                'revision_no' => null,
                'revision_date' => null,
                'lines' => $po->lines->map(fn ($l) => [
                    'item_id' => $l->item_id,
                    'quantity' => (string) $l->quantity,
                    'rate' => (string) $l->rate,
                    'delivery_date' => Dates::show($delivery),
                ])->all(),
            ], $actor, $po->unit, $year, null, $po);
            ActivityLogger::log('customer_po.sales_order_made', $po, 'Made '.$so->activityLabel().' from '.$po->activityLabel(), [
                'Sales order' => ['old' => null, 'new' => $so->so_no],
            ]);

            return $so;
        }, self::ATTEMPTS);
    }

    /** Why a sales order cannot be made from this PO yet (02/11/2026), or null; the PO page shows it under the button. */
    public static function whyNoOrderFrom(CustomerPo $po): ?string
    {
        if (! $po->party_id) {
            // (03/11/2026) The PO form has no Customer field since the Separate PO menu was removed: the PO takes its
            // customer from its quotation, so the old "choose it as the PO's customer" pointed to nothing.
            return 'Add this client to the Party master first. The PO takes its customer from its quotation.';
        }
        if (! $po->lines()->exists()) {
            return 'Add items to the PO first.';
        }

        return null;
    }

    public function update(SalesOrder $so, array $data, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($so, $data, $actor) {
            $so = SalesOrder::whereKey($so->id)->lockForUpdate()->firstOrFail();
            if (! $so->isEditableBy($actor)) {
                throw ValidationException::withMessages(['form' => $so->so_no.' is '.$so->status.', so only an Admin can change it.']);
            }
            $billed = $so->invoices()->where('status', 'Active')->exists();
            if ($billed && array_key_exists('party_id', $data) && (int) $data['party_id'] !== $so->party_id) {
                throw ValidationException::withMessages(['party_id' => 'Invoices are made against this order, so its party cannot change.']);
            }
            $this->lockParty((int) ($data['party_id'] ?? $so->party_id));
            if (array_key_exists('po_number', $data)) {
                $this->checkPoNumber((int) ($data['party_id'] ?? $so->party_id), $data['po_number'], $so->id);
            }
            $so->load('lines.item', 'party');
            $before = $this->snapshot($so);
            $beforeLines = $this->lineSummary($so);

            $so->fill($this->attributes($data));
            // A rejected order that is changed starts again as a Draft.
            if ($so->status === SalesOrder::REJECTED) {
                $so->status = SalesOrder::DRAFT;
                $so->status_reason = null;
            }
            $so->updated_by = $actor->id;
            $so->save();
            $this->saveLines($so, $data['lines']);
            $so->unsetRelations();

            $changes = ActivityLogger::diff($before, $this->snapshot($so));
            if ($lineChanges = ActivityLogger::listDiff($beforeLines, $this->lineSummary($so))) {
                $changes['items'] = $lineChanges;
            }
            if ($changes) {
                ActivityLogger::log('sales_order.updated', $so, 'Updated '.$so->activityLabel(), $changes);
            }

            return $so;
        }, self::ATTEMPTS);
    }

    /** An order with invoices cannot be deleted; an Approved or Pending one only by an Admin. */
    public function delete(SalesOrder $so, User $actor): void
    {
        DB::transaction(function () use ($so, $actor) {
            $so = SalesOrder::whereKey($so->id)->lockForUpdate()->firstOrFail();
            if ($n = $so->invoices()->count()) {
                throw ValidationException::withMessages(['delete' => $so->so_no.' has '.$n.' invoice(s), so it cannot be deleted.']);
            }
            if (! $so->isEditableBy($actor)) {
                throw ValidationException::withMessages(['delete' => $so->so_no.' is '.$so->status.', so only an Admin can delete it.']);
            }
            ActivityLogger::log('sales_order.deleted', $so, 'Deleted '.$so->activityLabel(), ['fields' => $this->snapshot($so), 'items' => $this->lineSummary($so)]);
            $so->delete();
        }, self::ATTEMPTS);
    }

    /**
     * Send for approval (D3). Without a rule, or at or below the rule's amount, the order is approved at once;
     * otherwise it waits for someone with Sales order approval · Edit.
     */
    public function submit(SalesOrder $so, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($so, $actor) {
            $so = SalesOrder::whereKey($so->id)->lockForUpdate()->firstOrFail();
            if (! in_array($so->status, [SalesOrder::DRAFT, SalesOrder::REJECTED], true)) {
                throw ValidationException::withMessages(['status' => $so->so_no.' is already '.$so->status.'.']);
            }
            if (! $so->lines()->exists()) {
                throw ValidationException::withMessages(['status' => 'Add at least one item before sending for approval.']);
            }
            $rule = ApprovalRule::for('sales_order');
            $amount = (float) $so->total_value;
            // The limit is in rupees; an export customer's order (foreign currency) always goes to an approver.
            if ($rule->needsApproval($amount, ! $so->party?->isForeign())) {
                $this->addApproval($so, 'Submitted', $actor, null);
                $this->changeStatus($so, SalesOrder::PENDING, null, $actor, 'Sent '.$so->activityLabel().' for approval');
            } else {
                $why = $rule->is_required ? 'total at or below '.Money::rupees($rule->approve_above_amount, 2) : 'approval not required';
                $this->addApproval($so, 'Approved automatically', $actor, ucfirst($why));
                $this->changeStatus($so, SalesOrder::APPROVED, null, $actor, 'Approved '.$so->activityLabel().' automatically ('.$why.')');
            }

            return $so;
        }, self::ATTEMPTS);
    }

    public function approve(SalesOrder $so, User $actor, ?string $remarks): SalesOrder
    {
        return DB::transaction(function () use ($so, $actor, $remarks) {
            $so = $this->lockPending($so);
            $this->addApproval($so, 'Approved', $actor, $remarks);
            $this->changeStatus($so, SalesOrder::APPROVED, null, $actor, 'Approved '.$so->activityLabel());

            return $so;
        }, self::ATTEMPTS);
    }

    public function reject(SalesOrder $so, User $actor, string $reason): SalesOrder
    {
        return DB::transaction(function () use ($so, $actor, $reason) {
            $so = $this->lockPending($so);
            $this->addApproval($so, 'Rejected', $actor, $reason);
            $this->changeStatus($so, SalesOrder::REJECTED, $reason, $actor, 'Rejected '.$so->activityLabel().': '.$reason);

            return $so;
        }, self::ATTEMPTS);
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(SalesOrder $so): array
    {
        $so->loadMissing('party');

        return [
            'Party name' => $so->party?->name,
            'PO number' => $so->po_number,
            'Start date' => Dates::show($so->start_date),
            'End date' => Dates::show($so->end_date),
            'Rev no' => $so->revision_no,
            'Rev date' => Dates::show($so->revision_date),
            'Total' => $so->total_value,
            'Status' => $so->status,
        ];
    }

    /** One readable line per item, for the log. */
    public function lineSummary(SalesOrder $so): array
    {
        return $so->lines()->with('item:id,item_no')->get()
            ->map(fn (SalesOrderLine $l) => ($l->item?->item_no ?? '?').' × '.rtrim(rtrim((string) $l->quantity, '0'), '.')
                .' @ '.rtrim(rtrim((string) $l->rate, '0'), '.').' by '.Dates::show($l->delivery_date))->all();
    }

    private function attributes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(SalesOrder::FIELDS));
        foreach (['start_date', 'end_date', 'revision_date'] as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = Dates::toDb($out[$f]);
            }
        }

        return $out;
    }

    /**
     * Lines are matched by id, so a line keeps its id (and its billed quantity) when the order is changed.
     * A billed line cannot be removed, change its part, or drop below the billed quantity.
     */
    private function saveLines(SalesOrder $so, array $lines): void
    {
        $existing = SalesOrderLine::where('sales_order_id', $so->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $items = Item::with('currency:id,code')->whereIn('id', array_column($lines, 'item_id'))->get(['id', 'item_no', 'price', 'currency_id'])->keyBy('id');
        $partyCurrency = Party::with('currency:id,code')->find($so->party_id)?->currency?->code ?? 'INR';
        $keep = [];
        $total = 0.0;
        foreach (array_values($lines) as $i => $l) {
            $line = isset($l['id']) && $existing->has((int) $l['id']) ? $existing[(int) $l['id']] : new SalesOrderLine();
            $qty = round((float) $l['quantity'], 3);
            // Rate left blank: the Item master price (D14), only when the item is priced in the party's currency (M3).
            if (($l['rate'] ?? '') === '' || $l['rate'] === null) {
                $item = $items->get((int) $l['item_id']);
                $itemCurrency = $item?->currency?->code ?? 'INR';
                if ($item && $itemCurrency !== $partyCurrency) {
                    throw ValidationException::withMessages(["lines.$i.rate" => 'Item '.($i + 1).' ('.$item->item_no.") is priced in $itemCurrency. Enter the rate in $partyCurrency."]);
                }
                $rate = (float) ($item?->price ?? 0);
            } else {
                $rate = round((float) $l['rate'], 4);
            }
            if ($line->exists && (float) $line->invoiced_quantity > 0) {
                // Invoices keep the rate they billed at, so a billed line keeps its rate too (database review SL-S3).
                if (abs($rate - (float) $line->rate) > 0.00005) {
                    throw ValidationException::withMessages(["lines.$i.rate" => 'Item '.($i + 1).' is already invoiced at '.rtrim(rtrim((string) $line->rate, '0'), '.').', so its rate cannot change.']);
                }
                if ((int) $l['item_id'] !== $line->item_id) {
                    throw ValidationException::withMessages(["lines.$i.item_id" => 'Item '.($i + 1).' is already invoiced, so its part cannot change.']);
                }
                if ($qty < (float) $line->invoiced_quantity) {
                    throw ValidationException::withMessages(["lines.$i.quantity" => 'Item '.($i + 1).': '.Money::indian($line->invoiced_quantity, 3).' is already invoiced, so the quantity cannot be lower.']);
                }
            }
            $line->fill([
                'line_no' => $i + 1,
                'item_id' => (int) $l['item_id'],
                'quantity' => $qty,
                'rate' => $rate,
                'amount' => round($qty * $rate, 2),
                'delivery_date' => Dates::toDb($l['delivery_date']),
            ]);
            $line->sales_order_id = $so->id;
            $line->save();
            $keep[] = $line->id;
            $total += round($qty * $rate, 2);
        }
        foreach ($existing->except($keep) as $gone) {
            if ((float) $gone->invoiced_quantity > 0) {
                throw ValidationException::withMessages(['lines' => 'Item '.$gone->line_no.' is already invoiced, so it cannot be removed.']);
            }
            $gone->delete();
        }
        $so->total_value = round($total, 2);
        $so->saveQuietly();
    }

    /**
     * The same customer PO cannot be on two orders. Checked again here under the party's row lock, so two saves of the
     * same PO at the same moment queue and the second one is refused (code review S3, database review SL-S2).
     */
    private function checkPoNumber(int $partyId, ?string $po, ?int $exceptId): void
    {
        if ($po !== null && $po !== '' && SalesOrder::where('party_id', $partyId)->where('po_number', $po)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) {
            throw ValidationException::withMessages(['po_number' => 'This PO number is already on another sales order of this party.']);
        }
    }

    /** The party must still exist; its row is locked for the save, so two orders of one customer queue (PO check). */
    private function lockParty(int $id): Party
    {
        $p = Party::whereKey($id)->lockForUpdate()->first();
        if (! $p) {
            throw ValidationException::withMessages(['party_id' => 'This party was deleted while you were working. Choose another party.']);
        }

        return $p;
    }

    private function lockPending(SalesOrder $so): SalesOrder
    {
        $so = SalesOrder::whereKey($so->id)->lockForUpdate()->firstOrFail();
        if ($so->status !== SalesOrder::PENDING) {
            throw ValidationException::withMessages(['status' => $so->so_no.' is not waiting for approval (it is '.$so->status.').']);
        }

        return $so;
    }

    private function changeStatus(SalesOrder $so, string $status, ?string $reason, User $actor, string $description): void
    {
        $old = $so->status;
        $so->status = $status;
        $so->status_reason = $reason;
        $so->updated_by = $actor->id;
        $so->save();
        ActivityLogger::log('sales_order.status_changed', $so, $description, ['Status' => ['old' => $old, 'new' => $status]]);
    }

    private function addApproval(SalesOrder $so, string $action, User $actor, ?string $remarks): void
    {
        $so->approvals()->create(['action' => $action, 'amount' => $so->total_value, 'remarks' => $remarks, 'acted_by' => $actor->id, 'acted_at' => now()]);
    }
}
