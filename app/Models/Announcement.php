<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

final class Announcement extends Model
{
    /**
     * Disk holding announcement attachments. Published announcements are
     * broadcast to every employee and their images render inline in the mobile
     * app, so the file is served straight from the public disk.
     */
    public const ATTACHMENT_DISK = 'public';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'pinned' => 'boolean',
            'attachment_size' => 'integer',
        ];
    }

    /**
     * Public URL of the attachment, or null when the announcement has none.
     */
    public function attachmentUrl(): ?string
    {
        return $this->attachment_path === null
            ? null
            : Storage::disk(self::ATTACHMENT_DISK)->url($this->attachment_path);
    }

    /**
     * Whether the attachment is an image (so a client can render it inline
     * rather than offering a download).
     */
    public function attachmentIsImage(): bool
    {
        return str_starts_with((string) $this->attachment_mime, 'image/');
    }

    /**
     * Delete the stored attachment file, if any. Does not touch the columns.
     */
    public function deleteAttachmentFile(): void
    {
        if ($this->attachment_path !== null) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($this->attachment_path);
        }
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class);
    }
}
