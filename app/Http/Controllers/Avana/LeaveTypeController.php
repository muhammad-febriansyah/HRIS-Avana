<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Policies\LeaveTypePolicy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD controller for the AvanaHR "Jenis Cuti" (leave type) master data.
 *
 * Every action is tenant-scoped via {@see LeaveType::scopeForTenant()} and
 * guarded by {@see LeaveTypePolicy}.
 */
class LeaveTypeController extends Controller
{
    use AuthorizesRequests;

    /**
     * List the tenant's active (non-trashed) leave types with usage counts.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveType::class);

        $tenantId = $request->user()->tenant_id;

        $leaveTypes = LeaveType::forTenant($tenantId)
            ->withCount('leaveRequests')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'default_quota', 'allow_negative', 'requires_attachment', 'status'])
            ->map(fn (LeaveType $leaveType): array => [
                'id' => $leaveType->id,
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'default_quota' => $leaveType->default_quota,
                'allow_negative' => $leaveType->allow_negative,
                'requires_attachment' => $leaveType->requires_attachment,
                'status' => $leaveType->status,
                'usage' => $leaveType->leave_requests_count,
            ]);

        return Inertia::render('avana/jenis-cuti', [
            'leaveTypes' => $leaveTypes,
        ]);
    }

    /**
     * Validate and create a new leave type under the current tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveType::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate($this->rules($tenantId), $this->messages());

        LeaveType::create([
            'tenant_id' => $tenantId,
            'code' => $data['code'],
            'name' => $data['name'],
            'default_quota' => $data['default_quota'],
            'allow_negative' => $request->boolean('allow_negative'),
            'requires_attachment' => $request->boolean('requires_attachment'),
            'status' => $data['status'],
        ]);

        return redirect()->route('avana.cuti.jenis')
            ->with('success', 'Jenis cuti dibuat');
    }

    /**
     * Validate and update a leave type belonging to the current tenant.
     */
    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $leaveType);
        $this->authorize('update', $leaveType);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate($this->rules($tenantId, (int) $leaveType->getKey()), $this->messages());

        $leaveType->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'default_quota' => $data['default_quota'],
            'allow_negative' => $request->boolean('allow_negative'),
            'requires_attachment' => $request->boolean('requires_attachment'),
            'status' => $data['status'],
        ]);

        return redirect()->route('avana.cuti.jenis')
            ->with('success', 'Jenis cuti diperbarui');
    }

    /**
     * Soft delete a leave type belonging to the current tenant.
     */
    public function destroy(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $leaveType);
        $this->authorize('delete', $leaveType);

        $leaveType->delete();

        return redirect()->route('avana.cuti.jenis')
            ->with('success', 'Jenis cuti dihapus');
    }

    /**
     * Build the tenant-scoped validation rules, ignoring the edited record.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(int $tenantId, ?int $recordId = null): array
    {
        $code = Rule::unique('leave_types', 'code')->where('tenant_id', $tenantId);

        if ($recordId !== null) {
            $code->ignore($recordId);
        }

        return [
            'code' => ['required', 'string', 'max:255', $code],
            'name' => ['required', 'string', 'max:255'],
            'default_quota' => ['required', 'integer', 'min:0'],
            'allow_negative' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Indonesian validation messages for the leave type form.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode sudah digunakan.',
            'name.required' => 'Nama wajib diisi.',
            'default_quota.required' => 'Kuota default wajib diisi.',
            'default_quota.integer' => 'Kuota default harus berupa angka.',
            'default_quota.min' => 'Kuota default tidak boleh kurang dari 0.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
        ];
    }

    /**
     * Abort with 404 when the leave type does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, LeaveType $leaveType): void
    {
        abort_if((int) $leaveType->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
