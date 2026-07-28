<?php

use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->tenantAdmin->tenant_id);
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    $this->package = Package::create([
        'name' => 'Pro',
        'code' => 'pro-test',
        'price' => 1_000_000,
        'billing_cycle' => 'monthly',
        'max_users' => 50,
        'max_employees' => 500,
        'max_branches' => 10,
        'ai_token_quota' => 750_000,
        'is_active' => true,
    ]);

    $this->tenant->update(['end_date' => now()->addDays(5)->toDateString()]);
});

it('renders the langganan page with the current term and priced packages', function (): void {
    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/langganan/index', false)
            ->where('subscription.days_left', 5)
            ->has('terms', 3)
            ->has('packages')
            ->has('orders')
            ->has('invoices'));
});

it('prices a yearly term at twelve months less the discount', function (): void {
    $quotes = collect(
        actingAs($this->tenantAdmin)
            ->get(route('avana.langganan'))
            ->viewData('page')['props']['packages']
    )->firstWhere('name', 'Pro')['quotes'];

    $yearly = collect($quotes)->firstWhere('cycle', 'yearly');

    // 1.000.000/bln × 12 = 12.000.000, less 15%.
    expect($yearly['months'])->toBe(12)
        ->and($yearly['list_price'])->toBe(12_000_000)
        ->and($yearly['price'])->toBe(10_200_000);
});

it('creates a pending renewal order and redirects to the Pakasir checkout', function (): void {
    $response = actingAs($this->tenantAdmin)
        ->post(route('avana.langganan.purchase'), [
            'package_id' => $this->package->id,
            'cycle' => 'quarterly',
        ]);

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    expect($order->status)->toBe(SubscriptionOrder::STATUS_PENDING)
        ->and($order->months)->toBe(3)
        ->and($order->amount)->toBe(2_850_000) // 3 × 1.000.000 less 5%
        ->and($order->tenant_id)->toBe($this->tenant->id)
        ->and($order->applied_at)->toBeNull();

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain("/pay/avanahr/2850000?order_id={$order->order_number}");
});

it('handles an Inertia purchase request with a location redirect (not a 500)', function (): void {
    $response = actingAs($this->tenantAdmin)
        ->withHeader('X-Inertia', 'true')
        ->post(route('avana.langganan.purchase'), [
            'package_id' => $this->package->id,
            'cycle' => 'monthly',
        ]);

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toContain('/pay/avanahr/1000000');
});

it('extends the term from the current end date once the payment verifies', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed', 'payment_method' => 'qris']])]);

    actingAs($this->tenantAdmin)
        ->post(route('avana.langganan.purchase'), [
            'package_id' => $this->package->id,
            'cycle' => 'monthly',
        ]);

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan.callback', ['order' => $order->order_number]))
        ->assertRedirect(route('avana.langganan'))
        ->assertSessionHas('success');

    // Renewing early keeps the unused days: the new term starts where the old ended.
    $expectedEnd = now()->addDays(5)->addMonthNoOverflow()->toDateString();
    $tenant = $this->tenant->fresh();

    expect($tenant->end_date->toDateString())->toBe($expectedEnd)
        ->and((int) $tenant->package_id)->toBe($this->package->id)
        ->and((int) $tenant->max_employees)->toBe(500)
        ->and($tenant->status)->toBe('active');

    $order->refresh();

    expect($order->applied_at)->not->toBeNull()
        ->and($order->period_end->toDateString())->toBe($expectedEnd);

    $subscription = Subscription::forTenant($this->tenant->id)->where('status', 'active')->latest('id')->firstOrFail();

    expect($subscription->end_date->toDateString())->toBe($expectedEnd);

    $invoice = Invoice::whereKey($order->invoice_id)->firstOrFail();

    expect($invoice->status)->toBe('paid')
        ->and((float) $invoice->total)->toBe(1_000_000.0)
        ->and($invoice->items()->count())->toBe(1);
});

it('starts a lapsed tenant from today rather than backdating the term', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed']])]);

    $this->tenant->update(['end_date' => now()->subDays(10)->toDateString()]);
    Subscription::forTenant($this->tenant->id)->delete();

    actingAs($this->tenantAdmin)
        ->post(route('avana.langganan.purchase'), [
            'package_id' => $this->package->id,
            'cycle' => 'monthly',
        ]);

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan.callback', ['order' => $order->order_number]))
        ->assertRedirect(route('avana.langganan'));

    expect($this->tenant->fresh()->end_date->toDateString())
        ->toBe(now()->addMonthNoOverflow()->toDateString());
});

it('applies a renewal once when both the webhook and the callback fire', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed']])]);

    actingAs($this->tenantAdmin)
        ->post(route('avana.langganan.purchase'), [
            'package_id' => $this->package->id,
            'cycle' => 'monthly',
        ]);

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    postJson('/api/v1/pakasir/webhook', [
        'amount' => $order->amount,
        'order_id' => $order->order_number,
        'project' => 'avanahr',
        'status' => 'completed',
        'payment_method' => 'qris',
    ])->assertOk()->assertJson(['ok' => true]);

    $endAfterWebhook = $this->tenant->fresh()->end_date->toDateString();

    // The buyer then returns from Pakasir — the term must not move a second time.
    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan.callback', ['order' => $order->order_number]))
        ->assertRedirect(route('avana.langganan'));

    expect($this->tenant->fresh()->end_date->toDateString())->toBe($endAfterWebhook)
        ->and(Invoice::where('notes', 'like', '%'.$order->order_number.'%')->count())->toBe(1);
});

it('leaves the term alone while the payment is unconfirmed', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'pending']])]);

    actingAs($this->tenantAdmin)
        ->post(route('avana.langganan.purchase'), [
            'package_id' => $this->package->id,
            'cycle' => 'monthly',
        ]);

    $order = SubscriptionOrder::latest('id')->firstOrFail();
    $before = $this->tenant->fresh()->end_date->toDateString();

    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan.callback', ['order' => $order->order_number]))
        ->assertRedirect(route('avana.langganan'))
        ->assertSessionHas('info');

    expect($this->tenant->fresh()->end_date->toDateString())->toBe($before)
        ->and($order->fresh()->applied_at)->toBeNull();
});

it('notifies the tenant admin that the subscription was renewed', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed']])]);

    actingAs($this->tenantAdmin)
        ->post(route('avana.langganan.purchase'), [
            'package_id' => $this->package->id,
            'cycle' => 'monthly',
        ]);

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan.callback', ['order' => $order->order_number]));

    $note = Notification::where('user_id', $this->tenantAdmin->id)
        ->where('type', 'subscription_renewed')
        ->first();

    expect($note)->not->toBeNull()
        ->and($note->body)->toContain('Pro');
});

it('forbids a user without the langganan permission', function (): void {
    actingAs($this->employee)
        ->get(route('avana.langganan'))
        ->assertForbidden();

    actingAs($this->employee)
        ->post(route('avana.langganan.purchase'), [
            'package_id' => $this->package->id,
            'cycle' => 'monthly',
        ])
        ->assertForbidden();
});
