<?php

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::query()->firstOrFail();
});

/**
 * Create a subscription for the demo tenant.
 */
function makeSubscription(int $tenantId, array $overrides = []): Subscription
{
    return Subscription::create(array_merge([
        'tenant_id' => $tenantId,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'price' => 1500000,
        'start_date' => '2026-01-01',
    ], $overrides));
}

it('renders the billing dashboard for a super admin', function (): void {
    makeSubscription($this->tenant->id);

    actingAs($this->superAdmin)
        ->get(route('avana.billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/billing/index', false)
            ->has('invoices')
            ->has('subscriptions.0')
            ->has('tenants')
            ->has('packages')
            ->has('kpis.outstanding')
            ->has('kpis.mrr'));
});

it('forbids a non super admin from the billing dashboard', function (): void {
    actingAs($this->admin)
        ->get(route('avana.billing'))
        ->assertForbidden();
});

it('creates a subscription', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.billing.subscription.store'), [
            'tenant_id' => $this->tenant->id,
            'package_id' => null,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'price' => 2000000,
            'start_date' => '2026-07-01',
            'end_date' => null,
        ])
        ->assertSessionHas('success');

    expect(Subscription::where('tenant_id', $this->tenant->id)->where('price', 2000000)->exists())->toBeTrue();
});

it('generates an invoice from a subscription', function (): void {
    $subscription = makeSubscription($this->tenant->id, ['price' => 1500000]);

    actingAs($this->superAdmin)
        ->post(route('avana.billing.invoice.generate', $subscription))
        ->assertRedirect(route('avana.billing'))
        ->assertSessionHas('success');

    $invoice = Invoice::where('subscription_id', $subscription->id)->firstOrFail();

    expect($invoice->total)->toBe('1500000.00');
    expect($invoice->invoice_number)->toStartWith('INV-');
    expect($invoice->items()->count())->toBe(1);
});

it('creates a manual invoice and computes totals', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.billing.invoice.store'), [
            'tenant_id' => $this->tenant->id,
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
            'tax' => 110000,
            'items' => [
                ['description' => 'Langganan Pro', 'quantity' => 1, 'unit_price' => 1000000],
                ['description' => 'Add-on user', 'quantity' => 2, 'unit_price' => 50000],
            ],
        ])
        ->assertRedirect(route('avana.billing'))
        ->assertSessionHas('success');

    $invoice = Invoice::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();

    expect($invoice->subtotal)->toBe('1100000.00');
    expect($invoice->total)->toBe('1210000.00');
    expect($invoice->items()->count())->toBe(2);
});

it('validates invoice items are required', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.billing.invoice.store'), [
            'tenant_id' => $this->tenant->id,
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
            'items' => [],
        ])
        ->assertSessionHasErrors(['items']);
});

it('marks an invoice as paid', function (): void {
    $subscription = makeSubscription($this->tenant->id);
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'subscription_id' => $subscription->id,
        'invoice_number' => 'INV-202607-0001',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'subtotal' => 1500000,
        'total' => 1500000,
        'status' => 'unpaid',
    ]);

    actingAs($this->superAdmin)
        ->post(route('avana.billing.invoice.pay', $invoice))
        ->assertSessionHas('success');

    $invoice->refresh();

    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_at)->not->toBeNull();
});

it('cancels and deletes an invoice', function (): void {
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'invoice_number' => 'INV-202607-0002',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'subtotal' => 500000,
        'total' => 500000,
        'status' => 'unpaid',
    ]);

    actingAs($this->superAdmin)
        ->post(route('avana.billing.invoice.cancel', $invoice))
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe('cancelled');

    actingAs($this->superAdmin)
        ->delete(route('avana.billing.invoice.destroy', $invoice))
        ->assertSessionHas('success');

    expect(Invoice::find($invoice->id))->toBeNull();
});

it('renders the printable invoice', function (): void {
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'invoice_number' => 'INV-202607-0003',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'subtotal' => 750000,
        'total' => 750000,
        'status' => 'unpaid',
    ]);
    $invoice->items()->create(['description' => 'Langganan', 'quantity' => 1, 'unit_price' => 750000, 'amount' => 750000]);

    $response = actingAs($this->superAdmin)->get(route('avana.billing.invoice.print', $invoice));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
