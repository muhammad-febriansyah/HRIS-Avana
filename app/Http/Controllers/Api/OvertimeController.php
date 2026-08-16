<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Services\ApprovalEngine;
use App\Services\AutoApproval;
use App\Support\FeatureGate;
use App\Support\OvertimeRules;
use App\Support\OvertimeWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Employee self-service overtime requests. */
class OvertimeController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        FeatureGate::ensure($request->user(), 'overtime', 'Fitur lembur tidak aktif untuk perusahaan Anda.');

        $employee = $this->currentEmployee($request);

        $data = OvertimeRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('date')
            ->get(['id', 'date', 'day_type', 'start_time', 'end_time', 'hours', 'reason', 'status'])
            ->map(fn (OvertimeRequest $o): array => [
                'id' => $o->id,
                'date' => $o->date instanceof Carbon ? $o->date->toDateString() : $o->date,
                'day_type' => $o->day_type,
                'day_type_label' => OvertimeRules::DAY_TYPES[OvertimeRules::normaliseDayType($o->day_type)],
                'hours' => (float) $o->hours,
                'start_time' => $o->start_time !== null ? substr($o->start_time, 0, 5) : null,
                'end_time' => $o->end_time !== null ? substr($o->end_time, 0, 5) : null,
                'time_range' => OvertimeWindow::label($o->start_time, $o->end_time),
                'reason' => $o->reason,
                'status' => $o->status,
            ]);

        return response()->json([
            'data' => $data,
            // The phone previews a duration before it files one, so it needs the
            // same rounding rule the server will apply — otherwise it shows an
            // employee 0,75 jam for a stretch that pays half an hour.
            'policy' => $this->policyPayload((int) $employee->tenant_id),
        ]);
    }

    /**
     * The tenant's overtime shaping rules, as the app needs them.
     *
     * @return array{rounding_minutes: int, min_hours: float, max_hours: float}
     */
    private function policyPayload(int $tenantId): array
    {
        $rounding = (int) OvertimeRules::policyFor($tenantId)->rounding_minutes;

        return [
            'rounding_minutes' => $rounding,
            // Below this a filing is worth nothing, so the app can say so before
            // the server has to.
            'min_hours' => $rounding > 0
                ? round($rounding / 60, 2)
                : OvertimeWindow::MIN_HOURS,
            'max_hours' => OvertimeWindow::MAX_HOURS,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        FeatureGate::ensure($request->user(), 'overtime', 'Fitur lembur tidak aktif untuk perusahaan Anda.');

        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'day_type' => ['nullable', Rule::in(array_keys(OvertimeRules::DAY_TYPES))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Hours are derived, never taken from the client: the range is what the
        // employee filed and what an approver checks, so payroll must agree
        // with it by construction.
        // Rounded by the tenant's own rule, so an employee's own filing and the
        // HR desk's arrive at the same payable hours.
        $payable = OvertimeRules::payableHours(
            (int) $employee->tenant_id,
            OvertimeWindow::hoursBetween($data['start_time'], $data['end_time']),
        );

        if ($payable['error'] !== null) {
            throw ValidationException::withMessages(['end_time' => $payable['error']]);
        }

        $hours = $payable['hours'];
        $date = Carbon::parse($data['date']);

        $overlap = OvertimeRules::overlapViolation(
            (int) $employee->tenant_id,
            (int) $employee->id,
            $date,
            $data['start_time'],
            $data['end_time'],
        );

        if ($overlap !== null) {
            throw ValidationException::withMessages(['start_time' => $overlap]);
        }

        // PP 35/2021 caps overtime at 4 hours a day and 18 a week; the mobile
        // client files against the same ceilings the web does.
        $violation = OvertimeRules::limitViolation(
            (int) $employee->tenant_id,
            (int) $employee->id,
            $date,
            $hours,
        );

        if ($violation !== null) {
            throw ValidationException::withMessages(['end_time' => $violation]);
        }

        $overtime = OvertimeRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'date' => $data['date'],
            'day_type' => OvertimeRules::normaliseDayType($data['day_type'] ?? OvertimeRules::suggestDayType($date)),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'hours' => $hours,
            'reason' => $data['reason'] ?? null,
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        // A top approver (director) has no manager above them, so their own
        // request is approved on the spot rather than left waiting.
        if ($employee->is_top_approver) {
            AutoApproval::overtime($overtime);

            return response()->json([
                'message' => 'Pengajuan lembur langsung disetujui (approver puncak)',
                'data' => ['id' => $overtime->id, 'status' => 'approved'],
            ], 201);
        }

        // Route through the configured approval workflow when one is active.
        ApprovalEngine::start($overtime, $employee);

        return response()->json(['message' => 'Pengajuan lembur terkirim', 'data' => ['id' => $overtime->id]], 201);
    }
}
