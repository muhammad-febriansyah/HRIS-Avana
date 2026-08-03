<?php

namespace App\Models;

use App\Support\PrivateFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One post on the employee social wall.
 *
 * Posts go live on submit — moderation is after the fact, by hiding
 * (`status = hidden`, kept for audit) or deleting.
 */
final class SocialPost extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** Visible on the wall. */
    public const STATUS_PUBLISHED = 'published';

    /** Taken down by HR; kept in the table so the moderation trail survives. */
    public const STATUS_HIDDEN = 'hidden';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<SocialCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SocialCategory::class, 'social_category_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<SocialPostLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(SocialPostLike::class);
    }

    /** @return HasMany<SocialPostComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(SocialPostComment::class);
    }

    /** @return HasMany<SocialPostReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(SocialPostReport::class);
    }

    /**
     * Public URL of the attached photo, or null when the post is text-only.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path !== null
            ? PrivateFile::urlFor($this->image_path)
            : null;
    }

    /**
     * Drop the stored photo, if any. Called before a hard delete.
     */
    public function deleteImageFile(): void
    {
        if ($this->image_path !== null) {
            PrivateFile::delete($this->image_path);
        }
    }
}
