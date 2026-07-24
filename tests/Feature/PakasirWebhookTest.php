<?php

use App\Models\AiTokenLedger;
use App\Models\AiTokenOrder;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

/**
 * Create a pending token order for a fresh tenant.
 */
function pendingOrder(int $amount = 50_000, int $tokens = 100_000): AiTokenOrder
{
    $tenant = Tenant::create(['name' => 'PT Uji', 'slug' => 'pt-uji-'.uniqid(), 'ai_token_balance' => 0]);

    return AiTokenOrder::create([
        'order_number' => 'AIT-'.$tenant->id.'-TEST-'.strtoupper(substr(md5((string) $tenant->id), 0, 6)),
        'tenant_id' => $tenant->id,
        'pack_name' => 'Paket Uji',
        'token_amount' => $tokens,
        'amount' => $amount,
        'status' => AiTokenOrder::STATUS_PENDING,
    ]);
}

/**
 * A well-formed webhook payload for the given order.
 *
 * @return array<string, mixed>
 */
function webhookPayload(AiTokenOrder $order, array $overrides = []): array
{
    return array_merge([
        'amount' => $order->amount,
        'order_id' => $order->order_number,
        'project' => 'avanahr',
        'status' => 'completed',
        'payment_method' => 'qris',
        'completed_at' => '2026-07-25T08:00:00+07:00',
    ], $overrides);
}

it('credits the wallet once when the payment verifies as completed', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed', 'payment_method' => 'qris']])]);

    $order = pendingOrder();

    postJson('/api/v1/pakasir/webhook', webhookPayload($order))
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($order->tenant->fresh()->ai_token_balance)->toBe(100_000)
        ->and($order->fresh()->status)->toBe(AiTokenOrder::STATUS_COMPLETED)
        ->and($order->fresh()->credited_at)->not->toBeNull()
        ->and(AiTokenLedger::where('ai_token_order_id', $order->id)->where('type', 'credit')->count())->toBe(1);

    // Idempotent: a duplicate webhook does not credit again.
    postJson('/api/v1/pakasir/webhook', webhookPayload($order))->assertOk();

    expect($order->tenant->fresh()->ai_token_balance)->toBe(100_000)
        ->and(AiTokenLedger::where('ai_token_order_id', $order->id)->where('type', 'credit')->count())->toBe(1);
});

it('rejects a webhook whose amount does not match the order', function (): void {
    $order = pendingOrder(50_000);

    postJson('/api/v1/pakasir/webhook', webhookPayload($order, ['amount' => 999]))
        ->assertStatus(422);

    expect($order->tenant->fresh()->ai_token_balance)->toBe(0);
});

it('rejects a webhook for the wrong project', function (): void {
    $order = pendingOrder();

    postJson('/api/v1/pakasir/webhook', webhookPayload($order, ['project' => 'someoneelse']))
        ->assertStatus(422);

    expect($order->tenant->fresh()->ai_token_balance)->toBe(0);
});

it('does not credit when server-side verification is not completed', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'pending']])]);

    $order = pendingOrder();

    postJson('/api/v1/pakasir/webhook', webhookPayload($order))
        ->assertStatus(422);

    expect($order->tenant->fresh()->ai_token_balance)->toBe(0)
        ->and($order->fresh()->credited_at)->toBeNull();
});

it('returns 404 for an unknown order', function (): void {
    postJson('/api/v1/pakasir/webhook', [
        'amount' => 1000,
        'order_id' => 'AIT-DOES-NOT-EXIST',
        'project' => 'avanahr',
        'status' => 'completed',
    ])->assertStatus(404);
});
