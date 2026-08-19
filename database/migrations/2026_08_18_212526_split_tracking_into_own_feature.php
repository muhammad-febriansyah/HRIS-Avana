<?php

use App\Models\Feature;
use App\Models\Tenant;
use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give Live Tracking its own on/off switch instead of riding piggyback on the
 * `attendance` feature. Every tenant already runs tracking today, so this is
 * enable-by-default for the existing base — nobody loses the screen they had
 * yesterday. From here, the tenant admin can flip it off from Kelola Fitur and
 * the Super Admin can kill it platform-wide from Katalog Fitur, same as any
 * other feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        $feature = Feature::firstOrCreate(
            ['code' => 'tracking'],
            [
                'name' => 'Live Tracking',
                'module_group' => 'time',
                'permission_modules' => ['attendance'],
                'description' => 'Lacak rute perjalanan karyawan selama jam kerja lewat GPS, peta live & riwayat tracking.',
                'is_active' => true,
            ],
        );

        $tenantIds = Tenant::query()->pluck('id');

        $tenantIds->each(function (int $tenantId) use ($feature): void {
            DB::table('tenant_features')->updateOrInsert(
                ['tenant_id' => $tenantId, 'feature_id' => $feature->id],
                ['is_enabled' => true, 'updated_at' => now(), 'created_at' => now()],
            );

            // Adds the leaf for tenants that have no menu_items row for it yet;
            // skips (does not touch) the ones that do.
            AvanaNav::seedDefaultsFor($tenantId);

            // seedDefaultsFor leaves existing rows untouched, so every tenant
            // that already has the Live Tracking leaves needs its stored
            // `feature` column repointed from `attendance` to `tracking`
            // directly — otherwise the DB-backed menu keeps gating on the old
            // code forever.
            DB::table('menu_items')
                ->where('tenant_id', $tenantId)
                ->whereIn('key', ['tracking-live', 'tracking-history'])
                ->update(['feature' => 'tracking', 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Non-destructive: leave the feature, tenant_features rows and menu
        // seeding alone.
    }
};
