<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // A tenant seeded before the menu was switched off in the canonical
    // sidebar: the row exists and is still on. That is the case this command
    // is for — a new tenant is already seeded with it off.
    MenuItem::where('tenant_id', $this->tenant->id)
        ->where('key', 'payroll-insentif')
        ->update(['is_active' => true]);
});

/** The tenant's row for a menu key. */
function menuRow(object $ctx, string $key): MenuItem
{
    return MenuItem::where('tenant_id', $ctx->tenant->id)->where('key', $key)->firstOrFail();
}

it('hides a menu across tenants and shows it again', function (): void {
    expect((bool) menuRow($this, 'payroll-insentif')->is_active)->toBeTrue();

    artisan('avana:menu-visibility', ['key' => 'payroll-insentif'])->assertSuccessful();

    expect((bool) menuRow($this, 'payroll-insentif')->is_active)->toBeFalse();

    artisan('avana:menu-visibility', ['key' => 'payroll-insentif', '--show' => true])->assertSuccessful();

    expect((bool) menuRow($this, 'payroll-insentif')->is_active)->toBeTrue();
});

it('closes the screen behind a hidden menu instead of leaving it open', function (): void {
    actingAs($this->admin)->get('/avana/payroll/insentif')->assertOk();

    artisan('avana:menu-visibility', ['key' => 'payroll-insentif'])->assertSuccessful();

    actingAs($this->admin)->get('/avana/payroll/insentif')->assertForbidden();
});

it('fails on a key nobody has', function (): void {
    artisan('avana:menu-visibility', ['key' => 'tidak-ada'])->assertFailed();
});
