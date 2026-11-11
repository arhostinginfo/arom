<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModuleSettingsRequest;
use App\Http\Requests\Admin\PageSetupRequest;
use App\Services\ModuleSettings;
use App\Services\PageSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin > Page setup (CRM changes, Sat 13/10/2026; every page and the optional parts, Sat 14/10/2026):
 * show / hide and mandatory / optional for each field of every entry page listed in config('nursery.page_fields'),
 * and the switches for the optional parts of the CRM flow (site visit, BOQ, costing).
 */
class PageSetupController extends Controller
{
    public function __construct(private readonly PageSetupService $setup)
    {
    }

    public function index(ModuleSettings $modules): View
    {
        $groups = [];
        // Pages of a part that is switched off stay listed, greyed, so an admin can see where they went (UX review S2).
        foreach (config('nursery.page_fields') as $key => $page) {
            if (! $this->setup->available($key)) {
                $groups[$page['group'] ?? 'Other'][$key] = ['label' => $page['label'], 'off' => config('nursery.modules.'.\App\Support\Modules::offOf($page['module'] ?? null).'.label')];
            }
        }
        foreach ($this->setup->pages() as $group => $pages) {
            foreach ($pages as $key => $label) {
                $fields = $this->setup->fields($key);
                $groups[$group][$key] = [
                    'label' => $label,
                    'shown' => count(array_filter($fields, fn ($f) => $f['visible'])),
                    'required' => count(array_filter($fields, fn ($f) => $f['required'])),
                    'total' => count($fields),
                ];
            }
        }

        return view('admin.page-setup.index', ['groups' => $groups, 'modules' => $modules->all()]);
    }

    public function edit(string $page): View
    {
        abort_unless($this->setup->available($page), 404);

        return view('admin.page-setup.edit', [
            'page' => $page,
            'label' => config("nursery.page_fields.$page.label"),
            'fields' => $this->setup->fields($page),
        ]);
    }

    public function update(PageSetupRequest $request, string $page): RedirectResponse
    {
        abort_unless($this->setup->available($page), 404);
        $this->setup->save($page, (array) $request->validated('fields', []), $request->user());

        return redirect()->route('admin.page-setup.edit', $page)->with('toast', 'Page setup of '.config("nursery.page_fields.$page.label").' saved');
    }

    /** Switch the optional parts of the CRM flow on or off for the whole installation. */
    public function updateModules(ModuleSettingsRequest $request, ModuleSettings $modules): RedirectResponse
    {
        $modules->save((array) $request->validated('modules', []), $request->user());
        // The toast says the new state: "Site Visit / Requirement Gathering switched off · BOQ Preparation and Cost details (costing) on".
        $labels = fn (bool $on) => array_map(fn ($k) => config("nursery.modules.$k.label"), array_keys(array_filter($modules->all(), fn ($v) => $v === $on)));
        $join = fn (array $l) => count($l) > 1 ? implode(', ', array_slice($l, 0, -1)).' and '.end($l) : ($l[0] ?? '');
        $toast = implode(' · ', array_filter([$join($labels(false)) ? $join($labels(false)).' switched off' : null, $join($labels(true)) ? $join($labels(true)).' on' : null]));

        return redirect()->route('admin.page-setup.index')->with('toast', $toast);
    }
}
