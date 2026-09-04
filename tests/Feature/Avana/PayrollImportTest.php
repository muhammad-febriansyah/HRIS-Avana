<?php

use App\Imports\PayrollImportRowsImport;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
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

it('builds the template columns from the tenant master components', function (): void {
    $headings = PayrollImportLayout::headings(PayrollImportLayout::components($this->tenant->id));

    // Gaji Pokok opens the component block, deductions close it, and the fixed
    // columns bracket the lot.
    expect(array_slice($headings, 0, 3))->toBe(['nomor_karyawan', 'nama', 'Gaji Pokok']);
    expect(array_slice($headings, -4))->toBe(['bpjs_karyawan', 'bpjs_perusahaan', 'pph21', 'take_home_pay']);
    expect($headings)->toContain('Tunjangan Transport', 'Potongan Koperasi');
    expect(array_search('Potongan Koperasi', $headings, true))
        ->toBeGreaterThan(array_search('Tunjangan Kinerja', $headings, true));

    // Nothing the system derives for itself belongs in the file.
    expect($headings)->not->toContain('status_ptkp', 'kategori_ter', 'tarif_ter', 'gaji_bruto');

    // A tenant that adds a component gets a column for it, with no code change.
    PayrollComponent::create([
        'tenant_id' => $this->tenant->id, 'code' => 'TJ-PLS', 'name' => 'Tunjangan Pulsa',
        'type' => 'earning', 'component_group' => 'penerimaan', 'calc_basis' => 'fixed',
        'is_fixed' => true, 'status' => 'active',
    ]);

    expect(PayrollImportLayout::headings(PayrollImportLayout::components($this->tenant->id)))
        ->toContain('Tunjangan Pulsa')
        ->toHaveCount(count($headings) + 1);
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
    expect((float) $item->total_deduction)->toBe(200_000.0);
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
    // Read as a deduction component instead, it would have landed here.
    expect((float) $item->total_deduction)->toBe(0.0);
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

it('keeps the upload away from a role without payroll rights', function (): void {
    $employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    actingAs($employeeUser)->post(route('avana.payroll.impor.store'), [
        'payroll_period_id' => $this->period->id,
        'file' => payrollCsv("{$this->employee->employee_number},a,10000000,0,0,0,0,0,\n"),
    ])->assertForbidden();
});
