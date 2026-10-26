<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the local database in step with OJS on a fixed cadence. withoutOverlapping
// stops a slow run from being started again before the previous one finishes.
Schedule::command('ojs:sync')->hourly()->withoutOverlapping();
