<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EotmCoreValue;
use App\Models\EotmPeriod;
use App\Services\EotmVoting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Employee of the Month voting for the mobile app: the open period with its
 * live tally, the core values to pick from, and casting a vote.
 */
class EotmController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Everything the voting screen needs in one call: the period, whether the
     * caller has voted, the core values, and the current standings.
     */
    public function show(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $voting = app(EotmVoting::class);

        // Fall back to the most recent period so a closed month still shows its
        // winner rather than an empty screen.
        $period = $voting->openPeriod((int) $employee->tenant_id)
            ?? EotmPeriod::forTenant($employee->tenant_id)->latest('period')->first();

        if ($period === null) {
            return response()->json([
                'data' => [
                    'period' => null,
                    'my_vote' => null,
                    'core_values' => [],
                    'standings' => [],
                ],
            ]);
        }

        $myVote = $voting->voteOf($period, $employee);

        return response()->json([
            'data' => [
                'period' => [
                    'id' => $period->id,
                    'period' => $period->period,
                    'label' => $period->label(),
                    'title' => $period->title,
                    'description' => $period->description,
                    'status' => $period->status,
                    'is_open' => $period->isOpen(),
                    'closes_at' => $period->closes_at?->toDateTimeString(),
                    'winner' => $period->winner?->full_name,
                    'winner_votes' => $period->winner_votes,
                    'total_votes' => $period->votes()->count(),
                ],
                'my_vote' => $myVote === null ? null : [
                    'nominee_employee_id' => $myVote->nominee_employee_id,
                    'nominee' => $myVote->nominee?->full_name,
                    'core_value_id' => $myVote->eotm_core_value_id,
                    'core_value' => $myVote->coreValue?->name,
                    'reason' => $myVote->reason,
                ],
                'core_values' => EotmCoreValue::forTenant($employee->tenant_id)
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'icon', 'color']),
                'standings' => $voting->standings($period)
                    ->map(function (array $row): array {
                        $row['photo'] = $row['photo'] !== null
                            ? Storage::disk('public')->url($row['photo'])
                            : null;

                        return $row;
                    })
                    ->all(),
            ],
        ]);
    }

    /**
     * Colleagues this employee may vote for — themselves excluded, since a
     * self-vote is rejected anyway.
     */
    public function nominees(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $search = trim((string) $request->string('search'));

        $data = Employee::forTenant($employee->tenant_id)
            ->where('status', 'active')
            ->where('id', '!=', $employee->id)
            ->when(
                $search !== '',
                fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")),
            )
            ->orderBy('full_name')
            ->take(30)
            ->get(['id', 'full_name', 'employee_number', 'photo_path'])
            ->map(fn (Employee $row): array => [
                'id' => $row->id,
                'name' => $row->full_name,
                'employee_number' => $row->employee_number,
                'photo' => $row->photo_path !== null
                    ? Storage::disk('public')->url($row->photo_path)
                    : null,
            ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Cast (or change) this employee's vote for the open period.
     */
    public function vote(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $voting = app(EotmVoting::class);

        $period = $voting->openPeriod((int) $employee->tenant_id);

        abort_if($period === null, 404, 'Belum ada periode voting yang dibuka.');

        $data = $request->validate([
            'nominee_employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $employee->tenant_id),
            ],
            'eotm_core_value_id' => [
                'nullable',
                'integer',
                Rule::exists('eotm_core_values', 'id')->where('tenant_id', $employee->tenant_id),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'nominee_employee_id.required' => 'Pilih dulu karyawan yang kamu vote.',
        ]);

        $nominee = Employee::forTenant($employee->tenant_id)->findOrFail($data['nominee_employee_id']);

        $voting->vote(
            $period,
            $employee,
            $nominee,
            $data['eotm_core_value_id'] ?? null,
            $data['reason'] ?? null,
        );

        return response()->json([
            'message' => 'Vote kamu tersimpan',
            'data' => [
                'standings' => $voting->standings($period)->all(),
                'total_votes' => $period->votes()->count(),
            ],
        ]);
    }
}
