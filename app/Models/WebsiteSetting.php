<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide (super admin) website settings: branding, SEO meta, social
 * links and contact info. Stored as a single row and accessed via
 * {@see self::current()}.
 */
final class WebsiteSetting extends Model
{
    protected $guarded = [];

    /**
     * The singleton settings row, created on first access.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }
}
