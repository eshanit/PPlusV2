<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync CouchDB → MySQL every 5 minutes.
// Adjust the interval once you know the acceptable reporting lag.
Schedule::command('sync:couchdb')->everyFiveMinutes()->withoutOverlapping();
