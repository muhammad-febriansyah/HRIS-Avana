<?php

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $tenantId = $this->employee->tenant_id;

    $period = PayrollPeriod::create(['tenant_id' => $tenantId, 'code' => '2026-07', 'name' => 'Juli 2026']);
    // Locked: an employee only ever reaches a payslip whose run is final.
    $run = PayrollRun::create(['tenant_id' => $tenantId, 'payroll_period_id' => $period->id, 'status' => PayrollRun::STATUS_LOCKED]);
    $this->run = $run;

    $make = fn (int $employeeId): PayrollRunItem => PayrollRunItem::create([
        'tenant_id' => $tenantId,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employeeId,
        'gross_salary' => 10000000,
        'total_deduction' => 500000,
        'net_salary' => 9500000,
        'pph21_total' => 200000,
        'bpjs_employee_total' => 100000,
        'status' => 'calculated',
        'calculation_snapshot' => [
            'earnings' => [['name' => 'Gaji Pokok', 'amount' => 10000000]],
            'deductions' => [['name' => 'BPJS Kesehatan', 'amount' => 500000]],
        ],
    ]);

    $this->item = $make($this->employee->id);

    $other = Employee::forTenant($tenantId)->where('id', '!=', $this->employee->id)->firstOrFail();
    $this->foreignItem = $make($other->id);
});

it('streams the employee payslip as a pdf', function (): void {
    $res = ($this->auth)()->get('/api/v1/me/payslips/'.$this->item->id.'/pdf');

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->headers->get('content-disposition'))->toContain('.pdf');
    expect(substr((string) $res->getContent(), 0, 4))->toBe('%PDF');
});

it('rejects downloading another employees payslip', function (): void {
    ($this->auth)()->get('/api/v1/me/payslips/'.$this->foreignItem->id.'/pdf')->assertNotFound();
});

it('hides payslips from a run that is not locked yet', function (): void {
    foreach ([PayrollRun::STATUS_CALCULATED, PayrollRun::STATUS_APPROVED, 'draft'] as $status) {
        $this->run->update(['status' => $status]);

        ($this->auth)()->getJson('/api/v1/me/payslips')->assertOk()->assertJsonCount(0, 'data');
        ($this->auth)()->getJson('/api/v1/me/payslips/'.$this->item->id)->assertNotFound();
        ($this->auth)()->get('/api/v1/me/payslips/'.$this->item->id.'/pdf')->assertNotFound();
    }
});

it('shows the payslip once the run is locked', function (): void {
    $this->run->update(['status' => PayrollRun::STATUS_APPROVED]);
    ($this->auth)()->getJson('/api/v1/me/payslips')->assertOk()->assertJsonCount(0, 'data');

    $this->run->update(['status' => PayrollRun::STATUS_LOCKED]);

    ($this->auth)()->getJson('/api/v1/me/payslips')->assertOk()->assertJsonCount(1, 'data');
    ($this->auth)()->getJson('/api/v1/me/payslips/'.$this->item->id)->assertOk();
    ($this->auth)()->get('/api/v1/me/payslips/'.$this->item->id.'/pdf')->assertOk();
});
