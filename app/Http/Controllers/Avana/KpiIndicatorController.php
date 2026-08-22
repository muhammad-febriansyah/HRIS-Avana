<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\KpiIndicator;
use App\Models\PerformanceKpiItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Master KPI indicator CRUD ("Definisi KPI"): the tenant-scoped catalogue of
 * indicators managers pick from when building a review's KPI items. Gated
 * under the `performance` module — this is config of the performance module,
 * not a separate one.
 */
class KpiIndicatorController extends Controller
{
    private const MODULE = 'performance';

    /**
     * @var array<int, string>
     */
    private const DIRECTIONS = ['higher_better', 'lower_better'];

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $indicators = KpiIndicator::forTenant($request->user()->tenant_id)
            ->orderBy('name')
            ->get()
            ->map(fn (KpiIndicator $indicator): array => $this->transform($indicator));

        return Inertia::render('avana/kinerja/kpi-indicators', [
            'indicators' => $indicators,
            'directions' => $this->directionOptions(),
        ]);
    }

    /**
     * The tenant's active indicators, for use in a picker (e.g. the KPI item
     * form on a review). Not a full page — consumed via XHR/props.
     */
    public function options(Request $request): JsonResponse
    {
        $this->ensureCan($request, 'view');

        $indicators = KpiIndicator::forTenant($request->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (KpiIndicator $indicator): array => $this->transform($indicator));

        return response()->json(['indicators' => $indicators]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;
        $data = $this->validateIndicator($request);

        KpiIndicator::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return back()->with('success', 'Indikator KPI berhasil ditambahkan');
    }

    public function update(Request $request, KpiIndicator $indicator): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $indicator);

        $data = $this->validateIndicator($request);

        $indicator->update($data);

        return back()->with('success', 'Indikator KPI berhasil diperbarui');
    }

    public function destroy(Request $request, KpiIndicator $indicator): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $indicator);

        // The FK nulls `kpi_indicator_id` on delete, which would leave a
        // `manual` KPI item pointing at no indicator while keeping its score.
        // Retiring an indicator that is still scored somewhere is done with
        // `is_active`, not by deleting it.
        $usage = PerformanceKpiItem::where('kpi_indicator_id', $indicator->id)->count();

        abort_if(
            $usage > 0,
            422,
            "Indikator ini dipakai pada {$usage} item KPI penilaian. Nonaktifkan saja alih-alih menghapusnya."
        );

        $indicator->delete();

        return back()->with('success', 'Indikator KPI dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIndicator(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'direction' => ['required', Rule::in(self::DIRECTIONS)],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(KpiIndicator $indicator): array
    {
        return [
            'id' => $indicator->id,
            'name' => $indicator->name,
            'unit' => $indicator->unit,
            'direction' => $indicator->direction,
            'category' => $indicator->category,
            'description' => $indicator->description,
            'is_active' => $indicator->is_active,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function directionOptions(): array
    {
        $labels = [
            'higher_better' => 'Makin tinggi makin baik',
            'lower_better' => 'Makin rendah makin baik',
        ];

        return collect(self::DIRECTIONS)
            ->map(fn (string $direction): array => ['value' => $direction, 'label' => $labels[$direction]])
            ->all();
    }

    private function ensureTenantOwnership(Request $request, KpiIndicator $indicator): void
    {
        abort_if((int) $indicator->tenant_id !== (int) $request->user()->tenant_id, 404);
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
