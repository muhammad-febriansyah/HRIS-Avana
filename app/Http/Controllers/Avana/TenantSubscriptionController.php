<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\SubscriptionOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PakasirGateway;
use App\Services\SubscriptionRenewalService;
use App\Support\SubscriptionStatus;
use App\Support\TenantQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Tenant-facing subscription renewal: see when the current term ends, pick a
 * package and a duration, pay through Pakasir, and get the term extended the
 * moment the payment is verified. Gated by the `langganan` permission (granted
 * to tenant admins, assignable to HR).
 */
class TenantSubscriptionController extends Controller
{
    /**
     * The permission module gating this controller.
     */
    private const MODULE = 'langganan';

    /**
     * Current standing, the pricing table, and this tenant's renewal history.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');
        $tenant = $this->tenant($request);
        $renewals = app(SubscriptionRenewalService::class);

        $packages = Package::query()
            ->where('is_active', true)
            ->with([
                'features' => fn ($query) => $query
                    ->wherePivot('is_enabled', true)
                    ->select('features.id', 'features.name', 'features.module_group')
                    ->orderBy('features.name'),
            ])
            ->orderBy('price')
            ->get()
            ->map(fn (Package $package): array => [
                // What the tier actually unlocks. An unscoped package grants the
                // whole catalogue, which the page words as "semua modul".
                'features' => $package->features->pluck('name')->all(),
                'grants_all_features' => $package->features->isEmpty(),
                'id' => $package->id,
                'name' => $package->name,
                'tagline' => $package->tagline,
                'code' => $package->code,
                'is_popular' => (bool) $package->is_popular,
                'is_current' => $package->id === $tenant->package_id,
                'max_users' => $package->max_users !== null ? (int) $package->max_users : null,
                'max_employees' => $package->max_employees !== null ? (int) $package->max_employees : null,
                'max_branches' => $package->max_branches !== null ? (int) $package->max_branches : null,
                'ai_token_quota' => $package->ai_token_quota !== null ? (int) $package->ai_token_quota : null,
                'feature_list' => $package->feature_list ?? [],
                'quotes' => $renewals->quotes($package),
            ]);

        $orders = SubscriptionOrder::query()
            ->forTenant($tenant->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (SubscriptionOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'package_name' => $order->package_name,
                'months' => $order->months,
                'amount' => $order->amount,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'period_end' => $order->period_end?->toDateString(),
                'applied' => $order->applied_at !== null,
                'created_at' => $order->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('avana/langganan/index', [
            'subscription' => SubscriptionStatus::forTenant($tenant),
            'tenant' => [
                'package' => $tenant->package?->name,
                'max_users' => (int) $tenant->max_users,
                'max_employees' => (int) $tenant->max_employees,
                'max_branches' => (int) $tenant->max_branches,
                'users_count' => TenantQuota::used($tenant, 'users'),
                'employees_count' => TenantQuota::used($tenant, 'employees'),
                'branches_count' => TenantQuota::used($tenant, 'branches'),
            ],
            'packages' => $packages->values()->all(),
            'terms' => array_map(
                fn (string $cycle): array => [
                    'cycle' => $cycle,
                    'label' => SubscriptionRenewalService::TERMS[$cycle]['label'],
                    'discount_percent' => (int) round(SubscriptionRenewalService::TERMS[$cycle]['discount'] * 100),
                ],
                array_keys(SubscriptionRenewalService::TERMS),
            ),
            'orders' => $orders->values()->all(),
            'invoices' => Invoice::forTenant($tenant->id)
                ->orderByDesc('issue_date')
                ->limit(10)
                ->get(['id', 'invoice_number', 'total', 'status', 'issue_date', 'period_end'])
                ->map(fn (Invoice $invoice): array => [
                    'number' => $invoice->invoice_number,
                    'total' => (float) $invoice->total,
                    'status' => $invoice->status,
                    'issue_date' => $invoice->issue_date?->toDateString(),
                    'period_end' => $invoice->period_end?->toDateString(),
                ])->all(),
        ]);
    }

    /**
     * The lock notice a lapsed tenant lands on. Reachable by every role — an
     * employee cannot renew, but they must be told why the app went dark — so it
     * carries no permission check.
     */
    public function locked(Request $request): Response
    {
        $user = $request->user();
        $tenant = $this->tenant($request);

        return Inertia::render('avana/langganan/locked', [
            'subscription' => SubscriptionStatus::forTenant($tenant),
            'canRenew' => $user->isSuperAdmin() || $user->hasPermissionTo(self::MODULE.'.view'),
            'tenantName' => $tenant->company_name ?? $tenant->name,
        ]);
    }

    /**
     * Create a pending renewal order and hand the buyer off to Pakasir checkout.
     *
     * Returns a Symfony response, not a redirect: {@see Inertia::location()}
     * emits a 409 with `X-Inertia-Location` for an Inertia request (and a plain
     * away-redirect otherwise), so the return type must cover both.
     */
    public function purchase(Request $request): SymfonyResponse
    {
        $this->ensureCan($request, 'create');
        $tenant = $this->tenant($request);

        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'cycle' => ['required', 'string', Rule::in(array_keys(SubscriptionRenewalService::TERMS))],
        ]);

        $package = Package::query()->where('is_active', true)->findOrFail($data['package_id']);
        $quote = app(SubscriptionRenewalService::class)->quote($package, $data['cycle']);

        if ($quote['price'] <= 0) {
            return back()->with('error', 'Paket ini belum memiliki harga. Hubungi tim AvanaHR.');
        }

        $order = SubscriptionOrder::create([
            'order_number' => 'SUB-'.$tenant->id.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'package_id' => $package->id,
            'package_name' => $package->name,
            'billing_cycle' => $quote['cycle'],
            'months' => $quote['months'],
            'amount' => $quote['price'],
            'status' => SubscriptionOrder::STATUS_PENDING,
        ]);

        $returnUrl = route('avana.langganan.callback', ['order' => $order->order_number]);
        $payUrl = app(PakasirGateway::class)->payUrl($order->order_number, (int) $order->amount, $returnUrl);

        return Inertia::location($payUrl);
    }

    /**
     * Browser return from Pakasir. Verifies server-side and applies the renewal
     * optimistically (the webhook remains the source of truth if the buyer never
     * comes back).
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'view');
        $tenant = $this->tenant($request);

        $order = SubscriptionOrder::query()
            ->forTenant($tenant->id)
            ->where('order_number', (string) $request->query('order'))
            ->first();

        if ($order === null) {
            return redirect()->route('avana.langganan')->with('error', 'Pesanan perpanjangan tidak ditemukan.');
        }

        if ($order->applied_at !== null) {
            return redirect()->route('avana.langganan')->with('success', 'Langganan sudah diperpanjang.');
        }

        $gateway = app(PakasirGateway::class);
        $transaction = $gateway->verify($order->order_number, (int) $order->amount);

        if (! $gateway->isCompleted($transaction)) {
            return redirect()->route('avana.langganan')
                ->with('info', 'Pembayaran belum selesai. Langganan akan diperpanjang otomatis setelah pembayaran dikonfirmasi.');
        }

        $order->update([
            'status' => SubscriptionOrder::STATUS_COMPLETED,
            'payment_method' => $transaction['payment_method'] ?? null,
            'completed_at' => now(),
            'raw_payload' => $transaction,
        ]);

        app(SubscriptionRenewalService::class)->apply($order);

        return redirect()->route('avana.langganan')->with('success', 'Pembayaran berhasil. Langganan Anda telah diperpanjang.');
    }

    /**
     * The acting tenant, aborting when there is none (super admin has no tenant).
     */
    private function tenant(Request $request): Tenant
    {
        $tenantId = $request->user()->tenant_id;

        abort_if($tenantId === null, 403, 'Fitur ini hanya untuk pengguna tenant.');

        return Tenant::query()->with('package:id,name')->findOrFail($tenantId);
    }

    /**
     * Abort with 403 unless the user is a super admin or holds the permission.
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}
