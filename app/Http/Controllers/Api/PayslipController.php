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

        $items = PayrollRunItem::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('period:id,name,code')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayrollRunItem $i): array => [
                'id' => $i->id,
                'period' => $i->period?->name,
                'gross' => (float) $i->gross_salary,
                'deduction' => (float) $i->total_deduction,
                'net' => (float) $i->net_salary,
                'status' => $i->status,
            ]);

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, PayrollRunItem $item): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        abort_if((int) $item->tenant_id !== (int) $employee->tenant_id || (int) $item->employee_id !== (int) $employee->id, 404);

        $item->loadMissing('period:id,name,code');
        $snapshot = $item->calculation_snapshot ?? [];

        return response()->json([
            'data' => [
                'id' => $item->id,
                'period' => $item->period?->name,
                'earnings' => $snapshot['earnings'] ?? [],
                'deductions' => $snapshot['deductions'] ?? [],
                'gross' => (float) $item->gross_salary,
                'deduction' => (float) $item->total_deduction,
                'net' => (float) $item->net_salary,
                'bpjs_employee' => (float) $item->bpjs_employee_total,
                'pph21' => (float) $item->pph21_total,
            ],
        ]);
    }
}
