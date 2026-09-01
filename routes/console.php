<?php

use App\Models\FaceScanLog;
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

// Open the year's leave balances. Daily rather than once on 1 January so a
// tenant onboarded mid-year, or one whose leave types were set up late, still
// gets its rows without anyone remembering to run the command. Existing rows —
// including quotas HR adjusted by hand — are left untouched.
Schedule::command('avana:generate-leave-balance')->dailyAt('00:45');

// Drop face-scan diagnostics past their 60-day retention window.
Schedule::command('model:prune', ['--model' => [FaceScanLog::class]])->dailyAt('01:00');

// Close meeting recordings a phone never came back from. Nothing else moves
// them out of "recording", so without this they sit in the list claiming to be
// live for good.
Schedule::command('avana:close-stale-meetings')->hourly();

// Credit referral commissions past their hold window, so a partner's balance
// updates itself without a super admin touching each conversion by hand.
Schedule::command('referral:release-holds')->dailyAt('02:00');

// Alert super admins to a tenant missing something provisioning should have
// left behind. Reports only — repairing is a deliberate `--fix` run — because
// the gaps are invisible from inside the tenant and otherwise surface as a
// client complaint weeks later.
Schedule::command('avana:periksa-tenant')->dailyAt('05:00');

// Drop the security and activity trails past their retention window. Personal
// data is not meant to be kept for ever, and an activity log is personal data.
Schedule::command('avana:prune-security-data')->dailyAt('01:30');

// Nightly database dump, pruned to its retention window. Point BACKUP_DISK at
// off-site storage in production — a copy on the same server survives a
// mistake, not a fire.
Schedule::command('avana:backup-database')->dailyAt('02:30');

// Read the activity trail and raise what looks wrong: password guessing,
// off-hours sign-ins, one account in many places, bursts of exports.
Schedule::command('avana:scan-security-anomalies')->dailyAt('06:30');
