<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Partner;
use App\Models\ReferralConversion;
use App\Models\ReferralLedger;
use App\Models\ReferralSetting;
use App\Models\Tenant;
use App\Observers\InvoiceObserver;
use Illuminate\Support\Facades\DB;

/**
 * Turns a tenant's first paid invoice into a referral commission, and undoes
 * that if the invoice is later cancelled. Called from {@see InvoiceObserver}
 * so every path that can mark an invoice paid — the super admin's manual
 * billing screen and the self-service Pakasir renewal — feeds the same place.
 */
final class ReferralConversionService
{
    /**
     * Credit the partner attributed to this invoice's tenant, if any.
     *
     * Idempotent per tenant: commission is earned once, on the FIRST paid
     * invoice only — a second call for the same tenant (a renewal, or a
     * retried webhook) is a no-op because {@see ReferralConversion} enforces
     * a unique `tenant_id`.
     */
    public function creditForInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $tenant = Tenant::query()->whereKey($invoice->tenant_id)->lockForUpdate()->first();

            if ($tenant === null || $tenant->partner_id === null) {
                return;
            }

            if (ReferralConversion::query()->where('tenant_id', $tenant->id)->exists()) {
                return;
            }

            $partner = Partner::query()->whereKey($tenant->partner_id)->first();

            if ($partner === null || $partner->status !== 'active') {
                return;
            }

            $settings = ReferralSetting::current();
            $computed = $settings->resolveCommission((float) $invoice->total, $partner);

            if ($computed['points'] <= 0) {
                return;
            }

            ReferralConversion::create([
                'partner_id' => $partner->id,
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'base_amount' => $invoice->total,
                'points' => $computed['points'],
                'commission_amount' => $computed['amount'],
                'mode' => $partner->commission_mode ?? $settings->mode,
                'status' => ReferralConversion::STATUS_PENDING,
                'hold_until' => now()->addDays($settings->hold_days)->toDateString(),
            ]);
        });
    }

    /**
     * Void the conversion tied to a cancelled invoice. A conversion still in
     * its hold period had never touched the ledger, so voiding it is just a
     * status flip. One already approved (past its hold, already earning) gets
     * a reversing `void` ledger entry so the partner's balance moves back
     * down by the same points it moved up by.
     */
    public function voidForInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $conversion = ReferralConversion::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', '!=', ReferralConversion::STATUS_VOID)
                ->lockForUpdate()
                ->first();

            if ($conversion === null) {
                return;
            }

            if ($conversion->status === ReferralConversion::STATUS_APPROVED) {
                $partner = Partner::query()->whereKey($conversion->partner_id)->lockForUpdate()->first();

                if ($partner !== null) {
                    $balanceAfter = $partner->balancePoints() - $conversion->points;

                    ReferralLedger::create([
                        'partner_id' => $partner->id,
                        'type' => ReferralLedger::TYPE_VOID,
                        'points' => -$conversion->points,
                        'amount' => -$conversion->commission_amount,
                        'balance_after' => $balanceAfter,
                        'reference_type' => 'conversion',
                        'reference_id' => $conversion->id,
                        'note' => 'Invoice dibatalkan',
                    ]);
                }
            }

            $conversion->update(['status' => ReferralConversion::STATUS_VOID, 'voided_at' => now()]);
        });
    }
}
