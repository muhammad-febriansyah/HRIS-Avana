<?php

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

    $period = PayrollPeriod::create([
        'tenant_id' => $tenantId, 'code' => '2026-01', 'name' => 'Januari 2026',
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
    ]);
    $run = PayrollRun::create(['tenant_id' => $tenantId, 'payroll_period_id' => $period->id, 'status' => 'approved']);
    PayrollRunItem::create([
        'tenant_id' => $tenantId, 'payroll_run_id' => $run->id, 'payroll_period_id' => $period->id,
        'employee_id' => $this->employee->id, 'gross_salary' => 12000000, 'total_deduction' => 500000,
        'net_salary' => 11500000, 'pph21_total' => 300000, 'bpjs_employee_total' => 120000, 'status' => 'calculated',
    ]);
});

it('lists the tax years the employee can download', function (): void {
    ($this->auth)()
        ->getJson('/api/v1/me/tax-forms')
        ->assertOk()
        ->assertJsonStructure(['data' => [['year', 'title', 'subtitle']]])
        ->assertJsonFragment(['year' => 2026]);
});

it('streams the employee 1721-A1 as a pdf', function (): void {
    $res = ($this->auth)()->get('/api/v1/me/tax-forms/2026/pdf');

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->headers->get('content-disposition'))->toContain('1721-A1');
    expect(substr((string) $res->getContent(), 0, 4))->toBe('%PDF');
});

it('returns 404 for a year with no payroll data', function (): void {
    ($this->auth)()->get('/api/v1/me/tax-forms/2019/pdf')->assertNotFound();
});
