<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\User;
use App\Support\DeviceIntegrity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-tenant attendance anti-spoofing policy. HR admins decide whether a face
 * mismatch or a failed device-integrity check blocks a punch or merely flags it
 * for review, and toggle mandatory face enrollment and the liveness challenge.
 */
class AttendancePolicyController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function edit(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) ($request->user()->tenant_id ?? 0);
        $policy = AttendancePolicy::resolve($tenantId);

        return Inertia::render('avana/absensi-kebijakan/index', [
            'policy' => [
                'attendance_scope' => $policy->attendance_scope ?? AttendancePolicy::SCOPE_ASSIGNED,
                'device_binding_enabled' => (bool) ($policy->device_binding_enabled ?? true),
                'face_mode' => $policy->face_mode ?? AttendancePolicy::FACE_MODE_RECOGNITION,
                'require_face_enrollment' => (bool) $policy->require_face_enrollment,
                'require_liveness_challenge' => (bool) $policy->require_liveness_challenge,
                'face_enforcement' => $policy->face_enforcement,
                'integrity_enforcement' => $policy->integrity_enforcement,
                'block_mock_location' => (bool) $policy->block_mock_location,
                'block_rooted' => (bool) $policy->block_rooted,
                'block_emulator' => (bool) $policy->block_emulator,
            ],
            'attestationEnabled' => DeviceIntegrity::attestationEnabled(),
            'scopeOptions' => AttendancePolicy::scopeOptions(),
            'overrides' => $this->overrides($tenantId),
            'assignableEmployees' => $this->assignableEmployees($tenantId),
        ]);
    }

    /**
     * Employees whose attendance scope departs from the tenant default, so an
     * admin can see at a glance who is on WFA without opening each profile.
     *
     * @return array<int, array<string, mixed>>
     */
    private function overrides(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->whereNotNull('attendance_scope')
            ->orderBy('full_name')
            ->get(['id', 'public_id', 'full_name', 'employee_number', 'attendance_scope'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'route_key' => $employee->getRouteKey(),
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'attendance_scope' => $employee->attendance_scope,
                'scope_label' => AttendancePolicy::scopeLabel($employee->attendance_scope),
            ])
            ->values()
            ->all();
    }

    /**
     * Active employees still on the tenant default — the only ones the picker
     * offers, so the same person cannot be added to the list twice.
     *
     * @return array<int, array<string, mixed>>
     */
    private function assignableEmployees(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->whereNull('attendance_scope')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->values()
            ->all();
    }

    /**
     * Give one employee a scope of their own — typically WFA for field staff —
     * without loosening the geofence for everybody else.
     */
    public function storeOverride(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = (int) ($request->user()->tenant_id ?? 0);

        $data = $request->validate([
            'overrides' => ['nullable', 'array', 'min:1'],
            'overrides.*.employee_id' => [
                'required_with:overrides',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'overrides.*.attendance_scope' => ['required_with:overrides', Rule::in(AttendancePolicy::SCOPES)],
            'employee_ids' => ['nullable', 'array', 'min:1'],
            'employee_ids.*' => [
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            // Keep the single-id payload working for existing clients.
            'employee_id' => [
                'nullable',
                'integer',
                'required_without_all:employee_ids,overrides',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'attendance_scope' => ['nullable', 'required_without:overrides', Rule::in(AttendancePolicy::SCOPES)],
        ]);

        $assignments = collect($data['overrides'] ?? [])
            ->map(fn (array $override): array => [
                'employee_id' => (int) $override['employee_id'],
                'attendance_scope' => $override['attendance_scope'],
            ]);

        if (isset($data['employee_ids'])) {
            $assignments = $assignments->merge(collect($data['employee_ids'])->map(
                fn (int|string $employeeId): array => [
                    'employee_id' => (int) $employeeId,
                    'attendance_scope' => $data['attendance_scope'],
                ],
            ));
        }

        if (isset($data['employee_id']) && ! isset($data['employee_ids']) && ! isset($data['overrides'])) {
            $assignments->push([
                'employee_id' => (int) $data['employee_id'],
                'attendance_scope' => $data['attendance_scope'],
            ]);
        }

        $assignments = $assignments->unique('employee_id')->values();

        if ($assignments->isEmpty()) {
            return back()->withErrors(['overrides' => 'Tambahkan minimal satu pengecualian.']);
        }

        DB::transaction(function () use ($tenantId, $assignments): void {
            foreach ($assignments as $assignment) {
                Employee::forTenant($tenantId)
                    ->whereKey($assignment['employee_id'])
                    ->update(['attendance_scope' => $assignment['attendance_scope']]);
            }
        });

        return back()->with('success', $assignments->count().' pengecualian absensi tersimpan.');
    }

    /** Put an employee back on the tenant-wide policy. */
    public function destroyOverride(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureCanManage($request);

        abort_unless((int) $employee->tenant_id === (int) $request->user()->tenant_id, 404);

        $employee->update(['attendance_scope' => null]);

        return back()->with('success', 'Karyawan kembali mengikuti kebijakan perusahaan.');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $data = $request->validate([
            'attendance_scope' => ['required', Rule::in(AttendancePolicy::SCOPES)],
            'device_binding_enabled' => ['boolean'],
            'face_mode' => ['sometimes', Rule::in(AttendancePolicy::FACE_MODES)],
            'require_face_enrollment' => ['boolean'],
            'require_liveness_challenge' => ['boolean'],
            'face_enforcement' => ['required', Rule::in(['block', 'flag'])],
            'integrity_enforcement' => ['required', Rule::in(['block', 'flag'])],
            'block_mock_location' => ['boolean'],
            'block_rooted' => ['boolean'],
            'block_emulator' => ['boolean'],
        ]);

        AttendancePolicy::updateOrCreate(
            ['tenant_id' => (int) ($request->user()->tenant_id ?? 0)],
            $data,
        );

        return back()->with('success', 'Kebijakan absensi tersimpan.');
    }

    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless($user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty(), 403);
    }
}
