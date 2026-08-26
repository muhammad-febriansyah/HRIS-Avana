<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Models\ReferralConversion;
use App\Models\ReferralLedger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('referral:release-holds')]
#[Description('Credit referral commissions whose hold period has passed.')]
class ReleaseReferralHolds extends Command
{
    /**
     * A conversion sits `pending` for its tenant's-first-invoice hold window
     * (a chargeback/refund guard) before it becomes spendable. Runs daily so
     * a conversion never sits stuck: the moment its hold date passes, its
     * commission lands in the partner's ledger — the only place their
     * balance is ever read from.
     */
    public function handle(): int
    {
        $due = ReferralConversion::query()
            ->where('status', ReferralConversion::STATUS_PENDING)
            ->whereDate('hold_until', '<=', Carbon::today())
            ->pluck('id');

        $credited = 0;

        foreach ($due as $conversionId) {
            DB::transaction(function () use ($conversionId, &$credited): void {
                $conversion = ReferralConversion::query()->whereKey($conversionId)->lockForUpdate()->first();

                if ($conversion === null || $conversion->status !== ReferralConversion::STATUS_PENDING) {
                    return;
                }

                $partner = Partner::query()->whereKey($conversion->partner_id)->lockForUpdate()->first();

                if ($partner === null) {
                    return;
                }

                $balanceAfter = $partner->balanceAmount() + $conversion->commission_amount;

                ReferralLedger::create([
                    'partner_id' => $partner->id,
                    'type' => ReferralLedger::TYPE_EARN,
                    'amount' => $conversion->commission_amount,
                    'balance_after' => $balanceAfter,
                    'reference_type' => 'conversion',
                    'reference_id' => $conversion->id,
                    'note' => 'Komisi referral disetujui',
                ]);

                $conversion->update(['status' => ReferralConversion::STATUS_APPROVED, 'approved_at' => now()]);

                $credited++;
            });
        }

        $this->info("Released {$credited} referral hold(s).");

        return self::SUCCESS;
    }
}
