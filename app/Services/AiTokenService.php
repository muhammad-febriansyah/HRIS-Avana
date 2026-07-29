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
 * Three pools fund a message, drawn in this order:
 *  1. the company's monthly FREE quota
 *     (`tenant.ai_token_quota ?? package.ai_token_quota`), which resets each
 *     calendar month,
 *  2. the company's permanent WALLET (`tenants.ai_token_balance`), topped up by
 *     the admin, and
 *  3. the user's own permanent wallet (`users.ai_token_balance`), which they
 *     bought themselves.
 *
 * Per-user monthly caps (role override `?? tenant default`, null = unlimited)
 * ration the two company pools so one person cannot drain them. They do not
 * touch the personal wallet: the cap exists to share out what the company paid
 * for, and this is not that. So somebody at their cap, or in a company whose
 * pools are dry, keeps working on tokens they own — and their money is not
 * burned while the company still has budget, because personal comes last.
 *
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
     * Tokens a single user has consumed this calendar month (from the ledger),
     * whoever paid for them.
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
     * Of that, the part the COMPANY paid for — the only part the per-user cap
     * rations.
     *
     * Counting the whole lot would make buying your own tokens self-defeating:
     * spending them would eat the company allowance you were topping up, and a
     * heavy user would end up worse off for having paid.
     */
    public function userCompanyUsed(User $user): int
    {
        $rows = AiTokenLedger::query()
            ->where('user_id', $user->id)
            ->where('type', AiTokenLedger::TYPE_DEBIT)
            ->where('period', $this->period())
            ->selectRaw('COALESCE(SUM(tokens), 0) AS total, COALESCE(SUM(ABS(personal_delta)), 0) AS personal')
            ->first();

        return max(0, (int) ($rows->total ?? 0) - (int) ($rows->personal ?? 0));
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

        // Tokens they paid for themselves answer both refusals below, so the
        // wallet is checked before either of them is raised.
        $personal = $this->personalBalance($user);

        $cap = $this->resolveUserCap($user, $tenant);

        $used = $this->userCompanyUsed($user);

        if ($cap !== null && $used >= $cap && $personal <= 0) {
            // Name the personal allowance and its size: the company pool can be
            // nearly untouched while this user is out, and a bare "token habis"
            // reads as though the whole tenant had run dry.
            return TokenGate::block(
                'user_cap',
                sprintf(
                    'Jatah token AI Anda dari perusahaan bulan ini sudah terpakai (%s dari %s). '
                    .'Minta admin menaikkan jatah Anda, atau beli token pribadi di menu Token AI Saya.',
                    number_format($used, 0, ',', '.'),
                    number_format($cap, 0, ',', '.'),
                ),
            );
        }

        $freeRemaining = max(0, $this->freeQuota($tenant) - $this->tenantMonthlyUsed($tenant));

        if ($freeRemaining <= 0 && (int) $tenant->ai_token_balance <= 0 && $personal <= 0) {
            return TokenGate::block(
                'pool_empty',
                'Kuota token AI perusahaan telah habis. Hubungi admin untuk menambah token, '
                .'atau beli token pribadi di menu Token AI Saya.',
            );
        }

        return TokenGate::allow();
    }

    /**
     * Tokens this user bought for themselves and has not spent yet.
     */
    public function personalBalance(User $user): int
    {
        return max(0, (int) $user->ai_token_balance);
    }

    /**
     * Record consumption: log the ledger debit and draw the overflow (beyond the
     * free monthly quota) from the permanent wallet. Locked to avoid races.
     *
     * `$source` only labels the ledger row — every kind of usage draws on the
     * same quota and the same wallet, so a picture and a paragraph compete for
     * one balance and one per-user cap.
     */
    public function debit(User $user, int $tokens, string $source = 'chat'): void
    {
        if ($tokens <= 0 || $user->tenant_id === null) {
            return;
        }

        DB::transaction(function () use ($user, $tokens, $source): void {
            $tenant = Tenant::query()->whereKey($user->tenant_id)->lockForUpdate()->first();

            if ($tenant === null) {
                return;
            }

            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first() ?? $user;

            // How much of this message the company is still willing to fund:
            // whatever is left of the user's monthly cap. Past it, the rest
            // falls to the wallet they bought themselves.
            $cap = $this->resolveUserCap($fresh, $tenant);
            $companyAllowance = $cap === null
                ? $tokens
                : max(0, min($tokens, $cap - $this->userCompanyUsed($fresh)));

            $freeRemaining = max(0, $this->freeQuota($tenant) - $this->tenantMonthlyUsed($tenant));
            $fromFree = min($companyAllowance, $freeRemaining);
            $fromWallet = min($companyAllowance - $fromFree, (int) $tenant->ai_token_balance);

            // Everything the company could not cover — capped out, quota spent,
            // wallet empty — comes off the personal wallet.
            $fromPersonal = min($tokens - $fromFree - $fromWallet, $this->personalBalance($fresh));

            if ($fromWallet > 0) {
                $tenant->decrement('ai_token_balance', $fromWallet);
            }

            if ($fromPersonal > 0) {
                $fresh->decrement('ai_token_balance', $fromPersonal);
            }

            AiTokenLedger::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'type' => AiTokenLedger::TYPE_DEBIT,
                'source' => $source,
                'tokens' => $tokens,
                'wallet_delta' => -$fromWallet,
                'balance_after' => (int) $tenant->ai_token_balance,
                'personal_delta' => -$fromPersonal,
                'personal_after' => (int) $fresh->ai_token_balance,
                'period' => $this->period(),
            ]);
        });
    }

    /**
     * Credit a paid order's tokens to whichever wallet it was bought for — the
     * company's, or the buyer's own. Idempotent: a no-op if already credited.
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

            $buyer = $fresh->scope === AiTokenOrder::SCOPE_USER && $fresh->user_id !== null
                ? User::query()->whereKey($fresh->user_id)->lockForUpdate()->first()
                : null;

            // A personal order with a buyer who has since been deleted would
            // otherwise silently credit nobody and still mark itself done.
            if ($fresh->scope === AiTokenOrder::SCOPE_USER && $buyer === null) {
                return;
            }

            if ($buyer !== null) {
                $buyer->increment('ai_token_balance', $fresh->token_amount);
            } else {
                $tenant->increment('ai_token_balance', $fresh->token_amount);
            }

            AiTokenLedger::create([
                'tenant_id' => $tenant->id,
                'user_id' => $fresh->user_id,
                'type' => AiTokenLedger::TYPE_CREDIT,
                'source' => $buyer !== null ? 'purchase_personal' : 'purchase',
                'tokens' => $fresh->token_amount,
                'wallet_delta' => $buyer !== null ? 0 : $fresh->token_amount,
                'balance_after' => (int) $tenant->ai_token_balance,
                'personal_delta' => $buyer !== null ? $fresh->token_amount : 0,
                'personal_after' => (int) ($buyer->ai_token_balance ?? 0),
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
                'personal_balance' => 0,
                'company_remaining' => null,
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
        $userUsed = $this->userCompanyUsed($user);
        $userRemaining = $cap !== null ? max(0, $cap - $userUsed) : null;

        $personal = $this->personalBalance($user);

        $poolRemaining = $freeRemaining + $wallet;
        $fromCompany = $userRemaining !== null
            ? min($userRemaining, $poolRemaining)
            : $poolRemaining;

        // Personal tokens sit outside the cap, so they add to what the company
        // allows rather than being clamped by it.
        $effectiveRemaining = $fromCompany + $personal;

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
            'personal_balance' => $personal,
            'company_remaining' => $fromCompany,
            'effective_remaining' => $effectiveRemaining,
        ];
    }

    /**
     * Raw free quota (tenant override, then package), nullable = unlimited.
     *
     * Public because reporting screens must tell "unlimited" apart from "none",
     * a distinction {@see freeQuota()} flattens to 0.
     */
    public function freeQuotaRaw(Tenant $tenant): ?int
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
