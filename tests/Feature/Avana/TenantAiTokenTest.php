<?php

use App\Models\AiRoleTokenCap;
use App\Models\AiTokenOrder;
use App\Models\AiTokenPack;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->tenantAdmin->tenant_id);
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
});

it('renders the token-ai page with wallet, packs, orders and members', function (): void {
    AiTokenPack::create(['name' => 'Paket A', 'token_amount' => 100_000, 'price' => 50_000]);
    $this->tenant->update(['ai_token_balance' => 250_000, 'ai_token_user_cap' => 10_000]);

    actingAs($this->tenantAdmin)
        ->get(route('avana.token-ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/token-ai/index', false)
            ->where('usage.wallet_balance', 250_000)
            ->where('defaultUserCap', 10_000)
            ->has('packs.0', fn (Assert $pack) => $pack->where('name', 'Paket A')->etc())
            ->has('orders')
            ->has('roles.0', fn (Assert $r) => $r->has('id')->has('name')->has('members')->has('cap')->has('used')->etc()));
});

it('creates a pending order and redirects to the Pakasir checkout', function (): void {
    $pack = AiTokenPack::create(['name' => 'Paket A', 'token_amount' => 100_000, 'price' => 50_000]);

    $response = actingAs($this->tenantAdmin)
        ->post(route('avana.token-ai.purchase'), ['pack_id' => $pack->id]);

    $order = AiTokenOrder::latest('id')->firstOrFail();

    expect($order->status)->toBe(AiTokenOrder::STATUS_PENDING)
        ->and($order->amount)->toBe(50_000)
        ->and($order->token_amount)->toBe(100_000)
        ->and($order->tenant_id)->toBe($this->tenant->id);

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain("/pay/avanahr/50000?order_id={$order->order_number}");
});

it('handles an Inertia purchase request with a location redirect (not a 500)', function (): void {
    $pack = AiTokenPack::create(['name' => 'Paket B', 'token_amount' => 100_000, 'price' => 50_000]);

    // An Inertia request makes Inertia::location() emit a 409 with the external
    // location header — the controller must not type-error on the non-redirect.
    $response = actingAs($this->tenantAdmin)
        ->withHeader('X-Inertia', 'true')
        ->post(route('avana.token-ai.purchase'), ['pack_id' => $pack->id]);

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toContain('/pay/avanahr/50000');
});

it('saves the default cap and per-role caps, deleting blanked rows', function (): void {
    $this->tenant->update(['ai_token_user_cap' => null]);
    $role = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    actingAs($this->tenantAdmin)
        ->put(route('avana.token-ai.allocation'), [
            'default_user_cap' => 5_000,
            'caps' => [
                ['role_id' => $role->id, 'monthly_cap' => 2_000],
            ],
        ])
        ->assertRedirect();

    expect((int) $this->tenant->fresh()->ai_token_user_cap)->toBe(5_000)
        ->and((int) AiRoleTokenCap::where('role_id', $role->id)->value('monthly_cap'))->toBe(2_000);

    // Blanking the role cap removes the row (inherit default).
    actingAs($this->tenantAdmin)
        ->put(route('avana.token-ai.allocation'), [
            'default_user_cap' => 5_000,
            'caps' => [
                ['role_id' => $role->id, 'monthly_cap' => null],
            ],
        ])
        ->assertRedirect();

    expect(AiRoleTokenCap::where('role_id', $role->id)->exists())->toBeFalse();
});

it('forbids a user without the ai_topup permission', function (): void {
    actingAs($this->employee)
        ->get(route('avana.token-ai'))
        ->assertForbidden();
});
