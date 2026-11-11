<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Installation-wide settings of Admin > Config (22/10/2026), e.g. the maximum upload size. Defaults from
 * config('nursery.settings'). Cached and kept for the rest of the request the same way as ModuleSettings: a save
 * writes the new values into the cache after its commit, and the entry lasts at most ModuleSettings::CACHE_TTL.
 */
class AppSettings
{
    private const CACHE_KEY = 'app-settings';

    /** @var array<string, string|null>|null this request's copy */
    private ?array $state = null;

    /** @return array<string, string|null> key => value, defaults filled in */
    public function all(): array
    {
        return $this->state ??= self::withDefaults(Cache::remember(self::CACHE_KEY, ModuleSettings::CACHE_TTL, fn () => self::saved()));
    }

    public function get(string $key): ?string
    {
        $all = $this->all();
        if (! array_key_exists($key, $all)) {
            throw new InvalidArgumentException("Unknown setting [$key].");
        }

        return $all[$key];
    }

    /** $values: key => value. $shown formats a value for the activity log, e.g. 1024 => "1 MB". */
    public function save(array $values, User $actor, ?callable $shown = null): void
    {
        $shown ??= fn ($v) => $v;
        $saved = DB::transaction(function () use ($values, $actor, $shown) {
            // Read locked, so two admins saving at once log the right old values (as ModuleSettings).
            $before = self::withDefaults(AppSetting::lockForUpdate()->pluck('value', 'key')->all());
            $now = now();
            $rows = [];
            $changes = [];
            foreach (config('nursery.settings') as $key => $s) {
                $value = array_key_exists($key, $values) ? (string) $values[$key] : $before[$key];
                $rows[] = ['key' => $key, 'value' => $value, 'updated_by' => $actor->id, 'created_at' => $now, 'updated_at' => $now];
                if ($value !== $before[$key]) {
                    $changes[$s['label']] = ['old' => $shown($before[$key], $key), 'new' => $shown($value, $key)];
                }
            }
            AppSetting::upsert($rows, ['key'], ['value', 'updated_by', 'updated_at']);
            if ($changes) {
                ActivityLogger::log('settings.updated', null, 'Changed '.implode(', ', array_map(fn ($l, $c) => $l.' to '.$c['new'], array_keys($changes), $changes)), $changes);
            }

            return array_combine(array_column($rows, 'key'), array_column($rows, 'value'));
        });

        Cache::put(self::CACHE_KEY, $saved, ModuleSettings::CACHE_TTL);
        $this->state = null;
    }

    /** @return array<string, string|null> what is stored, without defaults */
    private static function saved(): array
    {
        return AppSetting::pluck('value', 'key')->all();
    }

    private static function withDefaults(array $saved): array
    {
        $out = [];
        foreach (config('nursery.settings') as $key => $s) {
            $out[$key] = isset($saved[$key]) ? (string) $saved[$key] : (isset($s['default']) ? (string) $s['default'] : null);
        }

        return $out;
    }
}
