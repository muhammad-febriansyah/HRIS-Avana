<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\DutyTravel;
use DateTimeInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Perjalanan Dinas Saya" — the trips assigned to the employee. Arranging and
 * approving them is an HR/manager action on /avana/dinas, so this is read-only.
 */
class EssTravelController extends Controller
{
    use ResolvesApiEmployee;

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
     * The employee's own trips, newest first.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $travels = DutyTravel::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

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
            'summary' => [
                'total' => $rows->count(),
                'approved' => $rows->whereIn('status', ['approved', 'ongoing', 'completed'])->count(),
                'pending' => $rows->where('status', 'pending')->count(),
                'total_per_diem' => (int) $rows
                    ->whereIn('status', ['approved', 'ongoing', 'completed'])
                    ->sum('per_diem'),
            ],
        ]);
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
