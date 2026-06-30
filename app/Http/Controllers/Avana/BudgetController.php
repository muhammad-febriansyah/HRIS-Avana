<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    /**
     * Roles that may always manage budgets within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Allowed budget category enum values.
     *
     * @var array<int, string>
     */
    private const CATEGORIES = ['payroll', 'recruitment', 'training', 'operational', 'benefit', 'other'];

    /**
     * Allowed budget period-type enum values.
     *
     * @var array<int, string>
     */
    private const PERIOD_TYPES = ['monthly', 'yearly'];

    /**
     * Display the budget plan vs. realization table.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $budgets = Budget::forTenant($tenantId)
            ->orderByDesc('period')
            ->orderBy('category')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Budget $budget): array => $this->transformBudget($budget));

        $totalPlanned = (float) $budgets->sum('planned_amount');
        $totalActual = (float) $budgets->sum('actual_amount');

        return Inertia::render('avana/anggaran/index', [
            'budgets' => $budgets,
            'categories' => $this->categoryOptions(),
            'periodTypes' => $this->periodTypeOptions(),
            'kpis' => [
                'total_planned' => $totalPlanned,
                'total_actual' => $totalActual,
                'usage_percent' => $totalPlanned > 0 ? round($totalActual / $totalPlanned * 100, 1) : 0.0,
            ],
        ]);
    }

    /**
     * Persist a new budget under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        Budget::create([
            ...$this->validateBudget($request),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return back()->with('success', 'Anggaran berhasil ditambahkan');
    }

    /**
     * Update an existing budget.
     */
    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $budget);

        $budget->update($this->validateBudget($request));

        return back()->with('success', 'Anggaran berhasil diperbarui');
    }

    /**
     * Delete a budget.
     */
    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $budget);

        $budget->delete();

        return back()->with('success', 'Anggaran dihapus');
    }

    /**
     * Validate the create/update payload for a budget.
     *
     * @return array<string, mixed>
     */
    private function validateBudget(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'period_type' => ['required', Rule::in(self::PERIOD_TYPES)],
            'period' => ['required', 'string', 'max:50'],
            'planned_amount' => ['required', 'numeric', 'min:0'],
            'actual_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Build the row shape consumed by the budget table.
     *
     * @return array<string, mixed>
     */
    private function transformBudget(Budget $budget): array
    {
        $planned = (float) $budget->planned_amount;
        $actual = (float) $budget->actual_amount;

        return [
            'id' => $budget->id,
            'category' => $budget->category,
            'period_type' => $budget->period_type,
            'period' => $budget->period,
            'planned_amount' => $planned,
            'actual_amount' => $actual,
            'variance' => $planned - $actual,
            'variance_percent' => $planned > 0 ? round(($planned - $actual) / $planned * 100, 1) : 0.0,
            'notes' => $budget->notes,
        ];
    }

    /**
     * Build the `{ value, label }` list of budget categories.
     *
     * @return array<int, array<string, string>>
     */
    private function categoryOptions(): array
    {
        $labels = [
            'payroll' => 'Payroll',
            'recruitment' => 'Rekrutmen',
            'training' => 'Pelatihan',
            'operational' => 'Operasional',
            'benefit' => 'Benefit',
            'other' => 'Lainnya',
        ];

        return collect(self::CATEGORIES)
            ->map(fn (string $category): array => [
                'value' => $category,
                'label' => $labels[$category],
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of budget period types.
     *
     * @return array<int, array<string, string>>
     */
    private function periodTypeOptions(): array
    {
        return [
            ['value' => 'monthly', 'label' => 'Bulanan'],
            ['value' => 'yearly', 'label' => 'Tahunan'],
        ];
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Budget $record): void
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
