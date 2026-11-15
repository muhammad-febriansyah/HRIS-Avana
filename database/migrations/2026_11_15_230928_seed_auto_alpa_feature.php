<?php

use App\Models\Feature;
use Illuminate\Database\Migrations\Migration;

/**
 * "Alpa Otomatis": the scheduled marker writes an Alpa row — which docks pay —
 * for every no-show whose shift has ended. That is a decision made with each
 * client, so it is a feature the Super Admin switches on per tenant from the
 * Klien page. Seeded switched off for everyone; no tenant row is written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Feature::updateOrCreate(
            ['code' => 'auto_alpa'],
            [
                'name' => 'Alpa Otomatis',
                'module_group' => 'time',
                'permission_modules' => [],
                'is_active' => true,
            ],
        );
    }

    public function down(): void
    {
        Feature::where('code', 'auto_alpa')->delete();
    }
};
