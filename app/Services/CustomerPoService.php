<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Models\CustomerPo;
use App\Models\CustomerPoLine;
use App\Models\FinancialYear;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRM › 6 PO / Agreement received (CRM changes 13/10/2026). The PO is entered against a quotation made from an enquiry,
 * its current revision, already sent to the customer and not Rejected / Expired (canTakePo, checked again under lock with
 * the old messages). The client, enquiry and unit come from the quotation, the quotation becomes Accepted, the enquiry
 * PO received, and the PO / agreement no is unique per enquiry. Items from the Item master (customer_po_lines,
 * 29/10/2026) are saved in the same transaction; with items, the PO value is their sum, otherwise it is the value typed.
 * Separate PO menu removed (02/11/2026): the PO menu's direct POs and optional link are gone; "source" is always crm.
 */
class CustomerPoService
{
    private const DATE_FIELDS = ['po_date', 'valid_from', 'valid_to', 'received_date'];

    /** Retries after a deadlock (database review DB-C1). */
    private const ATTEMPTS = 3;

    public function __construct(private readonly EnquiryService $enquiries, private readonly DocumentService $documents)
    {
    }

    /**
     * $files: documents chosen in the form of the new PO, all of type $docCategory (Fri 23/10/2026). They are saved in the
     * same transaction as the PO, each logged as an upload: if one cannot be stored nothing is kept, neither the PO nor
     * the files already stored, so the user saves again instead of finding a PO with only some of its documents.
     */
    public function create(array $data, User $actor, array $files = [], ?string $docCategory = null): CustomerPo
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($data, $actor, $files, $docCategory, &$stored) {
                $this->deleteFiles($stored); // left by an attempt rolled back after a deadlock
                $po = $this->createPo($data, $actor);
                foreach ($files as $file) {
                    $stored[] = $this->documents->upload($po, (string) $docCategory, $file, $actor)->path;
                }

                return $po;
            }, self::ATTEMPTS);
        } catch (\Throwable $e) {
            $this->deleteFiles($stored);
            throw $e;
        }
    }

    /** Stored document files whose rows were rolled back. */
    private function deleteFiles(array &$paths): void
    {
        foreach ($paths as $path) {
            DocumentService::disk()->delete($path);
        }
        $paths = [];
    }

    private function createPo(array $data, User $actor): CustomerPo
    {
        return DB::transaction(function () use ($data, $actor) {
            // Everything comes from the quotation, locked and checked again (as before 29/10/2026).
            $q = $this->stepQuotation((int) ($data['quotation_id'] ?? 0));
            $po = new CustomerPo($this->attributes($data));
            $this->setQuotation($po, $q);
            $po->financial_year_id = FinancialYear::forDate($po->po_date)->id;
            $po->created_by = $actor->id;
            $po->updated_by = $actor->id;
            $po->po_value ??= 0;
            $this->ensureNewNumber($po);
            $po->save();
            if (array_key_exists('lines', $data)) {
                $this->saveLines($po, (array) $data['lines']);
            }
            $items = $this->lineSummary($po);
            ActivityLogger::log('customer_po.created', $po, 'Entered PO / agreement '.$po->po_no.' for '.$po->clientName().' against '.$q->activityLabel(),
                array_filter(['fields' => $this->snapshot($po), 'items' => $items]));
            $this->acceptQuotation($q, $po, $actor);
            $this->enquiries->refreshStatus($po->enquiry_id, 'PO '.$po->po_no.' received');

            return $po;
        }, self::ATTEMPTS);
    }

    /**
     * A new quotation must be able to take a PO, and the client, enquiry and unit follow it (as before 29/10/2026); a
     * saved PO keeps its quotation. The enquiries of the old and the new quotation are both refreshed.
     */
    public function update(CustomerPo $po, array $data, User $actor): CustomerPo
    {
        return DB::transaction(function () use ($po, $data, $actor) {
            $po = CustomerPo::whereKey($po->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($po);
            $beforeLines = $this->lineSummary($po);
            $oldEnquiry = $po->enquiry_id;
            $oldQuotation = $po->quotation_id;
            $po->fill($this->attributes($data));
            $newQuotation = null;
            if (! empty($data['quotation_id']) && (int) $data['quotation_id'] !== (int) $po->quotation_id) {
                $newQuotation = $this->stepQuotation((int) $data['quotation_id']);
                $this->setQuotation($po, $newQuotation);
            }
            $po->source = CustomerPo::CRM;
            $this->ensureNewNumber($po);
            $po->updated_by = $actor->id;
            $po->save();
            if (array_key_exists('lines', $data)) {
                $this->saveLines($po, (array) $data['lines']);
            }
            $po->unsetRelations();
            $changes = ActivityLogger::diff($before, $this->snapshot($po));
            if ($lineChanges = ActivityLogger::listDiff($beforeLines, $this->lineSummary($po))) {
                $changes['items'] = $lineChanges;
            }
            if ($changes) {
                ActivityLogger::log('customer_po.updated', $po, 'Updated '.$po->activityLabel(), $changes);
            }
            if ($oldQuotation && (int) $oldQuotation !== (int) $po->quotation_id && ($old = Quotation::withTrashed()->find($oldQuotation))) {
                // The quotation keeps its status (Accepted stays); the log says the PO was taken off it.
                ActivityLogger::log('quotation.po_unlinked', $old, 'PO '.$po->po_no.' is no longer linked to '.$old->activityLabel());
            }
            if ($newQuotation) {
                $this->acceptQuotation($newQuotation, $po, $actor);
            }
            // Moved to another enquiry: the first enquiry may have no PO left (code review M2).
            if ($oldEnquiry && (int) $oldEnquiry !== (int) $po->enquiry_id) {
                $this->enquiries->refreshStatus($oldEnquiry, 'PO '.$po->po_no.' is no longer linked to it');
            }
            $this->enquiries->refreshStatus($po->enquiry_id, 'PO '.$po->po_no.' received');

            return $po;
        }, self::ATTEMPTS);
    }

    /** Deleting the last PO of an enquiry opens the lead again. */
    public function delete(CustomerPo $po, User $actor): void
    {
        DB::transaction(function () use ($po) {
            $po = CustomerPo::whereKey($po->id)->lockForUpdate()->firstOrFail();
            ActivityLogger::log('customer_po.deleted', $po, 'Deleted '.$po->activityLabel(), array_filter(['fields' => $this->snapshot($po), 'items' => $this->lineSummary($po)]));
            // Soft delete: the PO keeps its items, as it keeps its documents.
            $po->delete();
            // Counted under the enquiry's lock, so two POs deleted at once leave the right status.
            if ($po->enquiry_id) {
                $this->enquiries->refreshStatus($po->enquiry_id, 'its PO was deleted');
            }
        }, self::ATTEMPTS);
    }

    public function snapshot(CustomerPo $po): array
    {
        $po->loadMissing(['enquiry', 'quotation', 'receiver', 'party', 'prospect']);

        return [
            'PO / Agreement no' => $po->po_no,
            'PO date' => Dates::show($po->po_date),
            'Quotation' => $po->quotation?->activityLabel(),
            'Enquiry' => $po->enquiry?->enquiry_no,
            'Client' => $po->clientName() ?: null,
            'Site' => $po->site,
            'PO value' => $po->po_value,
            'PO quantity' => $po->po_quantity,
            'Valid from' => Dates::show($po->valid_from),
            'Valid to' => Dates::show($po->valid_to),
            'Payment terms' => $po->payment_terms,
            'GST / tax' => $po->tax_terms,
            'Customer contact' => $po->contact_name,
            'Received by' => $po->receiver?->name,
            'Received date' => Dates::show($po->received_date),
            'Remarks' => $po->remarks,
        ];
    }

    /** One readable line per item, for the log: "BRK-2210 × 10 NOS @ 42.5 = 425.00". */
    public function lineSummary(CustomerPo $po): array
    {
        if (! $po->exists) {
            return [];
        }
        $n = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');

        return CustomerPoLine::where('customer_po_id', $po->id)->with(['item:id,item_no', 'uom:id,code'])->orderBy('line_no')->orderBy('id')->get()
            ->map(fn (CustomerPoLine $l) => ($l->item?->item_no ?? '?').' × '.$n($l->quantity).($l->uom ? ' '.$l->uom->code : '').' @ '.$n($l->rate)
                .' = '.Money::indian($l->amount, 2).($l->description !== '' && $l->description !== $l->item?->description ? ' · '.$l->description : ''))->all();
    }

    /** The quotation gives the PO's unit, client (customer or prospect) and enquiry (as before 29/10/2026). */
    private function setQuotation(CustomerPo $po, Quotation $q): void
    {
        $po->unit_id = $q->unit_id;
        $po->quotation_id = $q->id;
        $po->enquiry_id = $q->enquiry_id;
        $po->party_id = $q->party_id;
        $po->prospect_id = $q->prospect_id;
        $po->source = CustomerPo::CRM;
    }

    private function attributes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(CustomerPo::FIELDS));
        foreach (self::DATE_FIELDS as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = Dates::toDb($out[$f]);
            }
        }
        // With items the value is their sum (saveLines); a blank value is not taken from the form.
        if (! isset($out['po_value']) || $out['po_value'] === '') {
            unset($out['po_value']);
        }

        return $out;
    }

    /**
     * Items matched by id, so an item keeps its row when the PO is changed; rows left out are removed. Blank
     * description, UOM or rate take the Item master's (the rate is the item's price). Amount = Quantity × Rate,
     * rounded to paise. With items, the PO value becomes the sum of the amounts (01/11/2026); a PO with no items keeps
     * the value typed.
     */
    private function saveLines(CustomerPo $po, array $lines): void
    {
        $existing = CustomerPoLine::where('customer_po_id', $po->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $items = Item::whereIn('id', array_map('intval', array_column($lines, 'item_id')))->get()->keyBy('id');
        $keep = [];
        $sum = 0.0;
        foreach (array_values($lines) as $i => $l) {
            $n = $i + 1;
            $item = $items->get((int) ($l['item_id'] ?? 0));
            if (! $item) {
                throw ValidationException::withMessages(["lines.$i.item_id" => "Item $n was deleted while you were working. Choose another item."]);
            }
            $line = isset($l['id']) && $existing->has((int) $l['id']) ? $existing[(int) $l['id']] : new CustomerPoLine();
            $qty = round((float) $l['quantity'], 3);
            $rate = ($l['rate'] ?? '') === '' || $l['rate'] === null ? (float) $item->price : round((float) $l['rate'], 4);
            $line->fill([
                'line_no' => $n,
                'item_id' => $item->id,
                'description' => ($l['description'] ?? '') !== '' ? $l['description'] : $item->description,
                'uom_id' => ($l['uom_id'] ?? '') !== '' && $l['uom_id'] !== null ? (int) $l['uom_id'] : $item->uom_id,
                'quantity' => $qty,
                'rate' => $rate,
                'amount' => round($qty * $rate, 2),
            ]);
            $line->customer_po_id = $po->id;
            $line->save();
            $keep[] = $line->id;
            $sum += (float) $line->amount;
        }
        foreach ($existing->except($keep) as $gone) {
            $gone->delete();
        }
        if ($keep) {
            $po->po_value = round($sum, 2);
            $po->saveQuietly();
        }
    }

    /**
     * A quotation can take a PO (full test B2): made from an enquiry, the current revision, sent to the customer, and not
     * Rejected or Expired by them.
     */
    public static function canTakePo(Quotation $q): bool
    {
        return $q->is_latest && $q->enquiry_id && $q->status->isWithCustomer()
            && ! in_array($q->status, [QuotationStatus::Rejected, QuotationStatus::Expired], true);
    }

    /** Why this quotation cannot take a PO (the messages from before 29/10/2026), or null. */
    public static function whyNoPo(Quotation $q): ?string
    {
        if (! $q->enquiry_id) {
            return 'Choose a quotation made from an enquiry.';
        }
        if (self::canTakePo($q)) {
            return null;
        }
        $closed = $q->is_latest && in_array($q->status, [QuotationStatus::Rejected, QuotationStatus::Expired], true);

        return $closed
            ? $q->activityLabel().' is '.$q->status->value.'. Set its customer status to Accepted, or Revise it, before entering a PO.'
            : $q->activityLabel().' is '.($q->is_latest ? $q->status->value : 'an earlier revision')
                .'. A PO can be entered only against the current revision of a quotation sent to the customer.';
    }

    /**
     * The quotation of a PO, locked (code review M2, as before 29/10/2026): the current revision of a quotation made from
     * an enquiry and already sent to the customer, not Rejected or Expired.
     */
    private function stepQuotation(int $id): Quotation
    {
        $q = $id ? Quotation::whereKey($id)->lockForUpdate()->first() : null;
        if (! $q) {
            throw ValidationException::withMessages(['quotation_id' => 'Choose a quotation made from an enquiry.']);
        }
        if ($why = self::whyNoPo($q)) {
            throw ValidationException::withMessages(['quotation_id' => $why]);
        }

        return $q;
    }

    /** The PO's quotation becomes Accepted, so it and its lead agree (full test B2). */
    private function acceptQuotation(Quotation $q, CustomerPo $po, User $actor): void
    {
        if ($q->status === QuotationStatus::Accepted) {
            return;
        }
        $old = $q->status;
        $q->status = QuotationStatus::Accepted;
        $q->status_reason = null;
        $q->updated_by = $actor->id;
        $q->save();
        ActivityLogger::log('quotation.status_changed', $q, $q->activityLabel().' is now Accepted (PO '.$po->po_no.' received)', [
            'Status' => ['old' => $old?->value, 'new' => QuotationStatus::Accepted->value],
        ]);
    }

    /** The same PO / agreement no cannot be entered twice for one enquiry (as before 29/10/2026). */
    private function ensureNewNumber(CustomerPo $po): void
    {
        if ($po->enquiry_id && CustomerPo::where('po_no', $po->po_no)->when($po->exists, fn ($w) => $w->whereKeyNot($po->id))
            ->where('enquiry_id', $po->enquiry_id)->exists()) {
            throw ValidationException::withMessages(['po_no' => 'PO / agreement '.$po->po_no.' is already entered for this enquiry.']);
        }
    }
}
