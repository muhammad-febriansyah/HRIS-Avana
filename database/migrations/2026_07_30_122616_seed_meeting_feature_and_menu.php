<?php

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\AvanaNav;
use App\Support\MobileMenu;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put "Rapat & Transkrip" on the map: the feature row, the `meeting` action
 * permissions, the web sidebar leaf, and the phone's AI Recorder tile.
 *
 * The feature is switched on only for tenants that already run the AI
 * Assistant — the recorder spends the same token wallet, so a company that has
 * not been given AI at all should not silently gain a way to spend it.
 * Idempotent: every write is a firstOrCreate or a syncWithoutDetaching.
 */
return new class extends Migration
{
    public function up(): void
    {
        $module = 'meeting';

        $feature = Feature::firstOrCreate(
            ['code' => 'meeting_ai'],
            [
                'name' => 'Rapat & Transkrip AI',
                'module_group' => 'engagement',
                'permission_modules' => [$module],
                'description' => 'Rekam rapat dari HP, transkrip otomatis per pembicara, ringkasan & analisis AI.',
                'is_active' => true,
            ],
        );

        $existing = DB::table('permissions')->pluck('code')->flip();
        $rows = [];

        foreach (PermissionCatalog::actionKeys() as $action) {
            $code = $module.'.'.$action;

            if ($existing->has($code)) {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'module' => $module,
                'action' => $action,
                'name' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('permissions')->insert($rows);
        }

        $permissionIds = Permission::where('module', $module)->pluck('id');

        // Tenant admin / HR gets the full set; other roles are given it from
        // Hak Akses, where the company decides who may read a transcript.
        Role::whereIn('code', ['admin_tenant_hr', 'super_admin'])->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds),
        );

        $aiFeatureId = Feature::where('code', 'ai')->value('id');

        // Packages that already sell the AI Assistant sell the recorder too.
        // Without this a tenant switched on below would lose it again the next
        // time their package is re-applied, which reads as the feature breaking
        // by itself.
        if ($aiFeatureId !== null) {
            DB::table('package_features')
                ->where('feature_id', $aiFeatureId)
                ->where('is_enabled', true)
                ->pluck('package_id')
                ->each(fn (int $packageId) => DB::table('package_features')->updateOrInsert(
                    ['package_id' => $packageId, 'feature_id' => $feature->id],
                    ['is_enabled' => true, 'updated_at' => now(), 'created_at' => now()],
                ));
        }

        Tenant::query()->pluck('id')->each(function (int $tenantId) use ($feature, $aiFeatureId): void {
            if ($aiFeatureId !== null) {
                $hasAi = DB::table('tenant_features')
                    ->where('tenant_id', $tenantId)
                    ->where('feature_id', $aiFeatureId)
                    ->where('is_enabled', true)
                    ->exists();

                if ($hasAi) {
                    DB::table('tenant_features')->updateOrInsert(
                        ['tenant_id' => $tenantId, 'feature_id' => $feature->id],
                        ['is_enabled' => true, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            }

            AvanaNav::seedDefaultsFor($tenantId);
            MobileMenu::seedDefaultsFor($tenantId);
        });
    }

    public function down(): void
    {
        // Non-destructive: leave the feature, permissions and menu rows alone.
    }
};
