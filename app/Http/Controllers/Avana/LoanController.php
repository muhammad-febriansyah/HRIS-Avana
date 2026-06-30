<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    /**
     * Roles that may always manage employee loans within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Indonesian labels for the status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'paid' => 'Lunas',
    ];

    /**
     * Deterministic avatar background palette (mirrors CashAdvanceController).
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Display a server-side paginated, filterable list of employee loans.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $paginator = Loan::query()
            ->forTenant($tenantId)
            ->with('employee:id,full_name,employee_number')
            ->when($request->query('search'), function ($query, $search): void {
                $query->whereHas('employee', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/pinjaman/index', [
            'loans' => [
                'data' => $paginator->getCollection()
                    ->map(fn (Loan $loan): array => $this->shapeLoan($loan))
                    ->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'filters' => $request->only(['search', 'status', 'per_page']),
            'employees' => $this->employeeOptions($tenantId),
            'kpis' => [
                'outstanding_total' => (float) Loan::forTenant($tenantId)->where('status', 'approved')->sum('amount'),
                'pending_count' => Loan::forTenant($tenantId)->where('status', 'pending')->count(),
            ],
        ]);
    }

    /**
     * Persist a new loan request with a computed monthly installment.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'amount' => ['required', 'numeric', 'min:1'],
            'tenor_months' => ['required', 'integer', 'min:1'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'purpose' => ['nullable', 'string'],
        ]);

        $amount = round((float) $data['amount'], 2);
        $tenor = (int) $data['tenor_months'];
        $rate = round((float) ($data['interest_rate'] ?? 0), 2);

        Loan::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'amount' => $amount,
            'tenor_months' => $tenor,
            'interest_rate' => $rate,
            'monthly_installment' => round($amount * (1 + $rate / 100) / $tenor, 2),
            'purpose' => $data['purpose'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.pinjaman')
            ->with('success', 'Pengajuan pinjaman dibuat');
    }

    /**
     * Approve a pending loan and stamp the approval time.
     */
    public function approve(Request $request, Loan $loan): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $loan);

        $loan->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Pinjaman disetujui');
    }

    /**
     * Reject a pending loan.
     */
    public function reject(Request $request, Loan $loan): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $loan);

        $loan->update(['status' => 'rejected']);

        return back()->with('success', 'Pinjaman ditolak');
    }

    /**
     * Delete a loan record.
     */
    public function destroy(Request $request, Loan $loan): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $loan);

        $loan->delete();

        return back()->with('success', 'Pinjaman dihapus');
    }

    /**
     * Build the selectable employee option list for the acting tenant.
     *
     * @return Collection<int, array{id: int, name: string, employee_number: string|null}>
     */
    private function employeeOptions(int $tenantId): Collection
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ]);
    }

    /**
     * Shape a loan row for the index DataTable.
     *
     * @return array<string, mixed>
     */
    private function shapeLoan(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'employee' => $this->shapeEmployee($loan),
            'amount' => (float) $loan->amount,
            'tenor_months' => (int) $loan->tenor_months,
            'interest_rate' => (float) $loan->interest_rate,
            'monthly_installment' => (float) $loan->monthly_installment,
            'purpose' => $loan->purpose,
            'status' => $loan->status,
            'status_label' => $this->statusLabel($loan->status),
            'approved_at' => $loan->approved_at?->format('d M Y'),
        ];
    }

    /**
     * Shape the eager-loaded employee for a row, deriving initials and color.
     *
     * @return array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null
     */
    private function shapeEmployee(Model $record): ?array
    {
        $employee = $record->employee;

        if ($employee === null) {
            return null;
        }

        return [
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'initials' => $this->initials($employee->full_name),
            'avatar_color' => $this->avatarColor($employee->full_name),
        ];
    }

    /**
     * Build up to two uppercase initials from a full name.
     */
    private function initials(?string $fullName): string
    {
        $words = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        $initials = collect($words)
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Pick a deterministic avatar color derived from the employee name.
     */
    private function avatarColor(?string $fullName): string
    {
        $index = crc32((string) $fullName) % count(self::AVATAR_PALETTE);

        return self::AVATAR_PALETTE[$index];
    }

    /**
     * Map a status enum value to its Indonesian label.
     */
    private function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Abort with 404 when the loan does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Loan $loan): void
    {
        abort_if((int) $loan->tenant_id !== (int) $request->user()->tenant_id, 404);
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
