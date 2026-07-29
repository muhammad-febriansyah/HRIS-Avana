<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiRoleTokenCap;
use App\Models\AiTokenLedger;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiTokenService;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->service = app(AiTokenService::class);
});

it('allows chat and does not touch the wallet while within the free quota', function (): void {
    $this->tenant->update(['ai_token_quota' => 1_000, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);

    expect($this->service->canChat($this->admin->fresh())->allowed)->toBeTrue();

    $this->service->debit($this->admin->fresh(), 500);

    expect($this->tenant->fresh()->ai_token_balance)->toBe(0)
        ->and($this->service->tenantMonthlyUsed($this->tenant->fresh()))->toBe(500);

    $ledger = AiTokenLedger::where('user_id', $this->admin->id)->where('type', 'debit')->firstOrFail();
    expect($ledger->tokens)->toBe(500)->and($ledger->wallet_delta)->toBe(0);
});

it('draws the overflow from the wallet once the free quota is exhausted', function (): void {
    $this->tenant->update(['ai_token_quota' => 0, 'ai_token_balance' => 1_000, 'ai_token_user_cap' => null]);

    expect($this->service->canChat($this->admin->fresh())->allowed)->toBeTrue();

    $this->service->debit($this->admin->fresh(), 300);

    expect($this->tenant->fresh()->ai_token_balance)->toBe(700);

    $ledger = AiTokenLedger::where('user_id', $this->admin->id)->where('type', 'debit')->firstOrFail();
    expect($ledger->wallet_delta)->toBe(-300);
});

it('blocks a user who has reached the tenant default cap', function (): void {
    $this->tenant->update(['ai_token_quota' => 10_000, 'ai_token_balance' => 0, 'ai_token_user_cap' => 100]);

    $this->service->debit($this->admin->fresh(), 100);

    $gate = $this->service->canChat($this->admin->fresh());

    expect($gate->allowed)->toBeFalse()->and($gate->reason)->toBe('user_cap');
});

it('blocks a user who has reached their role cap', function (): void {
    $this->tenant->update(['ai_token_quota' => 10_000, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);
    $role = Role::where('tenant_id', $this->tenant->id)->where('code', 'admin_tenant_hr')->firstOrFail();
    AiRoleTokenCap::create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id, 'monthly_cap' => 150]);

    $this->service->debit($this->admin->fresh(), 150);

    $gate = $this->service->canChat($this->admin->fresh());

    expect($gate->allowed)->toBeFalse()->and($gate->reason)->toBe('user_cap');
});

it('blocks everyone when the free quota and wallet are both empty', function (): void {
    $this->tenant->update(['ai_token_quota' => 0, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);

    $gate = $this->service->canChat($this->admin->fresh());

    expect($gate->allowed)->toBeFalse()->and($gate->reason)->toBe('pool_empty');
});

it('streams a block message from the web chat when the pool is empty', function (): void {
    $this->tenant->update(['ai_token_quota' => 0, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);

    $response = actingAs($this->admin)
        ->post(route('avana.ai.stream'), ['message' => 'Berapa sisa cuti saya?']);

    $response->assertOk()->assertHeader('X-Token-Blocked', 'pool_empty');
    expect($response->streamedContent())->toContain('habis');
});

it('does not restore usage when a conversation is deleted (ledger-based)', function (): void {
    $this->tenant->update(['ai_token_quota' => 10_000, 'ai_token_balance' => 0]);

    $this->service->debit($this->admin->fresh(), 500);

    $conversation = AiConversation::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'title' => 'Percakapan',
    ]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'role' => 'assistant',
        'content' => 'halo',
        'total_tokens' => 999,
    ]);

    $conversation->delete();

    expect($this->service->userMonthlyUsed($this->admin->fresh()))->toBe(500);
});

it('names the personal allowance and says the company pool is still available', function (): void {
    // A tiny per-user cap against an almost untouched company pool — the exact
    // shape that made the meter read "sisa 486.298" beside a "habis" reply.
    $this->tenant->update([
        'ai_token_quota' => 500_000,
        'ai_token_balance' => 0,
        'ai_token_user_cap' => 10_000,
    ]);

    AiTokenLedger::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'type' => AiTokenLedger::TYPE_DEBIT,
        'source' => 'chat',
        'tokens' => 10_571,
        'wallet_delta' => 0,
        'balance_after' => 0,
        'period' => now()->format('Y-m'),
    ]);

    $gate = $this->service->canChat($this->admin->fresh());

    // The refusal names the allowance and its size, so it cannot be mistaken
    // for the whole company having run dry, and points at both ways out: the
    // admin can raise the cap, or they can buy tokens of their own.
    expect($gate->allowed)->toBeFalse()
        ->and($gate->reason)->toBe('user_cap')
        ->and($gate->message)
        ->toContain('dari perusahaan')
        ->toContain('10.571')
        ->toContain('10.000')
        ->toContain('beli token pribadi');
});

it('reports the per-user cap to the meter, not just the company pool', function (): void {
    $this->tenant->update([
        'ai_token_quota' => 500_000,
        'ai_token_balance' => 0,
        'ai_token_user_cap' => 10_000,
    ]);

    AiTokenLedger::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'type' => AiTokenLedger::TYPE_DEBIT,
        'source' => 'chat',
        'tokens' => 10_571,
        'wallet_delta' => 0,
        'balance_after' => 0,
        'period' => now()->format('Y-m'),
    ]);

    $usage = $this->service->remainingForUser($this->admin->fresh());

    expect($usage['user_cap'])->toBe(10_000)
        ->and($usage['user_used'])->toBe(10_571)
        // Already over the cap: nothing left for this user...
        ->and($usage['user_remaining'])->toBe(0)
        ->and($usage['effective_remaining'])->toBe(0)
        // ...even though the company pool is barely touched.
        ->and($usage['free_remaining'])->toBe(489_429);
});
