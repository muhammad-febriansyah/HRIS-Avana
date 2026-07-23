<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:remind-billing {--expiry-days=7}')]
#[Description('Flag overdue invoices and alert super admins to overdue invoices and expiring subscriptions.')]
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

        $this->info("Flagged {$overdue->count()} overdue invoice(s); checked {$expiring->count()} expiring subscription(s).");

        return self::SUCCESS;
    }
}
