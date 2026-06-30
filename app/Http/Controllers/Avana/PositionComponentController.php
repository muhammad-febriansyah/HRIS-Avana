<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\PayrollComponent;
use App\Models\Position;
use App\Models\PositionPayrollComponent;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PositionComponentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the position × payroll-component nominal matrix.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PositionPayrollComponent::class);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/payroll-components/index', [
            'positions' => Position::forTenant($tenantId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'components' => PayrollComponent::forTenant($tenantId)
                ->where('status', 'active')
                ->get(['id', 'name', 'type', 'calc_basis']),
            'matrix' => PositionPayrollComponent::forTenant($tenantId)
                ->get(['position_id', 'payroll_component_id', 'amount'])
                ->map(fn (PositionPayrollComponent $row): array => [
                    'position_id' => $row->position_id,
                    'payroll_component_id' => $row->payroll_component_id,
                    'amount' => (float) $row->amount,
                ]),
        ]);
    }

    /**
     * Upsert the per-position nominals for the supplied components.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage', PositionPayrollComponent::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.position_id' => [
                'required', 'integer',
                Rule::exists('positions', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'items.*.payroll_component_id' => [
                'required', 'integer',
                Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($validated['items'] as $item) {
            PositionPayrollComponent::updateOrCreate(
                [
                    'position_id' => (int) $item['position_id'],
                    'payroll_component_id' => (int) $item['payroll_component_id'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'amount' => (float) $item['amount'],
                ],
            );
        }

        return back()->with('success', 'Komponen per jabatan disimpan');
    }

    /**
     * Update a payroll component's attendance calculation basis.
     */
    public function updateBasis(Request $request): RedirectResponse
    {
        $this->authorize('manage', PositionPayrollComponent::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'payroll_component_id' => [
                'required', 'integer',
                Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'calc_basis' => ['required', Rule::in(['fixed', 'per_present_day', 'per_overtime_hour'])],
        ]);

        PayrollComponent::forTenant($tenantId)
            ->whereKey($validated['payroll_component_id'])
            ->update(['calc_basis' => $validated['calc_basis']]);

        return back()->with('success', 'Basis komponen diperbarui');
    }
}
