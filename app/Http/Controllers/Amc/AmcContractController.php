<?php

namespace App\Http\Controllers\Amc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Amc\AmcContractRequest;
use App\Http\Requests\Amc\AmcFileRequest;
use App\Models\ActivityLog;
use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcContract;
use App\Models\Amc\AmcEmployee;
use App\Models\Amc\AmcFile;
use App\Models\Amc\AmcType;
use App\Services\Amc\AmcContractService;
use App\Services\Amc\AmcFiles;
use App\Services\Amc\AmcFileService;
use App\Services\Amc\AmcImages;
use App\Support\Csv;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AMC › Customer PO / AMC and AMC list (21/11/2026): the contract form with its files, the colour-coded list (paged,
 * searched and filtered on the server, rule P1) and its CSV download.
 */
class AmcContractController extends Controller
{
    public function __construct(private readonly AmcContractService $contracts, private readonly AmcFileService $files)
    {
    }

    /** Menu item "Customer PO / AMC": a new contract for users who may add one, otherwise the AMC list. */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->can('amc-contract.add') ? 'amc.contract.create' : 'amc.contracts.list');
    }

    public function list(Request $request): View
    {
        $f = $this->filters($request);
        $query = $this->filtered($f)->with(['client:id,name', 'type:id,name', 'gardener:id,name']);
        [$sort, $dir] = ListQuery::sort($query, $request, ['end' => 'end_date', 'start' => 'start_date', 'added' => 'id']);

        return view('amc.contracts.list', $f + [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $df = config('nursery.date_format');
        $rows = $this->filtered($this->filters($request))->with(['client:id,name', 'type:id,name', 'operationManager:id,name', 'gardener:id,name'])
            ->lazyById(1000)->map(fn (AmcContract $c) => [
                $c->number(), $c->client?->name, $c->type?->name, $c->start_date?->format($df), $c->end_date?->format($df), $c->billing_cycle, $c->visit_plan,
                Money::indian($c->amount, 2), $c->operationManager?->name, $c->gardener?->name, $c->status, AmcContract::COLOURS[$c->colour()][0], $c->daysLeft(),
            ]);

        return Csv::download('amc-contracts-'.now()->format('Y-m-d').'.csv', ['AMC no', 'Customer', 'AMC type', 'Start date', 'End date', 'Billing cycle',
            'Visit plan', 'AMC amount', 'Operation manager', 'Gardener', 'Status', 'Colour', 'Days left'], $rows);
    }

    public function create(Request $request): View
    {
        $client = $request->integer('client') ?: null;

        return $this->form(new AmcContract(['status' => AmcContract::ACTIVE, 'billing_cycle' => 'Monthly', 'amc_client_id' => $client]));
    }

    public function show(AmcContract $contract): View
    {
        return $this->form($contract);
    }

    public function store(AmcContractRequest $request): RedirectResponse
    {
        $c = $this->contracts->save(null, $request->forDb(), $request->user());

        return redirect()->route('amc.contract.show', $c)->with('toast', 'AMC contract '.$c->number().' added');
    }

    public function update(AmcContractRequest $request, AmcContract $contract): RedirectResponse
    {
        $this->contracts->save($contract, $request->forDb(), $request->user());

        return redirect()->route('amc.contract.show', $contract)->with('toast', 'AMC contract '.$contract->number().' saved');
    }

    public function destroy(Request $request, AmcContract $contract): RedirectResponse
    {
        $this->contracts->delete($contract, $request->user());

        return redirect()->route('amc.contracts.list')->with('toast', 'AMC contract '.$contract->number().' deleted');
    }

    public function upload(AmcFileRequest $request, AmcContract $contract): RedirectResponse
    {
        $category = $request->validated('category');
        $r = $this->files->upload($contract, $category, $request->file('files'), $request->user());
        $label = config('amc.file_categories.'.$category.'.label');
        $msg = match (true) {
            $r['collage'] => $label.': the images were combined into one collage',
            ! $r['gd'] => $label.': '.$r['saved'].' file(s) saved as they are (image tools are not available on this server)',
            default => $label.': '.$r['saved'].' file(s) saved',
        };

        return redirect()->to(route('amc.contract.show', $contract).'#files')->with('toast', $msg);
    }

    /** Opens a file of the contract from the private disk; images and PDFs show in the browser. */
    public function download(AmcFile $file): Response
    {
        abort_unless(AmcFiles::disk()->exists($file->path), 404);
        $inline = $file->isImage() || $file->mime === 'application/pdf';

        return AmcFiles::disk()->response($file->path, $file->original_name, ['Content-Type' => $file->mime ?: 'application/octet-stream'], $inline ? 'inline' : 'attachment');
    }

    public function destroyFile(Request $request, AmcFile $file): RedirectResponse
    {
        $contract = $file->contract;
        $this->files->delete($file, $request->user());

        return redirect()->to(route('amc.contract.show', $contract).'#files')->with('toast', $file->original_name.' deleted');
    }

    /** Admin only: the daily clean-up run now. */
    public function purge(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $count = $this->files->purgeOldDeployment($request->user());

        return back()->with('toast', $count.' deployment image(s) older than 1 year deleted');
    }

    /** ?q= customer name starts with, ?status=Active|Inactive, ?colour=green|red|black|grey, ?renewal=1 (ending within 60 days). */
    private function filters(Request $request): array
    {
        return [
            'q' => LookupController::term($request),
            'status' => in_array($request->query('status'), ['Active', 'Inactive'], true) ? $request->query('status') : null,
            'colour' => array_key_exists((string) $request->query('colour'), AmcContract::COLOURS) ? $request->query('colour') : null,
            'renewal' => $request->query('renewal') === '1',
        ];
    }

    private function filtered(array $f)
    {
        return AmcContract::query()
            ->when($f['q'] !== '', fn ($w) => preg_match('/^(?:AMC-?)?0*(\d{1,9})$/i', $f['q'], $m)
                ? $w->where(fn ($s) => $s->whereKey((int) $m[1])->orWhereIn('amc_client_id', AmcClient::select('id')->where('name', 'like', LookupController::prefix($f['q']))))
                : $w->whereIn('amc_client_id', AmcClient::select('id')->where('name', 'like', LookupController::prefix($f['q']))))
            ->when($f['status'], fn ($w) => $w->where('status', $f['status']))
            ->when($f['colour'], fn ($w) => $w->ofColour($f['colour']))
            ->when($f['renewal'], fn ($w) => $w->dueForRenewal());
    }

    private function form(AmcContract $c): View
    {
        $c->loadMissing(['client.vertical', 'client.lines.planter', 'client.lines.plant', 'operationManager', 'gardener', 'creator', 'updater']);
        $keep = fn ($f) => old($f, $c->{$f});
        $employees = fn (string $role, $selected) => AmcLookupController::options(AmcEmployee::forPicker($role, $selected), $selected, fn ($e) => $e->label());
        $client = $keep('amc_client_id') ? AmcClient::with(['vertical', 'lines.planter', 'lines.plant'])->find($keep('amc_client_id')) : null;

        return view('amc.contracts.form', [
            'c' => $c,
            'client' => $client,
            'clients' => AmcLookupController::options(AmcClient::query()->orderBy('name')->orderBy('id'), $keep('amc_client_id'), fn ($m) => $m->name),
            'types' => AmcType::forPicker($keep('amc_type_id'))->get(),
            'managers' => $employees(AmcEmployee::OPERATION_MANAGER, $keep('operation_manager_id')),
            'gardeners' => $employees(AmcEmployee::GARDENER, $keep('gardener_id')),
            'fileList' => $c->exists ? $c->files()->with('uploader:id,name')->latest('id')->get()->groupBy('category') : collect(),
            'gd' => AmcImages::available(),
            'history' => ActivityLog::history($c),
        ]);
    }
}
