<?php

use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\PayrollComponent;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the tax basis and every BPJS programme as its own payslip line', function () {
    $this->seed(AvanaDemoSeeder::class);
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $employee = Employee::forTenant($admin->tenant_id)
        ->whereNotNull('position_id')
        ->orderBy('id')
        ->firstOrFail();

    // A wage high enough to fall in a TER bracket above 0%.
    $basic = PayrollComponent::forTenant($admin->tenant_id)->where('code', 'BASIC')->firstOrFail();
    giveMasterComponent($employee, $basic, 8_000_000);

    EmployeeBpjsProfile::create([
        'tenant_id' => $admin->tenant_id,
        'employee_id' => $employee->id,
        'registered_wage' => 8_000_000,
        'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
        'jp_enabled' => true, 'kesehatan_enabled' => true,
        'effective_start_date' => '2026-01-01',
    ]);

    actingAs($admin);

    visit('/avana/payroll?slip_employee='.$employee->id)
        ->assertSee('Dasar Pajak')
        ->assertSee('Bruto Pajak')
        ->assertSee('Tarif TER')
        ->assertSee('JHT (Karyawan)')
        ->assertSee('JP (Karyawan)')
        ->assertSee('BPJS Kesehatan (Karyawan)')
        ->assertDontSee('BPJS (Karyawan)')
        ->assertNoJavascriptErrors();
});
