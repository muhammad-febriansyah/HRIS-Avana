<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\PayrollRunItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
