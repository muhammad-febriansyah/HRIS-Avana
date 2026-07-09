<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outbound (SMTP) email configuration. Kept as one row per scope: a null
 * tenant_id is the platform-wide default managed by the super admin; a tenant
 * id is that tenant's own override managed by its admin. The SMTP password is
 * encrypted at rest.
 */
final class EmailSetting extends Model
{
    protected $guarded = [];

    /**
     * Supported SMTP encryption options (value => label).
     *
     * @var array<string, string>
     */
    public const ENCRYPTIONS = [
        'tls' => 'TLS',
        'ssl' => 'SSL',
        'none' => 'Tanpa Enkripsi',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_enabled' => 'boolean',
            'port' => 'integer',
        ];
    }

    /**
     * The settings row for a scope, created on first access. Pass null for the
     * platform default (super admin) or a tenant id for that tenant's override.
     */
    public static function forScope(?int $tenantId): self
    {
        return self::query()->firstOrCreate(['tenant_id' => $tenantId]);
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Whether this scope has a usable SMTP configuration.
     */
    public function isReady(): bool
    {
        return $this->is_enabled
            && (string) $this->host !== ''
            && (int) $this->port > 0
            && (string) $this->from_email !== '';
    }

    /**
     * A masked preview of the stored password, or null when unset.
     */
    public function passwordPreview(): ?string
    {
        return (string) $this->password === '' ? null : str_repeat('•', 8);
    }

    /**
     * Build a Laravel mailer config array from this row for a runtime send.
     *
     * @return array<string, mixed>
     */
    public function mailerConfig(): array
    {
        $encryption = $this->encryption === 'none' ? null : $this->encryption;

        return [
            'transport' => 'smtp',
            'host' => (string) $this->host,
            'port' => (int) $this->port,
            'encryption' => $encryption,
            'username' => $this->username ?: null,
            'password' => $this->password ?: null,
            'timeout' => 10,
        ];
    }
}
