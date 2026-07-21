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
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

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
        $this->ensureCan($request, 'create');

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
        $this->ensureCan($request, 'archive');
        abort_if((int) $umr->tenant_id !== (int) $request->user()->tenant_id, 404);

        $umr->delete();

        return back()->with('success', 'UMR dihapus');
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
