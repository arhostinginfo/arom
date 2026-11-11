<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Crm\ContactRequest;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Party;
use App\Models\Prospect;
use App\Services\ContactService;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
| CRM > Contacts (Contact / POC of the CRM changes Word file, built 14/10/2026). The contacts of every client,
| a prospect or a customer: a list paged and searched on the server (rule P1) and a form. The enquiry and
| prospect pages show their client's contacts and link here, filtered by that client (?prospect= or ?party=).
| Contacts belong to the client, not to a unit, like prospects.
*/
class ContactController extends Controller
{
    /** Contacts shown on the enquiry and prospect pages; "View all" opens the list for the rest. */
    public const BOX_SIZE = 10;

    public function __construct(private readonly ContactService $contacts)
    {
    }

    public function index(Request $request): View
    {
        $q = LookupController::term($request);
        $prospect = $request->integer('prospect') ?: null;
        $party = $prospect ? null : ($request->integer('party') ?: null);
        $query = Contact::query()->with(['party:id,name', 'prospect:id,name'])
            ->when($prospect, fn ($w) => $w->where('prospect_id', $prospect))
            ->when($party, fn ($w) => $w->where('party_id', $party))
            // Name, Contact ID, mobile (any way it is typed) or client name, all "starts with" so indexes are used. The
            // matching clients are looked up first (at most 200 of each) and passed as plain id lists, so every part
            // of the OR uses an index (database review PS-S1).
            ->when($q !== '', function ($w) use ($q) {
                $prospects = Prospect::where('name', 'like', LookupController::prefix($q))->limit(200)->pluck('id')->all();
                $parties = Party::where('name', 'like', LookupController::prefix($q))->limit(200)->pluck('id')->all();
                $digits = preg_match('/^[\d\s+()-]+$/', $q) ? \App\Models\Enquiry::phoneDigits($q) : null;
                $w->where(fn ($s) => $s->where('name', 'like', LookupController::prefix($q))
                    ->orWhere('contact_no', 'like', LookupController::prefix(strtoupper($q)))
                    ->when($digits && strlen($digits) >= 4, fn ($m) => $m->orWhere('mobile_digits', 'like', LookupController::prefix((string) $digits)))
                    ->when($prospects, fn ($m) => $m->orWhereIn('prospect_id', $prospects))
                    ->when($parties, fn ($m) => $m->orWhereIn('party_id', $parties)));
            });
        [$sort, $dir] = ListQuery::sort($query, $request, ['name' => 'name', 'no' => 'contact_no']);

        return view('crm.contacts.index', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'client' => $prospect ? Prospect::withTrashed()->find($prospect) : ($party ? Party::withTrashed()->find($party) : null),
            'clientParams' => array_filter(['prospect' => $prospect, 'party' => $party]),
        ]);
    }

    /**
     * New contact; ?prospect= or ?party= fills in the client. From the enquiry or prospect page ?from=enquiry:12
     * also brings the user back there after saving (UX review M2); from an enquiry whose client has no contacts
     * yet, Name and Mobile start with the enquiry's contact name and no (UX review S8).
     */
    public function create(Request $request): View
    {
        $c = new Contact();
        $c->prospect_id = $request->integer('prospect') ?: null;
        $c->party_id = $c->prospect_id ? null : ($request->integer('party') ?: null);
        $origin = self::origin($request->input('from'));
        if (($e = $origin['enquiry'] ?? null) && ($c->party_id || $c->prospect_id)
            && ! Contact::ofClient($c->party_id, $c->prospect_id)->exists()) {
            $c->name = $e->contact_name;
            $c->mobile = $e->contact_no;
        }

        return $this->form($request, $c);
    }

    public function show(Request $request, Contact $contact): View
    {
        return $this->form($request, $contact);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $c = $this->contacts->create($request->validated(), $request->user());
        $origin = self::origin($request->input('from'));

        return redirect()->to($origin ? $origin['url'].'#contacts' : route('crm.contacts.show', $c))->with('toast', 'Contact '.$c->contact_no.' '.$c->name.' added');
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $this->contacts->update($contact, $request->validated(), $request->user());
        $origin = self::origin($request->input('from'));
        if ($origin) {
            return redirect()->to($origin['url'].'#contacts')->with('toast', 'Contact '.$contact->contact_no.' saved');
        }

        return redirect()->route('crm.contacts.show', $contact)->with('toast', 'Contact '.$contact->contact_no.' saved');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        $this->contacts->delete($contact, $request->user());
        $back = array_filter(['prospect' => $contact->prospect_id, 'party' => $contact->party_id]);

        return redirect()->route('crm.contacts.index', $back)->with('toast', 'Contact '.$contact->contact_no.' '.$contact->name.' deleted');
    }

    /**
     * The Contacts / POC box of a client for the enquiry and prospect pages: the first BOX_SIZE by name (one
     * indexed query, no count), or null when the user cannot see contacts or there is no client yet.
     */
    public static function box(?int $partyId, ?int $prospectId, ?string $from = null): ?array
    {
        if ((! $partyId && ! $prospectId) || ! auth()->user()?->can('contact.view')) {
            return null;
        }
        $list = Contact::ofClient($partyId, $prospectId)->orderBy('name')->orderBy('id')->limit(self::BOX_SIZE + 1)->get();

        return [
            'list' => $list->take(self::BOX_SIZE),
            'more' => $list->count() > self::BOX_SIZE,
            'params' => $partyId ? ['party' => $partyId] : ['prospect' => $prospectId],
            // The page the box is on, so Add contact and a contact opened from it come back here.
            'from' => $from,
        ];
    }

    /**
     * The page a contact was opened from, "enquiry:12" or "prospect:7": its address and a "Back to …" label.
     * Only those two kinds, only a record the user may open, so it can never send anyone elsewhere.
     */
    public static function origin(?string $from): ?array
    {
        if (! is_string($from) || ! preg_match('/^(enquiry|prospect):(\d{1,10})$/', $from, $m)) {
            return null;
        }
        $user = auth()->user();
        if ($m[1] === 'enquiry') {
            $e = \App\Models\Enquiry::whereIn('unit_id', \App\Support\WorkContext::unitIds())->find((int) $m[2]);

            return $e && $user->can('enquiry.view') ? ['from' => $from, 'url' => route('crm.enquiries.show', $e), 'label' => 'Back to '.$e->enquiry_no, 'enquiry' => $e] : null;
        }
        $p = Prospect::find((int) $m[2]);

        return $p && $user->can('prospect.view') ? ['from' => $from, 'url' => route('crm.prospects.show', $p), 'label' => 'Back to '.$p->name] : null;
    }

    private function form(Request $request, Contact $contact): View
    {
        $contact->loadMissing(['party', 'prospect', 'creator', 'updater']);
        $keep = fn ($f) => $request->old($f, $contact->{$f});

        return view('crm.contacts.form', [
            'contact' => $contact,
            'ps' => app(\App\Services\PageSetupService::class)->form('contact'),
            // Type-ahead pickers (decision A11): the select lists every record only while there are few.
            'customers' => QuotationController::pickerOptions(Party::customers(), Party::class, $keep('party_id')),
            'prospects' => QuotationController::pickerOptions(Prospect::orderBy('name'), Prospect::class, $keep('prospect_id')),
            'hasCustomers' => Party::customers()->exists(),
            'history' => ActivityLog::history($contact, 6),
            'origin' => self::origin($request->input('from')),
        ]);
    }
}
