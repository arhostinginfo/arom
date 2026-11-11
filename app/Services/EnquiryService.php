<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Enums\QuotationStatus;
use App\Models\Activity;
use App\Models\CustomerPo;
use App\Models\Quotation;
use App\Models\Enquiry;
use App\Models\FinancialYear;
use App\Models\Prospect;
use App\Models\Unit;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRM > Enquiry (CRM changes, Sat 13/10/2026): adds, changes and deletes leads and records their
 * follow-ups. Enquiry numbers come from NumberSeries (ENQ, per unit and financial year). Each
 * operation writes its activity log row in the same transaction.
 */
class EnquiryService
{
    private const ATTEMPTS = 3;

    private const DATE_FIELDS = ['enquiry_date', 'expected_closing_date', 'next_action_date'];

    public function __construct(private readonly NumberSeries $numbers)
    {
    }

    public function create(array $data, User $actor, Unit $unit): Enquiry
    {
        return DB::transaction(function () use ($data, $actor, $unit) {
            $this->ensureProspectExists($data);
            $e = new Enquiry($this->attributes($data));
            $this->contactFromProspect($e);
            // "Lead assigned to" hidden in Admin > Page setup: the lead goes to the person adding it (their active
            // employee record), so their follow-ups still show in their red pop up (code review M1).
            if (! array_key_exists('assigned_to', $data)) {
                $e->assigned_to = \App\Models\Employee::active()->where('user_id', $actor->id)->value('id');
            }
            $year = FinancialYear::forDate($e->enquiry_date ?? now());
            $e->unit_id = $unit->id;
            $e->financial_year_id = $year->id;
            $e->enquiry_no = $this->numbers->next('enquiry', $unit, $year);
            $e->status = EnquiryStatus::Open;
            $e->created_by = $actor->id;
            $e->updated_by = $actor->id;
            $e->save();
            ActivityLogger::log('enquiry.created', $e, 'Added enquiry '.$e->enquiry_no.' for '.$e->clientName(), ['fields' => $this->snapshot($e)]);
            CrmAlerts::forgetDue($e->unit_id, [$e->assigned_to]);

            return $e;
        }, self::ATTEMPTS);
    }

    public function update(Enquiry $e, array $data, User $actor): Enquiry
    {
        return DB::transaction(function () use ($e, $data, $actor) {
            $e = Enquiry::whereKey($e->id)->lockForUpdate()->firstOrFail();
            $this->ensureProspectExists($data);
            $before = $this->snapshot($e);
            $assignedBefore = $e->assigned_to;
            $prospectBefore = $e->prospect_id;
            $e->fill($this->attributes($data));
            // Only a newly picked Prospect replaces the contact; saving with the same one leaves it as stored (22/10/2026).
            if ((int) $e->prospect_id !== (int) $prospectBefore) {
                $this->contactFromProspect($e);
            }
            $e->updated_by = $actor->id;
            $e->save();
            $e->unsetRelations();
            CrmAlerts::forgetDue($e->unit_id, [$assignedBefore, $e->assigned_to]);
            if ($changes = ActivityLogger::diff($before, $this->snapshot($e))) {
                ActivityLogger::log('enquiry.updated', $e, 'Updated enquiry '.$e->enquiry_no, $changes);
            }

            return $e;
        }, self::ATTEMPTS);
    }

    /** An enquiry with a site visit, quotation or PO cannot be deleted; its follow-ups go with it. */
    public function delete(Enquiry $e, User $actor): void
    {
        DB::transaction(function () use ($e) {
            $e = Enquiry::whereKey($e->id)->lockForUpdate()->firstOrFail();
            $used = array_filter([
                'site visit(s)' => $e->siteVisits()->count(),
                'quotation(s)' => $e->quotations()->count(),
                'PO / agreement(s)' => $e->customerPos()->count(),
            ]);
            if ($used) {
                $list = implode(', ', array_map(fn ($n, $what) => $n.' '.$what, $used, array_keys($used)));
                // Site visits are kept while that part is switched off, but cannot be opened: say how to reach them (code review S3).
                $hint = isset($used['site visit(s)']) && ! \App\Support\Modules::on('site_visit')
                    ? ' Site visits are switched off; an administrator can switch them on in Admin › Page setup to remove them.' : '';
                throw ValidationException::withMessages(['delete' => $e->enquiry_no.' has '.$list.', so it cannot be deleted.'.$hint]);
            }
            ActivityLogger::log('enquiry.deleted', $e, 'Deleted enquiry '.$e->enquiry_no, ['fields' => $this->snapshot($e)]);
            $e->delete();
            CrmAlerts::forgetDue($e->unit_id, [$e->assigned_to]);
        });
    }

    /**
     * Follow-up & activity management: records a Call, Email or WhatsApp. When it names a next action,
     * that action and its target date become the enquiry's next action (shown in red when due).
     */
    public function addActivity(Enquiry $e, array $data, User $actor): Activity
    {
        return DB::transaction(function () use ($e, $data, $actor) {
            $e = Enquiry::whereKey($e->id)->lockForUpdate()->firstOrFail();
            // Details and the next action can be hidden or optional in Admin > Page setup (22/10/2026): a hidden
            // field is not sent. Details is a NOT NULL column, so no details are saved as an empty text.
            $a = new Activity([
                'activity_type' => $data['activity_type'],
                'activity_date' => Dates::toDb($data['activity_date']),
                'details' => (string) ($data['details'] ?? ''),
                'next_action' => $data['followup_action'] ?? null ?: null,
                'target_date' => Dates::toDb($data['target_date'] ?? null),
            ]);
            $a->enquiry_id = $e->id;
            $a->created_by = $actor->id;
            $a->save();

            $changes = ['Follow-up' => ['old' => null, 'new' => $a->activity_type.' on '.$data['activity_date'].($a->details !== '' ? ': '.$a->details : '')]];
            if ($a->next_action) {
                $changes['Next action'] = ['old' => $e->next_action, 'new' => $a->next_action];
                $changes['Next action date'] = ['old' => Dates::show($e->next_action_date), 'new' => $data['target_date']];
                $e->next_action = $a->next_action;
                $e->next_action_date = $a->target_date;
                $e->updated_by = $actor->id;
                $e->save();
            }
            ActivityLogger::log('enquiry.followup_added', $e, 'Recorded a '.$a->activity_type.' follow-up on '.$e->enquiry_no, $changes);
            CrmAlerts::forgetDue($e->unit_id, [$e->assigned_to]);

            return $a;
        }, self::ATTEMPTS);
    }

    /**
     * Works out Open / PO received / Lost from the records, under a lock on the enquiry row (database review
     * DB-C1), so a PO saved and a quotation status changed at the same moment cannot overwrite each other:
     * PO received while it has a PO / agreement; Lost when every current quotation is Rejected or Expired;
     * otherwise Open. Called inside the caller's transaction.
     */
    public function refreshStatus(?int $enquiryId, string $why): void
    {
        $e = $enquiryId ? Enquiry::whereKey($enquiryId)->lockForUpdate()->first() : null;
        if (! $e) {
            return;
        }
        $statuses = Quotation::where('enquiry_id', $e->id)->latestRevisions()->get(['status'])->map(fn ($q) => $q->status);
        $lost = $statuses->isNotEmpty() && $statuses->every(fn ($s) => in_array($s, [QuotationStatus::Rejected, QuotationStatus::Expired], true));
        $status = match (true) {
            CustomerPo::where('enquiry_id', $e->id)->exists() => EnquiryStatus::PoReceived,
            $lost => EnquiryStatus::Lost,
            default => EnquiryStatus::Open,
        };
        if ($e->status === $status) {
            return;
        }
        $old = $e->status;
        $e->status = $status;
        $e->save();
        ActivityLogger::log('enquiry.status_changed', $e, $e->enquiry_no.' is now '.$status->value.' ('.$why.')', [
            'Status' => ['old' => $old?->value, 'new' => $status->value],
        ]);
        CrmAlerts::forgetDue($e->unit_id, [$e->assigned_to]);
    }

    public function snapshot(Enquiry $e): array
    {
        $e->loadMissing(['source', 'industryType', 'city', 'serviceCategory', 'assignee', 'party', 'prospect']);

        return [
            'Enquiry date' => Dates::show($e->enquiry_date),
            'Source' => $e->source?->name,
            'Sub-source / Reference' => $e->sub_source,
            'Competitor details' => $e->competitor_details,
            'Prospect type' => $e->prospect_type?->value,
            'Company / Client name' => $e->clientName() ?: null,
            'Industry' => $e->industry,
            'Industry type' => $e->industryType?->name,
            'City' => $e->city?->name,
            'Address' => $e->address,
            'Website' => $e->website,
            'Contact name' => $e->contact_name,
            'Contact no' => $e->contact_no,
            'Service type' => $e->serviceCategory?->name,
            'Approx. requirement' => $e->approx_requirement,
            'Estimated value' => $e->estimated_value,
            'Expected closing date' => Dates::show($e->expected_closing_date),
            'Lead rating' => $e->lead_rating?->value,
            'Lead assigned to' => $e->assignee?->name,
            'Next action' => $e->next_action,
            'Next action date' => Dates::show($e->next_action_date),
            'Remarks' => $e->remarks,
        ];
    }

    /** Only the fields the form sent (hidden fields in Page setup are not sent, so they keep their value). */
    private function attributes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(Enquiry::FIELDS));
        foreach (self::DATE_FIELDS as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = Dates::toDb($out[$f]);
            }
        }
        if (array_key_exists('party_id', $out) || array_key_exists('prospect_id', $out)) {
            $out['party_id'] = ($data['party_id'] ?? null) ?: null;
            $out['prospect_id'] = ($data['prospect_id'] ?? null) ?: null;
        }

        return $out;
    }

    /**
     * The contact name / no are not typed for a Prospect enquiry (22/10/2026): they are copied from the Prospect, so
     * quotations, site visits, reports and the contact no search keep working. A customer enquiry is not changed.
     */
    private function contactFromProspect(Enquiry $e): void
    {
        if (! $e->prospect_id || ! ($p = Prospect::find($e->prospect_id))) {
            return;
        }
        $e->contact_name = $p->contact_person ?: null;
        $e->contact_no = $p->phone_1 ?: null;
    }

    /** Holds a shared lock on the chosen prospect, so a prospect delete at the same moment waits (A7). */
    private function ensureProspectExists(array $data): void
    {
        if (! empty($data['prospect_id']) && ! Prospect::whereKey((int) $data['prospect_id'])->sharedLock()->exists()) {
            throw ValidationException::withMessages(['prospect_id' => 'This prospect was deleted while you were working. Choose another prospect.']);
        }
    }
}
