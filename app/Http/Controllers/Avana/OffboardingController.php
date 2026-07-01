<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\OffboardingCase;
use App\Models\PositionPayrollComponent;
use App\Models\User;
use App\Services\SeveranceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OffboardingController extends Controller
{
    /**
     * Roles that may always manage offboarding within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Default clearance checklist seeded when a new case is opened.
     *
     * @var array<int, array{title: string, department: string}>
     */
    private const DEFAULT_CLEARANCE = [
        ['title' => 'Pengembalian aset IT (laptop, akun & akses)', 'department' => 'IT'],
        ['title' => 'Exit interview & serah terima dokumen', 'department' => 'HR'],
        ['title' => 'Pelunasan kasbon & klaim akhir', 'department' => 'Finance'],
        ['title' => 'Pengembalian aset & inventaris perusahaan', 'department' => 'Asset'],
    ];

    /**
     * Display the offboarding cases with their clearance checklists.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $cases = OffboardingCase::forTenant($tenantId)
            ->with([
                'employee:id,full_name,employee_number',
                'clearanceItems' => fn ($query) => $query->orderBy('id'),
            ])
            ->latest('id')
            ->get()
            ->map(fn (OffboardingCase $case): array => $this->transformCase($case));

        return Inertia::render('avana/offboarding/index', [
            'cases' => $cases,
            'employees' => $this->employeeOptions($tenantId),
            'severanceReasons' => collect(SeveranceCalculator::REASONS)
                ->map(fn (array $config, string $key): array => ['value' => $key, 'label' => $config['label']])
                ->values()
                ->all(),
            'kpis' => [
                'active' => $cases->where('status', 'in_progress')->count(),
                'completed' => $cases->where('status', 'completed')->count(),
                'pending_items' => ClearanceItem::forTenant($tenantId)->where('is_cleared', false)->count(),
            ],
        ]);
    }

    /**
     * Create an offboarding case and seed its default clearance checklist.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'last_day' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $case = OffboardingCase::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'last_day' => $data['last_day'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => 'in_progress',
        ]);

        foreach (self::DEFAULT_CLEARANCE as $item) {
            $case->clearanceItems()->create([
                'tenant_id' => $tenantId,
                'title' => $item['title'],
                'department' => $item['department'],
                'is_cleared' => false,
            ]);
        }

        $this->recomputeCaseStatus($case);

        return redirect()->route('avana.offboarding')
            ->with('success', 'Kasus offboarding berhasil dibuat');
    }

    /**
     * Toggle a clearance item and recompute the parent case status.
     */
    public function toggleItem(Request $request, ClearanceItem $item): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $item);

        $item->update(['is_cleared' => ! $item->is_cleared]);

        $this->recomputeCaseStatus($item->offboardingCase);

        return back()->with('success', 'Status clearance diperbarui');
    }

    /**
     * Compute and persist the termination settlement (pesangon / uang pisah)
     * for an offboarding case using the statutory PP 35/2021 formula.
     */
    public function settlement(Request $request, OffboardingCase $case, SeveranceCalculator $calculator): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $case);

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(SeveranceCalculator::REASONS))],
            'uph' => ['nullable', 'numeric', 'min:0'],
            'separation_pay' => ['nullable', 'numeric', 'min:0'],
        ]);

        $employee = $case->employee;

        abort_if($employee === null, 404);

        $endDate = $case->last_day ?? $employee->resign_date ?? Carbon::today();

        $tenureYears = $employee->join_date !== null
            ? max(0.0, $employee->join_date->diffInDays($endDate) / 365.25)
            : 0.0;

        $wage = $this->monthlyBaseWage($employee, (int) $request->user()->tenant_id);

        $breakdown = $calculator->calculate(
            $wage,
            $tenureYears,
            $data['reason'],
            (float) ($data['uph'] ?? 0),
            (float) ($data['separation_pay'] ?? 0),
        );

        $case->update([
            'settlement_reason' => $data['reason'],
            'settlement_amount' => $breakdown['total'],
            'settlement_breakdown' => $breakdown,
        ]);

        return back()->with('success', 'Settlement dihitung: Rp '.number_format($breakdown['total'], 0, ',', '.'));
    }

    /**
     * Sum an employee's full monthly fixed wage — the basis for severance.
     */
    private function monthlyBaseWage(Employee $employee, int $tenantId): float
    {
        $total = 0.0;

        $salaryComponents = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->with('component')
            ->get();

        foreach ($salaryComponents as $salaryComponent) {
            if ($salaryComponent->component !== null && $salaryComponent->component->type !== 'deduction') {
                $total += (float) $salaryComponent->amount;
            }
        }

        if ($employee->position_id !== null) {
            $positionComponents = PositionPayrollComponent::forTenant($tenantId)
                ->where('position_id', $employee->position_id)
                ->with('component')
                ->get();

            foreach ($positionComponents as $positionComponent) {
                $component = $positionComponent->component;

                if ($component !== null
                    && $component->type !== 'deduction'
                    && ! in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true)) {
                    $total += (float) $positionComponent->amount;
                }
            }
        }

        return $total;
    }

    /**
     * Delete an offboarding case together with its clearance items.
     */
    public function destroy(Request $request, OffboardingCase $case): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $case);

        $case->delete();

        return back()->with('success', 'Kasus offboarding dihapus');
    }

    /**
     * Recompute a case status: completed when every item is cleared.
     */
    private function recomputeCaseStatus(OffboardingCase $case): void
    {
        $total = $case->clearanceItems()->count();
        $cleared = $case->clearanceItems()->where('is_cleared', true)->count();

        $case->update([
            'status' => $total > 0 && $cleared === $total ? 'completed' : 'in_progress',
        ]);
    }

    /**
     * Build the card shape consumed by the offboarding board.
     *
     * @return array<string, mixed>
     */
    private function transformCase(OffboardingCase $case): array
    {
        $items = $case->clearanceItems->map(fn (ClearanceItem $item): array => [
            'id' => $item->id,
            'title' => $item->title,
            'department' => $item->department,
            'is_cleared' => (bool) $item->is_cleared,
        ]);

        $clearedCount = $items->where('is_cleared', true)->count();

        return [
            'id' => $case->id,
            'employee_id' => $case->employee_id,
            'employee' => $case->employee ? [
                'name' => $case->employee->full_name,
                'employee_number' => $case->employee->employee_number,
            ] : null,
            'last_day' => $case->last_day?->toDateString(),
            'reason' => $case->reason,
            'status' => $case->status,
            'settlement_reason' => $case->settlement_reason,
            'settlement_amount' => $case->settlement_amount !== null ? (float) $case->settlement_amount : null,
            'settlement_breakdown' => $case->settlement_breakdown,
            'items' => $items->all(),
            'items_total' => $items->count(),
            'items_cleared' => $clearedCount,
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
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, OffboardingCase|ClearanceItem $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
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
