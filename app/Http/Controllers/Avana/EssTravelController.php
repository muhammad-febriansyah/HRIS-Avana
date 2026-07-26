<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\DutyTravel;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Perjalanan Dinas Saya" — the employee's own trips, and the form they file a
 * new one with. Approving it stays with HR/their manager on /avana/dinas, as
 * does setting the per diem: an employee does not price their own allowance.
 */
class EssTravelController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Statuses the filter offers, alongside "semua".
     *
     * @var array<int, string>
     */
    private const FILTERABLE_STATUSES = ['pending', 'approved', 'rejected', 'completed'];

    /**
     * Indonesian labels for the duty travel status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'draft' => 'Draft',
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'ongoing' => 'Berjalan',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    /**
     * The employee's own trips, newest first, optionally filtered by status.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $status = $request->query('status');
        $status = in_array($status, self::FILTERABLE_STATUSES, true) ? $status : null;

        $travels = DutyTravel::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        // Totals describe the whole history, not the filtered slice — otherwise
        // picking a status quietly rewrites the figures above the table.
        $all = DutyTravel::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->get(['status', 'per_diem']);

        $rows = $travels->map(fn (DutyTravel $travel): array => [
            'id' => $travel->id,
            'destination' => $travel->destination,
            'purpose' => $travel->purpose,
            'transport' => $travel->transport,
            'start_date' => $this->dateString($travel->start_date),
            'end_date' => $this->dateString($travel->end_date),
            'estimated_cost' => (int) round((float) $travel->estimated_cost),
            'per_diem' => (int) round((float) $travel->per_diem),
            'status' => $travel->status,
            'status_label' => self::STATUS_LABELS[$travel->status] ?? $travel->status,
            'notes' => $travel->notes,
        ]);

        return Inertia::render('avana/saya/perjalanan-dinas', [
            'travels' => $rows->values(),
            'status' => $status,
            'statusOptions' => collect(self::FILTERABLE_STATUSES)
                ->map(fn (string $value): array => [
                    'value' => $value,
                    'label' => self::STATUS_LABELS[$value] ?? $value,
                ])->all(),
            'summary' => [
                'total' => $all->count(),
                'approved' => $all->whereIn('status', ['approved', 'ongoing', 'completed'])->count(),
                'pending' => $all->where('status', 'pending')->count(),
                'total_per_diem' => (int) $all
                    ->whereIn('status', ['approved', 'ongoing', 'completed'])
                    ->sum('per_diem'),
            ],
        ]);
    }

    /**
     * The submission form, on its own page rather than folded into the list.
     */
    public function create(Request $request): Response
    {
        $this->currentEmployee($request);

        return Inertia::render('avana/saya/perjalanan-dinas-ajukan');
    }

    /**
     * File a new trip for the signed-in employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'transport' => ['nullable', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
        ], [
            'destination.required' => 'Tujuan wajib diisi.',
            'start_date.required' => 'Tanggal berangkat wajib diisi.',
            'end_date.required' => 'Tanggal kembali wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal berangkat.',
            'estimated_cost.numeric' => 'Estimasi biaya harus berupa angka.',
            'estimated_cost.min' => 'Estimasi biaya tidak boleh negatif.',
        ]);

        DutyTravel::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'destination' => $data['destination'],
            'purpose' => $data['purpose'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'transport' => $data['transport'] ?? null,
            'estimated_cost' => $data['estimated_cost'] ?? null,
            // per_diem is deliberately left unset: the allowance is decided by
            // whoever approves the trip, not by the person taking it.
            'status' => 'pending',
        ]);

        return redirect()
            ->route('avana.saya.perjalanan-dinas')
            ->with('success', 'Pengajuan perjalanan dinas terkirim');
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
