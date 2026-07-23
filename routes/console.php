<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deactivate employees the day after their last working day.
Schedule::command('avana:flag-resigned-employees')->dailyAt('00:15');

// Warn HR about employee contracts nearing their end date.
Schedule::command('avana:remind-expiring-contracts')->dailyAt('06:00');

// Scan resign-risk scores and alert HR to high-risk employees (per-tenant config
// decides on/off, threshold, recipient role, and daily vs weekly cadence).
Schedule::command('avana:scan-attrition-alerts')->dailyAt('07:30');
