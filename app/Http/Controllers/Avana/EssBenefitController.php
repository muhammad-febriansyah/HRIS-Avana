<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\EmployeeBenefit;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Benefit Saya" — the benefits assigned to the employee. Assignment is an HR
 * action, so this is read-only.
 */
class EssBenefitController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the assignment status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'expired' => 'Berakhir',
        'ended' => 'Berakhir',
    ];

    /**
     * Indonesian labels for the benefit type enum, which is stored in English.
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'allowance' => 'Tunjangan',
        'insurance' => 'Asuransi',
        'facility' => 'Fasilitas',
        'bonus' => 'Bonus',
        'other' => 'Lainnya',
    ];

    /**
     * Every benefit assigned to the signed-in employee.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);
        $today = Carbon::today();

        $assignments = EmployeeBenefit::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('benefit:id,code,name,type,value,description')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $rows = $assignments->map(function (EmployeeBenefit $assignment) use ($today): array {
            $endDate = $assignment->end_date;

            return [
                'id' => $assignment->id,
                'name' => $assignment->benefit?->name,
                'code' => $assignment->benefit?->code,
                'type' => $this->typeLabel($assignment->benefit?->type),
                'value' => (int) round((float) ($assignment->benefit?->value ?? 0)),
                'description' => $assignment->benefit?->description,
                'start_date' => $this->dateString($assignment->start_date),
                'end_date' => $this->dateString($endDate),
                'status' => $assignment->status,
                'status_label' => self::STATUS_LABELS[$assignment->status] ?? $assignment->status,
                'notes' => $assignment->notes,
                // Still marked active while its window has closed — worth
                // showing plainly rather than as a bare "Aktif".
                'is_running' => $assignment->status === 'active'
                    && ($endDate === null || Carbon::parse($endDate)->gte($today)),
            ];
        });

        return Inertia::render('avana/saya/benefit', [
            'benefits' => $rows->values(),
            'summary' => [
                'total' => $rows->count(),
                'running' => $rows->where('is_running', true)->count(),
                'total_value' => (int) $rows->where('is_running', true)->sum('value'),
            ],
        ]);
    }

    /**
     * The Indonesian label for a benefit type, falling back to the raw value
     * for anything a tenant has added itself.
     */
    private function typeLabel(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return self::TYPE_LABELS[$type] ?? $type;
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
