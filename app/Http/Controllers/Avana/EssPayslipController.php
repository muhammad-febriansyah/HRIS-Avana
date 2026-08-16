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

        // Only finalised runs: a payslip from a run still being reviewed is a
        // figure the employee may not be paid.
        $items = PayrollRunItem::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->published()
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

        $item->loadMissing('run:id,status');

        abort_if(
            (int) $item->tenant_id !== (int) $employee->tenant_id
            || (int) $item->employee_id !== (int) $employee->id
            || ! $item->isPublished(),
            404,
        );

        $item->loadMissing('period:id,name');
        $snapshot = $item->calculation_snapshot ?? [];

        return Inertia::render('avana/saya/slip-gaji-detail', [
            'payslip' => [
                // Deliberately *_lines: summary() already carries a numeric
                // `deductions` total, and reusing that key would overwrite it.
                ...$this->summary($item),
                // A negative deduction is money coming back — the annual PPh 21
                // refund — so it reads as an earning on the slip instead of a
                // deduction with a minus sign in front of it.
                'earning_lines' => $this->lines([
                    ...($snapshot['earnings'] ?? []),
                    ...$this->refunds($snapshot['deductions'] ?? []),
                ]),
                'deduction_lines' => $this->lines(array_filter(
                    $snapshot['deductions'] ?? [],
                    fn (array $row): bool => (float) ($row['amount'] ?? 0) >= 0,
                )),
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
    /**
     * Deduction rows that are actually refunds, flipped to positive.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function refunds(array $rows): array
    {
        return array_values(array_map(
            fn (array $row): array => [...$row, 'amount' => abs((float) ($row['amount'] ?? 0))],
            array_filter($rows, fn (array $row): bool => (float) ($row['amount'] ?? 0) < 0),
        ));
    }

    private function lines(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [
            'name' => $row['name'] ?? '',
            'amount' => (int) round((float) ($row['amount'] ?? 0)),
        ], $rows));
    }
}
