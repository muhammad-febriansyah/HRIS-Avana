<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use App\Services\ApprovalEngine;
use App\Support\RequestDateClash;
use Closure;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Izin Saya" — short absences (sakit, keperluan pribadi, dinas luar) the
 * employee files for themselves.
 */
class EssPermissionController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Selectable izin types, mirroring the mobile app's picker.
     *
     * @var array<int, array<string, string>>
     */
    private const TYPES = [
        ['value' => 'sakit', 'label' => 'Sakit'],
        ['value' => 'pribadi', 'label' => 'Keperluan Pribadi'],
        ['value' => 'dinas', 'label' => 'Dinas Luar'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    /**
     * List the employee's izin requests, newest first.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $requests = PermissionRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'start_date', 'end_date', 'type', 'start_time', 'end_time', 'reason', 'status']);

        return Inertia::render('avana/saya/izin', [
            'requests' => $requests->map(fn (PermissionRequest $permission): array => [
                'id' => $permission->id,
                'start_date' => $this->dateString($permission->start_date),
                'end_date' => $this->dateString($permission->end_date),
                'type' => $permission->type,
                'start_time' => $this->shortTime($permission->start_time),
                'end_time' => $this->shortTime($permission->end_time),
                'reason' => $permission->reason,
                'status' => $permission->status,
            ])->values(),
            'types' => self::TYPES,
        ]);
    }

    /**
     * Submit an izin request for the signed-in employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', 'string', 'max:50'],
            // Times narrow a single-day izin to part of that day. A multi-day
            // izin covers whole days, so a time there is rejected rather than
            // stored and silently ignored.
            'start_time' => ['nullable', 'date_format:H:i', $this->singleDayOnly($request)],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time', $this->singleDayOnly($request)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'type.required' => 'Jenis izin wajib dipilih.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $clash = RequestDateClash::check(
            (int) $employee->tenant_id,
            (int) $employee->id,
            $data['start_date'],
            $data['end_date'],
        );

        if ($clash !== null) {
            return back()->withErrors(['start_date' => $clash]);
        }

        $permission = PermissionRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'type' => $data['type'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'reason' => $data['reason'] ?? null,
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        ApprovalEngine::start($permission, $employee);

        return back()->with('success', 'Pengajuan izin terkirim');
    }

    /**
     * Reject a clock time unless the izin sits on one single day.
     */
    private function singleDayOnly(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request): void {
            if ($request->input('start_date') !== $request->input('end_date')) {
                $fail('Jam hanya berlaku untuk izin satu hari.');
            }
        };
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
