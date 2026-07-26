<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Services\ApprovalEngine;
use App\Services\AutoApproval;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->get(['id', 'date', 'hours', 'reason', 'status']);

        return Inertia::render('avana/saya/lembur', [
            'requests' => $requests->map(fn (OvertimeRequest $overtime): array => [
                'id' => $overtime->id,
                'date' => $this->dateString($overtime->date),
                'hours' => (float) $overtime->hours,
                'reason' => $overtime->reason,
                'status' => $overtime->status,
            ])->values(),
            'approvedHours' => (float) $requests->where('status', 'approved')->sum('hours'),
            'pendingCount' => $requests->where('status', 'pending')->count(),
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
            'hours' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'date.required' => 'Tanggal wajib diisi.',
            'hours.required' => 'Jumlah jam wajib diisi.',
            'hours.min' => 'Minimal 0,5 jam.',
            'hours.max' => 'Maksimal 12 jam.',
        ]);

        $overtime = OvertimeRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'date' => $data['date'],
            'hours' => $data['hours'],
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
