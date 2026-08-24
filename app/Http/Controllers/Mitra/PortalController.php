<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Avana\ReferralController;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\ReferralConversion;
use App\Models\ReferralSetting;
use App\Models\ReferralWithdrawal;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The referral partner's own portal: one page, tabbed on the client — their
 * link, the leads and commissions it has earned, and their payout history.
 * See {@see ReferralController} for the super
 * admin's side of the same data.
 */
class PortalController extends Controller
{
    public function index(Request $request): Response
    {
        $partner = $this->partner($request);
        $settings = ReferralSetting::current();

        $leads = $partner->leads()
            ->with('tenant:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($lead): array => [
                'id' => $lead->id,
                'company_name' => $lead->company_name,
                'contact_name' => $lead->contact_name,
                'status' => $lead->status,
                'tenant_name' => $lead->tenant?->name,
                'created_at' => $lead->created_at?->toDateTimeString(),
            ]);

        $conversions = $partner->conversions()
            ->with('tenant:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ReferralConversion $c): array => [
                'id' => $c->id,
                'tenant_name' => $c->tenant?->name,
                'points' => $c->points,
                'commission_amount' => (float) $c->commission_amount,
                'status' => $c->status,
                'hold_until' => $c->hold_until?->toDateString(),
                'created_at' => $c->created_at?->toDateTimeString(),
            ]);

        $withdrawals = $partner->withdrawals()
            ->latest()
            ->get()
            ->map(fn (ReferralWithdrawal $w): array => [
                'id' => $w->id,
                'points' => $w->points,
                'amount' => (float) $w->amount,
                'status' => $w->status,
                'admin_note' => $w->admin_note,
                'proof_url' => PrivateFile::url($w->proof_path),
                'created_at' => $w->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('mitra/index', [
            'partner' => [
                'code' => $partner->code,
                'status' => $partner->status,
                'bank_name' => $partner->bank_name,
                'bank_account_number' => $partner->bank_account_number,
                'bank_account_holder' => $partner->bank_account_holder,
                'npwp' => $partner->npwp,
                'has_bank' => $partner->hasBankDetails(),
            ],
            'stats' => [
                'clicks' => $partner->clicks()->count(),
                'leads' => $partner->leads()->count(),
                'conversions' => $partner->conversions()->count(),
                'balance_points' => $partner->balancePoints(),
                'available_points' => $partner->availablePoints(),
                'pending_points' => (int) $partner->conversions()->where('status', ReferralConversion::STATUS_PENDING)->sum('points'),
            ],
            'settings' => [
                'point_value' => (float) $settings->point_value,
                'min_withdrawal_points' => $settings->min_withdrawal_points,
                'hold_days' => $settings->hold_days,
            ],
            'referralUrl' => url('/daftar-perusahaan?ref='.$partner->code),
            'leads' => $leads,
            'conversions' => $conversions,
            'withdrawals' => $withdrawals,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $partner = $this->partner($request);

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:100'],
            'bank_account_holder' => ['required', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:50'],
        ]);

        $partner->update($validated);

        return back()->with('success', 'Data rekening disimpan');
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        $partner = $this->partner($request);

        $validated = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
        ]);

        if (! $partner->hasBankDetails()) {
            return back()->with('error', 'Lengkapi data rekening terlebih dahulu sebelum menarik komisi.');
        }

        $settings = ReferralSetting::current();

        if ($validated['points'] < $settings->min_withdrawal_points) {
            return back()->with('error', "Minimal penarikan {$settings->min_withdrawal_points} poin.");
        }

        $insufficientFunds = false;

        DB::transaction(function () use ($partner, $validated, $settings, &$insufficientFunds): void {
            // Locked so two rapid requests cannot both pass the availability
            // check against the same points.
            $locked = Partner::query()->whereKey($partner->id)->lockForUpdate()->first();

            if ($validated['points'] > $locked->availablePoints()) {
                $insufficientFunds = true;

                return;
            }

            ReferralWithdrawal::create([
                'partner_id' => $locked->id,
                'points' => $validated['points'],
                'amount' => round($validated['points'] * (float) $settings->point_value, 2),
                'bank_name' => $locked->bank_name,
                'bank_account_number' => $locked->bank_account_number,
                'bank_account_holder' => $locked->bank_account_holder,
                'status' => ReferralWithdrawal::STATUS_PENDING,
            ]);
        });

        if ($insufficientFunds) {
            return back()->with('error', 'Poin tersedia tidak mencukupi.');
        }

        return back()->with('success', 'Permintaan penarikan diajukan, menunggu persetujuan super admin');
    }

    private function partner(Request $request): Partner
    {
        return $request->user()->partner()->firstOrFail();
    }
}
