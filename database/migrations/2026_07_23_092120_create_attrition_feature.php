<?php

use App\Models\Feature;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make "Prediksi Resign" (attrition) a first-class Feature so it owns a row in
 * the Hak Akses matrix (which is Feature-driven) — the tenant admin can then
 * grant attrition.view / .update per role there. Enables it for existing
 * tenants and points the seeded menu row at this feature so the access gate
 * resolves it. The attrition.* permission rows are created separately
 * (2026_07_23_091501).
 */
return new class extends Migration
{
    public function up(): void
    {
        $feature = Feature::updateOrCreate(
            ['code' => 'attrition'],
            [
                'name' => 'Prediksi Resign',
                'module_group' => 'analytics',
                'permission_modules' => ['attrition'],
            ],
        );

        // Enable it for every existing tenant so the row is un-dimmed and the
        // module is reachable.
        Tenant::query()->each(function (Tenant $tenant) use ($feature): void {
            $tenant->features()->updateOrCreate(
                ['feature_id' => $feature->id],
                ['is_enabled' => true],
            );
        });

        // Repoint the already-seeded menu row from the borrowed dynamic_report
        // feature to the dedicated one.
        DB::table('menu_items')
            ->where('href', '/avana/attrition')
            ->update(['feature' => 'attrition']);
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('href', '/avana/attrition')
            ->update(['feature' => 'dynamic_report']);

        Feature::where('code', 'attrition')->delete();
    }
};
