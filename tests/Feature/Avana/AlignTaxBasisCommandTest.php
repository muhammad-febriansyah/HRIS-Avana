<?php

use App\Models\BpjsProgram;
use App\Models\BpjsRate;
use App\Models\PayrollComponent;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->tenant = Tenant::findOrFail(User::where('email', 'rina.a@nusantara.co.id')->firstOrFail()->tenant_id);

    PayrollComponent::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'TJ-INS',
        'name' => 'Insentif Penjualan',
        'type' => 'earning',
        'component_group' => 'penerimaan',
        'calc_basis' => 'fixed',
        'status' => 'active',
    ]);

    // A meal allowance paid per attendance day — the shape the BPJS wage must
    // leave out, as opposed to the fixed monthly allowances.
    PayrollComponent::forTenant($this->tenant->id)
        ->where('code', 'TJ-MKN')
        ->update(['calc_basis' => 'per_present_day']);

    PayrollComponent::forTenant($this->tenant->id)
        ->where('type', 'earning')
        ->update(['is_taxable' => false, 'is_bpjs_base' => false]);
});

/** The tenant's component with this code, freshly read. */
function taxBasisComponent(object $ctx, string $code): PayrollComponent
{
    return PayrollComponent::forTenant($ctx->tenant->id)->where('code', $code)->firstOrFail();
}

it('reports without writing until --apply is passed', function (): void {
    artisan('avana:align-tax-basis', ['--tenant' => $this->tenant->id])->assertSuccessful();

    expect((bool) taxBasisComponent($this, 'BASIC')->is_taxable)->toBeFalse()
        ->and((bool) taxBasisComponent($this, 'BASIC')->is_bpjs_base)->toBeFalse();
});

it('puts every earning in the tax bruto and only the fixed ones in the BPJS wage', function (): void {
    artisan('avana:align-tax-basis', ['--tenant' => $this->tenant->id, '--apply' => true])->assertSuccessful();

    // Fixed monthly pay: taxable, and part of the contribution wage.
    expect((bool) taxBasisComponent($this, 'BASIC')->is_taxable)->toBeTrue()
        ->and((bool) taxBasisComponent($this, 'BASIC')->is_bpjs_base)->toBeTrue()
        ->and((bool) taxBasisComponent($this, 'TJ-JAB')->is_bpjs_base)->toBeTrue()
        ->and((bool) taxBasisComponent($this, 'TJ-TRP')->is_bpjs_base)->toBeTrue();

    // Counted per attendance day, so it swings month to month: taxable, but
    // never part of a premium the employee keeps paying afterwards.
    expect((bool) taxBasisComponent($this, 'TJ-MKN')->is_taxable)->toBeTrue()
        ->and((bool) taxBasisComponent($this, 'TJ-MKN')->is_bpjs_base)->toBeFalse();

    // Same treatment for an incentive, recognised by name rather than by how
    // it is counted.
    expect((bool) taxBasisComponent($this, 'TJ-INS')->is_taxable)->toBeTrue()
        ->and((bool) taxBasisComponent($this, 'TJ-INS')->is_bpjs_base)->toBeFalse();
});

it('keeps a named component out of the tax bruto', function (): void {
    artisan('avana:align-tax-basis', [
        '--tenant' => $this->tenant->id,
        '--exclude-tax' => 'TJ-MKN',
        '--apply' => true,
    ])->assertSuccessful();

    expect((bool) taxBasisComponent($this, 'TJ-MKN')->is_taxable)->toBeFalse()
        ->and((bool) taxBasisComponent($this, 'BASIC')->is_taxable)->toBeTrue();
});

it('switches the employer premium out of the tax bruto only when asked', function (): void {
    expect((bool) $this->tenant->fresh()->tax_includes_employer_bpjs)->toBeTrue();

    artisan('avana:align-tax-basis', ['--tenant' => $this->tenant->id, '--apply' => true])
        ->assertSuccessful();

    expect((bool) $this->tenant->fresh()->tax_includes_employer_bpjs)->toBeTrue();

    artisan('avana:align-tax-basis', [
        '--tenant' => $this->tenant->id,
        '--employer-premium' => 'exclude',
        '--apply' => true,
    ])->assertSuccessful();

    expect((bool) $this->tenant->fresh()->tax_includes_employer_bpjs)->toBeFalse();
});

it('refuses to write across every tenant at once', function (): void {
    artisan('avana:align-tax-basis', ['--apply' => true])->assertFailed();

    expect((bool) taxBasisComponent($this, 'BASIC')->is_taxable)->toBeFalse();
});

it('creates the employer-only premiums at the rate given', function (): void {
    BpjsProgram::where('code', 'JKK')->forceDelete();
    BpjsProgram::where('code', 'JKM')->forceDelete();

    artisan('avana:align-tax-basis', [
        '--tenant' => $this->tenant->id,
        '--jkk-rate' => '0.0024',
        '--jkm-rate' => '0.003',
        '--apply' => true,
    ])->assertSuccessful();

    $jkk = BpjsProgram::where('code', 'JKK')->firstOrFail();
    $rate = BpjsRate::where('program_id', $jkk->id)->firstOrFail();

    // Employer-only: it costs the company, never the payslip.
    expect((float) $rate->company_rate)->toBe(0.0024)
        ->and((float) $rate->employee_rate)->toBe(0.0);

    expect(BpjsProgram::where('code', 'JKM')->exists())->toBeTrue();
});

it('rejects a premium rate typed as a percentage instead of a decimal', function (): void {
    BpjsProgram::where('code', 'JKK')->forceDelete();

    artisan('avana:align-tax-basis', [
        '--tenant' => $this->tenant->id,
        '--jkk-rate' => '0.24',
        '--apply' => true,
    ])->assertSuccessful();

    expect(BpjsProgram::where('code', 'JKK')->exists())->toBeFalse();
});

it('sets the wage ceilings on the BPJS rate master', function (): void {
    artisan('avana:align-tax-basis', [
        '--tenant' => $this->tenant->id,
        '--kesehatan-cap' => '12000000',
        '--jp-cap' => '11086300',
        '--apply' => true,
    ])->assertSuccessful();

    $ceiling = fn (string $code): float => (float) BpjsRate::whereHas(
        'program',
        fn ($query) => $query->where('code', $code),
    )->where('is_active', true)->firstOrFail()->max_wage;

    expect($ceiling('KESEHATAN'))->toBe(12_000_000.0)
        ->and($ceiling('JP'))->toBe(11_086_300.0);
});
