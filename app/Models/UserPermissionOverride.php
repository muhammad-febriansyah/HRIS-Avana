<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-user grant/revoke that overrides the permissions granted by the user's
 * roles. Resolved in {@see User::permissionCodes()}.
 */
final class UserPermissionOverride extends Model
{
    use Auditable;

    public const TYPE_GRANT = 'grant';

    public const TYPE_REVOKE = 'revoke';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
