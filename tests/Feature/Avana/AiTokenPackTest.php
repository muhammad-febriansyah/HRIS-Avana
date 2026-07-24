<?php

use App\Models\AiTokenPack;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('lets a super admin view the pack catalogue with orders and kpis', function (): void {
    AiTokenPack::create(['name' => 'Paket Hemat', 'token_amount' => 100_000, 'price' => 50_000]);

    actingAs($this->superAdmin)
        ->get(route('avana.token-packs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/token-packs/index', false)
            ->has('packs.0', fn (Assert $pack) => $pack
                ->where('name', 'Paket Hemat')
                ->where('token_amount', 100_000)
                ->where('price', 50_000)
                ->etc())
            ->has('orders')
            ->has('kpis.revenue')
            ->has('kpis.tokens_sold'));
});

it('lets a super admin create, update and archive a pack', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.token-packs.store'), [
            'name' => 'Paket Pro',
            'token_amount' => 500_000,
            'price' => 200_000,
            'is_active' => true,
        ])
        ->assertRedirect();

    $pack = AiTokenPack::firstOrFail();
    expect($pack->token_amount)->toBe(500_000);

    actingAs($this->superAdmin)
        ->put(route('avana.token-packs.update', $pack), [
            'name' => 'Paket Pro+',
            'token_amount' => 750_000,
            'price' => 250_000,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($pack->fresh()->token_amount)->toBe(750_000)
        ->and($pack->fresh()->is_active)->toBeFalse();

    actingAs($this->superAdmin)
        ->delete(route('avana.token-packs.destroy', $pack))
        ->assertRedirect();

    expect(AiTokenPack::withTrashed()->find($pack->id)->trashed())->toBeTrue();
});

it('forbids a tenant admin from the platform pack catalogue', function (): void {
    actingAs($this->tenantAdmin)
        ->get(route('avana.token-packs'))
        ->assertForbidden();

    actingAs($this->tenantAdmin)
        ->post(route('avana.token-packs.store'), [
            'name' => 'x', 'token_amount' => 1, 'price' => 0,
        ])
        ->assertForbidden();
});
