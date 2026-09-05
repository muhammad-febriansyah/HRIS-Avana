<?php

use App\Imports\PayrollImportRowsImport;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryMaster;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BasicWageComponent;
use App\Support\PayrollImportLayout;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

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
    // The same invariant a computed item holds, so every screen and export
    // that subtracts total_deduction from the bruto lands on the netto.
    expect((float) $item->total_deduction)->toBe(950_000.0);
    expect((float) $item->gross_salary - (float) $item->total_deduction)->toBe((float) $item->net_salary);
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
    $basicAmount = 9_750_000;

    EmployeeSalaryComponent::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => BasicWageComponent::for($this->tenant->id)->id,
        'amount' => $basicAmount,
        'status' => 'active',
        'effective_start_date' => '2020-01-01',
    ]);

    $response = actingAs($this->admin)
        ->get(route('avana.payroll.impor.template', ['payroll_period_id' => $this->period->id]))
        ->assertOk()
        ->assertDownload();

    $sheet = Excel::toArray(
        new PayrollImportRowsImport,
        $response->baseResponse->getFile()->getPathname(),
        null,
        ExcelFormat::XLSX,
    )[0];

    expect($sheet[0])->toBe(PayrollImportLayout::headings(PayrollImportLayout::components($this->tenant->id)));

    // The employee rows are pre-filled with the Gaji Pokok their salary already
    // states, so HR corrects a figure instead of retyping every one.
    $row = collect($sheet)->firstWhere(0, $this->employee->employee_number);

    expect($row)->not->toBeNull();
    expect((float) $row[2])->toBe((float) $basicAmount);
});

it('pre-fills the template from Master Gaji for components the employee does not state', function (): void {
    $basic = BasicWageComponent::for($this->tenant->id);
    $allowance = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-JAB')->firstOrFail();
    $meal = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-MKN')->firstOrFail();

    // The employee states their own Gaji Pokok; everything else they take from
    // the master their position is on.
    EmployeeSalaryComponent::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => $basic->id,
        'amount' => 10_000_000,
        'status' => 'active',
        'effective_start_date' => '2020-01-01',
    ]);

    $master = SalaryMaster::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MASTER-IMPOR',
        'category' => 'Organik',
        'is_active' => true,
    ]);

    // A lower Gaji Pokok the employee's own figure must beat, an allowance only
    // the master states, and a per-present-day rate that is not a month's money.
    $master->components()->create(['payroll_component_id' => $basic->id, 'included' => true, 'amount' => 6_000_000]);
    $master->components()->create(['payroll_component_id' => $allowance->id, 'included' => true, 'amount' => 1_500_000]);
    $master->components()->create(['payroll_component_id' => $meal->id, 'included' => true, 'amount' => 25_000]);

    $this->employee->update(['salary_master_id' => $master->id]);

    $response = actingAs($this->admin)
        ->get(route('avana.payroll.impor.template', ['payroll_period_id' => $this->period->id]))
        ->assertOk();

    $sheet = Excel::toArray(
        new PayrollImportRowsImport,
        $response->baseResponse->getFile()->getPathname(),
        null,
        ExcelFormat::XLSX,
    )[0];

    $headings = $sheet[0];
    $row = collect($sheet)->firstWhere(0, $this->employee->employee_number);

    expect($row)->not->toBeNull();

    $at = fn (string $heading): string => (string) ($row[array_search($heading, $headings, true)] ?? '');

    // The employee's own figure wins over the master's.
    expect((float) $at('Gaji Pokok'))->toBe(10_000_000.0);
    // The allowance only the master states is filled in, not left blank.
    expect((float) $at('Tunjangan Jabatan'))->toBe(1_500_000.0);
    // A per-present-day rate is not a month's money, so nothing pre-fills it.
    expect($at('Tunjangan Makan'))->toBe('');
});

it('builds the template columns from the tenant master components', function (): void {
    // Gaji Pokok, the fixed allowances, the variable pay, then the deductions —
    // bracketed by the fixed columns.
    expect(PayrollImportLayout::headings(PayrollImportLayout::components($this->tenant->id)))->toBe([
        'nomor_karyawan', 'nama',
        'Gaji Pokok', 'Tunjangan Jabatan', 'Tunjangan Transport',
        'Tunjangan Makan',
        'Potongan Koperasi',
        'bpjs_karyawan', 'bpjs_perusahaan', 'pph21', 'take_home_pay',
    ]);

    // A tenant that adds components gets columns for them with no code change,
    // and variable pay still closes the earnings however late it was created.
    foreach ([
        ['LEMBUR', 'Uang Lembur', false],
        ['TJ-PLS', 'Tunjangan Pulsa', true],
    ] as [$code, $name, $fixed]) {
        PayrollComponent::create([
            'tenant_id' => $this->tenant->id, 'code' => $code, 'name' => $name,
            'type' => 'earning', 'component_group' => 'penerimaan', 'calc_basis' => 'fixed',
            'is_fixed' => $fixed, 'status' => 'active',
        ]);
    }

    $headings = PayrollImportLayout::headings(PayrollImportLayout::components($this->tenant->id));

    expect($headings)->toBe([
        'nomor_karyawan', 'nama',
        'Gaji Pokok', 'Tunjangan Jabatan', 'Tunjangan Transport', 'Tunjangan Pulsa',
        'Tunjangan Makan', 'Uang Lembur',
        'Potongan Koperasi',
        'bpjs_karyawan', 'bpjs_perusahaan', 'pph21', 'take_home_pay',
    ]);

    // Nothing the system derives for itself belongs in the file.
    expect($headings)->not->toContain('status_ptkp', 'kategori_ter', 'tarif_ter', 'gaji_bruto');
});

it('sums the component columns into the bruto and names them on the slip', function (): void {
    $components = PayrollImportLayout::components($this->tenant->id);
    $header = implode(',', PayrollImportLayout::headings($components));

    // Gaji Pokok 8jt, Tunjangan Jabatan 1jt, Tunjangan Transport 500rb,
    // Potongan Koperasi 200rb — everything else blank.
    $amounts = $components->map(fn (PayrollComponent $component): string => match ($component->name) {
        'Gaji Pokok' => '8.000.000',
        'Tunjangan Jabatan' => '1.000.000',
        'Tunjangan Transport' => '500.000',
        'Potongan Koperasi' => '200.000',
        default => '',
    })->implode(',');

    $body = "{$this->employee->employee_number},{$this->employee->full_name},{$amounts},400000,600000,300000,\n";

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => UploadedFile::fake()->createWithContent('payroll.csv', $header."\n".$body),
    ])->assertSessionHasNoErrors();

    $item = PayrollRunItem::where('payroll_period_id', $this->period->id)
        ->where('employee_id', $this->employee->id)
        ->firstOrFail();

    expect((float) $item->gross_salary)->toBe(9_500_000.0);
    // Everything above Gaji Pokok is allowance.
    expect((float) $item->total_allowance)->toBe(1_500_000.0);
    // Everything taken off the pay, as on a computed run: 200.000 potongan +
    // 400.000 BPJS + 300.000 PPh 21, so bruto − potongan = netto.
    expect((float) $item->total_deduction)->toBe(900_000.0);
    // 9.500.000 − 200.000 potongan − 400.000 BPJS − 300.000 PPh 21.
    expect((float) $item->net_salary)->toBe(8_600_000.0);

    $earnings = collect($item->calculation_snapshot['earnings'])->pluck('amount', 'name');

    expect($earnings->all())->toEqual([
        'Gaji Pokok' => 8_000_000,
        'Tunjangan Jabatan' => 1_000_000,
        'Tunjangan Transport' => 500_000,
    ]);

    expect(collect($item->calculation_snapshot['deductions'])->pluck('amount', 'name')->all())->toEqual([
        'Potongan Koperasi' => 200_000,
        'BPJS Karyawan' => 400_000,
        'PPh 21' => 300_000,
    ]);
});

it('reads component columns by header wherever they sit', function (): void {
    // A file whose columns are reordered and re-cased still lands correctly:
    // the header names the columns, position does not.
    $body = "nomor karyawan,PPh21,GAJI POKOK,nama,tunjangan transport\n"
        ."{$this->employee->employee_number},250000,7000000,x,300000\n";

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => UploadedFile::fake()->createWithContent('payroll.csv', $body),
    ])->assertSessionHasNoErrors();

    $item = PayrollRunItem::where('payroll_period_id', $this->period->id)->firstOrFail();

    expect((float) $item->gross_salary)->toBe(7_300_000.0);
    expect((float) $item->pph21_total)->toBe(250_000.0);
    expect((float) $item->net_salary)->toBe(7_050_000.0);
});

it('rejects a row with no earning component filled', function (): void {
    $header = implode(',', PayrollImportLayout::headings(PayrollImportLayout::components($this->tenant->id)));
    $blanks = str_repeat(',', PayrollImportLayout::components($this->tenant->id)->count());

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => UploadedFile::fake()->createWithContent(
            'payroll.csv',
            $header."\n{$this->employee->employee_number},x{$blanks}0,0,0,\n",
        ),
    ])->assertSessionHasErrors('file');

    expect(PayrollRunItem::where('payroll_period_id', $this->period->id)->count())->toBe(0);
});

it('counts a repeated component column only once', function (): void {
    // Two columns naming the same component must not add it into the bruto
    // twice — the first one wins and the second is ignored.
    $body = "nomor_karyawan,Gaji Pokok,Gaji Pokok\n{$this->employee->employee_number},6000000,6000000\n";

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => UploadedFile::fake()->createWithContent('payroll.csv', $body),
    ])->assertSessionHasNoErrors();

    expect((float) PayrollRunItem::where('payroll_period_id', $this->period->id)->firstOrFail()->gross_salary)
        ->toBe(6_000_000.0);
});

it('keeps the structural columns when a component shares their name', function (): void {
    // A component called "PPh21" must not take over the tax column: the fixed
    // columns are read before component names.
    PayrollComponent::create([
        'tenant_id' => $this->tenant->id, 'code' => 'PPH-GRS', 'name' => 'PPh21',
        'type' => 'deduction', 'component_group' => 'potongan', 'calc_basis' => 'fixed',
        'is_fixed' => false, 'status' => 'active',
    ]);

    $body = "nomor_karyawan,Gaji Pokok,pph21\n{$this->employee->employee_number},6000000,150000\n";

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => UploadedFile::fake()->createWithContent('payroll.csv', $body),
    ])->assertSessionHasNoErrors();

    $item = PayrollRunItem::where('payroll_period_id', $this->period->id)->firstOrFail();

    expect((float) $item->pph21_total)->toBe(150_000.0);
    // Read as the deduction component instead, the payslip would carry a
    // "PPh21" component line and the tax column would have stayed empty.
    expect(collect($item->calculation_snapshot['deductions'])->pluck('name')->all())->toBe(['PPh 21']);
    expect((float) $item->net_salary)->toBe(5_850_000.0);
});

it('rejects a row stating both a rolled-up bruto and its components', function (): void {
    // One of the two would have to be ignored — say so instead of quietly
    // dropping whichever lost.
    $body = "nomor_karyawan,gaji_bruto,Gaji Pokok\n{$this->employee->employee_number},9000000,9000000\n";

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => UploadedFile::fake()->createWithContent('payroll.csv', $body),
    ])->assertSessionHasErrors('file');

    expect(PayrollRunItem::where('payroll_period_id', $this->period->id)->count())->toBe(0);
});

it('rejects a non-numeric component amount', function (): void {
    $body = "nomor_karyawan,Gaji Pokok\n{$this->employee->employee_number},delapan juta\n";

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => UploadedFile::fake()->createWithContent('payroll.csv', $body),
    ])->assertSessionHasErrors('file');

    expect(PayrollRunItem::where('payroll_period_id', $this->period->id)->count())->toBe(0);
});

it('shows the uploaded figures on the payslip panel instead of recomputing them', function (): void {
    // A salary the engine would compute from, deliberately unlike the figure
    // the file states: a panel that recomputed would show this instead.
    EmployeeSalaryComponent::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => BasicWageComponent::for($this->tenant->id)->id,
        'amount' => 9_750_000,
        'status' => 'active',
        'effective_start_date' => '2020-01-01',
    ]);

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},a,4.000.000,0,0,0,0,0,\n"),
    ])->assertSessionHasNoErrors();

    $props = actingAs($this->admin)
        ->get(route('avana.payroll', ['period' => $this->period->id, 'slip_employee' => $this->employee->id]))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['slip']['employee_id'])->toBe($this->employee->id);
    // The panel must not caption uploaded figures as a live calculation.
    expect($props['slip']['source'])->toBe('import');
    expect($props['slip']['gross'])->toBe('Rp 4.000.000');
    expect($props['slip']['net'])->toBe('Rp 4.000.000');
    // The slip must not quietly read as a computed one.
    expect($props['slip']['notice'])->toContain('diunggah');
    // The lines the file named, not the components the engine would have used.
    expect(collect($props['slip']['earnings'])->pluck('k')->all())->toBe(['Gaji Bruto']);
    // The panel and the totals beside it now state the same payroll.
    expect($props['summary']['total_gross'])->toBe($props['slip']['gross']);
});

it('still computes the slip live for a period that was not uploaded', function (): void {
    EmployeeSalaryComponent::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => BasicWageComponent::for($this->tenant->id)->id,
        'amount' => 9_750_000,
        'status' => 'active',
        'effective_start_date' => '2020-01-01',
    ]);

    $props = actingAs($this->admin)
        ->get(route('avana.payroll', ['period' => $this->period->id, 'slip_employee' => $this->employee->id]))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['slip']['notice'] ?? '')->not->toContain('diunggah');
    expect($props['slip']['source'] ?? null)->not->toBe('import');
    // A computed slip annotates each line with where its number came from;
    // an imported one has no setting to point at.
    expect($props['slip']['earnings'][0])->toHaveKey('why');
});

it('refuses to upload over a period the engine already computed', function (): void {
    $run = PayrollRun::create([
        'tenant_id' => $this->tenant->id,
        'payroll_period_id' => $this->period->id,
        'revision' => 1,
        'status' => 'calculated',
        'source' => PayrollRun::SOURCE_ENGINE,
        'employee_count' => 1,
        'total_gross' => 9_750_000,
        'total_net' => 9_750_000,
    ]);

    PayrollRunItem::create([
        'tenant_id' => $this->tenant->id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $this->period->id,
        'employee_id' => $this->employee->id,
        'gross_salary' => 9_750_000,
        'taxable_gross' => 9_750_000,
        'tax_deductible_premium' => 0,
        'total_allowance' => 0,
        'total_deduction' => 0,
        'bpjs_employee_total' => 0,
        'bpjs_company_total' => 0,
        'pph21_total' => 0,
        'net_salary' => 9_750_000,
        'calculation_snapshot' => ['source' => 'engine'],
        'status' => 'calculated',
    ]);

    actingAs($this->admin)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},a,4000000,0,0,0,0,0,\n"),
    ])->assertSessionHasErrors('file');

    // The computed payroll survives untouched.
    expect($run->fresh()->source)->toBe(PayrollRun::SOURCE_ENGINE);
    expect((float) PayrollRunItem::where('payroll_run_id', $run->id)->sole()->gross_salary)->toBe(9_750_000.0);
});

it('keeps the upload away from a role without payroll rights', function (): void {
    $employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    actingAs($employeeUser)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},a,10000000,0,0,0,0,0,\n"),
    ])->assertForbidden();
});
