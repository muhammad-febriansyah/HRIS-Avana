<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\PayrollRunItem;
use App\Models\PkpRate;
use App\Models\PtkpRate;

/**
 * Computes the annual PPh 21 recap (Formulir 1721-A1) for an employee from
 * their payroll-run items, and builds the data array consumed by the
 * `pdf.bukti-potong-1721` view. Single source of truth for both the web
 * download and the mobile API.
 */
final class TaxForm1721
{
    /**
     * Tax years (descending) the employee has payroll items for — the set the
     * mobile app offers for download.
     *
     * @return array<int, int>
     */
    public static function availableYears(Employee $employee): array
    {
        return PayrollRunItem::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('period:id,start_date')
            ->get(['id', 'tenant_id', 'employee_id', 'payroll_period_id'])
            ->map(fn (PayrollRunItem $item): int => (int) substr((string) $item->period?->start_date, 0, 4))
            ->filter(fn (int $year): bool => $year > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * Build the blade data for the given employee + tax year.
     *
     * @return array{company: string, year: int, employee: array<string, string>, rows: array<int, array{0: string, 1: string}>}
     */
    public static function viewData(Employee $employee, int $year): array
    {
        $tenantId = (int) $employee->tenant_id;

        $items = PayrollRunItem::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->whereHas('period', fn ($query) => $query->whereYear('start_date', $year))
            ->get(['gross_salary', 'pph21_total', 'bpjs_employee_total']);

        $employee->loadMissing(['position', 'tenant', 'taxProfile']);

        $annualGross = (float) $items->sum('gross_salary');
        $withheld = (float) $items->sum('pph21_total');
        $biayaJabatan = min($annualGross * 0.05, 6_000_000);
        $ptkpStatus = $employee->taxProfile?->ptkp_status;
        $ptkp = self::ptkpFor($ptkpStatus, $tenantId, $year);
        $pkp = max(0.0, floor(($annualGross - $biayaJabatan - $ptkp) / 1000) * 1000);
        $annualTax = self::progressiveTax($pkp, $tenantId, $year);

        return [
            'company' => $employee->tenant?->company_name ?? $employee->tenant?->name ?? 'AvanaHR',
            'year' => $year,
            'employee' => [
                'name' => $employee->full_name,
                'nik' => $employee->nik ?: '-',
                'number' => $employee->employee_number,
                'position' => $employee->position?->name ?? '-',
                'ptkp' => $ptkpStatus ?? 'TK/0',
            ],
            'rows' => [
                ['Penghasilan Bruto Setahun', self::rupiah($annualGross)],
                ['Pengurangan — Biaya Jabatan', self::rupiah($biayaJabatan)],
                ['Penghasilan Neto', self::rupiah($annualGross - $biayaJabatan)],
                ['PTKP ('.($ptkpStatus ?? 'TK/0').')', self::rupiah($ptkp)],
                ['Penghasilan Kena Pajak (PKP)', self::rupiah($pkp)],
                ['PPh 21 Terutang Setahun', self::rupiah($annualTax)],
                ['PPh 21 Telah Dipotong', self::rupiah($withheld)],
            ],
        ];
    }

    /**
     * Resolve the tenant's configured PTKP amount for a status/year, falling
     * back to the UU HPP base (54jt + 4.5jt married + 4.5jt per dependent ≤ 3).
     */
    private static function ptkpFor(?string $status, int $tenantId, int $year): float
    {
        $status = $status !== null && $status !== '' ? strtoupper(trim($status)) : 'TK/0';

        $configured = PtkpRate::forTenant($tenantId)
            ->where('ptkp_status', $status)
            ->where('year', $year)
            ->value('amount');

        if ($configured !== null) {
            return (float) $configured;
        }

        $base = 54_000_000.0;
        $married = str_starts_with($status, 'K');
        $dependents = preg_match('/(\d+)/', $status, $matches) ? min((int) $matches[1], 3) : 0;

        return $base + ($married ? 4_500_000 : 0) + 4_500_000 * $dependents;
    }

    /**
     * Progressive annual income tax on taxable income (PKP). Reads the tenant's
     * configurable Tarif PKP brackets, falling back to UU HPP Pasal 17.
     */
    private static function progressiveTax(float $pkp, int $tenantId, int $year): float
    {
        $configured = PkpRate::forTenant($tenantId)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('up_to')
            ->get(['up_to', 'rate']);

        $brackets = $configured->isNotEmpty()
            ? $configured->map(fn (PkpRate $r): array => [
                $r->up_to !== null ? (float) $r->up_to : null,
                (float) $r->rate,
            ])->all()
            : [
                [60_000_000.0, 0.05],
                [250_000_000.0, 0.15],
                [500_000_000.0, 0.25],
                [5_000_000_000.0, 0.30],
                [null, 0.35],
            ];

        $tax = 0.0;
        $lower = 0.0;

        foreach ($brackets as [$upper, $rate]) {
            if ($pkp <= $lower) {
                break;
            }

            $ceiling = $upper ?? $pkp;
            $slice = min($pkp, $ceiling) - $lower;

            if ($slice > 0) {
                $tax += $slice * $rate;
            }

            $lower = $ceiling;
        }

        return round($tax);
    }

    private static function rupiah(int|float|string $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}
