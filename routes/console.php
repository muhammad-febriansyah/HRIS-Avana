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

// Alert super admins to overdue invoices and expiring subscriptions.
Schedule::command('avana:remind-billing')->dailyAt('07:00');

// Scan resign-risk scores and alert HR to high-risk employees (per-tenant config
// decides on/off, threshold, recipient role, and daily vs weekly cadence).
Schedule::command('avana:scan-attrition-alerts')->dailyAt('07:30');

// Push a clock-in reminder to employees who haven't clocked in yet (weekdays).
Schedule::command('avana:remind-attendance')->weekdays()->at('08:30');
