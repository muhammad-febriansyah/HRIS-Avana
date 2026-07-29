<?php

use App\Models\AiRoleTokenCap;
use App\Models\AiTokenLedger;
use App\Models\AiTokenOrder;
use App\Models\AiTokenPack;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiTokenService;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->employee->tenant_id);
    $this->tokens = app(AiTokenService::class);

    // A company with nothing free left, so the pools under test are the two
    // wallets rather than the monthly allowance.
    $this->tenant->update(['ai_token_quota' => 0, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);
});

it('shows the buy page with balance, packs and own orders', function (): void {
    AiTokenPack::create(['name' => 'Paket Hemat', 'token_amount' => 50_000, 'price' => 25_000]);
    $this->employee->forceFill(['ai_token_balance' => 7_500])->save();

    actingAs($this->employee)
        ->get(route('avana.saya.token-ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/saya/token-ai', false)
            ->where('summary.personal_balance', 7_500)
            ->has('packs.0', fn (Assert $pack) => $pack->where('name', 'Paket Hemat')->etc())
            ->has('orders'));
});

it('opens a personal order rather than a company one', function (): void {
    $pack = AiTokenPack::create(['name' => 'Paket Hemat', 'token_amount' => 50_000, 'price' => 25_000]);

    actingAs($this->employee)->post(route('avana.saya.token-ai.purchase'), ['pack_id' => $pack->id]);

    $order = AiTokenOrder::latest('id')->firstOrFail();

    expect($order->scope)->toBe(AiTokenOrder::SCOPE_USER)
        ->and($order->user_id)->toBe($this->employee->id)
        ->and($order->status)->toBe(AiTokenOrder::STATUS_PENDING);
});

it('credits a paid personal order to the buyer, not the company', function (): void {
    $order = AiTokenOrder::create([
        'order_number' => 'AIU-TEST-1',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'scope' => AiTokenOrder::SCOPE_USER,
        'pack_name' => 'Paket Hemat',
        'token_amount' => 50_000,
        'amount' => 25_000,
        'status' => AiTokenOrder::STATUS_COMPLETED,
    ]);

    $this->tokens->creditWallet($order);

    expect($this->employee->fresh()->ai_token_balance)->toBe(50_000)
        ->and($this->tenant->fresh()->ai_token_balance)->toBe(0);

    // Crediting twice must not mint tokens.
    $this->tokens->creditWallet($order->fresh());
    expect($this->employee->fresh()->ai_token_balance)->toBe(50_000);
});

it('spends the personal wallet before touching the company pools', function (): void {
    $this->tenant->forceFill(['ai_token_balance' => 1_000])->save();
    $this->employee->forceFill(['ai_token_balance' => 5_000])->save();

    $this->tokens->debit($this->employee, 400);

    // The balance the buyer watches is the one that moves.
    expect($this->employee->fresh()->ai_token_balance)->toBe(4_600)
        ->and($this->tenant->fresh()->ai_token_balance)->toBe(1_000);
});

it('falls through to the company once the personal wallet runs dry', function (): void {
    $this->tenant->forceFill(['ai_token_balance' => 1_000])->save();
    $this->employee->forceFill(['ai_token_balance' => 300])->save();

    // 800 needed, only 300 of their own left.
    $this->tokens->debit($this->employee, 800);

    expect($this->employee->fresh()->ai_token_balance)->toBe(0)
        ->and($this->tenant->fresh()->ai_token_balance)->toBe(500);

    $ledger = AiTokenLedger::latest('id')->firstOrFail();

    expect($ledger->personal_delta)->toBe(-300)
        ->and($ledger->wallet_delta)->toBe(-500)
        ->and($ledger->tokens)->toBe(800);
});

it('keeps serving a user who is over their cap from their own wallet', function (): void {
    $this->tenant->update(['ai_token_balance' => 100_000, 'ai_token_user_cap' => 1_000]);
    $this->employee->forceFill(['ai_token_balance' => 500])->save();

    // 500 of their own, then the company covers the rest up to the cap.
    $this->tokens->debit($this->employee, 1_500);

    expect($this->employee->fresh()->ai_token_balance)->toBe(0)
        ->and($this->tenant->fresh()->ai_token_balance)->toBe(99_000)
        ->and($this->tokens->userCompanyUsed($this->employee->fresh()))->toBe(1_000);

    // Their own wallet is empty and the cap is spent, so the gate closes.
    expect($this->tokens->canChat($this->employee->fresh())->allowed)->toBeFalse();

    // Topping up reopens it without the admin touching the cap.
    $this->employee->forceFill(['ai_token_balance' => 2_000])->save();

    expect($this->tokens->canChat($this->employee->fresh())->allowed)->toBeTrue();
});

it('does not let personal spending eat the company allowance', function (): void {
    $this->tenant->update(['ai_token_balance' => 100_000, 'ai_token_user_cap' => 1_000]);
    $this->employee->forceFill(['ai_token_balance' => 5_000])->save();

    // All 900 comes from the buyer's own wallet, since personal goes first.
    $this->tokens->debit($this->employee, 900);

    // Their company allowance is untouched: spending what they bought must not
    // count against the ration of what the company bought.
    expect($this->tokens->userCompanyUsed($this->employee->fresh()))->toBe(0)
        ->and($this->tokens->userMonthlyUsed($this->employee->fresh()))->toBe(900);
});

it('blocks a capped user with an empty personal wallet', function (): void {
    $role = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    AiRoleTokenCap::create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id, 'monthly_cap' => 500]);

    $this->tenant->forceFill(['ai_token_balance' => 100_000])->save();
    $this->tokens->debit($this->employee, 500);

    $gate = $this->tokens->canChat($this->employee->fresh());

    expect($gate->allowed)->toBeFalse()
        ->and($gate->reason)->toBe('user_cap');
});

it('adds the personal wallet on top of the capped company share', function (): void {
    $this->tenant->update(['ai_token_balance' => 100_000, 'ai_token_user_cap' => 2_000]);
    $this->employee->forceFill(['ai_token_balance' => 5_000])->save();

    $summary = $this->tokens->remainingForUser($this->employee->fresh());

    // The cap clamps the company share; what they own is added, not clamped.
    expect($summary['company_remaining'])->toBe(2_000)
        ->and($summary['personal_balance'])->toBe(5_000)
        ->and($summary['effective_remaining'])->toBe(7_000);
});

it('will not show another persons order history', function (): void {
    $other = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    AiTokenOrder::create([
        'order_number' => 'AIU-OTHER-1',
        'tenant_id' => $this->tenant->id,
        'user_id' => $other->id,
        'scope' => AiTokenOrder::SCOPE_USER,
        'pack_name' => 'Paket Hemat',
        'token_amount' => 50_000,
        'amount' => 25_000,
        'status' => AiTokenOrder::STATUS_COMPLETED,
    ]);

    $orders = actingAs($this->employee)
        ->get(route('avana.saya.token-ai'))
        ->assertOk()
        ->viewData('page')['props']['orders'];

    expect(collect($orders)->pluck('order_number'))->not->toContain('AIU-OTHER-1');
});

it('refuses to settle another persons order over the API', function (): void {
    $other = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    AiTokenOrder::create([
        'order_number' => 'AIU-OTHER-2',
        'tenant_id' => $this->tenant->id,
        'user_id' => $other->id,
        'scope' => AiTokenOrder::SCOPE_USER,
        'pack_name' => 'Paket Hemat',
        'token_amount' => 50_000,
        'amount' => 25_000,
        'status' => AiTokenOrder::STATUS_PENDING,
    ]);

    $this->actingAs($this->employee, 'api')
        ->getJson('/api/v1/me/ai/tokens/AIU-OTHER-2')
        ->assertNotFound();
});

it('serves packs and balance to the mobile app', function (): void {
    AiTokenPack::create(['name' => 'Paket Hemat', 'token_amount' => 50_000, 'price' => 25_000]);
    $this->employee->forceFill(['ai_token_balance' => 3_000])->save();

    $this->actingAs($this->employee, 'api')
        ->getJson('/api/v1/me/ai/tokens')
        ->assertOk()
        ->assertJsonPath('data.summary.personal_balance', 3_000)
        ->assertJsonPath('data.packs.0.name', 'Paket Hemat');
});

it('serves a usage series the app can chart', function (): void {
    $this->tokens->debit($this->employee, 3_000);

    AiTokenLedger::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'type' => AiTokenLedger::TYPE_DEBIT,
        'source' => 'chat',
        'tokens' => 1_200,
        'period' => now()->subDays(2)->format('Y-m'),
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $usage = $this->actingAs($this->employee, 'api')
        ->getJson('/api/v1/me/ai/tokens')
        ->assertOk()
        ->assertJsonPath('data.usage.today', 3_000)
        ->json('data.usage');

    // Seven days ending today, quiet days included as zero so the chart has no
    // gaps, and the day before yesterday carries its own spend.
    expect($usage['daily'])->toHaveCount(7)
        ->and($usage['daily'][6]['date'])->toBe(now()->format('Y-m-d'))
        ->and($usage['daily'][6]['tokens'])->toBe(3_000)
        ->and($usage['daily'][4]['tokens'])->toBe(1_200)
        ->and($usage['week'])->toBe(4_200)
        ->and($usage['monthly'])->toHaveCount(6)
        ->and($usage['monthly'][5]['month'])->toBe(now()->format('Y-m'));
});

it('opens a personal order from the mobile app and hands back a pay url', function (): void {
    $pack = AiTokenPack::create(['name' => 'Paket Hemat', 'token_amount' => 50_000, 'price' => 25_000]);

    $body = $this->actingAs($this->employee, 'api')
        ->postJson('/api/v1/me/ai/tokens', ['pack_id' => $pack->id])
        ->assertCreated()
        ->json('data');

    expect($body['pay_url'])->toBeString()->not->toBeEmpty()
        ->and($body['token_amount'])->toBe(50_000);

    expect(AiTokenOrder::where('order_number', $body['order_number'])->firstOrFail()->scope)
        ->toBe(AiTokenOrder::SCOPE_USER);
});

it('sends the mobile buyer to a return page that needs no session', function (): void {
    $pack = AiTokenPack::create(['name' => 'Paket Hemat', 'token_amount' => 50_000, 'price' => 25_000]);

    $payUrl = $this->actingAs($this->employee, 'api')
        ->postJson('/api/v1/me/ai/tokens', ['pack_id' => $pack->id])
        ->assertCreated()
        ->json('data.pay_url');

    // The gateway sends the browser back here; /avana would bounce to login.
    expect($payUrl)->toContain(urlencode(route('bayar.token-ai.selesai')))
        ->and($payUrl)->not->toContain('avana%2Fsaya');
});

it('tells a returning buyer where they stand without a login', function (): void {
    $order = AiTokenOrder::create([
        'order_number' => 'AIU-RETURN-1',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'scope' => AiTokenOrder::SCOPE_USER,
        'pack_name' => 'Paket Hemat',
        'token_amount' => 50_000,
        'amount' => 25_000,
        'status' => AiTokenOrder::STATUS_COMPLETED,
        'credited_at' => now(),
    ]);

    // No actingAs: this is an external browser with no session at all.
    $this->get(route('bayar.token-ai.selesai', ['order' => $order->order_number]))
        ->assertOk()
        ->assertSee('Pembayaran berhasil');
});

it('reveals nothing about the buyer from a guessed order number', function (): void {
    AiTokenOrder::create([
        'order_number' => 'AIU-SECRET-1',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'scope' => AiTokenOrder::SCOPE_USER,
        'pack_name' => 'Paket Rahasia',
        'token_amount' => 50_000,
        'amount' => 25_000,
        'status' => AiTokenOrder::STATUS_COMPLETED,
        'credited_at' => now(),
    ]);

    $page = $this->get(route('bayar.token-ai.selesai', ['order' => 'AIU-SECRET-1']))->assertOk();

    // A stranger learns only that some payment cleared — not who, nor what.
    $page->assertDontSee($this->employee->name)
        ->assertDontSee($this->employee->email)
        ->assertDontSee('Paket Rahasia')
        ->assertDontSee('50.000');
});

it('does not credit an unpaid order from the public return page', function (): void {
    $order = AiTokenOrder::create([
        'order_number' => 'AIU-UNPAID-1',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'scope' => AiTokenOrder::SCOPE_USER,
        'pack_name' => 'Paket Hemat',
        'token_amount' => 50_000,
        'amount' => 25_000,
        'status' => AiTokenOrder::STATUS_PENDING,
    ]);

    $this->get(route('bayar.token-ai.selesai', ['order' => $order->order_number]))
        ->assertOk()
        ->assertSee('Pembayaran belum selesai')
        ->assertSee('http-equiv="refresh"', escape: false);

    expect($order->fresh()->credited_at)->toBeNull()
        ->and($this->employee->fresh()->ai_token_balance)->toBe(0);
});
