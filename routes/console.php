<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the standard cron entry: * * * * * php /path/to/artisan schedule:run
Schedule::command('catalogs:flag-stale-drafts')->dailyAt('23:00');
Schedule::command('invoices:mark-overdue')->dailyAt('00:10');
Schedule::command('quotations:expire')->dailyAt('00:20');
Schedule::command('quotations:follow-up')->dailyAt('10:00');
Schedule::command('reminders:send')->dailyAt('09:00');
Schedule::command('recurring:run')->dailyAt('06:00');
