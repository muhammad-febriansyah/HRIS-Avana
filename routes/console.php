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

// Push a clock-in reminder to employees the roster expects at work today.
// The command decides who is due, so a weekend shift is covered. Hourly
// rather than daily because the hour it fires on is the tenant's own: a
// Jayapura office is reminded at 08:30 WIT, not at 08:30 WIB, which reaches
// them at half past ten.
Schedule::command('avana:remind-attendance')->hourlyAt(30);

// Close meeting recordings a phone never came back from. Nothing else moves
// them out of "recording", so without this they sit in the list claiming to be
// live for good.
Schedule::command('avana:close-stale-meetings')->hourly();
