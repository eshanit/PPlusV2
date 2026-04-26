<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync CouchDB → MySQL every 5 minutes.
Schedule::command('sync:couchdb')->everyFiveMinutes()->withoutOverlapping();

// Daily exports at 6am.
Schedule::command('export:reports --all')->dailyAt('06:00')->withoutOverlapping();

// Weekly Journey Status export every Monday at 6am.
Schedule::command('export:reports journey --filename=weekly')->weeklyOn(1, '06:00')->withoutOverlapping();
