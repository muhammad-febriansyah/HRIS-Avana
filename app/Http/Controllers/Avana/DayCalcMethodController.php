<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\DayCalcMethod;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Perhitungan Hari" (Setting Komponen submenu): manage the named day-calculation
 * methods a tenant uses to prorate salary. Each method pairs a counting basis
 * (absen / hari kerja / hari kalender / formula) with a divisor; a Master Gaji
 * references one so the payroll engine prorates fixed earnings by worked days.
 */
class DayCalcMethodController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * @var array<int, string>
     */
    private const BASES = ['absen', 'hari_kerja', 'hari_kalender', 'formula'];

    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $methods = DayCalcMethod::forTenant($tenantId)
            ->orderBy('name')
            ->get()
            ->map(fn (DayCalcMethod $m): array => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'basis' => $m->basis,
                'divisor' => $m->divisor,
                'description' => $m->description,
                'is_active' => $m->is_active,
            ]);

        return Inertia::render('avana/payroll-perhitungan-hari/index', [
            'methods' => $methods,
            'basisOptions' => self::BASES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $this->validated($request, $tenantId);

        DayCalcMethod::create([
            'tenant_id' => $tenantId,
            ...$data,
        ]);

        return back()->with('success', 'Perhitungan Hari disimpan');
    }

    public function update(Request $request, DayCalcMethod $perhitunganHari): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $perhitunganHari->tenant_id !== (int) $request->user()->tenant_id, 404);

        $data = $this->validated($request, (int) $perhitunganHari->tenant_id, $perhitunganHari->id);

        $perhitunganHari->update($data);

        return back()->with('success', 'Perhitungan Hari diperbarui');
    }

    public function destroy(Request $request, DayCalcMethod $perhitunganHari): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $perhitunganHari->tenant_id !== (int) $request->user()->tenant_id, 404);

        $perhitunganHari->delete();

        return back()->with('success', 'Perhitungan Hari dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $code = Rule::unique('day_calc_methods', 'code')->where('tenant_id', $tenantId);

        if ($ignoreId !== null) {
            $code->ignore($ignoreId);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', $code],
            'name' => ['required', 'string', 'max:255'],
            'basis' => ['required', Rule::in(self::BASES)],
            'divisor' => ['nullable', 'integer', 'min:1', 'max:31'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $validated['divisor'] = $validated['divisor'] ?? null;
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
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
