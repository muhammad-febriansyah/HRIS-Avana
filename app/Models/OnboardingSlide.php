<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A single intro/onboarding slide for the mobile app carousel. Managed by the
 * super admin and served publicly (via the API) to the Flutter app. The image
 * may be an SVG (vector) or a raster file stored on the public disk.
 */
class OnboardingSlide extends Model
{
    protected $guarded = [];

    /**
     * Public disk the slide images live on.
     */
    public const DISK = 'public';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Only active slides.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Carousel display order (sort_order, then id).
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Public URL for the slide image, or null when unset.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(self::DISK)->url($this->image_path) : null;
    }

    /**
     * Shape shared with the Flutter app.
     *
     * @return array{id: int, title: string, subtitle: ?string, image_url: ?string, order: int}
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_url' => $this->imageUrl(),
            'order' => $this->sort_order,
        ];
    }
}
