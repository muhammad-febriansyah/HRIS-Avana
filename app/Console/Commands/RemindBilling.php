<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Notifier;
use App\Support\SubscriptionStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:remind-billing {--expiry-days=7}')]
#[Description('Flag overdue invoices, alert super admins, and remind tenant admins of an expiring subscription.')]
class RemindBilling extends Command
{
    /**
     * Flip past-due unpaid invoices to `overdue` and alert super admins, then
     * warn them about subscriptions ending within the expiry window. Both
     * notifications are deduped, so this is safe to run daily.
     */
    public function handle(): int
    {
        $today = Carbon::today();

        $overdue = Invoice::query()
            ->where('status', 'unpaid')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->with('tenant:id,name')
            ->get();

        foreach ($overdue as $invoice) {
            $invoice->update(['status' => 'overdue']);
            Notifier::invoiceOverdue($invoice);
        }

        $days = max(0, (int) $this->option('expiry-days'));

        $expiring = Subscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays($days)->toDateString()])
            ->with(['tenant:id,name', 'package:id,name'])
            ->get();

        foreach ($expiring as $subscription) {
            Notifier::subscriptionExpiring($subscription);
        }

        $tenantAlerts = $this->remindTenantAdmins();

        $this->info("Flagged {$overdue->count()} overdue invoice(s); checked {$expiring->count()} expiring subscription(s); sent {$tenantAlerts} tenant admin reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Warn each live tenant's own admins on the countdown milestones (and once
     * after the end date passes). Nothing is blocked — the reminder is the whole
     * point, and a lapsed client keeps working until a super admin says otherwise.
     */
    private function remindTenantAdmins(): int
    {
        $sent = 0;

        // The status memo is scoped to a request; a scheduled run is long-lived
        // and must read today's dates, not whatever a previous run cached.
        SubscriptionStatus::forget();

        Tenant::query()
            ->whereIn('status', ['active', 'trial'])
            ->with('package:id,name')
            ->chunkById(100, function ($tenants) use (&$sent): void {
                foreach ($tenants as $tenant) {
                    $notice = SubscriptionStatus::forTenant($tenant);

                    if ($notice === null) {
                        continue;
                    }

                    $milestone = SubscriptionStatus::milestone($notice['end_date'], $notice['days_left']);

                    if ($milestone === null) {
                        continue;
                    }

                    $sent += Notifier::tenantSubscriptionExpiring(
                        $tenant,
                        $notice['end_date_label'],
                        $notice['days_left'],
                        $milestone,
                        $notice['package'],
                    );
                }
            });

        return $sent;
    }
}
