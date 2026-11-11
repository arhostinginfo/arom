<?php

use App\Models\FinancialYear;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Creates this and next financial year's rows ahead of 1 April and keeps is_current on this year only
// (architecture review A6). Scheduled daily; production runs "php artisan schedule:run" every minute.
Artisan::command('nursery:financial-years', function () {
    $current = FinancialYear::forDate(now());
    $next = FinancialYear::forDate(now()->addYear());
    FinancialYear::whereKeyNot($current->id)->where('is_current', true)->update(['is_current' => false]);
    $current->newQuery()->whereKey($current->id)->update(['is_current' => true]);
    $this->info('Financial years ready: '.$current->label().' (current) and '.$next->label().'.');
})->purpose('Create the current and next financial year rows');

Schedule::command('nursery:financial-years')->dailyAt('00:10');
