<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Support\Notifier;

/**
 * Notifies platform super admins the moment a tenant invoice is marked paid.
 * The super admin who performed the action is excluded from their own alert.
 */
class InvoiceObserver
{
    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status')) {
            return;
        }

        if ($invoice->status !== 'paid') {
            return;
        }

        Notifier::invoicePaid($invoice, request()->user()?->id);
    }
}
