<?php

namespace App\Services;

use App\Models\AiRoleTokenCap;
use App\Models\AiTokenLedger;
use App\Models\AiTokenOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TokenGate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single home for AI token accounting and enforcement.
 *
 * Three pools fund a message, drawn in this order:
 *  1. the user's own permanent wallet (`users.ai_token_balance`), bought by the
 *     person spending it,
 *  2. the company's monthly FREE quota
 *     (`tenant.ai_token_quota ?? package.ai_token_quota`), which resets each
 *     calendar month, and
 *  3. the company's permanent WALLET (`tenants.ai_token_balance`), topped up by
 *     the admin.
 *
 * Personal first: the balance somebody bought is the one they watch, so it is
 * the one that moves. The company pools are the fallback once it is empty.
 *
 * Per-user monthly caps (role override `?? tenant default`, null = unlimited)
 * ration the two company pools so one person cannot drain them. They do not
 * touch the personal wallet — the cap shares out what the company paid for, and
 * this is not that — which also means personal spending must not count against
 * the cap, or buying tokens would eat the allowance it was meant to extend.
 * Hence {@see userCompanyUsed()} beside the plain total.
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

            // Your own tokens go first. Somebody who topped up did so to keep
            // working, so the balance they can see is the one that moves.
            $fromPersonal = min($tokens, $this->personalBalance($fresh));
            $rest = $tokens - $fromPersonal;

            // What is left falls to the company, bounded by whatever remains of
            // this user's monthly cap.
            $cap = $this->resolveUserCap($fresh, $tenant);
            $companyAllowance = $cap === null
                ? $rest
                : max(0, min($rest, $cap - $this->userCompanyUsed($fresh)));

            $freeRemaining = max(0, $this->freeQuota($tenant) - $this->tenantMonthlyUsed($tenant));
            $fromFree = min($companyAllowance, $freeRemaining);
            $fromWallet = min($companyAllowance - $fromFree, (int) $tenant->ai_token_balance);

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
     * How much of their own AI budget somebody has been spending lately, as two
     * series a screen can chart: the last [$days] days, and the last [$months]
     * calendar months.
     *
     * Buckets are filled in PHP rather than grouped in SQL so the result is the
     * same on every database, and so quiet days appear as a zero-height bar
     * instead of a gap the reader has to notice is missing.
     *
     * @return array{
     *     today: int,
     *     week: int,
     *     month: int,
     *     daily: list<array{label: string, date: string, tokens: int}>,
     *     monthly: list<array{label: string, month: string, tokens: int}>
     * }
     */
    public function usageSeriesForUser(User $user, int $days = 7, int $months = 6): array
    {
        $from = now()->startOfDay()->subMonths($months - 1)->startOfMonth();

        /** @var Collection<int, AiTokenLedger> $debits */
        $debits = AiTokenLedger::query()
            ->where('user_id', $user->id)
            ->where('type', AiTokenLedger::TYPE_DEBIT)
            ->where('created_at', '>=', $from)
            ->get(['tokens', 'created_at']);

        $byDay = [];
        $byMonth = [];

        foreach ($debits as $debit) {
            $at = $debit->created_at;

            if ($at === null) {
                continue;
            }

            $byDay[$at->format('Y-m-d')] = ($byDay[$at->format('Y-m-d')] ?? 0) + (int) $debit->tokens;
            $byMonth[$at->format('Y-m')] = ($byMonth[$at->format('Y-m')] ?? 0) + (int) $debit->tokens;
        }

        $daily = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);

            $daily[] = [
                'label' => $day->locale('id')->translatedFormat('D'),
                'date' => $day->format('Y-m-d'),
                'tokens' => $byDay[$day->format('Y-m-d')] ?? 0,
            ];
        }

        $monthly = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);

            $monthly[] = [
                'label' => $month->locale('id')->translatedFormat('M'),
                'month' => $month->format('Y-m'),
                'tokens' => $byMonth[$month->format('Y-m')] ?? 0,
            ];
        }

        return [
            'today' => $byDay[now()->format('Y-m-d')] ?? 0,
            'week' => array_sum(array_column($daily, 'tokens')),
            'month' => $byMonth[now()->format('Y-m')] ?? 0,
            'daily' => $daily,
            'monthly' => $monthly,
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
