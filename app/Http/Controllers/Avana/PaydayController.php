<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payday;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mapping Payday": which employees are paid when, and which slice of
 * attendance their pay is computed from.
 *
 * A group's cut-off takes precedence over the Master Gaji window during a
 * payroll run, and its pay date lands in the payslip snapshot — so unlike the
 * screen this replaces, what is configured here shows up in the result.
 */
class PaydayController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;
        $month = Carbon::now();

        $paydays = Payday::forTenant($tenantId)
            ->withCount('employees')
            ->orderBy('code')
            ->get()
            ->map(fn (Payday $payday): array => [
                'id' => $payday->id,
                'code' => $payday->code,
                'name' => $payday->name,
                'pay_mode' => $payday->pay_mode,
                'pay_day' => $payday->pay_day,
                'pay_label' => $payday->payLabel(),
                'next_pay_date' => $payday->payDateFor($month)->toDateString(),
                'cut_off_start_day' => $payday->cut_off_start_day,
                'cut_off_end_day' => $payday->cut_off_end_day,
                'cut_off_label' => $payday->cutOffLabel(),
                'description' => $payday->description,
                'is_active' => $payday->is_active,
                'employees_count' => $payday->employees_count,
            ]);

        return Inertia::render('avana/payroll-payday/index', [
            'paydays' => $paydays,
            'employees' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number', 'payday_id'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'number' => $employee->employee_number,
                    'payday_id' => $employee->payday_id,
                ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validatePayday($request, $tenantId);

        Payday::create(['tenant_id' => $tenantId] + $data);

        return back()->with('success', 'Kelompok payday dibuat');
    }

    public function update(Request $request, Payday $payday): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        abort_if((int) $payday->tenant_id !== (int) $request->user()->tenant_id, 404);

        $payday->update($this->validatePayday($request, (int) $payday->tenant_id, $payday->id));

        return back()->with('success', 'Kelompok payday diperbarui');
    }

    public function destroy(Request $request, Payday $payday): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        abort_if((int) $payday->tenant_id !== (int) $request->user()->tenant_id, 404);

        $payday->delete();

        return back()->with('success', 'Kelompok payday dihapus');
    }

    /**
     * Map employees to a group — or, with a null group, unmap them.
     */
    public function assign(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'payday_id' => ['nullable', 'integer', Rule::exists('paydays', 'id')->where('tenant_id', $tenantId)],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
        ]);

        Employee::forTenant($tenantId)
            ->whereIn('id', $data['employee_ids'])
            ->update(['payday_id' => $data['payday_id'] ?? null]);

        return back()->with('success', $data['payday_id'] === null
            ? 'Karyawan dilepas dari kelompok payday'
            : 'Karyawan dipetakan ke kelompok payday');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayday(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $code = Rule::unique('paydays', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId));

        if ($ignoreId !== null) {
            $code = $code->ignore($ignoreId);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', $code],
            'name' => ['required', 'string', 'max:255'],
            'pay_mode' => ['required', Rule::in(['date', 'end_of_month'])],
            'pay_day' => ['nullable', 'integer', 'min:1', 'max:31', 'required_if:pay_mode,date'],
            'cut_off_start_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'cut_off_end_day' => ['nullable', 'integer', 'min:1', 'max:31', 'required_with:cut_off_start_day'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ], [
            'pay_day.required_if' => 'Tanggal pembayaran wajib diisi untuk mode tanggal tetap.',
            'cut_off_end_day.required_with' => 'Cut-off harus punya tanggal awal dan akhir.',
        ]);

        // A group paid at month end has no fixed day; keep the column clean so
        // the label never contradicts the mode.
        if ($data['pay_mode'] === 'end_of_month') {
            $data['pay_day'] = null;
        }

        return $data;
    }

    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}
