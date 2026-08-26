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
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Services\ReferralPartnerService;
use App\Services\TenantProvisioner;
use App\Support\PaginatedTable;
use App\Support\PrivateFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    /**
     * How long a self-serve trial lasts once approved — mirrors
     * {@see TenantController}'s default for an admin-created tenant.
     */
    private const TRIAL_DAYS = 14;

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
                'commission_value' => $partner->commission_value !== null ? (float) $partner->commission_value : null,
                'has_bank' => $partner->hasBankDetails(),
                'balance_amount' => $partner->balanceAmount(),
                'available_amount' => $partner->availableAmount(),
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

        // Same self-limiting shape as the partner applications queue above —
        // a self-serve "Daftar Perusahaan" submission waiting on approval
        // before it becomes a real Tenant. See approveTenant()/rejectTenant().
        $tenantApplications = TenantRegistration::query()
            ->where('status', TenantRegistration::STATUS_PENDING)
            ->with('partner:id,code,user_id', 'partner.user:id,name')
            ->latest()
            ->get()
            ->map(fn (TenantRegistration $r): array => [
                'id' => $r->id,
                'company_name' => $r->company_name,
                'phone' => $r->phone,
                'admin_name' => $r->admin_name,
                'admin_email' => $r->admin_email,
                'partner_code' => $r->partner?->code,
                'partner_name' => $r->partner?->user?->name,
                'source' => $r->source,
                'industry' => $r->industry,
                'employee_count_range' => $r->employee_count_range,
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
                'pending_tenant_applications' => $tenantApplications->count(),
                'pending_withdrawals' => ReferralWithdrawal::query()->where('status', ReferralWithdrawal::STATUS_PENDING)->count(),
                'active_partners' => Partner::query()->where('status', 'active')->count(),
                // Platform-wide ledger balance: earned commission still
                // sitting in partner wallets, not yet withdrawn.
                'amount_outstanding' => (float) ReferralLedger::query()->sum('amount'),
            ],
            'applications' => $applications,
            'tenantApplications' => $tenantApplications,
            'partners' => PaginatedTable::shape($partnersPage, $request, 'mitra_search'),
            'leads' => PaginatedTable::shape($leadsPage, $request, 'leads_search'),
            'conversions' => PaginatedTable::shape($conversionsPage, $request, 'konversi_search'),
            'withdrawals' => PaginatedTable::shape($withdrawalsPage, $request, 'penarikan_search'),
            'settings' => [
                'flat_amount' => (float) $settings->flat_amount,
                'hold_days' => $settings->hold_days,
                'min_withdrawal_amount' => (float) $settings->min_withdrawal_amount,
                'withdrawal_enabled' => $settings->withdrawal_enabled,
                'leads_tab_enabled' => $settings->leads_tab_enabled,
                'komisi_tab_enabled' => $settings->komisi_tab_enabled,
                'rekening_tab_enabled' => $settings->rekening_tab_enabled,
                'klien_tab_enabled' => $settings->klien_tab_enabled,
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

    /**
     * Approve a self-serve "Daftar Perusahaan" request: this is the only
     * place a Tenant from that flow ever gets created. Provisions the tenant
     * (trial, {@see TRIAL_DAYS} days from today — not from when the request
     * was submitted, so review time is never deducted from it), its admin
     * login using the password the applicant already chose, features/roles
     * via {@see TenantProvisioner}, and a converted {@see ReferralLead} so
     * this still shows up in the existing Leads/Konversi reporting.
     */
    public function approveTenant(Request $request, TenantRegistration $registration, TenantProvisioner $provisioner): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        if (User::query()->where('email', $registration->admin_email)->exists()) {
            return back()->with('error', 'Email '.$registration->admin_email.' sudah dipakai akun lain. Tidak bisa membuat login klien otomatis.');
        }

        $tenant = DB::transaction(function () use ($registration, $provisioner): Tenant {
            $registration = TenantRegistration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($registration->status === TenantRegistration::STATUS_PENDING, 422);

            $slug = $this->uniqueTenantSlug($registration->company_name);
            $start = now();

            $tenant = Tenant::create([
                'name' => $registration->company_name,
                'company_name' => $registration->company_name,
                'slug' => $slug,
                'package_id' => null,
                'partner_id' => $registration->partner_id,
                'status' => 'trial',
                // Only a self-serve signup skips picking a package/filling
                // a profile at signup time — EnsureOnboardingComplete gates
                // it on that until both are done. An admin-created tenant
                // (default false) is never affected, "Tanpa Paket" included.
                'requires_onboarding' => true,
                'max_users' => 0,
                'max_employees' => 0,
                'max_branches' => 0,
                'billing_status' => 'active',
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(self::TRIAL_DAYS)->toDateString(),
            ]);

            ReferralLead::create([
                'company_name' => $registration->company_name,
                'contact_name' => $registration->admin_name,
                'email' => $registration->admin_email,
                'phone' => $registration->phone,
                'partner_id' => $registration->partner_id,
                'status' => ReferralLead::STATUS_CONVERTED,
                'tenant_id' => $tenant->id,
                'converted_at' => now(),
            ]);

            $provisioner->provision($tenant);

            // Already hashed at submission time — createAdmin() carries it
            // straight into the User row without re-hashing (Laravel's
            // `hashed` cast skips a value that is already a hash), so the
            // applicant logs in with the exact password they chose.
            $provisioner->createAdmin(
                $tenant,
                $registration->admin_name,
                $registration->admin_email,
                $registration->admin_password,
            );

            $registration->update([
                'status' => TenantRegistration::STATUS_APPROVED,
                'tenant_id' => $tenant->id,
                'processed_by' => request()->user()->id,
                'processed_at' => now(),
            ]);

            return $tenant;
        });

        return back()->with('success', 'Klien '.$tenant->name.' disetujui. Mereka sudah bisa masuk dengan email dan password yang dibuat saat mendaftar.');
    }

    public function rejectTenant(Request $request, TenantRegistration $registration): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        abort_unless($registration->status === TenantRegistration::STATUS_PENDING, 422);

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ]);

        $registration->update([
            'status' => TenantRegistration::STATUS_REJECTED,
            'admin_note' => $validated['admin_note'],
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan pendaftaran perusahaan ditolak');
    }

    /**
     * Derive a unique slug from the company name (suffixing on collision).
     * Mirrors {@see TenantController::uniqueSlug()}.
     */
    private function uniqueTenantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'klien';
        $slug = $base;
        $suffix = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    public function updatePartner(Request $request, Partner $partner): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
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
     * can go make the actual transfer. No money moves yet — that happens
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

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($withdrawal, $validated, $request): void {
            $withdrawal = ReferralWithdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            abort_unless($withdrawal->status === ReferralWithdrawal::STATUS_APPROVED, 422);

            $partner = Partner::query()->whereKey($withdrawal->partner_id)->lockForUpdate()->first();

            abort_if($partner === null, 404);

            $path = PrivateFile::store($validated['proof'], 'referral-withdrawals');
            $balanceAfter = $partner->balanceAmount() - $withdrawal->amount;

            $partner->ledger()->create([
                'type' => ReferralLedger::TYPE_WITHDRAW,
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

        return back()->with('success', 'Penarikan ditolak, saldo dikembalikan ke saldo tersedia');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'flat_amount' => ['required', 'numeric', 'min:0'],
            'hold_days' => ['required', 'integer', 'min:0', 'max:365'],
            'min_withdrawal_amount' => ['required', 'numeric', 'min:0'],
            'withdrawal_enabled' => ['required', 'boolean'],
            'leads_tab_enabled' => ['required', 'boolean'],
            'komisi_tab_enabled' => ['required', 'boolean'],
            'rekening_tab_enabled' => ['required', 'boolean'],
            'klien_tab_enabled' => ['required', 'boolean'],
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
