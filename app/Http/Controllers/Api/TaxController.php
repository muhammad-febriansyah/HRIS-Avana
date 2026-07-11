<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Support\TaxForm1721;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Employee self-service annual tax forms (Bukti Potong 1721-A1). Reuses the
 * same generator as the web download, scoped to the authenticated employee.
 */
class TaxController extends Controller
{
    use ResolvesApiEmployee;

    /** The tax years the employee can download a 1721-A1 for. */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $years = TaxForm1721::availableYears($employee);

        return response()->json([
            'data' => array_map(fn (int $year): array => [
                'year' => $year,
                'title' => 'Bukti Potong 1721-A1',
                'subtitle' => 'Masa Pajak '.$year,
            ], $years),
        ]);
    }

    /** Stream the employee's own 1721-A1 for a year as a PDF. */
    public function pdf(Request $request, int $year): Response
    {
        $employee = $this->currentEmployee($request);

        abort_unless(in_array($year, TaxForm1721::availableYears($employee), true), 404);

        $pdf = Pdf::loadView('pdf.bukti-potong-1721', TaxForm1721::viewData($employee, $year))
            ->setPaper('a4');

        return $pdf->download('1721-A1-'.$employee->employee_number.'-'.$year.'.pdf');
    }
}
