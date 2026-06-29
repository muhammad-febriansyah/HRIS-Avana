<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Records an audit trail entry whenever a model is created, updated, or
 * deleted by an authenticated web user. Add the trait to any sensitive model;
 * Eloquent auto-invokes {@see static::bootAuditable()} when the model boots, so
 * no service-provider wiring is required.
 *
 * Logging is intentionally null-safe: during seeding, console commands, queued
 * jobs, or any unauthenticated context it is skipped, and a logging failure can
 * never break the underlying database write.
 *
 * @mixin Model
 */
trait Auditable
{
    /**
     * Attributes excluded from every audit diff: timestamps plus secrets that
     * must never be persisted to the audit log.
     *
     * @var array<int, string>
     */
    protected static array $auditExcluded = [
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * Register the model lifecycle hooks that write the audit trail.
     */
    public static function bootAuditable(): void
    {
        static::created(static function (Model $model): void {
            static::recordAuditLog($model, 'created');
        });

        static::updated(static function (Model $model): void {
            static::recordAuditLog($model, 'updated');
        });

        static::deleted(static function (Model $model): void {
            static::recordAuditLog($model, 'deleted');
        });
    }

    /**
     * Persist a single audit row for the given lifecycle action.
     */
    protected static function recordAuditLog(Model $model, string $action): void
    {
        // Only log changes performed by an authenticated web user. Seeders,
        // migrations, console commands, and unauthenticated requests are
        // skipped so the test suite and bootstrap data stay free of noise.
        if (! auth()->check()) {
            return;
        }

        try {
            [$old, $new] = static::auditChanges($model, $action);

            // An update that only touched excluded columns is not meaningful.
            if ($action === 'updated' && $old === [] && $new === []) {
                return;
            }

            $tenantId = static::resolveAuditTenantId($model);

            if ($tenantId === null) {
                return;
            }

            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'action' => $action,
                'old_values' => $old === [] ? null : $old,
                'new_values' => $new === [] ? null : $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (Throwable $exception) {
            // Auditing is best-effort: never let it break the business write.
            report($exception);
        }
    }

    /**
     * Resolve the [old, new] attribute diffs to store for the action.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected static function auditChanges(Model $model, string $action): array
    {
        $excluded = static::$auditExcluded;

        if ($action === 'created') {
            $new = collect($model->getAttributes())->except($excluded)->all();

            return [[], $new];
        }

        if ($action === 'deleted') {
            $old = collect($model->getOriginal())->except($excluded)->all();

            return [$old, []];
        }

        // updated: keep only the columns that actually changed.
        $changed = collect($model->getChanges())->except($excluded);

        $old = collect($model->getOriginal())
            ->only($changed->keys()->all())
            ->all();

        return [$old, $changed->all()];
    }

    /**
     * Resolve the tenant the audit row belongs to: the model's own tenant_id
     * when present, otherwise the acting user's tenant.
     */
    protected static function resolveAuditTenantId(Model $model): int|string|null
    {
        $tenantId = $model->getAttribute('tenant_id');

        if (! empty($tenantId)) {
            return $tenantId;
        }

        return auth()->user()?->getAttribute('tenant_id');
    }
}
