<?php

use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function billingTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Acme Corp',
        'slug' => Str::slug('acme-'.fake()->unique()->numberBetween(1, 99999)),
        'status' => 'active',
    ]);
}

function superAdmin(int $tenantId): User
{
    $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
    $user = User::factory()->create(['tenant_id' => $tenantId]);
    $user->roles()->attach($role->id);

    return $user;
}

function makeInvoice(int $tenantId, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'tenant_id' => $tenantId,
        'invoice_number' => 'INV-TEST-'.fake()->unique()->numberBetween(1000, 9999),
        'issue_date' => Carbon::today()->toDateString(),
        'due_date' => Carbon::today()->addDays(14)->toDateString(),
        'subtotal' => 1_000_000,
        'tax' => 0,
        'total' => 1_000_000,
        'status' => 'unpaid',
    ], $overrides));
}

it('notifies every super admin when an invoice is marked paid', function () {
    $tenant = billingTenant();
    $adminA = superAdmin($tenant->id);
    $adminB = superAdmin($tenant->id);
    $invoice = makeInvoice($tenant->id);

    $invoice->update(['status' => 'paid', 'paid_at' => now()]);

    foreach ([$adminA, $adminB] as $admin) {
        $note = Notification::where('user_id', $admin->id)->where('type', 'invoice')->first();
        expect($note)->not->toBeNull()
            ->and($note->title)->toBe('Invoice dibayar')
            ->and($note->data['event'])->toBe('paid')
            ->and($note->tenant_id)->toBe($tenant->id);
    }
});

it('excludes the acting super admin from the invoice-paid alert', function () {
    $tenant = billingTenant();
    $actor = superAdmin($tenant->id);
    $other = superAdmin($tenant->id);
    $invoice = makeInvoice($tenant->id, ['status' => 'paid', 'paid_at' => now()]);

    Notifier::invoicePaid($invoice, $actor->id);

    expect(Notification::where('user_id', $actor->id)->count())->toBe(0)
        ->and(Notification::where('user_id', $other->id)->where('type', 'invoice')->count())->toBe(1);
});

it('does not notify when a non-status field changes on the invoice', function () {
    $tenant = billingTenant();
    superAdmin($tenant->id);
    $invoice = makeInvoice($tenant->id);

    $invoice->update(['notes' => 'catatan baru']);

    expect(Notification::where('type', 'invoice')->count())->toBe(0);
});

it('flags overdue invoices once and re-runs idempotently', function () {
    $tenant = billingTenant();
    $admin = superAdmin($tenant->id);
    $invoice = makeInvoice($tenant->id, [
        'due_date' => Carbon::today()->subDay()->toDateString(),
    ]);

    $this->artisan('avana:remind-billing')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('overdue')
        ->and(Notification::where('user_id', $admin->id)->where('type', 'invoice')->where('data->event', 'overdue')->count())->toBe(1);

    $this->artisan('avana:remind-billing')->assertSuccessful();

    expect(Notification::where('user_id', $admin->id)->where('data->event', 'overdue')->count())->toBe(1);
});

it('alerts super admins about subscriptions expiring within the window and dedupes', function () {
    $tenant = billingTenant();
    $admin = superAdmin($tenant->id);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'price' => 500_000,
        'start_date' => Carbon::today()->subMonths(11)->toDateString(),
        'end_date' => Carbon::today()->addDays(3)->toDateString(),
    ]);

    $this->artisan('avana:remind-billing')->assertSuccessful();
    $this->artisan('avana:remind-billing')->assertSuccessful();

    expect(Notification::where('user_id', $admin->id)->where('type', 'subscription')->where('data->event', 'expiring')->count())->toBe(1);
});

it('does not alert about subscriptions ending beyond the window', function () {
    $tenant = billingTenant();
    $admin = superAdmin($tenant->id);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'price' => 500_000,
        'end_date' => Carbon::today()->addDays(30)->toDateString(),
    ]);

    $this->artisan('avana:remind-billing')->assertSuccessful();

    expect(Notification::where('user_id', $admin->id)->where('type', 'subscription')->count())->toBe(0);
});

it('notifies super admins when a subscription slips to past_due', function () {
    $tenant = billingTenant();
    $admin = superAdmin($tenant->id);

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'price' => 500_000,
    ]);

    $subscription->update(['status' => 'past_due']);

    $note = Notification::where('user_id', $admin->id)->where('type', 'subscription')->first();
    expect($note)->not->toBeNull()
        ->and($note->title)->toBe('Langganan menunggak')
        ->and($note->data['event'])->toBe('past_due');
});
