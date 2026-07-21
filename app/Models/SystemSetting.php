<?php

namespace App\Models;

use App\Support\Access;
use Illuminate\Database\Eloquent\Model;

/**
 * A single global key/value platform setting (feature flags). Read through
 * {@see Access} rather than directly.
 */
final class SystemSetting extends Model
{
    protected $guarded = [];
}
