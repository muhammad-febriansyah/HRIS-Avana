<?php

use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give "Backup Database" a place in the platform sidebar.
 *
 * The platform menu is stored as null-tenant rows once it has been seeded, so
 * a new leaf added to the defaults in code is invisible until those rows are
 * topped up. `seedPlatformDefaults()` is idempotent and leaves customised rows
 * alone, so this only adds what is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        AvanaNav::seedPlatformDefaults();
    }

    /**
     * Take the leaf back out, leaving every other platform row as it was.
     */
    public function down(): void
    {
        DB::table('menu_items')
            ->whereNull('tenant_id')
            ->where('key', 'backup')
            ->delete();
    }
};
