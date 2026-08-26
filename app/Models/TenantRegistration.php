<?php

namespace App\Models;

use App\Http\Controllers\Avana\ReferralController;
use App\Http\Controllers\CompanyRegistrationController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A self-serve "Daftar Perusahaan" submission awaiting super admin review —
 * see {@see CompanyRegistrationController}. Approving
 * one is what actually provisions the {@see Tenant} and its admin login
 * ({@see ReferralController::approveTenant()});
 * nothing here can be logged into on its own.
 */
final class TenantRegistration extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $guarded = [];

    protected $hidden = ['admin_password'];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'terms_accepted' => 'boolean',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
