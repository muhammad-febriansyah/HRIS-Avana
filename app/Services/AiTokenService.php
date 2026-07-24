<?php

namespace App\Services;

use App\Models\AiRoleTokenCap;
use App\Models\AiTokenLedger;
use App\Models\AiTokenOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TokenGate;
use Illuminate\Support\Facades\DB;

/**
 * Single home for AI token accounting and enforcement.
 *
 * Two pools fund a tenant's AI usage:
 *  - a monthly FREE quota (`tenant.ai_token_quota ?? package.ai_token_quota`)
 *    that resets each calendar month, and
 *  - a permanent WALLET (`tenants.ai_token_balance`) topped up via purchases.
 *
 * Consumption draws the free quota first, then the wallet. Per-user monthly caps
 * (override `?? tenant default`, null = unlimited) provide fair distribution.
 * All usage is read from {@see AiTokenLedger} (never `ai_messages`, which can be
 * erased by deleting a conversation).
 */
final class AiTokenService
{
    /**
     * Effective free monthly quota for the tenant (0 = none configured).
     */
    public function freeQuota(Tenant $tenant): int
    {
        return $this->freeQuotaRaw($tenant) ?? 0;
    }

    /**
     * Tokens the whole tenant has consumed this calendar month (from the ledger).
     */
    public function tenantMonthlyUsed(Tenant $tenant): int
    {
        return (int) AiTokenLedger::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', AiTokenLedger::TYPE_DEBIT)
            ->where('period', $this->period())
            ->sum('tokens');
    }

    /**
     * Tokens a single user has consumed this calendar month (from the ledger).
     */
    public function userMonthlyUsed(User $user): int
    {
        return (int) AiTokenLedger::query()
            ->where('user_id', $user->id)
            ->where('type', AiTokenLedger::TYPE_DEBIT)
            ->where('period', $this->period())
            ->sum('tokens');
    }

    /**
     * The monthly cap that applies to this user, resolved from their ROLES: each
     * role uses its own cap (or the tenant default when it has none), and the
     * most permissive wins. Null means unlimited.
     */
    public function resolveUserCap(User $user, ?Tenant $tenant = null): ?int
    {
        $tenant ??= $user->tenant_id !== null ? $user->tenant()->first() : null;
        $default = $tenant?->ai_token_user_cap;
        $default = $default !== null ? (int) $default : null;

        $roleIds = $user->roles()->pluck('roles.id');

        if ($roleIds->isEmpty()) {
            return $default;
        }

        $capByRole = AiRoleTokenCap::query()
            ->whereIn('role_id', $roleIds)
            ->pluck('monthly_cap', 'role_id');

        $max = 0;

        foreach ($roleIds as $roleId) {
            $cap = $capByRole->has($roleId) ? $capByRole->get($roleId) : $default;

            if ($cap === null) {
                return null; // a role with no cap makes the user unlimited
            }

            $max = max($max, (int) $cap);
        }

        return $max;
    }

    /**
     * Pre-flight gate run before the model call (tokens are not yet known).
     */
    public function canChat(User $user): TokenGate
    {
        if ($user->tenant_id === null) {
            return TokenGate::allow();
        }

        $tenant = $user->tenant()->with('package:id,ai_token_quota')->first();

        if ($tenant === null) {
            return TokenGate::allow();
        }

        $cap = $this->resolveUserCap($user, $tenant);

        if ($cap !== null && $this->userMonthlyUsed($user) >= $cap) {
            return TokenGate::block(
                'user_cap',
                'Batas token AI Anda bulan ini telah habis. Hubungi admin perusahaan untuk menambah jatah.',
            );
        }

        $freeRemaining = max(0, $this->freeQuota($tenant) - $this->tenantMonthlyUsed($tenant));

        if ($freeRemaining <= 0 && (int) $tenant->ai_token_balance <= 0) {
            return TokenGate::block(
                'pool_empty',
                'Kuota token AI perusahaan telah habis. Hubungi admin untuk menambah token.',
            );
        }

        return TokenGate::allow();
    }

    /**
     * Record consumption: log the ledger debit and draw the overflow (beyond the
     * free monthly quota) from the permanent wallet. Locked to avoid races.
     */
    public function debit(User $user, int $tokens): void
    {
        if ($tokens <= 0 || $user->tenant_id === null) {
            return;
        }

        DB::transaction(function () use ($user, $tokens): void {
            $tenant = Tenant::query()->whereKey($user->tenant_id)->lockForUpdate()->first();

            if ($tenant === null) {
                return;
            }

            $freeRemaining = max(0, $this->freeQuota($tenant) - $this->tenantMonthlyUsed($tenant));
            $walletDebit = max(0, $tokens - $freeRemaining);
            $applied = min($walletDebit, (int) $tenant->ai_token_balance);

            if ($applied > 0) {
                $tenant->decrement('ai_token_balance', $applied);
            }

            AiTokenLedger::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'type' => AiTokenLedger::TYPE_DEBIT,
                'source' => 'chat',
                'tokens' => $tokens,
                'wallet_delta' => -$applied,
                'balance_after' => (int) $tenant->ai_token_balance,
                'period' => $this->period(),
            ]);
        });
    }

    /**
     * Credit a paid order's tokens to the tenant wallet. Idempotent — a no-op if
     * the order was already credited.
     */
    public function creditWallet(AiTokenOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $fresh = AiTokenOrder::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->credited_at !== null) {
                return;
            }

            $tenant = Tenant::query()->whereKey($fresh->tenant_id)->lockForUpdate()->first();

            if ($tenant === null) {
                return;
            }

            $tenant->increment('ai_token_balance', $fresh->token_amount);

            AiTokenLedger::create([
                'tenant_id' => $tenant->id,
                'user_id' => $fresh->user_id,
                'type' => AiTokenLedger::TYPE_CREDIT,
                'source' => 'purchase',
                'tokens' => $fresh->token_amount,
                'wallet_delta' => $fresh->token_amount,
                'balance_after' => (int) $tenant->ai_token_balance,
                'period' => $this->period(),
                'ai_token_order_id' => $fresh->id,
            ]);

            $fresh->update(['credited_at' => now()]);
        });
    }

    /**
     * Token breakdown for the meter UI. Keeps the legacy `used`/`quota`/`period`
     * keys the mobile app already reads, plus the richer wallet/cap fields.
     *
     * @return array<string, mixed>
     */
    public function remainingForUser(User $user): array
    {
        $periodLabel = now()->locale('id')->translatedFormat('F Y');

        if ($user->tenant_id === null) {
            return [
                'used' => 0,
                'quota' => null,
                'period' => $periodLabel,
                'free_quota' => 0,
                'free_used' => 0,
                'free_remaining' => 0,
                'wallet_balance' => 0,
                'user_cap' => null,
                'user_used' => 0,
                'user_remaining' => null,
                'effective_remaining' => null,
            ];
        }

        $tenant = $user->tenant()->with('package:id,ai_token_quota')->first();

        $freeQuotaRaw = $tenant ? $this->freeQuotaRaw($tenant) : null;
        $freeQuota = $freeQuotaRaw ?? 0;
        $tenantUsed = $tenant ? $this->tenantMonthlyUsed($tenant) : 0;
        $freeRemaining = max(0, $freeQuota - $tenantUsed);
        $wallet = (int) ($tenant->ai_token_balance ?? 0);

        $cap = $tenant ? $this->resolveUserCap($user, $tenant) : null;
        $userUsed = $this->userMonthlyUsed($user);
        $userRemaining = $cap !== null ? max(0, $cap - $userUsed) : null;

        $poolRemaining = $freeRemaining + $wallet;
        $effectiveRemaining = $userRemaining !== null
            ? min($userRemaining, $poolRemaining)
            : $poolRemaining;

        return [
            'used' => $tenantUsed,
            'quota' => $freeQuotaRaw,
            'period' => $periodLabel,
            'free_quota' => $freeQuota,
            'free_used' => min($tenantUsed, $freeQuota),
            'free_remaining' => $freeRemaining,
            'wallet_balance' => $wallet,
            'user_cap' => $cap,
            'user_used' => $userUsed,
            'user_remaining' => $userRemaining,
            'effective_remaining' => $effectiveRemaining,
        ];
    }

    /**
     * Raw free quota (tenant override, then package), nullable = unlimited.
     */
    private function freeQuotaRaw(Tenant $tenant): ?int
    {
        if ($tenant->ai_token_quota !== null) {
            return (int) $tenant->ai_token_quota;
        }

        $tenant->loadMissing('package');
        $packageQuota = $tenant->package?->ai_token_quota;

        return $packageQuota !== null ? (int) $packageQuota : null;
    }

    /**
     * Current usage bucket (YYYY-MM).
     */
    private function period(): string
    {
        return now()->format('Y-m');
    }
}
