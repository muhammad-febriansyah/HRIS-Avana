<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Services\ApprovalEngine;
use App\Support\AttendanceCorrectionTimes;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Absensi Saya" and "Koreksi Absensi" — the employee's own attendance history
 * plus the correction requests they raise against it.
 *
 * Clocking in/out stays on the mobile app: it needs GPS, face capture and
 * device integrity, none of which the browser flow provides.
 */
class EssAttendanceController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the attendance status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'absent' => 'Tidak Hadir',
        'leave' => 'Cuti',
        'permit' => 'Izin',
        'sick' => 'Sakit',
        'holiday' => 'Libur',
        'wfh' => 'WFH',
    ];

    /**
     * The employee's attendance for a month, with a summary of that month.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $month = $this->resolveMonth($request);

        $records = Attendance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderByDesc('date')
            ->get();

        return Inertia::render('avana/saya/absensi', [
            'month' => $month->format('Y-m'),
            'records' => $records->map(fn (Attendance $record): array => [
                'id' => $record->id,
                'date' => $this->dateString($record->date),
                'clock_in' => $this->clockTime($record->clock_in_at),
                'clock_out' => $this->clockTime($record->clock_out_at),
                'late_minutes' => (int) $record->late_minutes,
                'work_minutes' => (int) $record->work_minutes,
                'status' => $record->status,
                'status_label' => self::STATUS_LABELS[$record->status] ?? $record->status,
                'work_mode' => $record->work_mode,
            ])->values(),
            'summary' => [
                'present' => $records->whereIn('status', ['present', 'late'])->count(),
                'late' => $records->where('status', 'late')->count(),
                'absent' => $records->where('status', 'absent')->count(),
                'leave' => $records->whereIn('status', ['leave', 'permit', 'sick'])->count(),
                'work_hours' => round($records->sum('work_minutes') / 60, 1),
            ],
        ]);
    }

    /**
     * The employee's correction requests, newest first.
     */
    public function corrections(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $corrections = AttendanceCorrection::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('avana/saya/koreksi-absensi', [
            'corrections' => $corrections->map(fn (AttendanceCorrection $correction): array => [
                'id' => $correction->id,
                'date' => $this->dateString($correction->date),
                'requested_clock_in' => $this->shortTime($correction->requested_clock_in),
                'requested_clock_out' => $this->shortTime($correction->requested_clock_out),
                'reason' => $correction->reason,
                'status' => $correction->status,
            ])->values(),
        ]);
    }

    /**
     * Raise a correction request against one of the employee's own days.
     */
    public function storeCorrection(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'requested_clock_in' => ['nullable', 'required_without:requested_clock_out', 'date_format:H:i'],
            'requested_clock_out' => ['nullable', 'required_without:requested_clock_in', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'date.required' => 'Tanggal wajib diisi.',
            'date.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
            'requested_clock_in.required_without' => 'Isi minimal jam masuk atau jam pulang.',
            'requested_clock_out.required_without' => 'Isi minimal jam masuk atau jam pulang.',
            'requested_clock_in.date_format' => 'Format jam masuk harus HH:MM.',
            'requested_clock_out.date_format' => 'Format jam pulang harus HH:MM.',
            'reason.required' => 'Alasan wajib diisi.',
        ]);

        if (! AttendanceCorrectionTimes::rangeIsValid(
            $employee,
            $data['date'],
            $data['requested_clock_in'] ?? null,
            $data['requested_clock_out'] ?? null,
        )) {
            return back()->withErrors(['requested_clock_out' => 'Jam pulang harus setelah jam masuk.']);
        }

        $attendance = Attendance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $data['date'])
            ->first();

        $correction = AttendanceCorrection::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'attendance_id' => $attendance?->id,
            'date' => $data['date'],
            'correction_type' => 'manual',
            'requested_clock_in' => $data['requested_clock_in'] ?? null,
            'requested_clock_out' => $data['requested_clock_out'] ?? null,
            'reason' => $data['reason'],
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        ApprovalEngine::start($correction, $employee);

        return back()->with('success', 'Pengajuan koreksi absen terkirim');
    }

    /**
     * The month being viewed, defaulting to the current one.
     *
     * Typed as CarbonInterface, not Illuminate\Support\Carbon: the app runs
     * Date::use(CarbonImmutable::class), which does not extend that class.
     */
    private function resolveMonth(Request $request): CarbonInterface
    {
        $month = $request->query('month');

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    /**
     * HH:MM for a clock timestamp, or null when the employee never punched.
     */
    private function clockTime(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format('H:i') : null;
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }

    /**
     * Trim a stored H:i:s time down to H:i.
     */
    private function shortTime(?string $time): ?string
    {
        return ($time === null || $time === '') ? null : substr($time, 0, 5);
    }
}
