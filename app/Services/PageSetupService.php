<?php

namespace App\Services;

use App\Models\PageSetting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Modules;
use App\Support\PageForm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Page setup (CRM changes, Sat 13/10/2026; every page, Sat 14/10/2026): which fields of a page show and
 * which are mandatory. Pages and their defaults come from config('nursery.page_fields'); saved choices override
 * them. Any page plugs in through configuration only:
 *   - its Form Request passes its rules through rules($page, $rules): hidden fields are dropped (not validated,
 *     not saved, so what is stored stays as it was) and the others become required or nullable as set up;
 *   - its view asks form($page) whether to show a field and whether to mark it mandatory.
 * Read on every form open and save, so each page's choices are cached (written again on each save, at most an
 * hour old) and kept for the rest of the request.
 */
class PageSetupService
{
    /** @var array<string, array> this request's copy, per page */
    private array $memo = [];

    /** Pages that can be set up now, grouped as on the Page setup list: group => key => label. Pages of a part that is off are left out. */
    public function pages(): array
    {
        $out = [];
        foreach (config('nursery.page_fields') as $key => $page) {
            if (Modules::allows($page['module'] ?? null)) {
                $out[$page['group'] ?? 'Other'][$key] = $page['label'];
            }
        }

        return $out;
    }

    /** True when the page exists and its optional part (if any) is on. */
    public function available(string $page): bool
    {
        $config = config("nursery.page_fields.$page");

        return $config && Modules::allows($config['module'] ?? null);
    }

    /** @return array<string, array{label: string, visible: bool, required: bool, fixed: bool, can_require: bool}> */
    public function fields(string $page): array
    {
        if (isset($this->memo[$page])) {
            return $this->memo[$page];
        }
        $config = config("nursery.page_fields.$page.fields");
        abort_unless($config, 404);

        $saved = Cache::remember(self::key($page), ModuleSettings::CACHE_TTL, fn () => self::load($page));

        $out = [];
        foreach ($config as $field => $c) {
            $fixed = (bool) ($c['fixed'] ?? false);
            $canRequire = ! $fixed && ($c['can_require'] ?? true);
            [$visible, $required] = $saved[$field] ?? [true, (bool) $c['required']];
            $out[$field] = [
                'label' => $c['label'],
                'fixed' => $fixed,
                'can_require' => $canRequire,
                'visible' => $fixed || $visible,
                // A hidden field cannot be mandatory.
                'required' => $fixed || ($canRequire && $visible && $required),
            ];
        }

        return $this->memo[$page] = $out;
    }

    public function visible(string $page, string $field): bool
    {
        return $this->fields($page)[$field]['visible'] ?? true;
    }

    public function required(string $page, string $field): bool
    {
        return $this->fields($page)[$field]['required'] ?? false;
    }

    /** What a view needs: show, mandatory star and the "Mandatory: …" line. */
    public function form(string $page): PageForm
    {
        return new PageForm($this->fields($page));
    }

    /**
     * A Form Request's rules with the page setup applied. Fixed fields keep their rules as written. For the
     * others: a hidden field's inputs (and their ".*" rules) are removed; a shown field's inputs start with
     * "required" or "nullable" as set up ("nullable" when the request checks it itself, field "checked").
     * Rules for inputs not listed in the page setup are left as they are.
     */
    public function rules(string $page, array $rules): array
    {
        $config = config("nursery.page_fields.$page.fields");
        foreach ($this->fields($page) as $field => $f) {
            if ($f['fixed']) {
                continue;
            }
            foreach ($config[$field]['inputs'] ?? [$field] as $input) {
                if (! $f['visible']) {
                    foreach (array_keys($rules) as $key) {
                        if ($key === $input || str_starts_with($key, $input.'.')) {
                            unset($rules[$key]);
                        }
                    }

                    continue;
                }
                if (! array_key_exists($input, $rules)) {
                    continue;
                }
                $rest = array_values(array_filter((array) $rules[$input], fn ($r) => ! in_array($r, ['required', 'nullable', 'sometimes'], true)));
                $need = $f['required'] && empty($config[$field]['checked']);
                $rules[$input] = array_merge([$need ? 'required' : 'nullable'], $rest);
            }
        }

        return $rules;
    }

    /** $choices: field => ['visible' => bool, 'required' => bool]. Fixed fields are not changed. */
    public function save(string $page, array $choices, User $actor): void
    {
        $before = $this->fields($page);

        DB::transaction(function () use ($page, $choices, $actor, $before) {
            $changes = [];
            foreach ($before as $field => $f) {
                if ($f['fixed']) {
                    continue;
                }
                $visible = (bool) ($choices[$field]['visible'] ?? false);
                $required = $f['can_require'] && $visible && (bool) ($choices[$field]['required'] ?? false);
                PageSetting::updateOrCreate(['page' => $page, 'field' => $field], ['is_visible' => $visible, 'is_required' => $required])
                    ->forceFill(['updated_by' => $actor->id])->save();
                $old = self::describe($f['visible'], $f['required']);
                $new = self::describe($visible, $required);
                if ($old !== $new) {
                    $changes[$f['label']] = ['old' => $old, 'new' => $new];
                }
            }
            if ($changes) {
                ActivityLogger::log('page_setup.updated', null, 'Changed page setup of '.config("nursery.page_fields.$page.label"), $changes);
            }
        });

        // The new choices go into the cache once committed (architecture review S1); the entry lasts at most an hour.
        Cache::put(self::key($page), self::load($page), ModuleSettings::CACHE_TTL);
        unset($this->memo[$page]);
    }

    /** Saved choices of a page: field => [visible, required]. */
    private static function load(string $page): array
    {
        return PageSetting::where('page', $page)->get(['field', 'is_visible', 'is_required'])
            ->keyBy('field')->map(fn ($s) => [$s->is_visible, $s->is_required])->all();
    }

    private static function describe(bool $visible, bool $required): string
    {
        return ! $visible ? 'Hidden' : ($required ? 'Shown, mandatory' : 'Shown, optional');
    }

    private static function key(string $page): string
    {
        return 'page-setup.'.$page;
    }
}
