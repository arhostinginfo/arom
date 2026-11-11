<?php

namespace App\Support;

use App\Enums\QuotationStatus;
use App\Models\Enquiry;
use App\Models\User;

/**
 * The Nursery end-to-end workflow of one enquiry (CRM changes, Sat 13/10/2026): Lead → Site visit →
 * BOQ + costing + quotation → Approval → Quotation sent → PO entry. Each step: label, state, done, link.
 * Optional parts that are switched off (Admin > Page setup) are left out: no Site visit step while site visits
 * are off, and the quotation step names BOQ and costing only while they are on.
 */
class CrmFlow
{
    public static function steps(Enquiry $e, User $u): array
    {
        $withVisits = Modules::on('site_visit');
        $visits = $withVisits ? $e->siteVisits()->count() : 0;
        $lastVisit = $visits ? $e->siteVisits()->latest('visit_at')->first(['id', 'visit_at', 'status']) : null;
        $qt = $e->quotations()->latestRevisions()->latest('id')->first(['id', 'quotation_no', 'revision_no', 'status', 'is_latest', 'enquiry_id']);
        $po = $e->customerPos()->latest('id')->first(['id', 'po_no']);
        $st = $qt?->status;
        $approved = $st && ($st === QuotationStatus::Approved || $st->isWithCustomer());
        $sent = $st && $st->isWithCustomer();

        return array_values(array_filter([
            ['label' => 'Lead', 'state' => $e->enquiry_no, 'done' => true, 'url' => null],
            ! $withVisits ? null : [
                'label' => 'Site visit',
                'state' => $visits ? $visits.' visit(s) · '.$lastVisit->status : null,
                // Done once a visit is saved, whatever its status; the state line still says Planned or Done (23/10/2026).
                'done' => $visits > 0,
                'url' => $lastVisit ? route('crm.site-visits.show', $lastVisit->id) : ($u->can('site-visit.add') ? route('crm.site-visits.create', ['enquiry' => $e->id]) : null),
            ],
            [
                'label' => self::quotationStep(),
                'state' => $qt ? $qt->quotation_no.' Rev '.$qt->revision_no : null,
                'done' => (bool) $qt,
                'url' => $qt ? route('crm.quotations.show', $qt->id) : ($u->can('quotation.add') ? route('crm.quotations.create', ['enquiry' => $e->id]) : null),
            ],
            [
                'label' => 'Approval',
                'state' => $st ? ($st === QuotationStatus::InternalApproval ? 'Pending' : ($approved ? 'Approved' : 'Draft')) : null,
                'done' => $approved,
                'url' => null,
            ],
            ['label' => 'Quotation sent', 'state' => $sent ? $st->value : null, 'done' => $sent, 'url' => null],
            // The step ticks when the enquiry has a PO and opens it in CRM › 6 PO / Agreement. With no PO yet, it links only
            // once the current quotation can take a PO (sent to the customer, not Rejected / Expired), as before 29/10/2026.
            // Without PO rights it shows its state with no link (02/11/2026: no PO menu any more).
            [
                'label' => 'PO / Agreement',
                'state' => $po?->po_no,
                'done' => (bool) $po,
                'url' => $po ? ($u->can('customer-po.view') ? route('crm.customer-pos.show', $po->id) : null)
                    : ($qt && $u->can('customer-po.add') && \App\Services\CustomerPoService::canTakePo($qt) ? route('crm.customer-pos.create', ['quotation' => $qt->id]) : null),
            ],
        ]));
    }

    /** "BOQ, costing, quotation" with both parts on, "Costing, quotation" or "BOQ, quotation" with one, "Quotation" with neither. */
    public static function quotationStep(): string
    {
        $parts = array_keys(array_filter(['BOQ' => Modules::on('boq'), 'costing' => Modules::on('costing')]));

        return ucfirst(implode(', ', array_merge($parts, ['quotation'])));
    }
}
