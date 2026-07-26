<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\EmployeeContract;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Kontrak Saya" — the employee's own employment contracts, read-only. Issuing
 * and amending them stays with HR on /avana/kontrak.
 */
class EssContractController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the contract status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'active' => 'Aktif',
        'expired' => 'Berakhir',
        'terminated' => 'Diputus',
    ];

    /**
     * A contract inside this many days of its end date is flagged as expiring.
     */
    private const EXPIRING_WINDOW_DAYS = 30;

    /**
     * The employee's contract history, newest first.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);
        $today = Carbon::today();

        $contracts = EmployeeContract::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $rows = $contracts->map(fn (EmployeeContract $contract): array => $this->shape($contract, $today));

        return Inertia::render('avana/saya/kontrak', [
            'contracts' => $rows->values(),
            // The one the employee is working under right now, surfaced on top.
            'active' => $rows->firstWhere('status', 'active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(EmployeeContract $contract, Carbon $today): array
    {
        $daysToExpiry = $contract->end_date !== null
            ? (int) round($today->diffInDays($contract->end_date, false))
            : null;

        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'contract_type' => $contract->contract_type,
            'start_date' => $this->dateString($contract->start_date),
            'end_date' => $this->dateString($contract->end_date),
            'basic_salary' => (int) round((float) $contract->basic_salary),
            'status' => $contract->status,
            'status_label' => self::STATUS_LABELS[$contract->status] ?? $contract->status,
            'notes' => $contract->notes,
            'days_to_expiry' => $daysToExpiry,
            'expiring_soon' => $contract->status === 'active'
                && $daysToExpiry !== null
                && $daysToExpiry >= 0
                && $daysToExpiry <= self::EXPIRING_WINDOW_DAYS,
        ];
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
