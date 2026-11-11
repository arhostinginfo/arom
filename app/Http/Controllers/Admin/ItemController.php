<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Crm\QuotationController;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Admin\ItemRequest;
use App\Http\Requests\Crm\ImportRequest;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Party;
use App\Models\TaxStructure;
use App\Models\Uom;
use App\Services\ItemService;
use App\Support\Csv;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Masters › Item master (built with the Sales module, 15/10/2026): list paged, searched and filtered on the
 * server (rule P1), a form, Import and Download CSV (OTHER POINTS row 5).
 */
class ItemController extends Controller
{
    public function __construct(private readonly ItemService $items)
    {
    }

    public function index(Request $request): View
    {
        $q = LookupController::term($request);
        $type = in_array($request->query('type'), Item::TYPES, true) ? $request->query('type') : null;
        $status = in_array($request->query('status'), ['Active', 'Inactive'], true) ? $request->query('status') : null;
        $party = $request->integer('party') ?: null;
        $query = Item::query()->with(['party:id,name', 'uom:id,code', 'hsn:id,code', 'currency:id,code'])
            ->when($type, fn ($w) => $w->where('type', $type))
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($party, fn ($w) => $w->where('party_id', $party))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('internal_item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('description', 'like', LookupController::prefix($q))));
        [$sort, $dir] = ListQuery::sort($query, $request, ['no' => 'item_no', 'description' => 'description']);

        return view('admin.items.index', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q, 'type' => $type, 'status' => $status, 'sort' => $sort, 'dir' => $dir,
            'partyName' => $party ? Party::withTrashed()->find($party)?->name : null,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Item(['type' => 'Sales', 'status' => 'Active', 'currency_id' => Currency::defaultId()]));
    }

    public function edit(Item $item): View
    {
        return $this->form($item);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        $item = $this->items->save(null, $request->validated(), $request->user());

        return redirect()->route('admin.items.edit', $item)->with('toast', 'Item '.$item->item_no.' added');
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $this->items->save($item, $request->validated(), $request->user());

        return redirect()->route('admin.items.edit', $item)->with('toast', 'Item '.$item->item_no.' saved');
    }

    public function destroy(Request $request, Item $item): RedirectResponse
    {
        $this->items->delete($item, $request->user());

        return redirect()->route('admin.items.index')->with('toast', 'Item '.$item->item_no.' deleted');
    }

    public function export(): StreamedResponse
    {
        $rows = Item::query()->with(['party:id,name', 'category:id,name', 'hsn:id,code', 'uom:id,code', 'taxStructure:id,name', 'currency:id,code'])
            ->lazyById(1000)->map(fn (Item $i) => [
                $i->party?->name, $i->category?->name, $i->description, $i->item_no, $i->internal_item_no, $i->type, $i->hsn?->code, $i->uom?->code,
                $i->taxStructure?->name, $i->price, $i->currency?->code, $i->min_stock, $i->max_stock, $i->weight_per_piece, $i->packaging_qty, $i->status,
            ]);

        return Csv::download('items-'.now()->format('Y-m-d').'.csv', array_values(ItemService::COLUMNS), $rows);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('item-import-template.csv', array_values(ItemService::COLUMNS), [
            ['Apex Auto Components', '', 'Brake bracket', 'BRK-2210', '10-4471', 'Sales', '8708', 'NOS', 'GST 18%', '42.50', 'INR', '500', '3000', '0.42', '100', 'Active'],
        ]);
    }

    public function import(ImportRequest $request): RedirectResponse
    {
        $rows = Csv::read($request->file('file'), ['Item description', 'Type', 'HSN/SAC', 'UOM', 'Tax structure']);
        $count = $this->items->import($rows, $request->user());

        return redirect()->route('admin.items.index')->with('toast', $count.' item(s) imported');
    }

    /** Type-ahead for the HSN/SAC picker: first 20 codes starting with the typed text. */
    public function hsnLookup(Request $request): JsonResponse
    {
        $q = LookupController::term($request);

        return response()->json(HsnSacCode::query()->where('is_active', true)
            ->when($q !== '', fn ($w) => $w->where('code', 'like', LookupController::prefix($q)))
            ->orderBy('code')->forPage(LookupController::page(), LookupController::LIMIT)->get()
            ->map(fn ($h) => ['id' => $h->id, 'label' => $h->label(), 'sub' => $h->type]));
    }

    /** HSN/SAC picker: every active code while there are at most 200 (type-ahead above that), plus the chosen one. */
    private static function hsnOptions($selected): \Illuminate\Support\Collection
    {
        $list = HsnSacCode::query()->where('is_active', true)->orderBy('code')->limit(201)->get();
        $list = $list->count() > 200 ? collect() : $list;
        if ($selected && ! $list->contains('id', (int) $selected) && ($h = HsnSacCode::find($selected))) {
            $list->push($h);
        }

        return $list->mapWithKeys(fn ($h) => [$h->id => $h->label()]);
    }

    private function form(Item $item): View
    {
        $item->loadMissing(['creator', 'updater']);
        $keep = fn ($f) => old($f, $item->{$f});

        return view('admin.items.form', [
            'item' => $item,
            'parties' => QuotationController::pickerOptions(Party::query()->orderBy('name'), Party::class, $keep('party_id')),
            'categories' => ItemCategory::forPicker($keep('item_category_id'))->orderBy('name')->get(),
            'hsns' => self::hsnOptions($keep('hsn_sac_code_id')),
            'uoms' => Uom::forPicker($keep('uom_id'))->orderBy('code')->get(),
            'taxes' => TaxStructure::forPicker($keep('tax_structure_id'))->orderBy('igst_rate')->get(),
            'currencies' => Currency::orderBy('code')->get(),
            'history' => ActivityLog::history($item),
        ]);
    }
}
