<?php

namespace App\Providers;

use App\Console\Commands\AmcPurgeOldImages;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * AMC module (21/11/2026), kept apart from CRM and Sales. Plugs in from here so no shared file has to change beyond
 * the one line in bootstrap/providers.php: its pages and permissions (Admin › Access rights) and its top menu come
 * from config/amc.php, its routes from routes/amc.php, plus its morph names, its command and the daily clean-up.
 */
class AmcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Added before anything reads them, and only once: after "php artisan config:cache" they are already there.
        $pages = config('nursery.pages', []);
        if (! isset($pages['AMC'])) {
            config(['nursery.pages' => self::insertBefore($pages, 'Admin', ['AMC' => config('amc.pages')])]);
        }
        $nav = config('nursery.nav', []);
        if (! collect($nav)->contains('key', 'amc')) {
            $at = collect($nav)->search(fn ($item) => $item['key'] === 'admin');
            array_splice($nav, $at === false ? count($nav) : $at, 0, [config('amc.nav')]);
            config(['nursery.nav' => $nav]);
        }
    }

    public function boot(): void
    {
        // Short names in activity_logs.subject_type, added to the app's enforced map.
        Relation::enforceMorphMap([
            'amc_vertical' => \App\Models\Amc\AmcVertical::class,
            'amc_planter' => \App\Models\Amc\AmcPlanter::class,
            'amc_plant' => \App\Models\Amc\AmcPlant::class,
            'amc_type' => \App\Models\Amc\AmcType::class,
            'amc_employee' => \App\Models\Amc\AmcEmployee::class,
            'amc_client' => \App\Models\Amc\AmcClient::class,
            'amc_contract' => \App\Models\Amc\AmcContract::class,
            'amc_return' => \App\Models\Amc\AmcReturn::class,
        ]);

        // Same middleware as every other page ('web', 'auth', 'active'), set in routes/amc.php.
        $this->loadRoutesFrom(base_path('routes/amc.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([AmcPurgeOldImages::class]);
        }
        // Deployment images older than a year: daily, with the server's "php artisan schedule:run" cron line.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('amc:purge-old-images')->dailyAt('01:30');
        });
    }

    /** $list with $add put in before the key $before (at the end when it is missing), keys kept. */
    private static function insertBefore(array $list, string $before, array $add): array
    {
        $out = [];
        foreach ($list as $k => $v) {
            if ($k === $before) {
                $out += $add;
            }
            $out[$k] = $v;
        }

        return $out + $add;
    }
}
