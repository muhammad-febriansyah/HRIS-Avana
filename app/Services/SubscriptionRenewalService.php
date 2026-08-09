<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\Tenant;
use App\Support\Notifier;
use App\Support\SubscriptionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Prices a self-service renewal and applies it once the payment clears.
 *
 * Pricing is derived from the package's own list price: whatever cycle it is
 * quoted in becomes a monthly rate, and a term is that rate times its months.
 * Terms longer than a month carry a discount so the yearly option is worth it.
 */
final class SubscriptionRenewalService
{
    /**
     * Selectable terms: months and the discount applied to the monthly rate.
     *
     * @var array<string, array{months: int, discount: float, label: string}>
     */
    public const TERMS = [
        'monthly' => ['months' => 1, 'discount' => 0.0, 'label' => '1 Bulan'],
        'quarterly' => ['months' => 3, 'discount' => 0.05, 'label' => '3 Bulan'],
        'yearly' => ['months' => 12, 'discount' => 0.15, 'label' => '1 Tahun'],
    ];

    /**
     * What a package costs for a given term, in whole rupiah.
     *
     * @return array{cycle: string, months: int, label: string, monthly_price: int, price: int, list_price: int, discount_percent: int}
     */
    public function quote(Package $package, string $cycle): array
    {
        $term = self::TERMS[$cycle] ?? self::TERMS['monthly'];
        $monthly = $this->monthlyPrice($package);
        $listPrice = $monthly * $term['months'];
        $price = (int) round($listPrice * (1 - $term['discount']));

        return [
            'cycle' => $cycle,
            'months' => $term['months'],
            'label' => $term['label'],
            'monthly_price' => $monthly,
            'price' => $price,
            'list_price' => $listPrice,
            'discount_percent' => (int) round($term['discount'] * 100),
        ];
    }

    /**
     * Every term priced for a package, for the pricing cards.
     *
     * @return array<int, array{cycle: string, months: int, label: string, monthly_price: int, price: int, list_price: int, discount_percent: int}>
     */
    public function quotes(Package $package): array
    {
        return array_values(array_map(
            fn (string $cycle): array => $this->quote($package, $cycle),
            array_keys(self::TERMS),
        ));
    }

    /**
     * Apply a paid renewal: extend the subscription, move the tenant onto the
     * package it paid for, and file a paid invoice. Idempotent — a second call
     * (webhook after browser callback, or a retried webhook) is a no-op.
     */
    public function apply(SubscriptionOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $fresh = SubscriptionOrder::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->applied_at !== null) {
                return;
            }

            $tenant = Tenant::query()->whereKey($fresh->tenant_id)->lockForUpdate()->first();

            if ($tenant === null) {
                return;
            }

            $package = $fresh->package_id !== null ? Package::find($fresh->package_id) : null;
            $start = $this->renewalStart($tenant);
            $end = $start->copy()->addMonthsNoOverflow($fresh->months);

            $subscription = $this->extendSubscription($tenant, $fresh, $package, $start, $end);
            $invoice = $this->fileInvoice($tenant, $fresh, $subscription, $start, $end);

            $this->moveTenantOntoPackage($tenant, $package, $end);

            $fresh->update([
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'applied_at' => now(),
            ]);

            // The lock gate memoises the term per request; a renewal applied in
            // this request must leave the tenant unlocked, not still expired.
            SubscriptionStatus::forget($tenant->id);

            Notifier::invoicePaid($invoice, $fresh->user_id);
            Notifier::tenantSubscriptionRenewed(
                $tenant,
                $end->locale('id')->translatedFormat('d F Y'),
                $fresh->package_name,
            );
        });
    }

    /**
     * Where the new term starts: the day after the current one ends, or today
     * when the subscription has already lapsed (no free backdated days, and no
     * lost days when renewing early).
     */
    private function renewalStart(Tenant $tenant): Carbon
    {
        $current = Subscription::query()
            ->forTenant($tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('end_date')
            ->orderByDesc('end_date')
            ->value('end_date') ?? $tenant->end_date;

        $today = Carbon::today();

        if ($current === null) {
            return $today;
        }

        $current = Carbon::parse($current)->startOfDay();

        return $current->greaterThan($today) ? $current : $today;
    }

    /**
     * Extend the tenant's current subscription, or open one when they have none.
     * A renewal on a different package also switches the row over to it.
     */
    private function extendSubscription(
        Tenant $tenant,
        SubscriptionOrder $order,
        ?Package $package,
        Carbon $start,
        Carbon $end,
    ): Subscription {
        $subscription = Subscription::query()
            ->forTenant($tenant->id)
            ->whereIn('status', ['active', 'trial', 'past_due'])
            ->orderByDesc('end_date')
            ->first();

        $attributes = [
            'package_id' => $package?->id ?? $subscription?->package_id,
            'status' => 'active',
            'billing_cycle' => $order->billing_cycle,
            'price' => $order->amount,
            'end_date' => $end->toDateString(),
        ];

        if ($subscription === null) {
            return Subscription::create([
                'tenant_id' => $tenant->id,
                'start_date' => $start->toDateString(),
                ...$attributes,
            ]);
        }

        $subscription->update($attributes);

        return $subscription;
    }

    /**
     * File the paid invoice for the term, so the client's billing history and the
     * platform's revenue reporting both show the self-service payment.
     */
    private function fileInvoice(
        Tenant $tenant,
        SubscriptionOrder $order,
        Subscription $subscription,
        Carbon $start,
        Carbon $end,
    ): Invoice {
        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => Invoice::nextNumber(),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => $order->amount,
            'tax' => 0,
            'total' => $order->amount,
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => 'Perpanjangan mandiri via Pakasir · pesanan '.$order->order_number,
        ]);

        $invoice->items()->create([
            'description' => 'Langganan '.$order->package_name.' — '.$order->months.' bulan ('
                .$start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y').')',
            'quantity' => 1,
            'unit_price' => $order->amount,
            'amount' => $order->amount,
        ]);

        return $invoice;
    }

    /**
     * Put the tenant on the paid package: new end date, live status, and the
     * package's own seat/branch/token ceilings. A package that leaves a limit
     * unset keeps the tenant's current one rather than dropping it to zero.
     */
    private function moveTenantOntoPackage(Tenant $tenant, ?Package $package, Carbon $end): void
    {
        $attributes = [
            'end_date' => $end->toDateString(),
            'status' => 'active',
            'billing_status' => 'active',
        ];

        if ($package !== null) {
            $attributes['package_id'] = $package->id;

            foreach (['max_users', 'max_employees', 'max_branches', 'ai_token_quota'] as $limit) {
                if ($package->{$limit} !== null) {
                    $attributes[$limit] = $package->{$limit};
                }
            }
        }

        $tenant->update($attributes);

        // The paid tier decides which modules the tenant keeps: buying a smaller
        // package narrows them, buying a bigger one opens the rest.
        if ($package !== null) {
            $tenant->setRelation('package', $package);
            app(TenantProvisioner::class)->applyPackageFeatures($tenant);
        }
    }

    /**
     * The package's list price expressed per month.
     */
    private function monthlyPrice(Package $package): int
    {
        $price = (float) $package->price;

        return (int) round(match ($package->billing_cycle) {
            'yearly' => $price / 12,
            'quarterly' => $price / 3,
            default => $price,
        });
    }
}
