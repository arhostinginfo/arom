<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Enums\QuotationStatus;
use App\Http\Requests\Crm\QuotationRequest;
use App\Jobs\SendQuotationMail;
use App\Models\ApprovalRule;
use App\Models\Currency;
use App\Models\Enquiry;
use App\Models\FinancialYear;
use App\Models\Quotation;
use App\Models\QuotationCost;
use App\Models\QuotationLine;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Csv;
use App\Support\Dates;
use App\Support\Gst;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates, changes, revises (decision D24), deletes and imports quotations, and moves them through
 * their status (CRM changes, Sat 13/10/2026): Draft → Internal Approval (amount criteria) → Approved →
 * Sent → customer statuses. A quotation is made from an enquiry, whose number and client it takes.
 * Numbers come from NumberSeries (D16). Each operation writes its activity log row in the same transaction.
 */
class QuotationService
{
    /** CSV columns for import. "Group" puts several item rows into one quotation (decision C4). */
    public const IMPORT_COLUMNS = [
        'group' => 'Group',
        'enquiry_no' => 'Enquiry no',
        'quotation_date' => 'Quotation date',
        'valid_until' => 'Valid until',
        'contact_name' => 'Contact',
        'site' => 'Site',
        'sales_person' => 'Sales person login email',
        'currency' => 'Currency',
        'payment_terms' => 'Payment terms',
        'delivery_terms' => 'Delivery / service terms',
        // GST / tax (text) dropped (23/10/2026): the quotation's tax is the first item's; an old file's column is ignored.
        'discount_amount' => 'Discount',
        'notes' => 'Notes',
        'item_no' => 'Item no',
        'description' => 'Item description',
        'qty' => 'Qty',
        'uom' => 'Unit',
        'rate' => 'Rate',
        'discount_percent' => 'Discount %',
        // A Tax master name (GST 18%) or rate (18) (23/10/2026); an old file's "Tax %" column is read the same way.
        'tax' => 'Tax',
    ];

    /** Retries after a deadlock (database review DB-M1). */
    private const ATTEMPTS = 3;

    private const LINE_COPY = ['line_no', 'item_no', 'description', 'qty', 'uom', 'rate', 'discount_percent', 'tax_structure_id', 'tax_percent', 'amount',
        'annual_volume', 'sample_target_date', 'production_target_date', 'technical_details'];

    public function __construct(private readonly NumberSeries $numbers, private readonly EnquiryService $enquiries)
    {
    }

    public function create(array $data, User $actor, Unit $unit): Quotation
    {
        return DB::transaction(function () use ($data, $actor, $unit) {
            $q = $this->make($data, $actor, $unit);
            ActivityLogger::log('quotation.created', $q, 'Created quotation '.$q->quotation_no.' (enquiry '.$q->enquiry_no.') for '.$q->customerName(), [
                'fields' => $this->snapshot($q),
                'items' => $this->lineSummary($q),
            ]);

            return $q;
        }, self::ATTEMPTS);
    }

    /** Only the current revision, and only while it is a Draft, can be changed (D24). */
    public function update(Quotation $q, array $data, User $actor): Quotation
    {
        return DB::transaction(function () use ($q, $data, $actor) {
            // Lock the row and check it is still the current draft, so a Revise or Submit by someone
            // else at the same moment cannot be overwritten (database review A7 / DB-M5).
            $q = Quotation::whereKey($q->id)->lockForUpdate()->firstOrFail();
            $this->ensureLatest($q);
            $this->ensureDraft($q);
            $q->load('lines', 'party', 'prospect', 'salesPerson', 'currency', 'cost', 'taxStructure');
            $before = $this->snapshot($q);
            $beforeLines = $this->lineSummary($q);

            $q->fill($this->attributes($data));
            $q->updated_by = $actor->id;
            $q->lines()->delete();
            $this->saveLines($q, $data['lines']);
            $this->saveCost($q, $data);
            $q->save();
            $q->unsetRelations();

            $changes = ActivityLogger::diff($before, $this->snapshot($q));
            if ($lineChanges = ActivityLogger::listDiff($beforeLines, $this->lineSummary($q))) {
                $changes['items'] = $lineChanges;
            }
            if ($changes) {
                ActivityLogger::log('quotation.updated', $q, 'Updated quotation '.$q->activityLabel(), $changes);
            }

            return $q;
        }, self::ATTEMPTS);
    }

    /** Copy the current revision into a new Draft with Rev no + 1. The earlier one becomes read-only. */
    public function revise(Quotation $q, User $actor): Quotation
    {
        $this->ensureLatest($q);

        return DB::transaction(function () use ($q, $actor) {
            $current = Quotation::whereKey($q->id)->lockForUpdate()->first();
            $this->ensureLatest($current);

            $new = $current->replicate(['created_at', 'updated_at', 'deleted_at']);
            $new->revision_no = $current->revision_no + 1;
            $new->previous_revision_id = $current->id;
            $new->is_latest = true;
            $new->status = QuotationStatus::Draft;
            $new->status_reason = null;
            $new->sent_at = null;
            $new->prepared_by = $actor->id;
            $new->created_by = $actor->id;
            $new->updated_by = $actor->id;

            $current->is_latest = false;
            $current->save();
            $new->save();

            foreach ($current->lines as $line) {
                $new->lines()->create($line->only(self::LINE_COPY));
            }
            if ($current->cost) {
                $new->cost()->create($current->cost->only($current->cost->getFillable()));
            }
            // The BOQ goes with the new version, as its own copy of the file (code review S4).
            app(DocumentService::class)->copyCategory($current, $new, 'BOQ', $actor);
            $this->enquiries->refreshStatus($new->enquiry_id, 'quotation '.$new->activityLabel().' made');

            ActivityLogger::log('quotation.revised', $new, 'Revised quotation '.$new->quotation_no.' from Rev '.$current->revision_no.' to Rev '.$new->revision_no, [
                'Rev no' => ['old' => $current->revision_no, 'new' => $new->revision_no],
            ]);

            return $new;
        }, self::ATTEMPTS);
    }

    /** Deleting a quotation removes all its revisions (soft delete, decision C3). The log keeps them. */
    public function delete(Quotation $q, User $actor): void
    {
        DB::transaction(function () use ($q) {
            // Lock every revision first, so a Revise at the same moment cannot add one that is left behind (A7).
            $revisions = $q->revisions()->lockForUpdate()->get();
            // Delete only from the current revision; an earlier one is read-only (D24).
            $current = $revisions->firstWhere('id', $q->id) ?? $q;
            $this->ensureLatest($current);
            if (\App\Models\CustomerPo::whereIn('quotation_id', $revisions->pluck('id'))->exists()) {
                throw ValidationException::withMessages(['delete' => 'A PO / agreement is entered against '.$q->quotation_no.', so it cannot be deleted.']);
            }
            ActivityLogger::log('quotation.deleted', $q, 'Deleted quotation '.$q->quotation_no.' with '.$revisions->count().' revision(s)', [
                'fields' => $this->snapshot($q),
            ]);
            $revisions->each->delete();
            $this->enquiries->refreshStatus($q->enquiry_id, 'quotation '.$q->quotation_no.' deleted');
        }, self::ATTEMPTS);
    }

    /**
     * Approval workflow: submit the costing, BOQ and quotation (with its Rev no) for approval. Above the
     * amount set in Admin > Approval it waits for an approver; at or below it, it is approved at once.
     */
    public function submit(Quotation $q, User $actor): Quotation
    {
        return DB::transaction(function () use ($q, $actor) {
            $q = Quotation::whereKey($q->id)->lockForUpdate()->firstOrFail();
            $this->ensureLatest($q);
            $this->ensureDraft($q);
            if (! $q->lines()->exists()) {
                throw ValidationException::withMessages(['status' => 'Add at least one item before submitting.']);
            }
            // Cost details are optional: a quotation can be submitted for approval without them (23/10/2026).

            $rule = ApprovalRule::for('quotation');
            $amount = (float) $q->grand_total;
            // The limit is in rupees and there is no exchange rate, so a quotation in another currency
            // always goes to an approver (code review M1).
            $inRupees = $q->currency?->code === 'INR';
            if ($rule->needsApproval($amount, $inRupees)) {
                $this->addApproval($q, 'Submitted', $actor, $inRupees ? null : 'In '.$q->currency?->code.': needs approval');
                $this->changeStatus($q, QuotationStatus::InternalApproval, null, $actor, 'Submitted '.$q->activityLabel().' for approval');
            } else {
                $limit = \App\Support\Money::indian($rule->approve_above_amount, 2);
                $this->addApproval($q, 'Approved automatically', $actor, $rule->is_required ? 'Total at or below '.$limit : 'Approval not required');
                $this->changeStatus($q, QuotationStatus::Approved, null, $actor, 'Approved '.$q->activityLabel().' automatically ('.($rule->is_required ? 'total at or below '.$limit : 'approval not required').')');
            }

            return $q;
        }, self::ATTEMPTS);
    }

    public function approve(Quotation $q, User $actor, ?string $remarks): Quotation
    {
        return DB::transaction(function () use ($q, $actor, $remarks) {
            $q = $this->lockPending($q);
            // Someone else approves: the person who submitted it cannot, except Admin (code review S1).
            $submittedBy = $q->approvals()->where('action', 'Submitted')->latest('id')->value('acted_by');
            if ($submittedBy === $actor->id && ! $actor->isAdmin()) {
                throw ValidationException::withMessages(['status' => 'You submitted '.$q->activityLabel().', so another approver has to approve it.']);
            }
            $this->addApproval($q, 'Approved', $actor, $remarks);
            $this->changeStatus($q, QuotationStatus::Approved, null, $actor, 'Approved '.$q->activityLabel());

            return $q;
        }, self::ATTEMPTS);
    }

    /** An internal rejection sends the quotation back to Draft with the reason, for changes. */
    public function reject(Quotation $q, User $actor, string $reason): Quotation
    {
        return DB::transaction(function () use ($q, $actor, $reason) {
            $q = $this->lockPending($q);
            $this->addApproval($q, 'Rejected', $actor, $reason);
            $this->changeStatus($q, QuotationStatus::Draft, 'Returned by '.$actor->name.': '.$reason, $actor, 'Rejected '.$q->activityLabel().' and returned it to Draft');

            return $q;
        }, self::ATTEMPTS);
    }

    /** Quotation sent: by the email below or by hand (WhatsApp, courier). Only an approved quotation is sent. */
    public function markSent(Quotation $q, User $actor, string $how): Quotation
    {
        return DB::transaction(function () use ($q, $actor, $how) {
            $q = Quotation::whereKey($q->id)->lockForUpdate()->firstOrFail();
            $this->ensureLatest($q);
            if ($q->status !== QuotationStatus::Approved && ! $q->status->isWithCustomer()) {
                throw ValidationException::withMessages(['status' => 'Only an approved quotation can be sent. Submit it for approval first.']);
            }
            $q->sent_at = now();
            if ($q->status === QuotationStatus::Approved) {
                $this->changeStatus($q, QuotationStatus::Sent, null, $actor, 'Sent '.$q->activityLabel().' '.$how);
            } else {
                $q->save();
                ActivityLogger::log('quotation.sent', $q, 'Sent '.$q->activityLabel().' again '.$how);
            }

            return $q;
        }, self::ATTEMPTS);
    }

    /** Emails the PDF with the BOQ attachments. The mail is sent in the background (queue). */
    public function email(Quotation $q, array $mail, User $actor): Quotation
    {
        $q = $this->markSent($q, $actor, 'by email to '.$mail['to']);
        SendQuotationMail::dispatch($q->id, $mail['to'], $mail['cc'] ?? null, $mail['message'] ?? null, $actor->id)->afterCommit();

        return $q;
    }

    /** Customer side: Customer Viewed/Feedback, Negotiation, Accepted, Rejected, Expired or Hold (reason needed for the last three). */
    public function setCustomerStatus(Quotation $q, QuotationStatus $status, ?string $reason, User $actor): Quotation
    {
        return DB::transaction(function () use ($q, $status, $reason, $actor) {
            $q = Quotation::whereKey($q->id)->lockForUpdate()->firstOrFail();
            $this->ensureLatest($q);
            if (! $q->status->isWithCustomer() || ! in_array($status, QuotationStatus::customerStatuses(), true)) {
                throw ValidationException::withMessages(['status' => 'Set the customer status after the quotation is sent.']);
            }
            if ($status->needsReason() && ! $reason) {
                throw ValidationException::withMessages(['status_reason' => 'Give the reason for '.$status->value.'.']);
            }
            $this->changeStatus($q, $status, $status->needsReason() ? $reason : null, $actor, $q->activityLabel().' is now '.$status->value);

            // Worked out under the enquiry's lock (database review DB-C1).
            $this->enquiries->refreshStatus($q->enquiry_id, 'quotation '.$q->activityLabel().' '.$status->value);

            return $q;
        }, self::ATTEMPTS);
    }

    /**
     * Import creates new quotations (Rev 0, Draft) against existing enquiries. Rows with the same Group
     * value are the items of one quotation; header values are read from the group's first row (C4).
     * Every quotation is checked first; if any has an error nothing is saved.
     */
    public function import(array $rows, User $actor, Unit $unit): int
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = ($row['group'] ?? '') !== '' ? $row['group'] : 'row-'.$row['_row'];
            $groups[$key][] = $row;
        }

        // Look up only the numbers and emails that appear in the file (database review DB-M2).
        $inFile = fn (string $col) => array_values(array_unique(array_filter(array_map(fn ($r) => $r[$col] ?? '', $rows))));
        $enquiries = Enquiry::where('unit_id', $unit->id)->whereIn('enquiry_no', $inFile('enquiry no'))->get(['id', 'enquiry_no'])->keyBy('enquiry_no');
        $sales = (QuotationRequest::salesPeopleQuery()?->whereIn('email', array_map('mb_strtolower', $inFile('sales person login email')))->get(['id', 'email']) ?? collect())
            ->keyBy(fn ($u) => mb_strtolower($u->email));
        $currencies = Currency::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id]);
        // UOM / Tax from the masters (23/10/2026): Unit by UOM code, Tax by structure name or rate, active rows only.
        $uoms = Uom::where('is_active', true)->pluck('code')->keyBy(fn ($c) => mb_strtoupper($c));
        $taxes = TaxStructure::where('is_active', true)->orderBy('name')->get();

        $valid = [];
        $errors = [];
        foreach ($groups as $key => $groupRows) {
            $first = $groupRows[0];
            $where = count($groupRows) > 1 ? 'Rows '.$first['_row'].' to '.end($groupRows)['_row'] : 'Row '.$first['_row'];
            $local = [];
            $cell = fn (string $col) => ($first[$col] ?? '') !== '' ? $first[$col] : null;

            $data = [
                'enquiry_id' => null,
                'quotation_date' => $cell('quotation date'),
                'valid_until' => $cell('valid until'),
                'contact_name' => $cell('contact'),
                'site' => $cell('site'),
                'sales_person_id' => null,
                'currency_id' => null,
                'payment_terms' => $cell('payment terms'),
                'delivery_terms' => $cell('delivery / service terms'),
                'discount_amount' => $cell('discount'),
                'notes' => $cell('notes'),
            ];
            if (($no = $first['enquiry no'] ?? '') !== '') {
                $data['enquiry_id'] = $enquiries[$no]->id ?? null;
                if (! $data['enquiry_id']) {
                    $local[] = 'enquiry "'.$no.'" does not exist in this unit';
                }
            }
            if (($email = $first['sales person login email'] ?? '') !== '') {
                $data['sales_person_id'] = $sales[mb_strtolower($email)]->id ?? null;
                if (! $data['sales_person_id']) {
                    $local[] = 'sales person "'.$email.'" is not an active user with the Sales role';
                }
            }
            $code = strtoupper(($first['currency'] ?? '') ?: 'INR');
            $data['currency_id'] = $currencies[$code] ?? null;
            if (! $data['currency_id']) {
                $local[] = 'currency "'.$code.'" is not in the currency list';
            }

            $lines = [];
            foreach ($groupRows as $r) {
                $uom = trim($r['unit'] ?? '');
                $tax = trim(($r['tax'] ?? '') !== '' ? $r['tax'] : ($r['tax %'] ?? ''));
                $taxId = $tax === '' ? '' : self::findTax($taxes, $tax);
                if ($uom !== '' && ! isset($uoms[mb_strtoupper($uom)])) {
                    $local[] = 'row '.$r['_row'].': unit "'.$uom.'" is not in the UOM master (use a code such as '.($uoms->first() ?? 'NOS').')';
                }
                if ($taxId === null) {
                    $local[] = 'row '.$r['_row'].': tax "'.$tax.'" is not in the Tax master (use a name such as '.($taxes->first()?->name ?? 'GST 18%').' or its rate)';
                }
                $lines[] = [
                    'item_no' => $r['item no'] ?? '',
                    'description' => $r['item description'] ?? '',
                    'qty' => $r['qty'] ?? '',
                    'uom' => $uom !== '' ? ($uoms[mb_strtoupper($uom)] ?? $uom) : '',
                    'rate' => $r['rate'] ?? '',
                    'discount_percent' => $r['discount %'] ?? '',
                    'tax_structure_id' => $taxId ? (string) $taxId : '',
                ];
            }
            $data['lines'] = QuotationRequest::cleanLines($lines);
            $data['lines'] = array_map(fn ($l) => array_map(fn ($v) => $v === '' ? null : $v, $l), $data['lines']);
            // The quotation's GST / tax is its first item's tax (23/10/2026).
            $data['tax_structure_id'] = $data['lines'][0]['tax_structure_id'] ?? null;

            // The same Page setup as the quotation page: a hidden header column is not read, a mandatory one is checked (architecture review S5).
            $v = Validator::make($data, app(PageSetupService::class)->rules('quotation', QuotationRequest::fieldRules(true)), QuotationRequest::fieldMessages(), QuotationRequest::fieldAttributes());
            $v->after(fn ($v) => QuotationRequest::check($v, $data));
            $messages = $local ?: [];
            if ($v->fails()) {
                // Lookups that failed already explain the empty id, so skip the generic message for those fields.
                foreach ($v->errors()->messages() as $field => $list) {
                    if ($local && (in_array($field, ['enquiry_id', 'sales_person_id', 'currency_id', 'tax_structure_id'], true) || preg_match('/^lines\.\d+\.(uom|tax_structure_id)$/', $field))) {
                        continue;
                    }
                    array_push($messages, ...$list);
                }
            }
            if ($messages) {
                foreach ($messages as $m) {
                    $errors[] = $where.' (group '.(str_starts_with((string) $key, 'row-') ? 'blank' : $key).'): '.$m;
                }

                continue;
            }
            $valid[] = $v->validated();
        }
        if ($errors) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 30)]);
        }

        // Reserve the whole block of numbers in one short locked step, so the import does not hold
        // the series lock while it inserts. If the insert then fails, those numbers stay unused (decision C12).
        $year = FinancialYear::forDate(now());
        $quotationNos = DB::transaction(fn () => $this->numbers->reserve('quotation', $unit, $year, count($valid)), self::ATTEMPTS);

        return DB::transaction(function () use ($valid, $actor, $unit, $quotationNos) {
            foreach (array_values($valid) as $i => $data) {
                $this->make($data, $actor, $unit, $quotationNos[$i]);
            }
            ActivityLogger::log('quotation.imported', null, 'Imported '.count($valid).' quotation(s) from a CSV file', [
                'added' => $quotationNos,
            ]);

            return count($valid);
        }, self::ATTEMPTS);
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(Quotation $q): array
    {
        $out = [
            'Enquiry no' => $q->enquiry_no,
            'Client' => $q->customerName() ?: null,
            'Contact' => $q->contact_name,
            'Site' => $q->site,
            'Quotation date' => Dates::show($q->quotation_date),
            'Valid until' => Dates::show($q->valid_until),
            'Sales person' => $q->salesPerson?->name,
            'Currency' => $q->currency?->code,
            'Payment terms' => $q->payment_terms,
            'Delivery / service terms' => $q->delivery_terms,
            // The Tax master row once chosen; an old quotation shows its GST / tax text (23/10/2026).
            'GST / tax' => $q->taxStructure?->name ?? $q->tax_terms,
            'Discount' => $q->discount_amount,
            'Notes' => $q->notes,
            'Total' => $q->grand_total,
        ];
        foreach ($this->costSnapshot($q) as $label => $value) {
            $out['Cost: '.$label] = $value;
        }

        return $out;
    }

    /** One readable line per item, for the log. */
    public function lineSummary(Quotation $q): array
    {
        $n = fn ($v) => $v === null ? null : rtrim(rtrim((string) $v, '0'), '.');

        return $q->lines->map(fn ($l) => implode(' · ', array_filter([
            $l->item_no,
            $l->description,
            $l->qty !== null ? 'qty '.$n($l->qty).($l->uom ? ' '.$l->uom : '') : null,
            $l->rate !== null ? 'rate '.$n($l->rate) : null,
            (float) $l->discount_percent ? 'discount '.$n($l->discount_percent).'%' : null,
            (float) $l->tax_percent ? 'tax '.$n($l->tax_percent).'%' : null,
            'total '.$l->amount,
        ])))->all();
    }

    /** $number: the quotation no already reserved by an import; otherwise it is taken here. */
    private function make(array $data, User $actor, Unit $unit, ?string $number = null): Quotation
    {
        $enquiry = $this->lockEnquiry((int) $data['enquiry_id'], $unit);
        $year = FinancialYear::forDate(now());
        $q = new Quotation($this->attributes($data));
        $q->enquiry_id = $enquiry->id;
        $q->party_id = $enquiry->party_id;
        $q->prospect_id = $enquiry->prospect_id;
        $q->enquiry_no = $enquiry->enquiry_no;
        $q->enquiry_receipt_date = $enquiry->enquiry_date;
        $q->source_of_enquiry = $enquiry->source?->name;
        $q->unit_id = $unit->id;
        $q->financial_year_id = $year->id;
        $q->quotation_no = $number ?? $this->numbers->next('quotation', $unit, $year);
        $q->revision_no = 0;
        $q->is_latest = true;
        $q->status = QuotationStatus::Draft;
        $q->prepared_by = $actor->id;
        $q->created_by = $actor->id;
        $q->updated_by = $actor->id;
        $q->save();
        $this->saveLines($q, $data['lines']);
        $this->saveCost($q, $data);
        $q->save();

        return $q;
    }

    /**
     * Saves the items with their totals and sets the quotation's subtotal and total (saved by the caller).
     * Tax % comes from the chosen Tax master row for the quotation's supply type, never from what was sent; a
     * line's old Tax % ("old:18") is kept. Unit is saved as the UOM master code; an old Unit text as it was (23/10/2026).
     */
    private function saveLines(Quotation $q, array $lines): void
    {
        $subtotal = 0.0;
        $supply = Gst::forQuotation($q->party);
        $structures = TaxStructure::all()->keyBy('id');
        $codes = Uom::pluck('code')->keyBy(fn ($c) => mb_strtoupper($c));
        foreach (array_values($lines) as $i => $l) {
            $qty = (float) ($l['qty'] ?? 0);
            $rate = (float) ($l['rate'] ?? 0);
            $disc = (float) (($l['discount_percent'] ?? '') ?: 0);
            $taxValue = trim((string) ($l['tax_structure_id'] ?? ''));
            $tax = QuotationRequest::taxPercent($taxValue, $supply, $structures);
            $uom = trim((string) ($l['uom'] ?? ''));
            $amount = QuotationLine::total($qty, $rate, $disc, $tax);
            $subtotal += $amount;
            $q->lines()->create([
                'line_no' => $i + 1,
                'item_no' => $l['item_no'] ?? null ?: null,
                'description' => $l['description'],
                'qty' => $qty,
                'uom' => $uom !== '' ? ($codes[mb_strtoupper($uom)] ?? $uom) : null,
                'rate' => $rate,
                'discount_percent' => $disc,
                'tax_structure_id' => ctype_digit($taxValue) && isset($structures[(int) $taxValue]) ? (int) $taxValue : null,
                'tax_percent' => $tax,
                'amount' => $amount,
            ]);
        }
        $q->subtotal = round($subtotal, 2);
        $q->grand_total = round(max(0, $subtotal - (float) ($q->discount_amount ?? 0)), 2);
    }

    /** Cost details: Total cost is the sum; Margin amount = Total × Margin %; Proposed price = Total + Margin. */
    private function saveCost(Quotation $q, array $data): void
    {
        if (! array_key_exists('cost', $data)) {
            return;
        }
        $values = [];
        $total = 0.0;
        foreach (array_keys(QuotationCost::COSTS) as $f) {
            $values[$f] = round((float) (($data['cost'][$f] ?? '') ?: 0), 2);
            $total += $values[$f];
        }
        $pct = round((float) (($data['cost']['margin_percent'] ?? '') ?: 0), 2);
        $margin = round($total * $pct / 100, 2);
        $values += [
            'total_cost' => round($total, 2),
            'margin_percent' => $pct,
            'margin_amount' => $margin,
            'proposed_selling_price' => round($total + $margin, 2),
        ];
        $q->cost()->updateOrCreate(['quotation_id' => $q->id], $values);
        $q->unsetRelation('cost');
    }

    private function costSnapshot(Quotation $q): array
    {
        $c = $q->cost;
        // While Cost details is switched off, costs are left out of the log and history like the form (architecture review S4).
        if (! $c || ! \App\Support\Modules::on('costing')) {
            return [];
        }
        $out = [];
        foreach (QuotationCost::COSTS as $f => $label) {
            $out[$label] = $c->{$f};
        }

        return $out + ['Total cost' => $c->total_cost, 'Margin %' => $c->margin_percent, 'Margin amount' => $c->margin_amount, 'Proposed selling price' => $c->proposed_selling_price];
    }

    private function attributes(array $data): array
    {
        $out = [
            'sales_person_id' => $data['sales_person_id'],
            'currency_id' => $data['currency_id'],
            'quotation_date' => Dates::toDb($data['quotation_date']),
            'discount_amount' => round((float) (($data['discount_amount'] ?? '') ?: 0), 2),
        ];
        // A field hidden in Admin > Page setup is not sent, so what is saved in it stays as it was.
        // tax_terms is no longer on the form (23/10/2026): an old quotation keeps its text; GST / tax is tax_structure_id.
        foreach (['contact_name', 'site', 'payment_terms', 'delivery_terms', 'notes'] as $f) {
            if (array_key_exists($f, $data)) {
                $out[$f] = $data[$f];
            }
        }
        if (array_key_exists('tax_structure_id', $data)) {
            $out['tax_structure_id'] = ($data['tax_structure_id'] ?? null) ?: null;
        }
        if (array_key_exists('valid_until', $data)) {
            $out['valid_until'] = Dates::toDb($data['valid_until']);
        }

        return $out;
    }

    /** Tax master row of an import cell (23/10/2026): by name (any case), else by rate (18 or 18%); null when none matches. */
    private static function findTax($taxes, string $value): ?int
    {
        $byName = $taxes->first(fn ($t) => mb_strtolower($t->name) === mb_strtolower($value));
        if ($byName) {
            return $byName->id;
        }
        $rate = rtrim(trim($value), '% ');
        if (! is_numeric($rate)) {
            return null;
        }

        return $taxes->first(fn ($t) => $t->rateFor(Gst::INTRA) == (float) $rate || (float) $t->igst_rate == (float) $rate)?->id;
    }

    private function changeStatus(Quotation $q, QuotationStatus $status, ?string $reason, User $actor, string $description): void
    {
        $old = $q->status;
        $q->status = $status;
        $q->status_reason = $reason;
        $q->updated_by = $actor->id;
        $q->save();
        ActivityLogger::log('quotation.status_changed', $q, $description, array_filter([
            'Status' => ['old' => $old?->value, 'new' => $status->value],
            'Reason' => $reason ? ['old' => null, 'new' => $reason] : null,
        ]));
    }

    private function addApproval(Quotation $q, string $action, User $actor, ?string $remarks): void
    {
        $q->approvals()->create([
            'action' => $action,
            'amount' => $q->grand_total,
            'remarks' => $remarks,
            'acted_by' => $actor->id,
            'acted_at' => now(),
        ]);
    }

    private function lockPending(Quotation $q): Quotation
    {
        $q = Quotation::whereKey($q->id)->lockForUpdate()->firstOrFail();
        $this->ensureLatest($q);
        if ($q->status !== QuotationStatus::InternalApproval) {
            throw ValidationException::withMessages(['status' => $q->activityLabel().' is not waiting for approval (it is '.$q->status->value.').']);
        }

        return $q;
    }

    private function ensureLatest(Quotation $q): void
    {
        if (! $q->is_latest) {
            throw ValidationException::withMessages(['delete' => $q->activityLabel().' is an earlier revision and is read-only. Open the current revision to make changes.']);
        }
    }

    private function ensureDraft(Quotation $q): void
    {
        if (! $q->status->isEditable()) {
            throw ValidationException::withMessages(['status' => $q->activityLabel().' is '.$q->status->value.', so it cannot be changed. Use Revise to make a new revision.']);
        }
    }

    /** The enquiry must exist in the unit; a shared lock makes an enquiry delete at the same moment wait (A7). */
    private function lockEnquiry(int $id, Unit $unit): Enquiry
    {
        $e = Enquiry::whereKey($id)->where('unit_id', $unit->id)->sharedLock()->first();
        if (! $e) {
            throw ValidationException::withMessages(['enquiry_id' => 'This enquiry was deleted or is in another unit. Choose another enquiry.']);
        }

        return $e;
    }
}
