<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Services\ApprovalEngine;
use App\Services\AutoApproval;
use App\Support\OvertimeRules;
use App\Support\OvertimeWindow;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Lembur Saya" — the employee's own overtime requests.
 */
class EssOvertimeController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * List the employee's overtime requests, newest first.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $requests = OvertimeRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['id', 'date', 'day_type', 'start_time', 'end_time', 'hours', 'reason', 'status']);

        $policy = OvertimeRules::policyFor((int) $employee->tenant_id);

        return Inertia::render('avana/saya/lembur', [
            'requests' => $requests->map(fn (OvertimeRequest $overtime): array => [
                'id' => $overtime->id,
                'date' => $this->dateString($overtime->date),
                'day_type' => $overtime->day_type,
                'day_type_label' => OvertimeRules::DAY_TYPES[OvertimeRules::normaliseDayType($overtime->day_type)],
                'hours' => (float) $overtime->hours,
                'time_range' => OvertimeWindow::label($overtime->start_time, $overtime->end_time),
                'reason' => $overtime->reason,
                'status' => $overtime->status,
            ])->values(),
            'approvedHours' => (float) $requests->where('status', 'approved')->sum('hours'),
            'pendingCount' => $requests->where('status', 'pending')->count(),
            'dayTypes' => OvertimeRules::dayTypeOptions(),
            'limits' => [
                'per_day' => (float) $policy->max_hours_per_day,
                'per_week' => (float) $policy->max_hours_per_week,
                'enforced' => (bool) $policy->enforce_hour_limits,
            ],
        ]);
    }

    /**
     * Submit an overtime request for the signed-in employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'day_type' => ['nullable', Rule::in(array_keys(OvertimeRules::DAY_TYPES))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'date.required' => 'Tanggal wajib diisi.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'end_time.required' => 'Jam selesai wajib diisi.',
        ]);

        // Hours are derived, never typed: the range is what an approver checks,
        // so payroll must agree with it by construction.

        if (! OvertimeWindow::isPlausible($data['start_time'], $data['end_time'])) {
            throw ValidationException::withMessages([
                'end_time' => sprintf(
                    'Durasi lembur harus antara %s dan %s jam.',
                    OvertimeWindow::MIN_HOURS,
                    OvertimeWindow::MAX_HOURS,
                ),
            ]);
        }

        $hours = OvertimeWindow::hoursBetween($data['start_time'], $data['end_time']);
        $date = Carbon::parse($data['date']);

        // PP 35/2021 caps overtime at 4 hours a day and 18 a week.
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

        // A top approver (director) has nobody above them, so their own request
        // is settled on the spot rather than left waiting.
        if ($employee->is_top_approver) {
            AutoApproval::overtime($overtime);

            return back()->with('success', 'Pengajuan lembur langsung disetujui (approver puncak)');
        }

        ApprovalEngine::start($overtime, $employee);

        return back()->with('success', 'Pengajuan lembur terkirim');
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
