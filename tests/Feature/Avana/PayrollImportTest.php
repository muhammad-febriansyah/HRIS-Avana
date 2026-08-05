<?php

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();

    $this->period = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MN-2026-05', 'name' => 'Mei 2026',
        'cycle' => 'monthly', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31',
        'status' => 'draft',
    ]);
});

/** A CSV upload in the template's column order. */
function payrollCsv(string $body): UploadedFile
{
    $header = "nomor_karyawan,nama,gaji_bruto,tunjangan,potongan,bpjs_karyawan,bpjs_perusahaan,pph21,take_home_pay\n";

    return UploadedFile::fake()->createWithContent('payroll.csv', $header.$body);
}

it('writes the uploaded numbers into the period run', function (): void {
    $response = actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv(
            "{$this->employee->employee_number},{$this->employee->full_name},10.000.000,1.000.000,250.000,400.000,600.000,300.000,\n",
        ),
    ]);

    $response->assertSessionHasNoErrors();

    $item = PayrollRunItem::where('payroll_period_id', $this->period->id)
        ->where('employee_id', $this->employee->id)
        ->firstOrFail();

    expect((float) $item->gross_salary)->toBe(10_000_000.0);
    expect((float) $item->total_allowance)->toBe(1_000_000.0);
    expect((float) $item->pph21_total)->toBe(300_000.0);
    // Take-home left blank: bruto − potongan − BPJS karyawan − PPh 21.
    expect((float) $item->net_salary)->toBe(9_050_000.0);
    expect($item->calculation_snapshot['source'])->toBe('import');

    $run = PayrollRun::where('payroll_period_id', $this->period->id)->firstOrFail();

    expect((float) $run->total_gross)->toBe(10_000_000.0);
    expect((float) $run->total_net)->toBe(9_050_000.0);
    expect($run->employee_count)->toBe(1);
    expect($run->status)->toBe('calculated');
});

it('keeps an explicit take home pay instead of deriving one', function (): void {
    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},x,10000000,0,0,0,0,0,7500000\n"),
    ])->assertSessionHasNoErrors();

    $item = PayrollRunItem::where('payroll_period_id', $this->period->id)->firstOrFail();

    expect((float) $item->net_salary)->toBe(7_500_000.0);
});

it('replaces the period run on a second upload', function (): void {
    $second = Employee::forTenant($this->tenant->id)
        ->where('id', '!=', $this->employee->id)
        ->orderBy('id')
        ->firstOrFail();

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv(
            "{$this->employee->employee_number},a,10000000,0,0,0,0,0,\n"
            ."{$second->employee_number},b,8000000,0,0,0,0,0,\n",
        ),
    ])->assertSessionHasNoErrors();

    expect(PayrollRunItem::where('payroll_period_id', $this->period->id)->count())->toBe(2);

    // The second file drops an employee — the run must drop them too.
    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},a,12000000,0,0,0,0,0,\n"),
    ])->assertSessionHasNoErrors();

    $items = PayrollRunItem::where('payroll_period_id', $this->period->id)->get();

    expect($items)->toHaveCount(1);
    expect((float) $items->first()->gross_salary)->toBe(12_000_000.0);
});

it('rejects the whole file when a row is unusable', function (): void {
    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv(
            "{$this->employee->employee_number},a,10000000,0,0,0,0,0,\n"
            ."TIDAK-ADA,b,8000000,0,0,0,0,0,\n",
        ),
    ])->assertSessionHasErrors('file');

    expect(PayrollRunItem::where('payroll_period_id', $this->period->id)->count())->toBe(0);
});

it('rejects a non-numeric amount', function (): void {
    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},a,sepuluh juta,0,0,0,0,0,\n"),
    ])->assertSessionHasErrors('file');

    expect(PayrollRunItem::where('payroll_period_id', $this->period->id)->count())->toBe(0);
});

it('refuses to overwrite a locked period', function (): void {
    $this->period->update(['status' => 'locked']);

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},a,10000000,0,0,0,0,0,\n"),
    ])->assertSessionHasErrors('file');

    expect(PayrollRunItem::where('payroll_period_id', $this->period->id)->count())->toBe(0);
});

it('serves a template listing the period employees', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.impor.template', ['payroll_period_id' => $this->period->id]))
        ->assertOk()
        ->assertDownload();
});

it('keeps the upload away from a role without payroll rights', function (): void {
    $employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    actingAs($employeeUser)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},a,10000000,0,0,0,0,0,\n"),
    ])->assertForbidden();
});
