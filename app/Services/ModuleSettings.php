<?php

namespace App\Services;

use App\Models\ModuleSetting;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Switches for the optional parts of the CRM flow (Sat 14/10/2026): Site Visit / Requirement Gathering,
 * BOQ Preparation and Cost details. One setting for the whole installation (one installation per client),
 * defaults from config('nursery.modules'). Read on every page (gate, menu, alerts), so the saved choices are
 * cached and kept for the rest of the request: no query per check (target 5,000+ users at once).
 * A save writes the new values into the cache after its commit (architecture review S1), and the cache entry
 * lasts at most CACHE_TTL seconds, so a value written back by a request that read just before the save can
 * never stay longer than that.
 */
class ModuleSettings
{
    private const CACHE_KEY = 'module-settings';

    public const CACHE_TTL = 3600;

    /** @var array<string, bool>|null this request's copy */
    private ?array $state = null;

    /** @return array<string, bool> module => on */
    public function all(): array
    {
        return $this->state ??= self::withDefaults(Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::saved()));
    }

    public function on(string $module): bool
    {
        $all = $this->all();
        if (! array_key_exists($module, $all)) {
            throw new InvalidArgumentException("Unknown module [$module].");
        }

        return $all[$module];
    }

    /** $choices: module => bool. Logs what changed in the same transaction. */
    public function save(array $choices, User $actor): void
    {
        $saved = DB::transaction(function () use ($choices, $actor) {
            // The rows are read locked, so two admins saving at once log the right old values (database review PS-S4).
            $before = self::withDefaults(ModuleSetting::lockForUpdate()->pluck('is_enabled', 'module')->map(fn ($v) => (bool) $v)->all());
            $now = now();
            $rows = [];
            $changes = [];
            foreach (config('nursery.modules') as $key => $m) {
                $on = (bool) ($choices[$key] ?? $before[$key]);
                $rows[] = ['module' => $key, 'is_enabled' => $on, 'updated_by' => $actor->id, 'created_at' => $now, 'updated_at' => $now];
                if ($on !== $before[$key]) {
                    $changes[$m['label']] = ['old' => $before[$key] ? 'On' : 'Off', 'new' => $on ? 'On' : 'Off'];
                }
            }
            // One statement, insert or update: a first save by two admins at once cannot hit the UNIQUE (module) key.
            ModuleSetting::upsert($rows, ['module'], ['is_enabled', 'updated_by', 'updated_at']);
            if ($changes) {
                ActivityLogger::log('modules.updated', null, 'Switched '.implode(', ', array_map(fn ($l, $c) => $l.' '.strtolower($c['new']), array_keys($changes), $changes)), $changes);
            }

            return array_combine(array_column($rows, 'module'), array_column($rows, 'is_enabled'));
        });

        // The new values go into the cache once committed, so the next request on any server sees them.
        Cache::put(self::CACHE_KEY, $saved, self::CACHE_TTL);
        $this->state = null;
    }

    /** @return array<string, bool> what is stored, without defaults */
    private static function saved(): array
    {
        return ModuleSetting::pluck('is_enabled', 'module')->map(fn ($v) => (bool) $v)->all();
    }

    private static function withDefaults(array $saved): array
    {
        $out = [];
        foreach (config('nursery.modules') as $key => $m) {
            $out[$key] = (bool) ($saved[$key] ?? $m['default'] ?? true);
        }

        return $out;
    }
}
