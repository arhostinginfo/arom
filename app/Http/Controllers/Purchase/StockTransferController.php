<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Purchase\StockTransferActionRequest;
use App\Http\Requests\Purchase\StockTransferRequest;
use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\StockLedger;
use App\Services\StockTransferService;
use App\Support\ListQuery;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
| Purchase › Stock transfer (prototype D9): one entry as a full form with an entry picker (D11), its approval, and a
| list paged on the server (P1). The prototype's page capability table gives this page no Import, CSV or PDF.
| Entries shown are those of the financial year where the unit on the top bar is the source or the destination.
*/
class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $transfers)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('id')) {
            return redirect()->route('purchase.stock-transfers.show', (int) $request->query('id'));
        }

        return $this->page($request, self::search($request)->first() ?? $this->blank());
    }

    public function create(Request $request): View
    {
        return $this->page($request, $this->blank());
    }

    public function show(Request $request, StockTransfer $transfer): View
    {
        $this->authorizeUnit($transfer);

        return $this->page($request, $transfer);
    }

    public function store(StockTransferRequest $request): RedirectResponse
    {
        $t = $this->transfers->create($request->validated(), $request->user(), WorkContext::financialYear());

        return redirect()->route('purchase.stock-transfers.show', $t)->with('toast', 'Stock transfer '.$t->transfer_no.' added as a Draft. Send it for approval to move the stock.');
    }

    public function update(StockTransferRequest $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeSource($transfer);
        $this->transfers->update($transfer, $request->validated(), $request->user());

        return redirect()->route('purchase.stock-transfers.show', $transfer)->with('toast', 'Stock transfer '.$transfer->transfer_no.' saved');
    }

    public function destroy(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeSource($transfer);
        $this->transfers->delete($transfer, $request->user());

        return redirect()->route('purchase.stock-transfers.index')->with('toast', 'Stock transfer '.$transfer->transfer_no.' deleted');
    }

    public function submit(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeSource($transfer);
        $t = $this->transfers->submit($transfer, $request->user());

        return redirect()->route('purchase.stock-transfers.show', $transfer)->with('toast', $t->transfer_no.' is '.$t->status);
    }

    public function approve(StockTransferActionRequest $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeSource($transfer);
        $this->transfers->approve($transfer, $request->user(), $request->validated('remarks'));

        return redirect()->route('purchase.stock-transfers.show', $transfer)->with('toast', $transfer->transfer_no.' approved; the stock has moved');
    }

    public function reject(StockTransferActionRequest $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeSource($transfer);
        $this->transfers->reject($transfer, $request->user(), (string) $request->validated('remarks'));

        return redirect()->route('purchase.stock-transfers.show', $transfer)->with('toast', $transfer->transfer_no.' rejected');
    }

    /** Cancel needs Stock transfer · Delete; an Approved transfer (stock already moved) also needs the approver's right. */
    public function cancel(StockTransferActionRequest $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeSource($transfer);
        if ($transfer->status === StockTransfer::APPROVED && ! $request->user()->can('stock-transfer-approval.edit')) {
            throw ValidationException::withMessages(['status' => $transfer->transfer_no.' is Approved and its stock has moved, so only an approver can cancel it.']);
        }
        $this->transfers->cancel($transfer, $request->user(), (string) $request->validated('remarks'));

        return redirect()->route('purchase.stock-transfers.show', $transfer)->with('toast', $transfer->transfer_no.' cancelled');
    }

    /** Stock transfer list: all, waiting for approval, approved. Paged, searched and sorted on the server (P1). */
    public function list(Request $request): View
    {
        $views = ['all' => 'All', 'pending' => 'Pending approval', 'approved' => 'Approved'];
        $view = isset($views[$request->query('view')]) ? $request->query('view') : 'all';
        $query = self::search($request)->reorder()->with(['fromUnit:id,name', 'toUnit:id,name'])->withCount('lines');
        match ($view) {
            'pending' => $query->where('status', StockTransfer::PENDING),
            'approved' => $query->where('status', StockTransfer::APPROVED),
            default => null,
        };
        [$sort, $dir] = ListQuery::sort($query, $request, ['date' => 'transfer_date', 'no' => 'transfer_no'], 'desc');

        return view('purchase.stock-transfers-list', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'view' => $view, 'views' => $views, 'q' => LookupController::term($request), 'sort' => $sort, 'dir' => $dir,
            'unit' => WorkContext::unit(),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json(self::search($request)->with(['fromUnit:id,name', 'toUnit:id,name'])
            ->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (StockTransfer $t) => [
                'id' => $t->id,
                'label' => $t->transfer_no.' · '.($t->fromUnit?->name ?? '').' → '.($t->toUnit?->name ?? ''),
                'sub' => $t->transfer_date?->format(config('nursery.date_format')).' · '.$t->status,
                'url' => route('purchase.stock-transfers.show', $t),
            ]));
    }

    /** Item picker: active items, with the stock at the chosen source unit and the UOM from the Item master. */
    public function items(Request $request): JsonResponse
    {
        $q = LookupController::term($request);
        $from = $this->allowedUnit($request->integer('from'));
        $items = Item::where('status', 'Active')->with('uom:id,code')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('internal_item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('description', 'like', LookupController::prefix($q))))
            ->orderBy('item_no')->orderBy('id')->forPage(LookupController::page(), LookupController::LIMIT)->get();
        $stock = $from ? StockLedger::onHand([$from], $items->pluck('id')->all()) : [];

        return response()->json($items->map(fn (Item $i) => [
            'id' => $i->id,
            'label' => $i->pickerLabel(),
            'sub' => $from ? 'Stock at source '.\App\Support\Money::qty($stock[$i->id] ?? 0).' '.$i->uom?->code : $i->uom?->code,
            'data' => ['uom' => (string) $i->uom?->code, 'stock' => $from ? (string) ($stock[$i->id] ?? 0) : ''],
        ]));
    }

    /** Stock on hand at a unit for the given items (?unit=&items=1,2,3), for the rows on screen when Location from changes. */
    public function stock(Request $request): JsonResponse
    {
        $unit = $this->allowedUnit($request->integer('unit'));
        $ids = array_slice(array_values(array_filter(array_map('intval', explode(',', (string) $request->query('items'))))), 0, 200);
        if (! $unit || ! $ids) {
            return response()->json([]);
        }
        $stock = StockLedger::onHand([$unit], $ids);

        return response()->json(collect($ids)->mapWithKeys(fn ($id) => [$id => (float) ($stock[$id] ?? 0)]));
    }

    /** Entries of the year where the unit on the top bar is the source or the destination, newest first; number search. */
    public static function search(Request $request)
    {
        $q = LookupController::term($request);
        $unit = WorkContext::unit()?->id;

        return StockTransfer::query()->where('financial_year_id', WorkContext::financialYear()->id)
            ->where(fn ($w) => $w->where('from_unit_id', $unit)->orWhere('to_unit_id', $unit))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => LookupController::orNumber($s, 'transfer_no', 'ST', strtoupper($q))))
            ->latest('id');
    }

    private function page(Request $request, StockTransfer $transfer): View
    {
        $user = $request->user();
        $transfer->loadMissing(['lines.item.uom', 'lines.uom', 'fromUnit', 'toUnit', 'creator', 'updater', 'approver']);

        return view('purchase.stock-transfer', [
            'transfer' => $transfer,
            'entries' => self::search($request)->with(['fromUnit:id,name', 'toUnit:id,name'])->limit(50)->get(),
            'units' => $user->units()->orderBy('name')->get(['units.id', 'units.name']),
            'rule' => ApprovalRule::for('stock_transfer'),
            'approvals' => $transfer->exists ? $transfer->approvals()->with('actor:id,name')->latest('id')->limit(10)->get() : collect(),
            'history' => ActivityLog::history($transfer),
            'unit' => WorkContext::unit($user),
            'q' => LookupController::term($request),
        ]);
    }

    private function blank(): StockTransfer
    {
        $t = new StockTransfer(['transfer_date' => today(), 'from_unit_id' => WorkContext::unit()?->id, 'source_type' => 'Purchase']);
        $t->status = StockTransfer::DRAFT;

        return $t;
    }

    private function allowedUnit(?int $id): ?int
    {
        return $id && in_array($id, WorkContext::unitIds(), true) ? $id : null;
    }

    /**
     * Changing, sending, approving, rejecting, deleting or cancelling a transfer moves (or decides about) the source
     * unit's stock, so it needs the source unit; save already needs both units (review fix, 27/10/2026).
     */
    private function authorizeSource(StockTransfer $t): void
    {
        abort_unless(in_array($t->from_unit_id, WorkContext::unitIds(), true), 403);
    }

    /** A transfer is seen by users of either unit it touches. */
    private function authorizeUnit(StockTransfer $t): void
    {
        $mine = WorkContext::unitIds();
        abort_unless(in_array($t->from_unit_id, $mine, true) || in_array($t->to_unit_id, $mine, true), 403);
    }
}
