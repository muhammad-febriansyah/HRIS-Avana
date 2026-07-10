<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\DayCalcMethod;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Payday;
use App\Models\PayrollComponent;
use App\Models\PayrollComponentValue;
use App\Models\PayrollFormula;
use App\Models\PkpRate;
use App\Models\Position;
use App\Models\PtkpRate;
use App\Models\SalaryGrade;
use App\Models\SalaryGradeStep;
use App\Models\SalaryMaster;
use App\Models\SalesOrder;
use App\Models\Shift;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\UmrRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo data that exercises the attendance-linked, Master-Gaji payroll engine:
 * one Master Gaji template per position carrying its component nominals (fixed,
 * per-present-day & per-overtime-hour), BPJS/tax profiles, plus June attendance
 * and approved overtime so a payroll run produces real gross/BPJS/PPh21/net.
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
            ['name' => 'Uang Lembur', 'type' => 'earning', 'component_group' => 'penerimaan', 'is_taxable' => true, 'status' => 'active'],
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

        $positions = Position::where('tenant_id', $tenant->id)->orderBy('id')->get();

        // The manual-basis components (Tabel + Formula) and the named day-calc
        // methods a Master Gaji can pick — created before the templates so they
        // can be added to each master's checklist.
        $hariKerja = $this->seedManualKomponen($tenant, $positions);

        // Re-read components now that the Tabel/Formula ones (TJ-KES, TJ-KIN) exist.
        $components = PayrollComponent::where('tenant_id', $tenant->id)->get()->keyBy('code');

        // One Master Gaji per position, carrying that position's component
        // nominals, then attached to every employee holding the position.
        foreach ($positions->values() as $index => $position) {
            $master = $this->buildPositionMaster($tenant, $position, $index, $components, $hariKerja);

            Employee::where('tenant_id', $tenant->id)
                ->where('position_id', $position->id)
                ->update(['salary_master_id' => $master->id]);
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

        $this->seedWageScaleAndPaydays($tenant, $sample);
        $this->seedSalesOrders($tenant);
    }

    /**
     * A few demo Sales Orders (BPR manual 1.4) — three unmapped "new" orders plus
     * one already mapped to the MG-ORG master, a shift and a leave type.
     */
    private function seedSalesOrders(Tenant $tenant): void
    {
        $master = SalaryMaster::forTenant($tenant->id)->where('code', 'MG-ORG')->first();
        $shift = Shift::forTenant($tenant->id)->first();
        $leave = LeaveType::forTenant($tenant->id)->first();

        $orders = [
            ['SO-2026-001', 'PT Bank Central Niaga', 'Teller', 5, 'new'],
            ['SO-2026-002', 'PT Astra Finance', 'Customer Service', 3, 'new'],
            ['SO-2026-003', 'PT Telkom Akses', 'Teknisi Lapangan', 10, 'new'],
            ['SO-2026-004', 'PT Pegadaian', 'Security', 8, 'mapped'],
        ];

        foreach ($orders as [$code, $client, $position, $headcount, $status]) {
            SalesOrder::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'client_name' => $client,
                    'position_name' => $position,
                    'headcount' => $headcount,
                    'status' => $status,
                    'contract_start' => '2026-08-01',
                    'contract_end' => '2027-07-31',
                    'salary_master_id' => $status === 'mapped' ? $master?->id : null,
                    'shift_id' => $status === 'mapped' ? $shift?->id : null,
                    'leave_type_id' => $status === 'mapped' ? $leave?->id : null,
                    'mapped_at' => $status === 'mapped' ? now() : null,
                ],
            );
        }
    }

    /**
     * Create/update a Master Gaji for one position and populate its component
     * checklist with monthly nominals. The first position keeps the well-known
     * "MG-ORG" code so existing fixtures resolve; the rest are per-position.
     *
     * @param  Collection<string, PayrollComponent>  $components
     */
    private function buildPositionMaster(Tenant $tenant, Position $position, int $index, Collection $components, DayCalcMethod $hariKerja): SalaryMaster
    {
        $code = $index === 0 ? 'MG-ORG' : 'MG-'.strtoupper((string) ($position->code ?? $position->id));
        $category = $index === 0 ? 'Organik' : $position->name;

        $master = SalaryMaster::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $code],
            [
                'category' => $category,
                'note' => 'Template gaji '.$position->name,
                'is_active' => true,
                'process_type' => 'normal',
                'period_start_day' => 25, 'period_end_day' => 24,
                'cut_off_day' => 15, 'day_divisor' => 22,
                'day_calc_method' => 'hari_kerja', 'day_calc_method_id' => $hariKerja->id,
                'overtime_calc_method' => 'reguler',
            ],
        );

        // Monthly nominals per component (fixed monthly, per-hadir, per-jam).
        // is_prorate stays off so fixed lines are prorated only by mid-period
        // join/resign, matching the previous per-position behaviour.
        $lines = [
            'BASIC' => ['amount' => 6_000_000 + ($index * 500_000)],
            'TJ-JAB' => ['amount' => 1_500_000],
            'TJ-TRP' => ['amount' => 20_000],
            'TJ-MKN' => ['amount' => 25_000],
            'LEMBUR' => ['amount' => 30_000, 'is_overtime_base' => true],
            'POT-KOP' => ['amount' => 50_000],
            // Manual-basis lines resolve their own nominal (Tabel / Formula).
            'TJ-KES' => ['amount' => 0],
            'TJ-KIN' => ['amount' => 0],
        ];

        foreach ($lines as $componentCode => $line) {
            if (! isset($components[$componentCode])) {
                continue;
            }

            $master->components()->updateOrCreate(
                ['payroll_component_id' => $components[$componentCode]->id],
                [
                    'included' => true,
                    'amount' => $line['amount'],
                    'is_prorate' => false,
                    'is_overtime_base' => $line['is_overtime_base'] ?? false,
                    'is_kompensasi' => false,
                ],
            );
        }

        return $master;
    }

    /**
     * BPR-manual "Komponen" demo: a Tabel-based component with a Nilai Komponen
     * mapping, a Formula-based component, and the named day-calc methods. Returns
     * the default Hari Kerja method so each Master Gaji can reference it.
     *
     * @param  Collection<int, Position>  $positions
     */
    private function seedManualKomponen(Tenant $tenant, Collection $positions): DayCalcMethod
    {
        // Tabel: nominal resolved from the Nilai Komponen mapping.
        $kesehatan = PayrollComponent::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'TJ-KES'],
            [
                'name' => 'Tunjangan Kesehatan', 'type' => 'earning', 'component_group' => 'penerimaan',
                'is_taxable' => true, 'status' => 'active', 'calc_basis' => 'fixed', 'basis_type' => 'tabel',
            ],
        );

        // Generic value for anyone, plus a richer value for the first position.
        PayrollComponentValue::updateOrCreate(
            ['tenant_id' => $tenant->id, 'payroll_component_id' => $kesehatan->id, 'position_id' => null],
            ['value' => 200_000, 'note' => 'Default semua pegawai'],
        );
        if ($positions->isNotEmpty()) {
            PayrollComponentValue::updateOrCreate(
                ['tenant_id' => $tenant->id, 'payroll_component_id' => $kesehatan->id, 'position_id' => $positions->first()->id],
                ['value' => 350_000, 'note' => 'Posisi tertentu'],
            );
        }

        // Formula: Tunjangan Kinerja = 10% x Gaji Pokok (BASIC).
        $basic = PayrollComponent::where('tenant_id', $tenant->id)->where('code', 'BASIC')->first();
        $formula = PayrollFormula::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Kinerja 10% Gaji Pokok'],
            ['note' => '10% dari Gaji Pokok', 'is_active' => true],
        );
        if ($basic !== null && $formula->items()->count() === 0) {
            $formula->items()->create([
                'tipe' => 'penerimaan', 'payroll_component_id' => $basic->id,
                'operator' => '*', 'nilai' => 0.10, 'prorate' => false, 'sort_order' => 1,
            ]);
        }
        PayrollComponent::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'TJ-KIN'],
            [
                'name' => 'Tunjangan Kinerja', 'type' => 'earning', 'component_group' => 'penerimaan',
                'is_taxable' => true, 'status' => 'active', 'calc_basis' => 'fixed',
                'basis_type' => 'formula', 'payroll_formula_id' => $formula->id,
            ],
        );

        // Named day-calculation methods the tenant can pick per Master Gaji.
        $hariKerja = DayCalcMethod::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'HK-22'],
            [
                'name' => 'Hari Kerja 22', 'basis' => 'hari_kerja', 'divisor' => 22,
                'description' => 'Prorata berdasar 22 hari kerja', 'is_active' => true,
            ],
        );
        DayCalcMethod::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'HK-25'],
            [
                'name' => 'Hari Kalender 25', 'basis' => 'hari_kalender', 'divisor' => 25,
                'description' => 'Prorata berdasar 25 hari kalender', 'is_active' => true,
            ],
        );
        DayCalcMethod::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'ABSEN'],
            [
                'name' => 'Berdasar Absen', 'basis' => 'absen', 'divisor' => null,
                'description' => 'Prorata mengikuti jumlah hari hadir', 'is_active' => true,
            ],
        );

        // UMR per branch + a tenant-wide default (2026 DKI-ish figures).
        $year = 2026;
        UmrRate::updateOrCreate(
            ['tenant_id' => $tenant->id, 'branch_id' => null, 'year' => $year],
            ['region' => 'Default', 'amount' => 4_900_000, 'note' => 'UMR default tenant'],
        );
        foreach (Branch::where('tenant_id', $tenant->id)->get() as $branch) {
            $amount = match (true) {
                str_contains(strtolower($branch->name), 'jakarta') => 5_396_761,
                str_contains(strtolower($branch->name), 'bandung') => 4_209_309,
                str_contains(strtolower($branch->name), 'surabaya') => 4_725_479,
                default => 4_900_000,
            };
            UmrRate::updateOrCreate(
                ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'year' => $year],
                ['region' => $branch->name, 'amount' => $amount],
            );
        }

        return $hariKerja;
    }

    /**
     * Salary grades + their per-masa-kerja wage nominals ("Nilai Upah") and a
     * couple of payday schedules with the sample employees mapped to one.
     *
     * @param  Collection<int, Employee>  $sample
     */
    private function seedWageScaleAndPaydays(Tenant $tenant, Collection $sample): void
    {
        // Grade bands (Struktur & Skala Upah) + exact nominal per step (Nilai Upah).
        $grades = [
            ['grade_code' => 'G1', 'grade_name' => 'Golongan I', 'level' => 1, 'min' => 4_500_000, 'max' => 6_000_000, 'base' => 4_500_000],
            ['grade_code' => 'G2', 'grade_name' => 'Golongan II', 'level' => 2, 'min' => 6_000_000, 'max' => 9_000_000, 'base' => 6_000_000],
            ['grade_code' => 'G3', 'grade_name' => 'Golongan III', 'level' => 3, 'min' => 9_000_000, 'max' => 14_000_000, 'base' => 9_000_000],
        ];

        foreach ($grades as $g) {
            $grade = SalaryGrade::updateOrCreate(
                ['tenant_id' => $tenant->id, 'grade_code' => $g['grade_code']],
                [
                    'grade_name' => $g['grade_name'], 'level' => $g['level'],
                    'min_salary' => $g['min'], 'mid_salary' => ($g['min'] + $g['max']) / 2, 'max_salary' => $g['max'],
                ],
            );

            // Steps at 0/2/4/6 years, +5% compounding per step from the grade base.
            foreach ([0, 2, 4, 6] as $step) {
                $amount = (int) round($g['base'] * (1.05 ** ($step / 2)));
                SalaryGradeStep::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'salary_grade_id' => $grade->id, 'masa_kerja' => $step],
                    ['amount' => $amount, 'note' => $step === 0 ? 'Awal masuk' : "Masa kerja {$step} thn"],
                );
            }
        }

        // Payday schedules: staff paid on the 25th, daily/harian on the 1st.
        $staff = Payday::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'PD-STAFF'],
            ['name' => 'Staff Bulanan', 'pay_day' => 25, 'cut_off_day' => 20, 'description' => 'Gaji staf tetap', 'is_active' => true],
        );
        Payday::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'PD-HARIAN'],
            ['name' => 'Harian Tgl 1', 'pay_day' => 1, 'cut_off_day' => 25, 'description' => 'Pekerja harian', 'is_active' => true],
        );

        Employee::where('tenant_id', $tenant->id)
            ->whereIn('id', $sample->pluck('id'))
            ->update(['payday_id' => $staff->id]);
    }
}
