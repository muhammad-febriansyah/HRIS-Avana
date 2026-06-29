<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashAdvanceController extends Controller
{
    /**
     * Roles that may always manage cash advances within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

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
     * Deterministic avatar background palette (mirrors LeaveController).
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Display a server-side paginated, filterable list of cash advances.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $paginator = CashAdvance::query()
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

        return Inertia::render('avana/kasbon', [
            'requests' => [
                'data' => $paginator->getCollection()
                    ->map(fn (CashAdvance $advance): array => $this->shapeAdvance($advance))
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
            'employees' => Employee::forTenant($tenantId)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                ]),
        ]);
    }

    /**
     * Persist a new cash advance on behalf of an employee under the tenant.
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
            'installments' => ['required', 'integer', 'min:1'],
            'request_date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        $amount = round((float) $data['amount'], 2);
        $installments = (int) $data['installments'];

        CashAdvance::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'amount' => $amount,
            'installments' => $installments,
            'monthly_deduction' => round($amount / $installments, 2),
            'request_date' => $data['request_date'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.kasbon')
            ->with('success', 'Pengajuan kasbon dibuat');
    }

    /**
     * Approve a pending cash advance.
     */
    public function approve(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $cashAdvance);

        $cashAdvance->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Kasbon disetujui');
    }

    /**
     * Reject a pending cash advance.
     */
    public function reject(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $cashAdvance);

        $cashAdvance->update(['status' => 'rejected']);

        return back()->with('success', 'Kasbon ditolak');
    }

    /**
     * Shape a cash advance row for the index DataTable.
     *
     * @return array{id: int, employee: array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null, amount: float, installments: int, monthly_deduction: float, request_date: string|null, reason: string|null, status: string, status_label: string}
     */
    private function shapeAdvance(CashAdvance $advance): array
    {
        return [
            'id' => $advance->id,
            'employee' => $this->shapeEmployee($advance),
            'amount' => (float) $advance->amount,
            'installments' => (int) $advance->installments,
            'monthly_deduction' => (float) $advance->monthly_deduction,
            'request_date' => $advance->request_date?->format('d M Y'),
            'reason' => $advance->reason,
            'status' => $advance->status,
            'status_label' => $this->statusLabel($advance->status),
        ];
    }

    /**
     * Shape the eager-loaded employee for a row, deriving initials and color.
     *
     * @return array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null
     */
    private function shapeEmployee(Model $request): ?array
    {
        $employee = $request->employee;

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
     * Abort with 404 when the cash advance does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, CashAdvance $cashAdvance): void
    {
        abort_if((int) $cashAdvance->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds a payroll permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasPayrollPermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'payroll.'));

        abort_unless($isPrivileged || $hasPayrollPermission, 403);
    }
}
