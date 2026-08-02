<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\RosterPattern;
use App\Models\Shift;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rotation templates: the cycles a roster can be filled from, such as
 * "3 pagi – 3 siang – 3 malam – 2 libur" for a factory or "14 on – 14 off"
 * for a mine.
 */
class RosterPatternController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/roster-pola/index', [
            'patterns' => $this->patternsFor($tenantId),
            'shifts' => $this->shiftOptions($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $tenantId = $request->user()->tenant_id;
        $data = $this->validated($request, $tenantId);

        DB::transaction(function () use ($tenantId, $data): void {
            $pattern = RosterPattern::create([
                'tenant_id' => $tenantId,
                'code' => $data['code'],
                'name' => $data['name'],
                'industry' => $data['industry'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            $this->writeSteps($pattern, $data['steps']);
        });

        return back()->with('success', 'Pola roster dibuat');
    }

    public function update(Request $request, RosterPattern $pattern): RedirectResponse
    {
        $this->authorize('create', Attendance::class);
        $this->ensureTenantOwnership($request, $pattern);

        $tenantId = $request->user()->tenant_id;
        $data = $this->validated($request, $tenantId, (int) $pattern->id);

        DB::transaction(function () use ($pattern, $data): void {
            $pattern->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'industry' => $data['industry'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            // The cycle is replaced wholesale: a step's meaning is its place in
            // the order, so patching them one by one would be a worse lie than
            // rewriting the lot.
            $pattern->steps()->delete();
            $this->writeSteps($pattern, $data['steps']);
        });

        return back()->with('success', 'Pola roster diperbarui');
    }

    public function destroy(Request $request, RosterPattern $pattern): RedirectResponse
    {
        $this->authorize('delete', Attendance::class);
        $this->ensureTenantOwnership($request, $pattern);

        $pattern->delete();

        return back()->with('success', 'Pola roster dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $patternId = null): array
    {
        $code = Rule::unique('roster_patterns', 'code')->where('tenant_id', $tenantId);

        if ($patternId !== null) {
            $code->ignore($patternId);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:50', $code],
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:active,inactive'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'steps.*.days' => ['required', 'integer', 'min:1', 'max:60'],
        ], [
            'steps.required' => 'Pola harus punya minimal satu tahap.',
            'steps.*.days.min' => 'Jumlah hari tiap tahap minimal 1.',
        ]);
    }

    /**
     * @param  array<int, array{shift_id?: int|null, days: int}>  $steps
     */
    private function writeSteps(RosterPattern $pattern, array $steps): void
    {
        foreach (array_values($steps) as $position => $step) {
            $pattern->steps()->create([
                'tenant_id' => $pattern->tenant_id,
                'position' => $position,
                'shift_id' => $step['shift_id'] ?? null,
                'days' => (int) $step['days'],
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function patternsFor(int $tenantId): array
    {
        return RosterPattern::forTenant($tenantId)
            ->with('steps.shift:id,code,name')
            ->orderBy('name')
            ->get()
            ->map(fn (RosterPattern $pattern): array => [
                'id' => $pattern->id,
                'code' => $pattern->code,
                'name' => $pattern->name,
                'industry' => $pattern->industry,
                'description' => $pattern->description,
                'status' => $pattern->status,
                'cycle_days' => $pattern->cycleDays(),
                'summary' => $pattern->summary(),
                'steps' => $pattern->steps->map(fn ($step): array => [
                    'shift_id' => $step->shift_id,
                    'shift_code' => $step->shift?->code,
                    'shift_name' => $step->shift?->name,
                    'days' => $step->days,
                ])->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shiftOptions(int $tenantId): array
    {
        return Shift::forTenant($tenantId)
            ->where('status', 'active')
            ->orderBy('start_time')
            ->get(['id', 'code', 'name', 'start_time', 'end_time'])
            ->map(fn (Shift $shift): array => [
                'id' => $shift->id,
                'code' => $shift->code,
                'name' => $shift->name,
                'start_time' => substr((string) $shift->start_time, 0, 5),
                'end_time' => substr((string) $shift->end_time, 0, 5),
            ])
            ->all();
    }

    private function ensureTenantOwnership(Request $request, RosterPattern $pattern): void
    {
        abort_if((int) $pattern->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
