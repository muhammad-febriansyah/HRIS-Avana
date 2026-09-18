<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRosterPlanRequest;
use App\Models\Attendance;
use App\Models\RosterPlan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class RosterPlanController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreRosterPlanRequest $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $validated = $request->validated();
        $tenantId = $request->user()->tenant_id;

        DB::transaction(function () use ($request, $validated, $tenantId): void {
            $plan = RosterPlan::forTenant($tenantId)
                ->where('employee_id', $validated['employee_id'])
                ->whereDate('period_start', $validated['period_start'])
                ->whereDate('period_end', $validated['period_end'])
                ->where('status', RosterPlan::STATUS_DRAFT)
                ->first();

            if ($plan === null) {
                $plan = new RosterPlan([
                    'tenant_id' => $tenantId,
                    'employee_id' => $validated['employee_id'],
                    'period_start' => $validated['period_start'],
                    'period_end' => $validated['period_end'],
                    'status' => RosterPlan::STATUS_DRAFT,
                ]);
            }

            $plan->fill([
                'created_by' => $request->user()->id,
                'name' => $validated['name'] ?? null,
                'shift_label' => $validated['shift_label'] ?? null,
            ]);
            $plan->save();

            $plan->days()->delete();
            $plan->days()->createMany($validated['days']);
        });

        return back()->with(
            'success',
            'Draft plan roster disimpan. Draft ini belum memengaruhi absensi atau payroll.',
        );
    }
}
