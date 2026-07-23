<?php

namespace App\Observers;

use App\Models\Subscription;
use App\Support\Notifier;

/**
 * Notifies platform super admins when a tenant subscription slips to past_due.
 */
class SubscriptionObserver
{
    public function updated(Subscription $subscription): void
    {
        if (! $subscription->wasChanged('status')) {
            return;
        }

        if ($subscription->status !== 'past_due') {
            return;
        }

        Notifier::subscriptionPastDue($subscription);
    }
}
