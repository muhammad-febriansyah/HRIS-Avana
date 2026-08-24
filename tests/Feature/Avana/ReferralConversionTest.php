<?php

use App\Models\Invoice;
use App\Models\Partner;
use App\Models\ReferralConversion;
use App\Models\ReferralLedger;
use App\Models\ReferralSetting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();

    ReferralSetting::current()->update([
        'mode' => 'flat',
        'points_per_conversion' => 10,
        'point_value' => 5000,
        'hold_days' => 7,
        'min_withdrawal_points' => 5,
    ]);

    $partnerUser = User::create([
        'tenant_id' => null,
        'name' => 'Budi Mitra',
        'email' => 'budi.mitra@example.com',
        'password' => 'password',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $this->partner = Partner::create([
        'user_id' => $partnerUser->id,
        'code' => 'BUDI01',
        'status' => 'active',
    ]);

    $this->tenant = Tenant::create([
        'name' => 'PT Referral Test',
        'slug' => 'pt-referral-test-'.uniqid(),
        'partner_id' => $this->partner->id,
        'status' => 'trial',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
});

function makeReferralInvoice(int $tenantId, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'tenant_id' => $tenantId,
        'invoice_number' => 'INV-TEST-'.uniqid(),
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'subtotal' => 1000000,
        'total' => 1000000,
        'status' => 'unpaid',
    ], $overrides));
}

it('credits a pending referral conversion when a partner tenant\'s first invoice is paid', function (): void {
    $invoice = makeReferralInvoice($this->tenant->id);

    actingAs($this->superAdmin)
        ->post(route('avana.billing.invoice.pay', $invoice))
        ->assertSessionHas('success');

    $conversion = ReferralConversion::where('tenant_id', $this->tenant->id)->first();

    expect($conversion)->not->toBeNull();
    expect($conversion->partner_id)->toBe($this->partner->id);
    expect($conversion->points)->toBe(10);
    expect((float) $conversion->commission_amount)->toBe(50000.0);
    expect($conversion->status)->toBe(ReferralConversion::STATUS_PENDING);
    // Still just a hold — nothing lands in the ledger until it clears.
    expect(ReferralLedger::where('partner_id', $this->partner->id)->count())->toBe(0);
});

it('does not credit a second conversion for the same tenant\'s next invoice', function (): void {
    $first = makeReferralInvoice($this->tenant->id, ['invoice_number' => 'INV-TEST-A']);
    actingAs($this->superAdmin)->post(route('avana.billing.invoice.pay', $first));

    $second = makeReferralInvoice($this->tenant->id, ['invoice_number' => 'INV-TEST-B']);
    actingAs($this->superAdmin)->post(route('avana.billing.invoice.pay', $second));

    expect(ReferralConversion::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

it('credits nothing for a tenant with no attributed partner', function (): void {
    $tenant = Tenant::create([
        'name' => 'PT Tanpa Mitra',
        'slug' => 'pt-tanpa-mitra-'.uniqid(),
        'status' => 'trial',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $invoice = makeReferralInvoice($tenant->id);

    actingAs($this->superAdmin)->post(route('avana.billing.invoice.pay', $invoice));

    expect(ReferralConversion::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

it('voids a still-pending conversion when its invoice is cancelled, touching no ledger row', function (): void {
    $invoice = makeReferralInvoice($this->tenant->id);
    actingAs($this->superAdmin)->post(route('avana.billing.invoice.pay', $invoice));

    actingAs($this->superAdmin)->post(route('avana.billing.invoice.cancel', $invoice));

    $conversion = ReferralConversion::where('tenant_id', $this->tenant->id)->first();

    expect($conversion->status)->toBe(ReferralConversion::STATUS_VOID);
    expect(ReferralLedger::where('partner_id', $this->partner->id)->count())->toBe(0);
});

it('leaves a conversion pending until its hold date, then releases it into the ledger', function (): void {
    $invoice = makeReferralInvoice($this->tenant->id);
    actingAs($this->superAdmin)->post(route('avana.billing.invoice.pay', $invoice));

    $conversion = ReferralConversion::where('tenant_id', $this->tenant->id)->first();

    artisan('referral:release-holds');
    expect($conversion->fresh()->status)->toBe(ReferralConversion::STATUS_PENDING);

    $conversion->update(['hold_until' => Carbon::yesterday()]);
    artisan('referral:release-holds');
    $conversion->refresh();

    expect($conversion->status)->toBe(ReferralConversion::STATUS_APPROVED);

    $ledger = ReferralLedger::where('partner_id', $this->partner->id)->first();

    expect($ledger)->not->toBeNull();
    expect($ledger->type)->toBe(ReferralLedger::TYPE_EARN);
    expect($ledger->points)->toBe(10);
    expect($ledger->balance_after)->toBe(10);
    expect($this->partner->fresh()->balancePoints())->toBe(10);
});
