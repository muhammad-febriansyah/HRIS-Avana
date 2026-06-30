<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClaimController extends Controller
{
    /**
     * Roles that may always manage claims within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Allowed claim type enum values.
     *
     * @var array<int, string>
     */
    private const CLAIM_TYPES = ['medical', 'transport', 'meal', 'glasses', 'other'];

    /**
     * Display the claim list together with status KPIs.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $claims = Claim::forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'approver:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (Claim $claim): array => $this->shapeClaim($claim));

        return Inertia::render('avana/klaim/index', [
            'claims' => $claims,
            'employees' => $this->employeeOptions($tenantId),
            'claimTypes' => $this->claimTypeOptions(),
            'kpis' => [
                'pending' => $claims->where('status', 'pending')->count(),
                'approved' => $claims->where('status', 'approved')->count(),
                'paid' => $claims->where('status', 'paid')->count(),
                'total_amount' => (float) $claims->sum('amount'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new claim.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('avana/klaim/create', [
            'employees' => $this->employeeOptions($request->user()->tenant_id),
            'claimTypes' => $this->claimTypeOptions(),
        ]);
    }

    /**
     * Show the form for editing an existing claim.
     */
    public function edit(Request $request, Claim $claim): Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $claim);

        return Inertia::render('avana/klaim/edit', [
            'claim' => [
                'id' => $claim->id,
                'employee_id' => $claim->employee_id,
                'claim_type' => $claim->claim_type,
                'title' => $claim->title,
                'amount' => (float) $claim->amount,
                'claim_date' => $claim->claim_date?->toDateString(),
                'description' => $claim->description,
                'receipt_url' => $claim->receipt_path !== null
                    ? Storage::disk('public')->url($claim->receipt_path)
                    : null,
                'status' => $claim->status,
                'notes' => $claim->notes,
            ],
            'employees' => $this->employeeOptions($request->user()->tenant_id),
            'claimTypes' => $this->claimTypeOptions(),
        ]);
    }

    /**
     * Persist a new claim under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateClaim($request, $tenantId);

        $receiptPath = null;

        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('claims', 'public');
        }

        Claim::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'claim_type' => $data['claim_type'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'claim_date' => $data['claim_date'],
            'description' => $data['description'] ?? null,
            'receipt_path' => $receiptPath,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.klaim')
            ->with('success', 'Klaim berhasil ditambahkan');
    }

    /**
     * Update an existing claim, replacing the receipt when a new one is sent.
     */
    public function update(Request $request, Claim $claim): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $claim);

        $data = $this->validateClaim($request, $request->user()->tenant_id);

        $payload = [
            'employee_id' => $data['employee_id'],
            'claim_type' => $data['claim_type'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'claim_date' => $data['claim_date'],
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($request->hasFile('receipt')) {
            if ($claim->receipt_path !== null) {
                Storage::disk('public')->delete($claim->receipt_path);
            }

            $payload['receipt_path'] = $request->file('receipt')->store('claims', 'public');
        }

        $claim->update($payload);

        return redirect()->route('avana.klaim')
            ->with('success', 'Klaim berhasil diperbarui');
    }

    /**
     * Delete a claim and its uploaded receipt.
     */
    public function destroy(Request $request, Claim $claim): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $claim);

        if ($claim->receipt_path !== null) {
            Storage::disk('public')->delete($claim->receipt_path);
        }

        $claim->delete();

        return back()->with('success', 'Klaim dihapus');
    }

    /**
     * Approve a claim, recording the approver and timestamp.
     */
    public function approve(Request $request, Claim $claim): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $claim);

        $claim->update([
            'status' => 'approved',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Klaim disetujui');
    }

    /**
     * Reject a claim, recording the approver and timestamp.
     */
    public function reject(Request $request, Claim $claim): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $claim);

        $claim->update([
            'status' => 'rejected',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Klaim ditolak');
    }

    /**
     * Mark an approved claim as paid.
     */
    public function markPaid(Request $request, Claim $claim): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $claim);

        $claim->update(['status' => 'paid']);

        return back()->with('success', 'Klaim ditandai dibayar');
    }

    /**
     * Validate the create/update payload for a claim.
     *
     * @return array<string, mixed>
     */
    private function validateClaim(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'employee_id' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'claim_type' => ['required', 'in:medical,transport,meal,glasses,other'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'claim_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);
    }

    /**
     * Shape a claim row for the index table and edit screen.
     *
     * @return array<string, mixed>
     */
    private function shapeClaim(Claim $claim): array
    {
        $employee = $claim->employee;

        return [
            'id' => $claim->id,
            'employee' => $employee === null ? null : [
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ],
            'employee_id' => $claim->employee_id,
            'claim_type' => $claim->claim_type,
            'title' => $claim->title,
            'amount' => (float) $claim->amount,
            'claim_date' => $claim->claim_date?->toDateString(),
            'description' => $claim->description,
            'receipt_url' => $claim->receipt_path !== null
                ? Storage::disk('public')->url($claim->receipt_path)
                : null,
            'status' => $claim->status,
            'notes' => $claim->notes,
            'approver' => $claim->approver?->name,
            'approved_at' => $claim->approved_at?->toDateTimeString(),
        ];
    }

    /**
     * Build the tenant's selectable employee options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function employeeOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of claim type options.
     *
     * @return array<int, array<string, string>>
     */
    private function claimTypeOptions(): array
    {
        $labels = [
            'medical' => 'Kesehatan',
            'transport' => 'Transportasi',
            'meal' => 'Konsumsi',
            'glasses' => 'Kacamata',
            'other' => 'Lainnya',
        ];

        return collect(self::CLAIM_TYPES)
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => $labels[$type],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the claim does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Claim $claim): void
    {
        abort_if((int) $claim->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}
