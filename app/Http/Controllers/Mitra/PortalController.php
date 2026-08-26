<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Avana\ReferralController;
use App\Http\Controllers\Avana\TenantController;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Partner;
use App\Models\ReferralConversion;
use App\Models\ReferralSetting;
use App\Models\ReferralWithdrawal;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Support\FeatureGroups;
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

        $pendingRegistrations = TenantRegistration::query()
            ->whereBelongsTo($partner)
            ->where('status', TenantRegistration::STATUS_PENDING)
            ->latest()
            ->limit(5)
            ->get(['id', 'company_name', 'industry', 'employee_count_range', 'created_at'])
            ->map(fn (TenantRegistration $registration): array => [
                'id' => $registration->id,
                'company_name' => $registration->company_name,
                'industry' => $registration->industry,
                'employee_count_range' => $registration->employee_count_range,
                'created_at' => $registration->created_at?->toDateTimeString(),
            ]);

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
                'klien_tab_enabled' => $settings->klien_tab_enabled,
            ],
            'referralUrl' => url('/daftar-perusahaan?ref='.$partner->code),
            'clients' => $this->clientsFor($partner),
            'clientFeatures' => $this->featureCatalog(),
            'pendingRegistrations' => $pendingRegistrations,
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

    /**
     * Flip one feature module on/off for a tenant this partner referred.
     * Gated on the same "Kontrol Fitur Klien" switch the tab itself is,
     * checked again here in case an admin turns it off mid-session — the
     * tab hiding on the client is cosmetic, this is the real lock.
     */
    public function toggleClientFeature(Request $request, Tenant $tenant): RedirectResponse
    {
        $partner = $this->partner($request);

        abort_unless(ReferralSetting::current()->klien_tab_enabled, 403);
        // A partner may only ever touch a tenant they themselves referred —
        // never inferred from `tenant_id` on the user, since a partner login
        // has none.
        abort_unless($tenant->partner_id === $partner->id, 404);

        $validated = $request->validate([
            'feature_id' => ['required', 'integer', 'exists:features,id'],
        ]);

        $feature = $tenant->features()->firstOrNew(['feature_id' => $validated['feature_id']]);
        $feature->is_enabled = ! $feature->is_enabled;
        $feature->save();

        return back()->with('success', 'Fitur klien diperbarui');
    }

    /**
     * Tenants this partner's referral converted, with the same feature
     * catalog shape the super admin's Kelola Fitur modal uses — see
     * {@see TenantController::featureCatalog()}.
     *
     * @return array<int, array{id: int, name: string, company_name: ?string, status: string, package_name: ?string, feature_codes: array<int, string>}>
     */
    private function clientsFor(Partner $partner): array
    {
        return Tenant::query()
            ->where('partner_id', $partner->id)
            ->with([
                'package:id,name',
                'features' => fn ($query) => $query->where('is_enabled', true)->with('feature:id,code'),
            ])
            ->orderByDesc('id')
            ->get(['id', 'name', 'company_name', 'status', 'package_id', 'partner_id'])
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'company_name' => $tenant->company_name,
                'status' => $tenant->status,
                'package_name' => $tenant->package?->name,
                'feature_codes' => $tenant->features
                    ->pluck('feature.code')
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Same catalog shape as the super admin's Kelola Fitur modal, minus the
     * fixed "always on" core rows (Dashboard, Pengguna, ...) — noise for a
     * partner who only cares about the toggleable modules.
     *
     * @return array<int, array{key: string, id: int, code: string, name: string, group: string}>
     */
    private function featureCatalog(): array
    {
        return Feature::query()
            ->get(['id', 'code', 'name', 'module_group'])
            ->sortBy(fn (Feature $feature): string => sprintf('%02d-%s', FeatureGroups::sortIndex($feature->module_group), $feature->name))
            ->values()
            ->map(fn (Feature $feature): array => [
                'key' => $feature->code,
                'id' => $feature->id,
                'code' => $feature->code,
                'name' => $feature->name,
                'group' => FeatureGroups::label($feature->module_group),
            ])
            ->all();
    }

    private function partner(Request $request): Partner
    {
        return $request->user()->partner()->firstOrFail();
    }
}
