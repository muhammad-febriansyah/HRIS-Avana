<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Two months of platform billing for the demo tenant, telling a story the
 * Billing & Invoice screen can actually be walked through: last month's invoice
 * was settled late, and this month's has gone past its due date.
 *
 * Kept out of {@see AvanaDemoSeeder} on purpose — invoice counts are asserted in
 * tests, and a demo fixture should not move them. Run it on its own:
 *
 *     php artisan db:seed --class=BillingDemoSeeder
 *
 * Idempotent: re-running updates the same two invoices rather than stacking up
 * duplicates.
 */
class BillingDemoSeeder extends Seeder
{
    /** Indonesian VAT applied to the platform subscription fee. */
    private const VAT_RATE = 0.11;

    public function run(): void
    {
        $tenant = Tenant::query()->orderBy('id')->first();

        if ($tenant === null) {
            $this->command?->warn('Tidak ada tenant — jalankan AvanaDemoSeeder dulu.');

            return;
        }

        $package = Package::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->skip(1)
            ->first()
            ?? Package::query()->where('is_active', true)->orderBy('price')->first();

        if ($package === null) {
            $this->command?->warn('Tidak ada paket aktif — tidak bisa membuat langganan.');

            return;
        }

        // The tenant pointed at a package that no longer exists, which left the
        // billing screen unable to name the tier it is being charged for.
        if ($tenant->package_id !== $package->id && ! Package::query()->whereKey($tenant->package_id)->exists()) {
            $tenant->forceFill(['package_id' => $package->id])->save();
        }

        $price = (float) $package->price;

        $subscription = Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'package_id' => $package->id],
            [
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'price' => $price,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
        );

        $this->invoice($tenant, $subscription, $package->name, [
            'number' => 'INV-202606-0001',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'status' => 'paid',
            // Settled two and a half weeks late — the reason the paid figure
            // lands in July even though the period is June.
            'paid_at' => '2026-07-03 10:24:00',
            'notes' => 'Dibayar via transfer BCA a.n. PT Nusantara Jaya. Terlambat 18 hari.',
            'label' => 'Juni 2026',
        ], $price);

        $this->invoice($tenant, $subscription, $package->name, [
            'number' => 'INV-202607-0001',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
            'status' => 'overdue',
            'paid_at' => null,
            'notes' => 'Pengingat terkirim 16 Juli dan 23 Juli 2026. Belum ada pembayaran masuk.',
            'label' => 'Juli 2026',
        ], $price);

        $this->command?->info('2 invoice + 1 langganan demo dibuat untuk '.$tenant->name.'.');
    }

    /**
     * Write one invoice and its single subscription line.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function invoice(
        Tenant $tenant,
        Subscription $subscription,
        string $packageName,
        array $attributes,
        float $price,
    ): void {
        $tax = round($price * self::VAT_RATE, 2);

        $invoice = Invoice::query()->updateOrCreate(
            ['invoice_number' => $attributes['number']],
            [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'period_start' => $attributes['period_start'],
                'period_end' => $attributes['period_end'],
                'issue_date' => $attributes['issue_date'],
                'due_date' => $attributes['due_date'],
                'subtotal' => $price,
                'tax' => $tax,
                'total' => $price + $tax,
                'status' => $attributes['status'],
                'paid_at' => $attributes['paid_at'],
                'notes' => $attributes['notes'],
            ],
        );

        $invoice->items()->delete();
        $invoice->items()->create([
            'description' => 'Langganan '.$packageName.' — '.$attributes['label'],
            'quantity' => 1,
            'unit_price' => $price,
            'amount' => $price,
        ]);
    }
}
