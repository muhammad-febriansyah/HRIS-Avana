<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRunItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/** Employee self-service payslips (list + detail from the run snapshot). */
class PayslipController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = PayrollRunItem::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('period:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayrollRunItem $i): array => $this->summary($i));

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, PayrollRunItem $item): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        abort_if((int) $item->tenant_id !== (int) $employee->tenant_id || (int) $item->employee_id !== (int) $employee->id, 404);

        $item->loadMissing('period:id,name');
        $snapshot = $item->calculation_snapshot ?? [];

        $lines = [];
        foreach (($snapshot['earnings'] ?? []) as $row) {
            $lines[] = ['component_code' => '', 'component_name' => $row['name'] ?? '', 'type' => 'earning', 'amount' => (int) round((float) ($row['amount'] ?? 0))];
        }
        foreach (($snapshot['deductions'] ?? []) as $row) {
            $lines[] = ['component_code' => '', 'component_name' => $row['name'] ?? '', 'type' => 'deduction', 'amount' => (int) round((float) ($row['amount'] ?? 0))];
        }

        return response()->json(['data' => [...$this->summary($item), 'lines' => $lines]]);
    }

    /**
     * Stream the employee's own payslip as a password-protected PDF (BR-11.4).
     * The password defaults to the employee's birth date (ddmmyyyy).
     */
    public function pdf(Request $request, PayrollRunItem $item): Response
    {
        $employee = $this->currentEmployee($request);

        abort_if((int) $item->tenant_id !== (int) $employee->tenant_id || (int) $item->employee_id !== (int) $employee->id, 404);

        $item->loadMissing(['employee.position', 'employee.department', 'employee.tenant', 'period']);
        $slipEmployee = $item->employee;

        abort_if($slipEmployee === null, 404);

        $snapshot = $item->calculation_snapshot ?? [];

        $html = view('pdf.payslip', [
            'company' => $slipEmployee->tenant?->company_name ?? $slipEmployee->tenant?->name ?? 'AvanaHR',
            'period' => $item->period?->name ?? '-',
            'employee' => [
                'name' => $slipEmployee->full_name,
                'number' => $slipEmployee->nik ?: $slipEmployee->employee_number,
                'position' => $slipEmployee->position?->name ?? '-',
                'department' => $slipEmployee->department?->name ?? '-',
            ],
            'earnings' => array_map(
                fn (array $row): array => ['name' => $row['name'], 'amount' => $this->rupiah($row['amount'])],
                $snapshot['earnings'] ?? [],
            ),
            'deductions' => array_map(
                fn (array $row): array => ['name' => $row['name'], 'amount' => $this->rupiah($row['amount'])],
                $snapshot['deductions'] ?? [],
            ),
            'gross' => $this->rupiah($item->gross_salary),
            'deduction' => $this->rupiah($item->total_deduction),
            'net' => $this->rupiah($item->net_salary),
        ])->render();

        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf(['tempDir' => $tempDir]);
        $mpdf->SetProtection(['print'], $this->payslipPassword($slipEmployee), '');
        $mpdf->WriteHTML($html);

        $filename = 'slip-'.$slipEmployee->employee_number.'-'.($item->period?->code ?? $item->id).'.pdf';

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function rupiah(int|float|string $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    /**
     * Derive the payslip PDF password: birth date (ddmmyyyy), else NIK/number.
     */
    private function payslipPassword(Employee $employee): string
    {
        if ($employee->birth_date !== null) {
            return $employee->birth_date->format('dmY');
        }

        return (string) ($employee->nik ?: $employee->employee_number ?: 'avanahr');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PayrollRunItem $i): array
    {
        return [
            'id' => $i->id,
            'period' => $i->period?->name,
            'gross' => (int) round((float) $i->gross_salary),
            'deductions' => (int) round((float) $i->total_deduction),
            'tax' => (int) round((float) $i->pph21_total),
            'bpjs_employee' => (int) round((float) $i->bpjs_employee_total),
            'net' => (int) round((float) $i->net_salary),
            'issued_at' => $i->created_at?->toDateString(),
        ];
    }
}
