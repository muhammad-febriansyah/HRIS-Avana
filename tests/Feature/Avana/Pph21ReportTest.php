<?php

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\TaxPeriodCompliance;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TaxForm1721;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * A period with one live run carrying two payslips: one employee whose tax
 * profile is complete, one whose PTKP was never filled in.
 *
 * @return array{period: PayrollPeriod, run: PayrollRun, complete: Employee, incomplete: Employee}
 */
function seedPph21Period(Tenant $tenant, string $status = 'calculated'): array
{
    $employees = Employee::where('tenant_id', $tenant->id)->orderBy('id')->take(2)->get();
    [$complete, $incomplete] = [$employees[0], $employees[1]];

    TaxProfile::updateOrCreate(
        ['tenant_id' => $tenant->id, 'employee_id' => $complete->id],
        ['nik' => '3201010101010001', 'npwp' => '09.254.294.3-407.000', 'ptkp_status' => 'K/1'],
    );

    TaxProfile::updateOrCreate(
        ['tenant_id' => $tenant->id, 'employee_id' => $incomplete->id],
        ['nik' => null, 'npwp' => null, 'ptkp_status' => null],
    );

    $incomplete->forceFill(['nik' => null])->save();

    $period = PayrollPeriod::create([
        'tenant_id' => $tenant->id,
        'code' => 'PPH-TEST-2026-08',
        'name' => 'Agustus 2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'pay_date' => '2026-08-25',
        'status' => 'draft',
    ]);

    $run = PayrollRun::create([
        'tenant_id' => $tenant->id,
        'payroll_period_id' => $period->id,
        'status' => $status,
        'total_gross' => 20_000_000,
        'total_deduction' => 800_000,
        'total_tax' => 500_000,
        'total_net' => 18_700_000,
        'employee_count' => 2,
    ]);

    PayrollRunItem::create([
        'tenant_id' => $tenant->id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $complete->id,
        'gross_salary' => 12_000_000,
        'taxable_gross' => 12_500_000,
        'tax_deductible_premium' => 300_000,
        'total_deduction' => 500_000,
        'pph21_total' => 400_000,
        'net_salary' => 11_100_000,
        'calculation_snapshot' => [
            'tax' => [
                'method' => 'ter_bulanan',
                'ptkp_status' => 'K/1',
                'ter_category' => 'B',
                'ter_rate' => 0.032,
                'base' => 12_500_000,
            ],
        ],
        'status' => 'calculated',
    ]);

    PayrollRunItem::create([
        'tenant_id' => $tenant->id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $incomplete->id,
        'gross_salary' => 8_000_000,
        // Left at zero on purpose: a run closed before taxable_gross existed
        // must fall back to the payslip gross rather than report nothing.
        'taxable_gross' => 0,
        'total_deduction' => 300_000,
        'pph21_total' => 100_000,
        'net_salary' => 7_600_000,
        'calculation_snapshot' => [
            'tax' => ['method' => 'ter_bulanan', 'ter_category' => 'A', 'ter_rate' => 0.0125],
        ],
        'status' => 'calculated',
    ]);

    return ['period' => $period, 'run' => $run, 'complete' => $complete, 'incomplete' => $incomplete];
}

it('renders the PPh 21 report with the period totals from the payroll run', function (): void {
    $seeded = seedPph21Period($this->tenant);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/pph21-report/index', false)
            ->where('summary.period', 'Agustus 2026')
            ->where('summary.employee_count', 2)
            // 12.500.000 taxable + 8.000.000 fallback gross.
            ->where('summary.gross', 'Rp 20.500.000')
            ->where('summary.tax_due', 'Rp 500.000')
            ->etc());
});

it('counts tax as withheld only once the run is approved', function (): void {
    $seeded = seedPph21Period($this->tenant);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.tax_withheld', 'Rp 0')
            ->where('summary.withheld_pct', 0)
            ->etc());

    $seeded['run']->update(['status' => PayrollRun::STATUS_APPROVED]);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.tax_withheld', 'Rp 500.000')
            ->where('summary.withheld_pct', 100)
            ->etc());
});

it('excludes a superseded re-run so a recomputed month is not counted twice', function (): void {
    $seeded = seedPph21Period($this->tenant);

    $seeded['run']->update(['superseded_at' => now()]);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.employee_count', 0)
            ->where('summary.tax_due', 'Rp 0')
            ->etc());
});

it('reports the employees whose tax data is incomplete', function (): void {
    $seeded = seedPph21Period($this->tenant);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($seeded) {
            $page->where('completeness.issue_total', 1)->etc();

            $issues = $page->toArray()['props']['completeness']['issues'];

            expect($issues)->toHaveCount(1);
            expect($issues[0]['employee_id'])->toBe($seeded['incomplete']->id);
            expect($issues[0]['missing'])->toBe(['NIK', 'NPWP', 'Status PTKP']);
        });
});

it('counts a bukti potong as ready only for a resolvable PTKP status', function (): void {
    $seeded = seedPph21Period($this->tenant);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $steps = collect($page->toArray()['props']['compliance']['steps'])->keyBy('key');

            expect($steps['bukti_potong']['detail'])->toBe('1 / 2 siap terbit');
            expect($steps['bukti_potong']['state'])->toBe('warn');
        });
});

it('treats an unrecognised PTKP status as missing rather than valid', function (): void {
    $seeded = seedPph21Period($this->tenant);

    // "K/O" is the letter O — it stores happily and silently falls back to
    // Kategori A, which is exactly the class of typo this screen must surface.
    TaxProfile::where('tenant_id', $this->tenant->id)
        ->where('employee_id', $seeded['incomplete']->id)
        ->update(['ptkp_status' => 'K/O', 'nik' => '1', 'npwp' => '2']);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $issues = $page->toArray()['props']['completeness']['issues'];

            expect($issues)->toHaveCount(1);
            expect($issues[0]['missing'])->toBe(['PTKP tidak dikenal']);
        });
});

it('records the deposit and filing receipts for a period', function (): void {
    $seeded = seedPph21Period($this->tenant);

    actingAs($this->admin)
        ->post('/avana/payroll/pph21-report/kepatuhan', [
            'payroll_period_id' => $seeded['period']->id,
            'deposit_status' => 'done',
            'deposit_date' => '2026-09-10',
            'deposit_ntpn' => 'NTPN-12345678',
            'report_status' => 'done',
            'report_date' => '2026-09-20',
            'report_ntte' => 'NTTE-87654321',
            'note' => 'Disetor via bank persepsi.',
        ])
        ->assertRedirect();

    $record = TaxPeriodCompliance::where('payroll_period_id', $seeded['period']->id)->firstOrFail();

    expect($record->deposit_status)->toBe('done');
    expect($record->deposit_ntpn)->toBe('NTPN-12345678');
    expect($record->report_ntte)->toBe('NTTE-87654321');
    expect($record->updated_by)->toBe($this->admin->id);
});

it('refuses to mark a period reported before it is deposited', function (): void {
    $seeded = seedPph21Period($this->tenant);

    actingAs($this->admin)
        ->post('/avana/payroll/pph21-report/kepatuhan', [
            'payroll_period_id' => $seeded['period']->id,
            'deposit_status' => 'pending',
            'report_status' => 'done',
        ])
        ->assertSessionHasErrors('report_status');

    expect(TaxPeriodCompliance::where('payroll_period_id', $seeded['period']->id)->exists())->toBeFalse();
});

it('cannot record compliance for another tenant period', function (): void {
    $otherTenant = Tenant::create([
        'name' => 'PT Tenant Lain',
        'slug' => 'pt-tenant-lain',
        'status' => 'active',
    ]);

    $foreign = PayrollPeriod::create([
        'tenant_id' => $otherTenant->id,
        'code' => 'PPH-FOREIGN-2026-08',
        'name' => 'Agustus 2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'pay_date' => '2026-08-25',
        'status' => 'draft',
    ]);

    actingAs($this->admin)
        ->post('/avana/payroll/pph21-report/kepatuhan', [
            'payroll_period_id' => $foreign->id,
            'deposit_status' => 'done',
            'report_status' => 'pending',
        ])
        ->assertSessionHasErrors('payroll_period_id');
});

it('streams the per-employee PPh 21 CSV for the selected period', function (): void {
    $seeded = seedPph21Period($this->tenant);

    $response = actingAs($this->admin)
        ->get('/avana/payroll/pph21-report/export?period='.$seeded['period']->id);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $body = $response->streamedContent();

    expect($body)->toContain('Nama', 'NPWP', 'Status PTKP', 'Kategori TER', 'Tarif TER (%)', 'Bruto Pajak');
    expect($body)->toContain($seeded['complete']->full_name);
    expect($body)->toContain('09.254.294.3-407.000', 'K/1', '3.2', '12500000', '400000', 'TER Bulanan');
    // The fallback base for the row whose taxable_gross was never written.
    expect($body)->toContain('8000000');
});

it('downloads the per-employee PPh 21 detail as xlsx and pdf', function (): void {
    $seeded = seedPph21Period($this->tenant);

    foreach (['xlsx', 'pdf'] as $format) {
        $response = actingAs($this->admin)->get(
            '/avana/payroll/pph21-report/export?period='.$seeded['period']->id.'&format='.$format,
        );

        $response->assertOk();
        expect($response->headers->get('content-disposition'))->toContain('.'.$format);
    }
});

it('serves the 1721-A1 the report links to, carrying the NPWP from the tax profile', function (): void {
    $seeded = seedPph21Period($this->tenant);

    $response = actingAs($this->admin)
        ->get('/avana/payroll/1721/'.$seeded['complete']->public_id.'?year=2026');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');

    // The blade used to label a row "NIK / NPWP" while only ever printing the
    // NIK; the NPWP is now passed through and printed on its own line.
    $data = TaxForm1721::viewData($seeded['complete']->fresh(), 2026);

    expect($data['employee']['npwp'])->toBe('09.254.294.3-407.000');
    expect($data['employee']['nik'])->toBe('3201010101010001');
});

it('lists the per-employee detail with a bukti potong link', function (): void {
    $seeded = seedPph21Period($this->tenant);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($seeded) {
            $rows = collect($page->toArray()['props']['employees'])->keyBy('employee_id');

            expect($rows[$seeded['complete']->id]['npwp'])->toBe('09.254.294.3-407.000');
            expect($rows[$seeded['complete']->id]['ter_category'])->toBe('B');
            expect($rows[$seeded['complete']->id]['ter_rate'])->toBe(3.2);
            expect($rows[$seeded['complete']->id]['method_label'])->toBe('TER Bulanan');
            expect($rows[$seeded['complete']->id]['ptkp_valid'])->toBeTrue();
            // The link is built from the route key, not the numeric id.
            expect($rows[$seeded['complete']->id]['employee_route_key'])
                ->toBe($seeded['complete']->public_id);
            expect($rows[$seeded['incomplete']->id]['ptkp_valid'])->toBeFalse();
        });
});

it('reports the combined TER rate for a THR row that carries no plain rate', function (): void {
    $seeded = seedPph21Period($this->tenant);

    PayrollRunItem::where('payroll_run_id', $seeded['run']->id)
        ->where('employee_id', $seeded['complete']->id)
        ->update(['calculation_snapshot' => json_encode([
            'tax' => [
                'method' => 'ter_bulanan_thr',
                'ptkp_status' => 'K/1',
                'ter_category' => 'B',
                'ter_rate_regular' => 0.0375,
                'ter_rate_combined' => 0.0475,
            ],
        ])]);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id)
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($seeded) {
            $rows = collect($page->toArray()['props']['employees'])->keyBy('employee_id');

            expect($rows[$seeded['complete']->id]['ter_rate'])->toBe(4.75);
            expect($rows[$seeded['complete']->id]['method_label'])->toBe('TER Bulanan (THR)');
        });
});

it('narrows the per-employee list by withholding scheme', function (): void {
    $seeded = seedPph21Period($this->tenant);

    actingAs($this->admin)
        ->get('/avana/payroll/pph21-report?period='.$seeded['period']->id.'&scheme=pasal17')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('employees', [])->etc());
});

it('denies a user without the pph21 view permission', function (): void {
    $outsider = User::where('tenant_id', $this->tenant->id)
        ->whereDoesntHave('roles', fn ($query) => $query->whereIn('code', ['super_admin', 'admin_tenant_hr']))
        ->firstOrFail();

    actingAs($outsider)->get('/avana/payroll/pph21-report')->assertForbidden();
});
