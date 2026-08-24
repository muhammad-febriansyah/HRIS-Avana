<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\ReferralConversionService;
use App\Support\Notifier;

/**
 * Notifies platform super admins the moment a tenant invoice is marked paid,
 * and — the same status change — credits the referral commission for a
 * partner-attributed tenant's first paid invoice. The super admin who
 * performed the action is excluded from their own alert.
 */
class InvoiceObserver
{
    public function __construct(private readonly ReferralConversionService $referrals) {}

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status')) {
            return;
        }

        if ($invoice->status === 'paid') {
            Notifier::invoicePaid($invoice, request()->user()?->id);
            $this->referrals->creditForInvoice($invoice);

            return;
        }

        if ($invoice->status === 'cancelled') {
            $this->referrals->voidForInvoice($invoice);
        }
    }
}
