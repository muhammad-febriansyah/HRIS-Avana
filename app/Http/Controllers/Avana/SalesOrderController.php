<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\SalaryMaster;
use App\Models\SalesOrder;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Sales Order" mapping (BPR manual 1.4): Payroll attaches the payroll benefits
 * — a Master Gaji, a work shift and a leave type — onto a client manpower order
 * created in Marketing, before it is forwarded to Recruitment.
 */
class SalesOrderController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $filters = $request->only(['search', 'status']);

        $orders = SalesOrder::forTenant($tenantId)
            ->with(['salaryMaster:id,code,category', 'shift:id,name', 'leaveType:id,name'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search): void {
                $inner->where('client_name', 'like', "%{$search}%")
                    ->orWhere('position_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->orderByDesc('id')
            ->get()
            ->map(fn (SalesOrder $o): array => [
                'id' => $o->id,
                'code' => $o->code,
                'client_name' => $o->client_name,
                'position_name' => $o->position_name,
                'headcount' => $o->headcount,
                'contract_start' => $o->contract_start?->toDateString(),
                'contract_end' => $o->contract_end?->toDateString(),
                'status' => $o->status,
                'salary_master' => $o->salaryMaster?->code,
                'shift' => $o->shift?->name,
                'leave_type' => $o->leaveType?->name,
                'salary_master_id' => $o->salary_master_id,
                'shift_id' => $o->shift_id,
                'leave_type_id' => $o->leave_type_id,
                'benefit_note' => $o->benefit_note,
            ]);

        return Inertia::render('avana/payroll-sales-order/index', [
            'orders' => $orders,
            'filters' => $filters,
            'masterOptions' => SalaryMaster::forTenant($tenantId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'category'])
                ->map(fn (SalaryMaster $m): array => [
                    'id' => $m->id,
                    'name' => $m->code.' — '.$m->category,
                ])->all(),
            'shiftOptions' => Shift::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shift $s): array => ['id' => $s->id, 'name' => $s->name])->all(),
            'leaveOptions' => LeaveType::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (LeaveType $l): array => ['id' => $l->id, 'name' => $l->name])->all(),
        ]);
    }

    /**
     * Sinkronisasi (BPR manual 1.4.1): attach the Master Gaji, shift and leave
     * type to the order and mark it mapped so Recruitment can take it over.
     */
    public function map(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $salesOrder->tenant_id !== (int) $request->user()->tenant_id, 404);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'salary_master_id' => ['required', 'integer', Rule::exists('salary_masters', 'id')->where('tenant_id', $tenantId)],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'leave_type_id' => ['nullable', 'integer', Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId)],
        ]);

        $salesOrder->update([
            'salary_master_id' => $data['salary_master_id'],
            'shift_id' => $data['shift_id'] ?? null,
            'leave_type_id' => $data['leave_type_id'] ?? null,
            'status' => 'mapped',
            'mapped_at' => now(),
        ]);

        return back()->with('success', 'Sales Order berhasil di-mapping');
    }

    /**
     * Forward a mapped Sales Order to Recruitment for benefit approval
     * (BPR manual 1.4: "diteruskan ke menu rekrutmen untuk approval benefit").
     */
    public function forward(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $salesOrder->tenant_id !== (int) $request->user()->tenant_id, 404);

        if ($salesOrder->status !== 'mapped') {
            return back()->withErrors(['sales_order' => 'Mapping benefit dulu sebelum diteruskan ke rekrutmen.']);
        }

        $salesOrder->update([
            'status' => 'forwarded',
            'forwarded_by' => $request->user()->id,
            'forwarded_at' => now(),
            'benefit_note' => null,
        ]);

        return back()->with('success', 'Sales Order diteruskan ke rekrutmen');
    }

    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasPermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'payroll.'));

        abort_unless($isPrivileged || $hasPermission, 403);
    }
}
