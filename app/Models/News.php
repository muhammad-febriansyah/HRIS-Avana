<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class News extends Model
{
    use HasFactory;

    public const DISK = 'public';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : Storage::disk(self::DISK)->url($this->image_path);
    }

    public function deleteImage(): void
    {
        if ($this->image_path !== null) {
            Storage::disk(self::DISK)->delete($this->image_path);
        }
    }
}
