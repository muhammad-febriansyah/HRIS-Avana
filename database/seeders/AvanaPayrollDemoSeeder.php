<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\OvertimeRequest;
use App\Models\PayrollComponent;
use App\Models\PkpRate;
use App\Models\Position;
use App\Models\PositionPayrollComponent;
use App\Models\PtkpRate;
use App\Models\TaxProfile;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data that exercises the attendance-linked, by-job payroll engine:
 * per-position component nominals, per-present-day & per-overtime-hour bases,
 * an overtime component, BPJS/tax profiles, plus June attendance and approved
 * overtime so a payroll run produces real gross/BPJS/PPh21/net figures.
 *
 * Kept out of {@see AvanaDemoSeeder} so the core test fixtures stay on a clean
 * payroll slate; run explicitly: `php artisan db:seed --class=AvanaPayrollDemoSeeder`.
 */
final class AvanaPayrollDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'nusantara')->first();

        if ($tenant === null) {
            return;
        }

        // Higher TER brackets so above-PTKP earners owe internal PPh 21.
        // Configurable Tarif PTKP + progressive Tarif PKP (BPR-manual PPh 21).
        $ptkp = [
            'TK/0' => 54_000_000, 'TK/1' => 58_500_000, 'TK/2' => 63_000_000, 'TK/3' => 67_500_000,
            'K/0' => 58_500_000, 'K/1' => 63_000_000, 'K/2' => 67_500_000, 'K/3' => 72_000_000,
        ];
        foreach ($ptkp as $status => $amount) {
            PtkpRate::updateOrCreate(
                ['tenant_id' => $tenant->id, 'ptkp_status' => $status, 'year' => 2026],
                ['amount' => $amount],
            );
        }
        foreach ([[60_000_000, 0.05], [250_000_000, 0.15], [500_000_000, 0.25], [5_000_000_000, 0.30], [null, 0.35]] as $i => [$upTo, $rate]) {
            PkpRate::updateOrCreate(
                ['tenant_id' => $tenant->id, 'year' => 2026, 'sort_order' => $i],
                ['up_to' => $upTo, 'rate' => $rate],
            );
        }

        // Overtime-per-hour earning component.
        PayrollComponent::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'LEMBUR'],
            ['name' => 'Uang Lembur', 'type' => 'earning', 'is_taxable' => true, 'status' => 'active'],
        );

        // Attendance calculation basis per component.
        $basisByCode = [
            'BASIC' => 'fixed', 'TJ-JAB' => 'fixed',
            'TJ-TRP' => 'per_present_day', 'TJ-MKN' => 'per_present_day',
            'LEMBUR' => 'per_overtime_hour', 'POT-KOP' => 'fixed',
        ];
        $components = PayrollComponent::where('tenant_id', $tenant->id)->get()->keyBy('code');
        foreach ($basisByCode as $code => $basis) {
            if (isset($components[$code])) {
                $components[$code]->update(['calc_basis' => $basis]);
            }
        }

        // Per-job (position) nominals: daily/hourly rates for attendance-linked
        // components, flat monthly for fixed ones.
        $positions = Position::where('tenant_id', $tenant->id)->orderBy('id')->get();
        foreach ($positions->values() as $index => $position) {
            $amounts = [
                'BASIC' => 6_000_000 + ($index * 500_000),
                'TJ-JAB' => 1_500_000,
                'TJ-TRP' => 20_000,   // per hari hadir
                'TJ-MKN' => 25_000,   // per hari hadir
                'LEMBUR' => 30_000,   // per jam lembur
                'POT-KOP' => 50_000,
            ];
            foreach ($amounts as $code => $amount) {
                if (! isset($components[$code])) {
                    continue;
                }
                PositionPayrollComponent::updateOrCreate(
                    ['position_id' => $position->id, 'payroll_component_id' => $components[$code]->id],
                    ['tenant_id' => $tenant->id, 'amount' => $amount],
                );
            }
        }

        // June present days + an approved overtime + BPJS/tax profiles for the
        // first three employees so the attendance-linked bases have values.
        $sample = Employee::where('tenant_id', $tenant->id)
            ->whereNotNull('position_id')
            ->orderBy('id')
            ->take(3)
            ->get();

        foreach ($sample as $employee) {
            for ($day = 1; $day <= 20; $day++) {
                $date = sprintf('2026-06-%02d', $day);
                if (Carbon::createFromFormat('Y-m-d', $date)->isWeekend()) {
                    continue;
                }
                Attendance::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'date' => $date],
                    ['branch_id' => $employee->branch_id, 'status' => 'present', 'clock_in_at' => $date.' 08:00:00', 'clock_out_at' => $date.' 17:00:00'],
                );
            }

            OvertimeRequest::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'date' => '2026-06-10'],
                ['branch_id' => $employee->branch_id, 'hours' => 6, 'reason' => 'Lembur tutup buku', 'status' => 'approved'],
            );

            EmployeeBpjsProfile::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $employee->id],
                [
                    'registered_wage' => 6_000_000,
                    'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
                    'jp_enabled' => true, 'kesehatan_enabled' => true,
                    'effective_start_date' => '2026-01-01',
                ],
            );

            TaxProfile::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $employee->id],
                ['ptkp_status' => 'TK/0', 'tax_method' => 'gross', 'tax_category' => 'A', 'effective_start_date' => '2026-01-01'],
            );
        }
    }
}
