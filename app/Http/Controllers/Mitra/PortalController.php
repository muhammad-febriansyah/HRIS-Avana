<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Avana\ReferralController;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\ReferralConversion;
use App\Models\ReferralSetting;
use App\Models\ReferralWithdrawal;
use App\Support\PaginatedTable;
use App\Support\PrivateFile;
use Illuminate\Database\Eloquent\Builder;
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

        $leadsPage = $partner->leads()
            ->with('tenant:id,name')
            ->when($request->string('leads_search')->toString(), function (Builder $query, string $term): void {
                $query->where(function (Builder $q) use ($term): void {
                    $q->where('company_name', 'like', "%{$term}%")
                        ->orWhere('contact_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'leads_page')
            ->through(fn ($lead): array => [
                'id' => $lead->id,
                'company_name' => $lead->company_name,
                'contact_name' => $lead->contact_name,
                'status' => $lead->status,
                'tenant_name' => $lead->tenant?->name,
                'created_at' => $lead->created_at?->toDateTimeString(),
            ]);

        $conversionsPage = $partner->conversions()
            ->with('tenant:id,name')
            ->when($request->string('komisi_search')->toString(), function (Builder $query, string $term): void {
                $query->whereHas('tenant', fn (Builder $q) => $q->where('name', 'like', "%{$term}%"));
            })
            ->latest()
            ->paginate(10, ['*'], 'komisi_page')
            ->through(fn (ReferralConversion $c): array => [
                'id' => $c->id,
                'tenant_name' => $c->tenant?->name,
                'commission_amount' => (float) $c->commission_amount,
                'status' => $c->status,
                'hold_until' => $c->hold_until?->toDateString(),
                'created_at' => $c->created_at?->toDateTimeString(),
            ]);

        $withdrawalsPage = $partner->withdrawals()
            ->when($request->string('penarikan_search')->toString(), function (Builder $query, string $term): void {
                $query->where(function (Builder $q) use ($term): void {
                    $q->where('status', 'like', "%{$term}%")
                        ->orWhere('admin_note', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'penarikan_page')
            ->through(fn (ReferralWithdrawal $w): array => [
                'id' => $w->id,
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
                'balance_amount' => $partner->balanceAmount(),
                'available_amount' => $partner->availableAmount(),
                'pending_amount' => (float) $partner->conversions()->where('status', ReferralConversion::STATUS_PENDING)->sum('commission_amount'),
            ],
            'settings' => [
                'flat_amount' => (float) $settings->flat_amount,
                'min_withdrawal_amount' => (float) $settings->min_withdrawal_amount,
                'hold_days' => $settings->hold_days,
                'withdrawal_enabled' => $settings->withdrawal_enabled,
                'leads_tab_enabled' => $settings->leads_tab_enabled,
                'komisi_tab_enabled' => $settings->komisi_tab_enabled,
                'rekening_tab_enabled' => $settings->rekening_tab_enabled,
            ],
            'referralUrl' => url('/daftar-perusahaan?ref='.$partner->code),
            // Dashboard's "Komisi Terbaru" glance — independent of the Komisi
            // tab's own search/pagination state below.
            'recentConversions' => $partner->conversions()
                ->with('tenant:id,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ReferralConversion $c): array => [
                    'id' => $c->id,
                    'tenant_name' => $c->tenant?->name,
                    'commission_amount' => (float) $c->commission_amount,
                    'status' => $c->status,
                    'hold_until' => $c->hold_until?->toDateString(),
                    'created_at' => $c->created_at?->toDateTimeString(),
                ]),
            'leads' => PaginatedTable::shape($leadsPage, $request, 'leads_search'),
            'conversions' => PaginatedTable::shape($conversionsPage, $request, 'komisi_search'),
            'withdrawals' => PaginatedTable::shape($withdrawalsPage, $request, 'penarikan_search'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $partner = $this->partner($request);

        if (! ReferralSetting::current()->rekening_tab_enabled) {
            return back()->with('error', 'Pengaturan rekening sedang dinonaktifkan oleh admin.');
        }

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
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $settings = ReferralSetting::current();

        if (! $settings->withdrawal_enabled) {
            return back()->with('error', 'Penarikan saldo sedang dinonaktifkan oleh admin.');
        }

        if (! $partner->hasBankDetails()) {
            return back()->with('error', 'Lengkapi data rekening terlebih dahulu sebelum menarik komisi.');
        }

        if ($validated['amount'] < $settings->min_withdrawal_amount) {
            return back()->with('error', 'Minimal penarikan Rp'.number_format((float) $settings->min_withdrawal_amount, 0, ',', '.').'.');
        }

        $insufficientFunds = false;

        DB::transaction(function () use ($partner, $validated, &$insufficientFunds): void {
            // Locked so two rapid requests cannot both pass the availability
            // check against the same balance.
            $locked = Partner::query()->whereKey($partner->id)->lockForUpdate()->first();

            if ($validated['amount'] > $locked->availableAmount()) {
                $insufficientFunds = true;

                return;
            }

            ReferralWithdrawal::create([
                'partner_id' => $locked->id,
                'amount' => round((float) $validated['amount'], 2),
                'bank_name' => $locked->bank_name,
                'bank_account_number' => $locked->bank_account_number,
                'bank_account_holder' => $locked->bank_account_holder,
                'status' => ReferralWithdrawal::STATUS_PENDING,
            ]);
        });

        if ($insufficientFunds) {
            return back()->with('error', 'Saldo tersedia tidak mencukupi.');
        }

        return back()->with('success', 'Permintaan penarikan diajukan, menunggu persetujuan super admin');
    }

    private function partner(Request $request): Partner
    {
        return $request->user()->partner()->firstOrFail();
    }
}
