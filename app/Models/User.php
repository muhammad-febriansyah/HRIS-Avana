<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\Auditable;
use App\Support\Access;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'tenant_id', 'phone', 'status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements JWTSubject, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'token_version' => 'integer',
        ];
    }

    /**
     * The identifier stored in the JWT `sub` claim.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Extra claims embedded in the JWT payload.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'tv' => (int) ($this->token_version ?? 0),
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    /**
     * Whether the user holds the platform super-admin role. Uses the loaded
     * relation when present so shared-prop resolution stays query-light.
     */
    public function isSuperAdmin(): bool
    {
        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();

        return $roles->contains(fn (Role $role): bool => $role->code === 'super_admin');
    }

    /**
     * The permission codes effective for this user: every code granted by their
     * roles, plus per-user grants, minus per-user revokes. A super admin holds
     * every permission. Overrides win over role permissions.
     *
     * @return Collection<int, string>
     */
    public function permissionCodes(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()->pluck('code');
        }

        $this->loadMissing('roles.permissions', 'permissionOverrides');

        $codes = $this->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code');

        $grants = $this->permissionOverrides
            ->where('type', UserPermissionOverride::TYPE_GRANT)
            ->pluck('permission_code');

        $revokes = $this->permissionOverrides
            ->where('type', UserPermissionOverride::TYPE_REVOKE)
            ->pluck('permission_code');

        return $codes
            ->merge($grants)
            ->reject(fn (string $code): bool => $revokes->contains($code))
            ->unique()
            ->values();
    }

    /**
     * Whether the user may perform the given `{module}.{action}` permission.
     */
    public function hasPermissionTo(string $code): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Kill-switch: when enforcement is disabled, every check passes.
        if (! Access::enforced()) {
            return true;
        }

        return $this->permissionCodes()->contains($code);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function branchAccesses(): HasMany
    {
        return $this->hasMany(UserBranchAccess::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function activeDevice(): HasOne
    {
        return $this->hasOne(UserDevice::class)->where('status', 'active');
    }

    public function dataScopes(): HasMany
    {
        return $this->hasMany(UserDataScope::class);
    }
}
