<?php

use App\Models\AiTokenPack;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
});

it('creates a token pack', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.token-packs.store'), [
            'name' => 'Paket Hemat',
            'token_amount' => 100_000,
            'price' => 50_000,
            'description' => 'Paket uji',
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect();

    expect(AiTokenPack::where('name', 'Paket Hemat')->exists())->toBeTrue();
});

it('updates a token pack over PUT', function (): void {
    $pack = AiTokenPack::create([
        'name' => 'Paket Lama',
        'token_amount' => 10_000,
        'price' => 10_000,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    actingAs($this->superAdmin)
        ->put(route('avana.token-packs.update', $pack), [
            'name' => 'Paket Baru',
            'token_amount' => 20_000,
            'price' => 25_000,
            'description' => null,
            'is_active' => false,
            'sort_order' => 2,
        ])
        ->assertRedirect();

    $pack->refresh();

    expect($pack->name)->toBe('Paket Baru')
        ->and($pack->token_amount)->toBe(20_000)
        ->and($pack->price)->toBe(25_000)
        ->and($pack->is_active)->toBeFalse();
});

it('rejects a POST to the pack update route', function (): void {
    $pack = AiTokenPack::create([
        'name' => 'Paket Lama',
        'token_amount' => 10_000,
        'price' => 10_000,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    actingAs($this->superAdmin)
        ->post(route('avana.token-packs.update', $pack), [
            'name' => 'Paket Baru',
            'token_amount' => 20_000,
            'price' => 25_000,
        ])
        ->assertMethodNotAllowed();
});

it('deletes a token pack', function (): void {
    $pack = AiTokenPack::create([
        'name' => 'Paket Hapus',
        'token_amount' => 5_000,
        'price' => 5_000,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    actingAs($this->superAdmin)
        ->delete(route('avana.token-packs.destroy', $pack))
        ->assertRedirect();

    expect(AiTokenPack::find($pack->id))->toBeNull();
});

it('blocks non super admins', function (): void {
    $tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    actingAs($tenantAdmin)
        ->get(route('avana.token-packs'))
        ->assertForbidden();
});
