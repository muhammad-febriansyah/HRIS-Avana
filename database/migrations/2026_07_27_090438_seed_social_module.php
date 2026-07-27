<?php

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SocialCategory;
use App\Models\Tenant;
use App\Support\AvanaNav;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Register the employee social wall: the `social` feature, the `social.*`
 * permissions, the tenant-admin grant, the sidebar leaf, and a starter set of
 * categories so the wall is usable the moment it appears. Idempotent.
 */
return new class extends Migration
{
    /**
     * Starter categories — the ones the brief named, with a matching icon and
     * accent. A tenant edits or replaces them from the Sosmed screen.
     *
     * @var array<int, array{name: string, icon: string, color: string, description: string}>
     */
    private const STARTER_CATEGORIES = [
        [
            'name' => 'Ide Perbaikan',
            'icon' => 'lightbulb',
            'color' => '#F59E0B',
            'description' => 'Usulan perbaikan proses, produk, atau tempat kerja.',
        ],
        [
            'name' => 'Sports Day',
            'icon' => 'trophy',
            'color' => '#16A34A',
            'description' => 'Kegiatan olahraga dan keseruan bareng tim.',
        ],
        [
            'name' => 'Employee of the Month',
            'icon' => 'star',
            'color' => '#7C3AED',
            'description' => 'Apresiasi untuk rekan kerja yang berprestasi.',
        ],
    ];

    public function up(): void
    {
        $module = 'social';

        $feature = Feature::updateOrCreate(
            ['code' => $module],
            ['name' => 'Ruang Kita', 'module_group' => 'engagement', 'permission_modules' => [$module]],
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

        Role::where('code', 'admin_tenant_hr')->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds),
        );

        Tenant::query()->get()->each(function (Tenant $tenant) use ($feature): void {
            $tenant->features()->firstOrCreate(
                ['feature_id' => $feature->id],
                ['is_enabled' => true],
            );

            foreach (self::STARTER_CATEGORIES as $index => $category) {
                SocialCategory::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $category['name']],
                    [
                        'slug' => Str::slug($category['name']),
                        'icon' => $category['icon'],
                        'color' => $category['color'],
                        'description' => $category['description'],
                        'status' => 'active',
                        'sort_order' => $index,
                    ],
                );
            }

            AvanaNav::seedDefaultsFor($tenant->id);
        });
    }

    public function down(): void
    {
        // Non-destructive: leave permissions, features, categories and menu rows.
    }
};
