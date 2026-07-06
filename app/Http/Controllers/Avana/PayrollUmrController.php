<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\UmrRate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "UMR" (BPR payroll Komponen submenu): manage the regional minimum wage per
 * branch/area and year. Consumed by the engine as the Master Formula `umr`
 * operand.
 */
class PayrollUmrController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $rates = UmrRate::forTenant($tenantId)
            ->with('branch:id,name')
            ->orderByDesc('year')
            ->orderBy('branch_id')
            ->get()
            ->map(fn (UmrRate $r): array => [
                'id' => $r->id,
                'branch_id' => $r->branch_id,
                'branch' => $r->branch?->name,
                'region' => $r->region,
                'year' => $r->year,
                'amount' => (float) $r->amount,
                'note' => $r->note,
            ]);

        return Inertia::render('avana/payroll-umr/index', [
            'rates' => $rates,
            'branchOptions' => Branch::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $b): array => ['id' => $b->id, 'name' => $b->name])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'region' => ['nullable', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        UmrRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'branch_id' => $data['branch_id'] ?? null, 'year' => $data['year']],
            ['region' => $data['region'] ?? null, 'amount' => $data['amount'], 'note' => $data['note'] ?? null],
        );

        return back()->with('success', 'UMR disimpan');
    }

    public function destroy(Request $request, UmrRate $umr): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $umr->tenant_id !== (int) $request->user()->tenant_id, 404);

        $umr->delete();

        return back()->with('success', 'UMR dihapus');
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
