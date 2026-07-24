<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiRoleTokenCap;
use App\Models\AiTokenLedger;
use App\Models\AiTokenOrder;
use App\Models\AiTokenPack;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiTokenService;
use App\Services\PakasirGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Tenant-facing AI token top-up: buy packs (paid via Pakasir), see the wallet &
 * usage meter and order history, and configure per-user monthly caps. Gated by
 * the `ai_topup` permission (granted to tenant admins, assignable to HR).
 */
class TenantAiTokenController extends Controller
{
    /**
     * The permission module gating this controller.
     */
    private const MODULE = 'ai_topup';

    /**
     * Wallet, usage meter, packs, order history and allocation config.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');
        $user = $request->user();
        $tenantId = $this->tenantId($request);

        $tenant = Tenant::query()->findOrFail($tenantId);

        $packs = AiTokenPack::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (AiTokenPack $pack): array => [
                'id' => $pack->id,
                'name' => $pack->name,
                'token_amount' => $pack->token_amount,
                'price' => $pack->price,
                'description' => $pack->description,
            ]);

        $orders = AiTokenOrder::query()
            ->forTenant($tenantId)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AiTokenOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'pack_name' => $order->pack_name,
                'token_amount' => $order->token_amount,
                'amount' => $order->amount,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('avana/token-ai/index', [
            'usage' => app(AiTokenService::class)->remainingForUser($user),
            'defaultUserCap' => $tenant->ai_token_user_cap !== null ? (int) $tenant->ai_token_user_cap : null,
            'packs' => $packs->values()->all(),
            'orders' => $orders->values()->all(),
            'roles' => $this->roles($tenantId),
        ]);
    }

    /**
     * Create a pending order and hand the buyer off to Pakasir checkout.
     *
     * Returns a Symfony response, not a redirect: {@see Inertia::location()}
     * emits a 409 with `X-Inertia-Location` for an Inertia request (and a plain
     * away-redirect otherwise), so the return type must cover both.
     */
    public function purchase(Request $request): SymfonyResponse
    {
        $this->ensureCan($request, 'create');
        $user = $request->user();
        $tenantId = $this->tenantId($request);

        $data = $request->validate([
            'pack_id' => ['required', 'integer', 'exists:ai_token_packs,id'],
        ]);

        $pack = AiTokenPack::query()->active()->findOrFail($data['pack_id']);

        $orderNumber = 'AIT-'.$tenantId.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));

        $order = AiTokenOrder::create([
            'order_number' => $orderNumber,
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'ai_token_pack_id' => $pack->id,
            'pack_name' => $pack->name,
            'token_amount' => $pack->token_amount,
            'amount' => $pack->price,
            'status' => AiTokenOrder::STATUS_PENDING,
        ]);

        $returnUrl = route('avana.token-ai.callback', ['order' => $order->order_number]);
        $payUrl = app(PakasirGateway::class)->payUrl($order->order_number, (int) $order->amount, $returnUrl);

        return Inertia::location($payUrl);
    }

    /**
     * Browser return from Pakasir. Verifies server-side and credits optimistically
     * (the webhook remains the source of truth if the user never returns).
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'view');
        $tenantId = $this->tenantId($request);

        $order = AiTokenOrder::query()
            ->forTenant($tenantId)
            ->where('order_number', (string) $request->query('order'))
            ->first();

        if ($order === null) {
            return redirect()->route('avana.token-ai')->with('error', 'Pesanan token tidak ditemukan.');
        }

        if ($order->credited_at !== null) {
            return redirect()->route('avana.token-ai')->with('success', 'Token telah ditambahkan ke saldo.');
        }

        $gateway = app(PakasirGateway::class);
        $transaction = $gateway->verify($order->order_number, (int) $order->amount);

        if ($gateway->isCompleted($transaction)) {
            $order->update([
                'status' => AiTokenOrder::STATUS_COMPLETED,
                'payment_method' => $transaction['payment_method'] ?? null,
                'completed_at' => now(),
                'raw_payload' => $transaction,
            ]);

            app(AiTokenService::class)->creditWallet($order);

            return redirect()->route('avana.token-ai')->with('success', 'Pembayaran berhasil. Token telah ditambahkan ke saldo.');
        }

        return redirect()->route('avana.token-ai')->with('info', 'Pembayaran belum selesai. Saldo akan diperbarui otomatis setelah pembayaran dikonfirmasi.');
    }

    /**
     * Save the tenant default cap and per-user overrides.
     */
    public function updateAllocation(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $tenantId = $this->tenantId($request);

        $data = $request->validate([
            'default_user_cap' => ['nullable', 'integer', 'min:0'],
            'caps' => ['array'],
            'caps.*.role_id' => ['required', 'integer', 'exists:roles,id'],
            'caps.*.monthly_cap' => ['nullable', 'integer', 'min:0'],
        ]);

        Tenant::query()->whereKey($tenantId)->update([
            'ai_token_user_cap' => $data['default_user_cap'] ?? null,
        ]);

        foreach ($data['caps'] ?? [] as $row) {
            $belongs = Role::query()
                ->whereKey($row['role_id'])
                ->where('tenant_id', $tenantId)
                ->exists();

            if (! $belongs) {
                continue;
            }

            $cap = $row['monthly_cap'] ?? null;

            if ($cap === null) {
                AiRoleTokenCap::query()->where('role_id', $row['role_id'])->delete();

                continue;
            }

            AiRoleTokenCap::updateOrCreate(
                ['role_id' => $row['role_id']],
                ['tenant_id' => $tenantId, 'monthly_cap' => $cap],
            );
        }

        return back()->with('success', 'Alokasi token per role disimpan');
    }

    /**
     * Tenant roles with their configured cap, member count and current-month
     * usage (summed across the role's members).
     *
     * @return array<int, array<string, mixed>>
     */
    private function roles(int $tenantId): array
    {
        $period = now()->format('Y-m');

        $capByRole = AiRoleTokenCap::query()
            ->where('tenant_id', $tenantId)
            ->pluck('monthly_cap', 'role_id');

        $usageByUser = AiTokenLedger::query()
            ->where('tenant_id', $tenantId)
            ->where('type', AiTokenLedger::TYPE_DEBIT)
            ->where('period', $period)
            ->selectRaw('user_id, SUM(tokens) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return Role::query()
            ->where('tenant_id', $tenantId)
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(function (Role $role) use ($capByRole, $usageByUser): array {
                $memberIds = $role->users()->pluck('users.id');
                $used = (int) $memberIds->sum(fn ($id): int => (int) ($usageByUser->get($id) ?? 0));

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                    'members' => $role->users_count,
                    'cap' => $capByRole->has($role->id) ? (int) $capByRole->get($role->id) : null,
                    'used' => $used,
                ];
            })
            ->all();
    }

    /**
     * The acting tenant, aborting when there is none (super admin has no tenant).
     */
    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()->tenant_id;

        abort_if($tenantId === null, 403, 'Fitur ini hanya untuk pengguna tenant.');

        return (int) $tenantId;
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
