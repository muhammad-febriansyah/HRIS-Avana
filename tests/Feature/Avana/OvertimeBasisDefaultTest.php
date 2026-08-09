<?php

use App\Models\PayrollComponent;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a payroll component through the Master Komponen screen.
 */
function storeComponent(array $overrides = []): void
{
    actingAs(test()->admin)
        ->post(route('avana.payroll.komponen.component.store'), array_merge([
            'name' => 'Tunjangan Uji',
            'group' => 'penerimaan',
            'calc_basis' => 'fixed',
            'period_basis' => 'berjalan',
        ], $overrides))
        ->assertSessionHasNoErrors();
}

it('keeps a per-day allowance out of the overtime basis', function (): void {
    storeComponent(['name' => 'Tunjangan Harian', 'calc_basis' => 'per_present_day']);

    $component = PayrollComponent::forTenant($this->tenant->id)->where('name', 'Tunjangan Harian')->firstOrFail();

    expect($component->is_fixed)->toBeFalse();
});

it('keeps a deduction out of the overtime basis', function (): void {
    storeComponent(['name' => 'Potongan Uji', 'group' => 'potongan']);

    $component = PayrollComponent::forTenant($this->tenant->id)->where('name', 'Potongan Uji')->firstOrFail();

    expect($component->is_fixed)->toBeFalse();
});

it('counts a fixed monthly allowance as part of the overtime basis', function (): void {
    storeComponent(['name' => 'Tunjangan Tetap Uji']);

    $component = PayrollComponent::forTenant($this->tenant->id)->where('name', 'Tunjangan Tetap Uji')->firstOrFail();

    expect($component->is_fixed)->toBeTrue();
});

it('drops the basis mark when an allowance becomes attendance-variable', function (): void {
    storeComponent(['name' => 'Tunjangan Berubah']);

    $component = PayrollComponent::forTenant($this->tenant->id)->where('name', 'Tunjangan Berubah')->firstOrFail();
    expect($component->is_fixed)->toBeTrue();

    actingAs($this->admin)
        ->put(route('avana.payroll.komponen.component.update', $component), [
            'name' => 'Tunjangan Berubah',
            'group' => 'penerimaan',
            'calc_basis' => 'per_present_day',
        ])
        ->assertSessionHasNoErrors();

    expect($component->fresh()->is_fixed)->toBeFalse();
});

it('leaves the seeded overtime line and per-day allowances out of the basis', function (): void {
    $flags = PayrollComponent::forTenant($this->tenant->id)
        ->pluck('is_fixed', 'code')
        ->map(fn ($value): bool => (bool) $value);

    // Transport counts as a fixed allowance per the payroll setup
    // documentation (§8.1) — the seeder's worked basis includes it.
    expect($flags['BASIC'])->toBeTrue()
        ->and($flags['TJ-JAB'])->toBeTrue()
        ->and($flags['TJ-TRP'])->toBeTrue()
        ->and($flags['TJ-MKN'])->toBeFalse()
        ->and($flags['POT-KOP'])->toBeFalse();
});
