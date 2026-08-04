<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\PayrollRunItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Slip Gaji Saya" — the employee's own payslips. The PDF itself is still
 * served by the mobile endpoint (`/api/v1/me/payslips/{item}/pdf`), which this
 * screen links out to.
 */
class EssPayslipController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Every payslip issued to the signed-in employee.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $items = PayrollRunItem::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('period:id,name')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('avana/saya/slip-gaji', [
            'payslips' => $items->map(fn (PayrollRunItem $item): array => $this->summary($item))->values(),
        ]);
    }

    /**
     * A single payslip with its earning/deduction breakdown.
     */
    public function show(Request $request, PayrollRunItem $item): Response
    {
        $employee = $this->currentEmployee($request);

        abort_if(
            (int) $item->tenant_id !== (int) $employee->tenant_id
            || (int) $item->employee_id !== (int) $employee->id,
            404,
        );

        $item->loadMissing('period:id,name');
        $snapshot = $item->calculation_snapshot ?? [];

        return Inertia::render('avana/saya/slip-gaji-detail', [
            'payslip' => [
                // Deliberately *_lines: summary() already carries a numeric
                // `deductions` total, and reusing that key would overwrite it.
                ...$this->summary($item),
                'earning_lines' => $this->lines($snapshot['earnings'] ?? []),
                'deduction_lines' => $this->lines($snapshot['deductions'] ?? []),
            ],
        ]);
    }

    /**
     * The list/header shape shared by index and show.
     *
     * @return array<string, mixed>
     */
    private function summary(PayrollRunItem $item): array
    {
        return [
            'id' => $item->id,
            'route_key' => $item->public_id,
            'period' => $item->period?->name,
            'gross' => (int) round((float) $item->gross_salary),
            'deductions' => (int) round((float) $item->total_deduction),
            'tax' => (int) round((float) $item->pph21_total),
            'bpjs_employee' => (int) round((float) $item->bpjs_employee_total),
            'net' => (int) round((float) $item->net_salary),
            'issued_at' => $item->created_at?->toDateString(),
        ];
    }

    /**
     * Normalise a snapshot section into name/amount rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [
            'name' => $row['name'] ?? '',
            'amount' => (int) round((float) ($row['amount'] ?? 0)),
        ], $rows));
    }
}
