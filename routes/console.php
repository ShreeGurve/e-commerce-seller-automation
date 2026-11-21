<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 8: evaluate internal automation rules hourly.
Schedule::command('automation:run')->hourly()->withoutOverlapping();

Schedule::command('suppliers:documents-expiry-notify --days=30')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('automation:tasks-generate-recurring')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('automation:tasks-remind --hours=24')->hourly()->withoutOverlapping();
Schedule::command('marketplace-writes:escalate')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('marketplace-writes:monitor')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('marketplace-writes:alerts-scan')->everyTenMinutes()->withoutOverlapping();
