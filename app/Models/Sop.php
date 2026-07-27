<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant's SOP document (PDF) plus the extracted text the AI assistant
 * answers from. `visibility` gates who the assistant may quote it to.
 */
final class Sop extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Readable by every employee of the tenant through the AI assistant.
     */
    public const VISIBILITY_PUBLIC = 'public';

    /**
     * Readable only by users holding the `sop.view` permission (HR/admin).
     */
    public const VISIBILITY_PRIVATE = 'private';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'file_size' => 'integer',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Restrict to the SOPs an employee without `sop.view` may be shown.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SopCategory::class, 'sop_category_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
