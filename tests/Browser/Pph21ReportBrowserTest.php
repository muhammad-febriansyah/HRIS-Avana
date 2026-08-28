<?php

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\TaxProfile;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $tenantId = (int) $this->admin->tenant_id;
    $employee = Employee::where('tenant_id', $tenantId)->orderBy('id')->firstOrFail();

    TaxProfile::updateOrCreate(
        ['tenant_id' => $tenantId, 'employee_id' => $employee->id],
        ['nik' => '3201010101010001', 'npwp' => '09.254.294.3-407.000', 'ptkp_status' => 'K/1'],
    );

    $period = PayrollPeriod::create([
        'tenant_id' => $tenantId,
        'code' => 'PPH-BROWSER-2026-08',
        'name' => 'Agustus 2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'pay_date' => '2026-08-25',
        'status' => 'draft',
    ]);

    $run = PayrollRun::create([
        'tenant_id' => $tenantId,
        'payroll_period_id' => $period->id,
        'status' => PayrollRun::STATUS_LOCKED,
        'total_gross' => 12_000_000,
        'total_deduction' => 500_000,
        'total_tax' => 400_000,
        'total_net' => 11_100_000,
        'employee_count' => 1,
    ]);

    PayrollRunItem::create([
        'tenant_id' => $tenantId,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'gross_salary' => 12_000_000,
        'taxable_gross' => 12_500_000,
        'total_deduction' => 500_000,
        'pph21_total' => 400_000,
        'net_salary' => 11_100_000,
        'calculation_snapshot' => [
            'tax' => [
                'method' => 'ter_bulanan',
                'ptkp_status' => 'K/1',
                'ter_category' => 'B',
                'ter_rate' => 0.032,
            ],
        ],
        'status' => 'calculated',
    ]);

    $this->employee = $employee;
});

it('renders the PPh 21 report with its KPIs, checklist and recap', function () {
    actingAs($this->admin);

    $page = visit('/avana/payroll/pph21-report?period='.PayrollPeriod::where('code', 'PPH-BROWSER-2026-08')->value('id'));

    $page->assertSee('Laporan PPh 21')
        ->assertSee('Masa Pajak Agustus 2026')
        ->assertSee('Total Karyawan')
        ->assertSee('Penghasilan Bruto (Dasar Pajak)')
        ->assertSee('PPh 21 Terutang')
        ->assertSee('PPh 21 Sudah Dipotong')
        ->assertSee('Rp 12.500.000')
        ->assertSee('Rp 400.000')
        ->assertSee('Status Kepatuhan Masa Pajak')
        ->assertSee('Pembayaran / setoran')
        ->assertSee('Pelaporan SPT Masa')
        ->assertSee('Kelengkapan Data Pajak')
        ->assertSee('Rekap PPh 21 Bulanan')
        ->assertSee('Rincian PPh 21 per Karyawan')
        ->assertSee($this->employee->full_name)
        ->assertSee('09.254.294.3-407.000')
        ->assertSee('TER Bulanan')
        ->assertNoJavascriptErrors();
});

it('records a deposit and a filing from the compliance panel', function () {
    actingAs($this->admin);

    $page = visit('/avana/payroll/pph21-report?period='.PayrollPeriod::where('code', 'PPH-BROWSER-2026-08')->value('id'));

    $page->click('[data-testid=edit-kepatuhan]')
        ->assertSee('Status Penyetoran')
        ->select('[data-testid=deposit-status]', 'done')
        ->type('[data-testid=deposit-ntpn]', 'NTPN-0001')
        ->select('[data-testid=report-status]', 'done')
        ->type('[data-testid=report-ntte]', 'NTTE-0002')
        ->click('[data-testid=simpan-kepatuhan]')
        ->assertSee('NTPN-0001')
        ->assertSee('NTTE-0002')
        ->assertSee('5 / 5 langkah')
        ->assertNoJavascriptErrors();
});
