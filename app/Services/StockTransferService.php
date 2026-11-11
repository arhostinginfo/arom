<?php

namespace App\Services;

use App\Models\ApprovalRule;
use App\Models\FinancialYear;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Unit;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase › Stock transfer (prototype D9): create, change, delete, send for approval, approve, reject, cancel.
 * Numbers come from NumberSeries (ST/26-27/0001, per source unit and year). Every operation writes its activity log
 * row in the same transaction.
 *
 * Stock (architecture review S7): Draft, Pending and Rejected transfers move nothing. On approval the quantities go
 * out of the source unit and into the destination unit through StockLedger (source_type 'stock_transfer'), in the
 * same transaction, after the source balances are read again under a row lock, so two approvals at once can never
 * take more than the source holds. Cancelling an approved transfer takes its movements off the ledger
 * (StockLedger::remove, as a cancelled invoice does), only while the destination still holds those quantities.
 */
class StockTransferService
{
    private const ATTEMPTS = 3;

    public function __construct(private readonly NumberSeries $numbers)
    {
    }

    public function create(array $data, User $actor, FinancialYear $year): StockTransfer
    {
        return DB::transaction(function () use ($data, $actor, $year) {
            $t = new StockTransfer($this->attributes($data));
            $t->financial_year_id = $year->id;
            $t->transfer_no = $this->numbers->next('stock_transfer', Unit::findOrFail($t->from_unit_id), $year);
            $t->status = StockTransfer::DRAFT;
            $t->created_by = $actor->id;
            $t->updated_by = $actor->id;
            $t->save();
            $this->saveLines($t, $data['lines']);
            $this->checkStock($t, false, 'lines');
            ActivityLogger::log('stock_transfer.created', $t, 'Added '.$t->activityLabel().' from '.$t->fromUnit?->name.' to '.$t->toUnit?->name, [
                'fields' => $this->snapshot($t),
                'items' => $this->lineSummary($t),
            ]);

            return $t;
        }, self::ATTEMPTS);
    }

    public function update(StockTransfer $t, array $data, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($t, $data, $actor) {
            $t = $this->lock($t);
            if (! $t->isEditable()) {
                throw ValidationException::withMessages(['form' => $t->transfer_no.' is '.$t->status.', so it cannot be changed.']);
            }
            // The number belongs to the source unit's series, so the source stays as saved.
            if (array_key_exists('from_unit_id', $data) && (int) $data['from_unit_id'] !== $t->from_unit_id) {
                throw ValidationException::withMessages(['from_unit_id' => 'Location from cannot change after saving: '.$t->transfer_no.' is numbered in its series. Cancel it and add a new transfer instead.']);
            }
            $before = $this->snapshot($t);
            $beforeLines = $this->lineSummary($t);

            $t->fill($this->attributes($data));
            // A rejected transfer that is changed starts again as a Draft.
            if ($t->status === StockTransfer::REJECTED) {
                $t->status = StockTransfer::DRAFT;
                $t->status_reason = null;
            }
            $t->updated_by = $actor->id;
            $t->save();
            $this->saveLines($t, $data['lines']);
            $t->unsetRelations();
            $this->checkStock($t, false, 'lines');

            $changes = ActivityLogger::diff($before, $this->snapshot($t));
            if ($lineChanges = ActivityLogger::listDiff($beforeLines, $this->lineSummary($t))) {
                $changes['items'] = $lineChanges;
            }
            if ($changes) {
                ActivityLogger::log('stock_transfer.updated', $t, 'Updated '.$t->activityLabel(), $changes);
            }

            return $t;
        }, self::ATTEMPTS);
    }

    /** Only a Draft or Rejected transfer (it never moved stock) can be deleted; the others are cancelled. */
    public function delete(StockTransfer $t, User $actor): void
    {
        DB::transaction(function () use ($t, $actor) {
            $t = $this->lock($t);
            if (! $t->isEditable()) {
                throw ValidationException::withMessages(['status' => $t->transfer_no.' is '.$t->status.', so it cannot be deleted. Cancel it instead.']);
            }
            ActivityLogger::log('stock_transfer.deleted', $t, 'Deleted '.$t->activityLabel(), ['fields' => $this->snapshot($t), 'items' => $this->lineSummary($t)], $actor);
            $t->delete();
        }, self::ATTEMPTS);
    }

    /**
     * Send for approval. With the Admin › Approval rule switched off the transfer is approved (and the stock moved)
     * at once; otherwise it waits for someone with Stock transfer approval · Edit.
     */
    public function submit(StockTransfer $t, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($t, $actor) {
            $t = $this->lock($t);
            if (! $t->isEditable()) {
                throw ValidationException::withMessages(['status' => $t->transfer_no.' is already '.$t->status.'.']);
            }
            if (! $t->lines()->exists()) {
                throw ValidationException::withMessages(['status' => 'Add at least one item before sending for approval.']);
            }
            if (ApprovalRule::for('stock_transfer')->is_required) {
                $this->checkStock($t, false, 'status');
                $this->addApproval($t, 'Submitted', $actor, null);
                $this->changeStatus($t, StockTransfer::PENDING, null, $actor, 'Sent '.$t->activityLabel().' for approval');
            } else {
                $this->moveStock($t);
                $this->addApproval($t, 'Approved automatically', $actor, 'Approval not required');
                $t->approved_by = $actor->id;
                $t->approved_at = now();
                $this->changeStatus($t, StockTransfer::APPROVED, null, $actor, 'Approved '.$t->activityLabel().' automatically (approval not required); stock moved');
            }

            return $t;
        }, self::ATTEMPTS);
    }

    public function approve(StockTransfer $t, User $actor, ?string $remarks): StockTransfer
    {
        return DB::transaction(function () use ($t, $actor, $remarks) {
            $t = $this->lockPending($t);
            $this->moveStock($t);
            $this->addApproval($t, 'Approved', $actor, $remarks);
            $t->approved_by = $actor->id;
            $t->approved_at = now();
            $this->changeStatus($t, StockTransfer::APPROVED, null, $actor, 'Approved '.$t->activityLabel().'; stock moved from '.$t->fromUnit?->name.' to '.$t->toUnit?->name);

            return $t;
        }, self::ATTEMPTS);
    }

    public function reject(StockTransfer $t, User $actor, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($t, $actor, $reason) {
            $t = $this->lockPending($t);
            $this->addApproval($t, 'Rejected', $actor, $reason);
            $this->changeStatus($t, StockTransfer::REJECTED, $reason, $actor, 'Rejected '.$t->activityLabel().': '.$reason);

            return $t;
        }, self::ATTEMPTS);
    }

    /**
     * Cancel with a reason; the number stays in the series. An approved transfer's movements are taken off the ledger
     * and the balances, which needs the destination to still hold every quantity it received (checked under lock).
     */
    public function cancel(StockTransfer $t, User $actor, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($t, $actor, $reason) {
            $t = $this->lock($t);
            if ($t->status === StockTransfer::CANCELLED) {
                throw ValidationException::withMessages(['status' => $t->transfer_no.' is already cancelled.']);
            }
            $wasApproved = $t->status === StockTransfer::APPROVED;
            // Checked again here under the row lock: the transfer may have been approved after the page's own check.
            if ($wasApproved && ! $actor->can('stock-transfer-approval.edit')) {
                throw ValidationException::withMessages(['status' => $t->transfer_no.' is Approved and its stock has moved, so only an approver can cancel it.']);
            }
            if ($wasApproved) {
                $qty = $this->quantities($t);
                $held = $this->lockBalances([$t->from_unit_id, $t->to_unit_id], array_keys($qty));
                $items = Item::withTrashed()->with('uom:id,code')->whereIn('id', array_keys($qty))->get()->keyBy('id');
                $short = [];
                foreach ($qty as $itemId => $q) {
                    $have = $held[$t->to_unit_id.':'.$itemId] ?? 0.0;
                    if ($have + 0.0005 < $q) {
                        $i = $items->get($itemId);
                        $short[] = ($i?->item_no ?? '?').': '.$t->toUnit?->name.' holds '.Money::qty($have).' '.($i?->uom?->code ?? '').' of the '.Money::qty($q).' received';
                    }
                }
                if ($short) {
                    throw ValidationException::withMessages(['status' => 'The stock received has been used or moved on, so '.$t->transfer_no.' cannot be cancelled. '.implode('; ', $short).'.']);
                }
                StockLedger::remove(StockTransfer::LEDGER_SOURCE, $t->id);
            }
            $t->cancelled_by = $actor->id;
            $t->cancelled_at = now();
            $this->changeStatus($t, StockTransfer::CANCELLED, $reason, $actor, 'Cancelled '.$t->activityLabel().($wasApproved ? ' and returned the stock to '.$t->fromUnit?->name : '').': '.$reason);

            return $t;
        }, self::ATTEMPTS);
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(StockTransfer $t): array
    {
        $t->loadMissing('fromUnit', 'toUnit');

        return [
            'Transfer date' => Dates::show($t->transfer_date),
            'Location from' => $t->fromUnit?->name,
            'Location to' => $t->toUnit?->name,
            'Source type' => $t->source_type,
            'Remarks' => $t->remarks,
            'Status' => $t->status,
        ];
    }

    /** One readable line per item, for the log. */
    public function lineSummary(StockTransfer $t): array
    {
        return $t->lines()->with(['item:id,item_no', 'uom:id,code'])->get()
            ->map(fn (StockTransferLine $l) => ($l->item?->item_no ?? '?').' × '.Money::qty($l->quantity).' '.($l->uom?->code ?? '').($l->remarks ? ' ('.$l->remarks.')' : ''))
            ->map(fn ($s) => trim($s))->all();
    }

    private function attributes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(StockTransfer::FIELDS));
        if (array_key_exists('transfer_date', $out)) {
            $out['transfer_date'] = Dates::toDb($out['transfer_date']);
        }
        foreach (['source_type', 'remarks'] as $f) {
            if (array_key_exists($f, $out) && $out[$f] === '') {
                $out[$f] = null;
            }
        }

        return $out;
    }

    /** Lines are written again on each save (they hold no quantities of other documents); the UOM comes from the Item master. */
    private function saveLines(StockTransfer $t, array $lines): void
    {
        $uoms = Item::withTrashed()->whereIn('id', array_column($lines, 'item_id'))->pluck('uom_id', 'id');
        StockTransferLine::where('stock_transfer_id', $t->id)->delete();
        foreach (array_values($lines) as $i => $l) {
            $line = new StockTransferLine([
                'line_no' => $i + 1,
                'item_id' => (int) $l['item_id'],
                'quantity' => round((float) $l['quantity'], 3),
                'uom_id' => $uoms[(int) $l['item_id']] ?? null,
                'remarks' => ($l['remarks'] ?? '') !== '' ? $l['remarks'] : null,
            ]);
            $line->stock_transfer_id = $t->id;
            $line->save();
        }
    }

    /** Quantity per item id, in item order. */
    private function quantities(StockTransfer $t): array
    {
        $qty = StockTransferLine::where('stock_transfer_id', $t->id)->get(['item_id', 'quantity'])
            ->groupBy('item_id')->map(fn ($g) => round((float) $g->sum('quantity'), 3))->all();
        ksort($qty);

        return $qty;
    }

    /**
     * Each item's quantity must not be more than the stock at the source. On save and submit this reads the balance as
     * it is (a warning that saves a wasted approval); on approval ($locked) the balances are locked first.
     */
    private function checkStock(StockTransfer $t, bool $locked, string $key): void
    {
        $qty = $this->quantities($t);
        if (! $qty) {
            return;
        }
        $have = $locked
            ? collect($this->lockBalances([$t->from_unit_id, $t->to_unit_id], array_keys($qty)))
                ->filter(fn ($q, $k) => str_starts_with($k, $t->from_unit_id.':'))->mapWithKeys(fn ($q, $k) => [(int) explode(':', $k)[1] => $q])->all()
            : StockLedger::onHand([$t->from_unit_id], array_keys($qty));
        $lines = StockTransferLine::where('stock_transfer_id', $t->id)->with('item.uom')->orderBy('line_no')->get();
        $errors = [];
        foreach ($lines as $i => $l) {
            $available = (float) ($have[$l->item_id] ?? 0);
            if ($qty[$l->item_id] > $available + 0.0005) {
                $msg = 'Item '.$l->line_no.' ('.($l->item?->item_no ?? '?').'): quantity is more than the stock at the source. '
                    .($t->fromUnit?->name ?? 'The source').' has '.Money::qty($available).' '.($l->item?->uom?->code ?? '').'.';
                $errors[$key === 'lines' ? "lines.$i.quantity" : $key][] = $msg;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Out of the source, into the destination, one row per item in item order, after locking the balance rows of
     * both units (in unit then item order, the order every transfer uses, so two transfers queue instead of deadlocking).
     */
    private function moveStock(StockTransfer $t): void
    {
        $this->checkStock($t, true, 'status');
        $date = $t->transfer_date->format('Y-m-d');
        foreach ($this->quantities($t) as $itemId => $q) {
            StockLedger::move($t->from_unit_id, (int) $itemId, $date, 0.0, $q, StockTransfer::LEDGER_SOURCE, $t->id);
        }
        foreach ($this->quantities($t) as $itemId => $q) {
            StockLedger::move($t->to_unit_id, (int) $itemId, $date, $q, 0.0, StockTransfer::LEDGER_SOURCE, $t->id);
        }
    }

    /** Lock the stock_balances rows of these units and items; returns "unit:item" => quantity. */
    private function lockBalances(array $unitIds, array $itemIds): array
    {
        sort($unitIds);
        sort($itemIds);
        // Missing rows are made first (quantity 0), so every row exists before the lock and the later balance update
        // never has to insert under a gap lock, which could deadlock two transfers to the same unit.
        $now = now()->toDateTimeString();
        $rows = [];
        foreach ($unitIds as $u) {
            foreach ($itemIds as $i) {
                $rows[] = ['unit_id' => $u, 'item_id' => $i, 'quantity' => 0, 'updated_at' => $now];
            }
        }
        if ($rows) {
            DB::table('stock_balances')->insertOrIgnore($rows);
        }

        return DB::table('stock_balances')->whereIn('unit_id', $unitIds)->whereIn('item_id', $itemIds)
            ->orderBy('unit_id')->orderBy('item_id')->lockForUpdate()->get(['unit_id', 'item_id', 'quantity'])
            ->mapWithKeys(fn ($r) => [$r->unit_id.':'.$r->item_id => (float) $r->quantity])->all();
    }

    private function lock(StockTransfer $t): StockTransfer
    {
        return StockTransfer::whereKey($t->id)->lockForUpdate()->firstOrFail();
    }

    private function lockPending(StockTransfer $t): StockTransfer
    {
        $t = $this->lock($t);
        if ($t->status !== StockTransfer::PENDING) {
            throw ValidationException::withMessages(['status' => $t->transfer_no.' is not waiting for approval (it is '.$t->status.').']);
        }

        return $t;
    }

    private function changeStatus(StockTransfer $t, string $status, ?string $reason, User $actor, string $description): void
    {
        $old = $t->status;
        $t->status = $status;
        $t->status_reason = $reason;
        $t->updated_by = $actor->id;
        $t->save();
        ActivityLogger::log('stock_transfer.status_changed', $t, $description, ['Status' => ['old' => $old, 'new' => $status]], $actor);
    }

    private function addApproval(StockTransfer $t, string $action, User $actor, ?string $remarks): void
    {
        $t->approvals()->create(['action' => $action, 'amount' => null, 'remarks' => $remarks, 'acted_by' => $actor->id, 'acted_at' => now()]);
    }
}
