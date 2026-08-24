<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mitra;
use App\Models\Partner;
use App\Models\PartnerRegistration;
use App\Models\ReferralConversion;
use App\Models\ReferralLead;
use App\Models\ReferralLedger;
use App\Models\ReferralSetting;
use App\Models\ReferralWithdrawal;
use App\Models\User;
use App\Services\ReferralPartnerService;
use App\Support\PaginatedTable;
use App\Support\PrivateFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin's Referral centre — one screen (tabbed on the client) covering
 * partner applications, partner accounts, the leads they bring in, the
 * commissions those leads earn, and payouts. See {@see Mitra}
 * for the partner-facing side of the same data.
 */
class ReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $partnersPage = Partner::query()
            ->with('user:id,name,email')
            ->when($request->string('mitra_search')->toString(), function (Builder $query, string $term): void {
                $query->where(function (Builder $q) use ($term): void {
                    $q->where('code', 'like', "%{$term}%")
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'mitra_page')
            ->through(fn (Partner $partner): array => [
                'id' => $partner->id,
                'code' => $partner->code,
                'name' => $partner->user?->name,
                'email' => $partner->user?->email,
                'phone' => $partner->phone,
                'status' => $partner->status,
                'commission_mode' => $partner->commission_mode,
                'commission_value' => $partner->commission_value !== null ? (float) $partner->commission_value : null,
                'has_bank' => $partner->hasBankDetails(),
                'balance_points' => $partner->balancePoints(),
                'available_points' => $partner->availablePoints(),
                'leads_count' => $partner->leads()->count(),
                'conversions_count' => $partner->conversions()->count(),
                'created_at' => $partner->created_at?->toDateTimeString(),
            ]);

        // Pending applications are a small, self-limiting approval queue (they
        // leave this list the moment they're approved/rejected) — not worth
        // paginating.
        $applications = PartnerRegistration::query()
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (PartnerRegistration $r): array => [
                'id' => $r->id,
                'full_name' => $r->full_name,
                'email' => $r->email,
                'whatsapp' => $r->whatsapp,
                'partner_type' => $r->partner_type,
                'company_name' => $r->company_name,
                'network_size' => $r->network_size,
                'network_focus' => $r->network_focus,
                'network_description' => $r->network_description,
                'created_at' => $r->created_at?->toDateTimeString(),
            ]);

        $leadsPage = ReferralLead::query()
            ->with(['partner:id,code', 'tenant:id,name'])
            ->when($request->string('leads_search')->toString(), function (Builder $query, string $term): void {
                $query->where(function (Builder $q) use ($term): void {
                    $q->where('company_name', 'like', "%{$term}%")
                        ->orWhere('contact_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhereHas('partner', fn (Builder $p) => $p->where('code', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'leads_page')
            ->through(fn (ReferralLead $lead): array => [
                'id' => $lead->id,
                'company_name' => $lead->company_name,
                'contact_name' => $lead->contact_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'note' => $lead->note,
                'status' => $lead->status,
                'partner_code' => $lead->partner?->code,
                'tenant_name' => $lead->tenant?->name,
                'created_at' => $lead->created_at?->toDateTimeString(),
            ]);

        $conversionsPage = ReferralConversion::query()
            ->with(['partner.user:id,name', 'tenant:id,name'])
            ->when($request->string('konversi_search')->toString(), function (Builder $query, string $term): void {
                $query->where(function (Builder $q) use ($term): void {
                    $q->whereHas('partner.user', fn (Builder $u) => $u->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('tenant', fn (Builder $t) => $t->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'konversi_page')
            ->through(fn (ReferralConversion $c): array => [
                'id' => $c->id,
                'partner_name' => $c->partner?->user?->name,
                'tenant_name' => $c->tenant?->name,
                'base_amount' => (float) $c->base_amount,
                'points' => $c->points,
                'commission_amount' => (float) $c->commission_amount,
                'status' => $c->status,
                'hold_until' => $c->hold_until?->toDateString(),
                'created_at' => $c->created_at?->toDateTimeString(),
            ]);

        $withdrawalsPage = ReferralWithdrawal::query()
            ->with(['partner.user:id,name'])
            ->when($request->string('penarikan_search')->toString(), function (Builder $query, string $term): void {
                $query->where(function (Builder $q) use ($term): void {
                    $q->where('status', 'like', "%{$term}%")
                        ->orWhere('bank_name', 'like', "%{$term}%")
                        ->orWhereHas('partner.user', fn (Builder $u) => $u->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'penarikan_page')
            ->through(fn (ReferralWithdrawal $w): array => [
                'id' => $w->id,
                'partner_name' => $w->partner?->user?->name,
                'points' => $w->points,
                'amount' => (float) $w->amount,
                'bank_name' => $w->bank_name,
                'bank_account_number' => $w->bank_account_number,
                'bank_account_holder' => $w->bank_account_holder,
                'status' => $w->status,
                'note' => $w->note,
                'admin_note' => $w->admin_note,
                'proof_url' => PrivateFile::url($w->proof_path),
                'created_at' => $w->created_at?->toDateTimeString(),
            ]);

        $settings = ReferralSetting::current();

        return Inertia::render('avana/referral/index', [
            'stats' => [
                'pending_applications' => $applications->count(),
                'pending_withdrawals' => ReferralWithdrawal::query()->where('status', ReferralWithdrawal::STATUS_PENDING)->count(),
                'active_partners' => Partner::query()->where('status', 'active')->count(),
                // Platform-wide ledger balance: earned points still sitting in
                // partner wallets, not yet withdrawn.
                'points_outstanding' => (int) ReferralLedger::query()->sum('points'),
            ],
            'applications' => $applications,
            'partners' => PaginatedTable::shape($partnersPage, $request, 'mitra_search'),
            'leads' => PaginatedTable::shape($leadsPage, $request, 'leads_search'),
            'conversions' => PaginatedTable::shape($conversionsPage, $request, 'konversi_search'),
            'withdrawals' => PaginatedTable::shape($withdrawalsPage, $request, 'penarikan_search'),
            'settings' => [
                'mode' => $settings->mode,
                'points_per_conversion' => $settings->points_per_conversion,
                'percent_rate' => (float) $settings->percent_rate,
                'point_value' => (float) $settings->point_value,
                'hold_days' => $settings->hold_days,
                'min_withdrawal_points' => $settings->min_withdrawal_points,
                'withdrawal_enabled' => $settings->withdrawal_enabled,
                'leads_tab_enabled' => $settings->leads_tab_enabled,
                'komisi_tab_enabled' => $settings->komisi_tab_enabled,
                'rekening_tab_enabled' => $settings->rekening_tab_enabled,
            ],
        ]);
    }

    /**
     * Approve a partner application: provisions the login using the password
     * the applicant already chose at registration, so they can sign in right
     * away — no credentials for the super admin to relay.
     */
    public function approvePartner(Request $request, PartnerRegistration $registration, ReferralPartnerService $service): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        abort_unless($registration->status === 'pending', 422);

        if (User::query()->where('email', $registration->email)->exists()) {
            return back()->with('error', 'Email '.$registration->email.' sudah dipakai akun lain. Tidak bisa membuat login mitra otomatis.');
        }

        $result = $service->approve($registration, $request->user());

        return back()->with('success', 'Mitra '.$result['user']->name.' disetujui. Kode referral: '.$result['partner']->code.'. Mitra sudah bisa login dengan password yang mereka buat saat mendaftar.');
    }

    public function rejectPartner(Request $request, PartnerRegistration $registration): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        abort_unless($registration->status === 'pending', 422);

        $registration->update(['status' => 'rejected']);

        return back()->with('success', 'Pengajuan mitra ditolak');
    }

    public function updatePartner(Request $request, Partner $partner): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'commission_mode' => ['nullable', Rule::in(['flat', 'percent'])],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $partner->update($validated);

        return back()->with('success', 'Data mitra diperbarui');
    }

    public function updateLeadStatus(Request $request, ReferralLead $lead): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'contacted', 'lost'])],
        ]);

        $lead->update($validated);

        return back()->with('success', 'Status lead diperbarui');
    }

    /**
     * First step of a payout: marks the request approved so the super admin
     * can go make the actual transfer. No points move yet — that happens
     * once the proof of transfer is uploaded via {@see payWithdrawal()}.
     */
    public function approveWithdrawal(Request $request, ReferralWithdrawal $withdrawal): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        abort_unless($withdrawal->status === ReferralWithdrawal::STATUS_PENDING, 422);

        $withdrawal->update([
            'status' => ReferralWithdrawal::STATUS_APPROVED,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Penarikan disetujui. Lanjutkan dengan mengunggah bukti transfer setelah dana dikirim.');
    }

    /**
     * Second and final step: the transfer has actually been sent. Uploads the
     * proof, debits the partner's ledger, and closes the withdrawal out.
     */
    public function payWithdrawal(Request $request, ReferralWithdrawal $withdrawal): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        abort_unless($withdrawal->status === ReferralWithdrawal::STATUS_APPROVED, 422);

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($withdrawal, $validated, $request): void {
            $partner = Partner::query()->whereKey($withdrawal->partner_id)->lockForUpdate()->first();

            abort_if($partner === null, 404);

            $path = PrivateFile::store($validated['proof'], 'referral-withdrawals');
            $balanceAfter = $partner->balancePoints() - $withdrawal->points;

            $partner->ledger()->create([
                'type' => ReferralLedger::TYPE_WITHDRAW,
                'points' => -$withdrawal->points,
                'amount' => -$withdrawal->amount,
                'balance_after' => $balanceAfter,
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawal->id,
                'note' => 'Penarikan dibayar',
            ]);

            $withdrawal->update([
                'status' => ReferralWithdrawal::STATUS_PAID,
                'proof_path' => $path,
                'admin_note' => $validated['admin_note'] ?? $withdrawal->admin_note,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);
        });

        return back()->with('success', 'Penarikan ditandai lunas dan bukti transfer diunggah');
    }

    public function rejectWithdrawal(Request $request, ReferralWithdrawal $withdrawal): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        abort_unless(in_array($withdrawal->status, [ReferralWithdrawal::STATUS_PENDING, ReferralWithdrawal::STATUS_APPROVED], true), 422);

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ]);

        $withdrawal->update([
            'status' => ReferralWithdrawal::STATUS_REJECTED,
            'admin_note' => $validated['admin_note'],
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Penarikan ditolak, poin dikembalikan ke saldo tersedia');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'mode' => ['required', Rule::in([ReferralSetting::MODE_FLAT, ReferralSetting::MODE_PERCENT])],
            'points_per_conversion' => ['required', 'integer', 'min:0'],
            'percent_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'point_value' => ['required', 'numeric', 'min:0'],
            'hold_days' => ['required', 'integer', 'min:0', 'max:365'],
            'min_withdrawal_points' => ['required', 'integer', 'min:0'],
            'withdrawal_enabled' => ['required', 'boolean'],
            'leads_tab_enabled' => ['required', 'boolean'],
            'komisi_tab_enabled' => ['required', 'boolean'],
            'rekening_tab_enabled' => ['required', 'boolean'],
        ]);

        ReferralSetting::current()->update($validated);

        return back()->with('success', 'Pengaturan komisi referral disimpan');
    }

    /**
     * Abort with 403 unless the acting user is a platform super admin.
     */
    private function ensureSuperAdmin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->roles()->where('code', 'super_admin')->exists(), 403);
    }
}
