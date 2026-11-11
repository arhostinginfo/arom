<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Models\Contact;
use App\Models\CustomerPo;
use App\Models\Enquiry;
use App\Models\Prospect;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Support\Facades\Cache;

/**
 * CRM page counts on the dashboard (22/10/2026): one tile per CRM page of the menu (config nursery.nav), in the
 * order of the menu, each a link to the page whose list shows the same number. A tile shows only with the page's
 * view right and while its optional part is on, exactly like the menu. Each count uses the same base query and
 * scopes as the list it opens: the unit on the top bar for enquiries, site visits, quotations and POs (prospects and
 * contacts belong to the client, not to a unit), soft-deleted rows left out. A handful of plain counts, no N+1.
 */
class CrmCounts
{
    /** @return list<array{key:string,label:string,count:int,noun:string,hint:string,url:string,alert?:bool}> */
    public function tiles(User $user, ?Unit $unit): array
    {
        $crm = collect(config('nursery.nav'))->firstWhere('key', 'crm');
        $tiles = [];
        $quotations = null;

        foreach ($crm['subs'] ?? [] as $sub) {
            if (! isset($sub['route']) || ! $user->can($sub['perm']) || ! Modules::allows($sub['module'] ?? null)) {
                continue;
            }
            $key = $sub['route'].(isset($sub['params']['view']) ? ':'.$sub['params']['view'] : '');
            // Pages of a unit need the unit on the top bar; their lists refuse to open without one.
            if (! $unit && ! in_array($key, ['crm.prospects.index', 'crm.contacts.index'], true)) {
                continue;
            }
            if (str_starts_with($key, 'crm.quotations.')) {
                $quotations ??= $this->quotationCounts($unit);
            }

            switch ($key) {
                case 'crm.enquiries.index':
                    // Enquiry list › All.
                    $tiles[] = $this->tile('enquiries', 'Enquiries / Leads', Enquiry::query()->where('unit_id', $unit->id)->count(),
                        ['enquiry / lead', 'enquiries / leads'], 'All enquiries of this unit', route('crm.enquiries.list'));
                    break;
                case 'crm.site-visits.index':
                    // Site visit list › All, and › Visits due.
                    $visits = SiteVisit::where('unit_id', $unit->id);
                    $tiles[] = $this->tile('site-visits', 'Site visits', (clone $visits)->count(),
                        ['site visit', 'site visits'], 'All site visits of this unit', route('crm.site-visits.list'));
                    $due = (clone $visits)->due()->count();
                    $tiles[] = $this->tile('site-visits-due', 'Site visits due', $due,
                        ['site visit due', 'site visits due'], 'Planned visits due today or earlier', route('crm.site-visits.list', ['view' => 'due'])) + ['alert' => $due > 0];
                    break;
                case 'crm.quotations.index':
                    // Quotation list › All: the current revision of each quotation.
                    $tiles[] = $this->tile('quotations', 'Quotations', $quotations['all'],
                        ['quotation', 'quotations'], 'Current revision of each quotation', route('crm.quotations.list'));
                    break;
                case 'crm.quotations.list:approval':
                    $tiles[] = $this->tile('approval', 'Pending approval', $quotations['approval'],
                        ['quotation pending approval', 'quotations pending approval'], 'Waiting for internal approval', route('crm.quotations.list', $sub['params']));
                    break;
                case 'crm.quotations.list:sent':
                    $tiles[] = $this->tile('sent', 'Quotations sent', $quotations['sent'],
                        ['quotation sent', 'quotations sent'], 'With the customer', route('crm.quotations.list', $sub['params']));
                    break;
                case 'crm.customer-pos.index':
                    // A CRM tile, opening CRM › PO / Agreement (Separate PO menu removed, 02/11/2026: no PO section).
                    $tiles[] = $this->tile('pos', 'PO / Agreements', CustomerPo::where('unit_id', $unit->id)->count(),
                        ['PO / agreement', 'PO / agreements'], 'Received for this unit', route('crm.customer-pos.index'));
                    break;
                case 'crm.prospects.index':
                    // The same cached count as the badge on the Prospect page (DB-S3).
                    $tiles[] = $this->tile('prospects', 'Prospects', (int) Cache::remember('crm.prospects.count', 60, fn () => Prospect::count()),
                        ['prospect', 'prospects'], 'All prospects', route('crm.prospects.index'));
                    break;
                case 'crm.contacts.index':
                    $tiles[] = $this->tile('contacts', 'Contacts', Contact::query()->count(),
                        ['contact', 'contacts'], 'Contacts of every client', route('crm.contacts.index'));
                    break;
            }
        }

        return $tiles;
    }

    /** All, pending approval and sent quotations of the unit, the filters of the quotation list, in one grouped query. */
    private function quotationCounts(Unit $unit): array
    {
        $byStatus = Quotation::where('unit_id', $unit->id)->latestRevisions()
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')
            ->mapWithKeys(fn ($n, $s) => [($s instanceof QuotationStatus ? $s->value : (string) $s) => (int) $n]);
        $sent = array_map(fn ($s) => $s->value, QuotationStatus::customerStatuses());

        return [
            'all' => $byStatus->sum(),
            'approval' => $byStatus->get(QuotationStatus::InternalApproval->value, 0),
            'sent' => $byStatus->only($sent)->sum(),
        ];
    }

    /** $noun: [one, many], for the link's spoken name ("12 enquiries, open list"). */
    private function tile(string $key, string $label, int $count, array $noun, string $hint, string $url): array
    {
        $noun = $count === 1 ? $noun[0] : $noun[1];

        return compact('key', 'label', 'count', 'noun', 'hint', 'url');
    }
}
