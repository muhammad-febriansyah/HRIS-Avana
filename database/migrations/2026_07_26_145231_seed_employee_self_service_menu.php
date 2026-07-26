<?php

use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the "LAYANAN SAYA" (employee self-service) group to every tenant's menu
 * and opens the org chart to employees.
 *
 * The runtime sidebar reads menu_items, and AvanaNav::seedDefaultsFor only
 * creates rows for a scope that has none — so tenants that already have a menu
 * would never see the new leaves without this.
 */
return new class extends Migration
{
    /**
     * The self-service leaves, mirroring AvanaNav::groups().
     *
     * @var array<int, array<string, string>>
     */
    private const LEAVES = [
        ['key' => 'saya-profil', 'label' => 'Profil Saya', 'icon' => 'user', 'href' => '/avana/saya/profil'],
        ['key' => 'saya-absensi', 'label' => 'Absensi Saya', 'icon' => 'fingerprint', 'href' => '/avana/saya/absensi'],
        ['key' => 'saya-koreksi', 'label' => 'Koreksi Absensi', 'icon' => 'clock-alert', 'href' => '/avana/saya/koreksi-absensi'],
        ['key' => 'saya-jadwal', 'label' => 'Jadwal Saya', 'icon' => 'calendar-clock', 'href' => '/avana/saya/jadwal'],
        ['key' => 'saya-organisasi', 'label' => 'Struktur Organisasi', 'icon' => 'network', 'href' => '/avana/saya/organisasi'],
        ['key' => 'saya-cuti', 'label' => 'Cuti Saya', 'icon' => 'palmtree', 'href' => '/avana/saya/cuti'],
        ['key' => 'saya-lembur', 'label' => 'Lembur Saya', 'icon' => 'timer', 'href' => '/avana/saya/lembur'],
        ['key' => 'saya-izin', 'label' => 'Izin Saya', 'icon' => 'file-clock', 'href' => '/avana/saya/izin'],
        ['key' => 'saya-kontrak', 'label' => 'Kontrak Saya', 'icon' => 'file-text', 'href' => '/avana/saya/kontrak'],
        ['key' => 'saya-kinerja', 'label' => 'Kinerja Saya', 'icon' => 'trending-up', 'href' => '/avana/saya/kinerja'],
        ['key' => 'saya-slip', 'label' => 'Slip Gaji Saya', 'icon' => 'receipt', 'href' => '/avana/saya/slip-gaji'],
        ['key' => 'saya-dokumen', 'label' => 'Dokumen Saya', 'icon' => 'folder', 'href' => '/avana/saya/dokumen'],
        ['key' => 'saya-onboarding', 'label' => 'Onboarding Saya', 'icon' => 'clipboard-check', 'href' => '/avana/saya/onboarding'],
    ];

    private const SECTION = 'LAYANAN SAYA';

    public function up(): void
    {
        $this->enableEssFeature();

        foreach ($this->tenantIds() as $tenantId) {
            $this->seedGroupFor($tenantId);
            $this->restoreAdminOrgChart($tenantId);
        }
    }

    public function down(): void
    {
        foreach ($this->tenantIds() as $tenantId) {
            MenuItem::forTenant($tenantId)
                ->whereIn('key', array_column(self::LEAVES, 'key'))
                ->delete();
        }
    }

    /**
     * Every tenant, so a scope that has not customised its menu still gets the
     * rows the moment it does.
     *
     * @return array<int, int>
     */
    private function tenantIds(): array
    {
        return Tenant::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * The `ess` feature gates every leaf here; a disabled feature hides them,
     * so make sure each tenant has the toggle before seeding.
     */
    private function enableEssFeature(): void
    {
        $feature = Feature::firstOrCreate(
            ['code' => 'ess'],
            ['name' => 'Employee Self-Service', 'module_group' => 'hr'],
        );

        foreach (Tenant::all() as $tenant) {
            $tenant->features()->firstOrCreate(['feature_id' => $feature->id], ['is_enabled' => true]);
        }
    }

    /**
     * Create the tenant's self-service leaves, ordered right after Dashboard so
     * the group lands near the top of the sidebar.
     */
    private function seedGroupFor(int $tenantId): void
    {
        if (MenuItem::forTenant($tenantId)->doesntExist()) {
            // Never customised: AvanaNav::seedDefaultsFor will build the whole
            // menu, new group included, the first time it is needed.
            return;
        }

        $dashboardOrder = (int) MenuItem::forTenant($tenantId)
            ->where('key', 'dashboard')
            ->whereNull('parent_id')
            ->value('sort_order');

        foreach (self::LEAVES as $index => $leaf) {
            MenuItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $leaf['key'], 'parent_id' => null],
                [
                    'section' => self::SECTION,
                    'label' => $leaf['label'],
                    'icon' => $leaf['icon'],
                    'href' => $leaf['href'],
                    'feature' => 'ess',
                    'modules' => ['own'],
                    'admin_only' => false,
                    'super_admin_only' => false,
                    'is_active' => true,
                    'is_system' => true,
                    // sort_order is an integer, so the group is nudged just past
                    // Dashboard; the Menu Builder can reorder it afterwards.
                    'sort_order' => $dashboardOrder + $index,
                ],
            );
        }
    }

    /**
     * Employees get their own org chart at /avana/saya/organisasi, so the admin
     * one keeps its `employee`-only gate. An earlier revision of this migration
     * added `own` there; strip it back off so the admin screen — which
     * authorises through EmployeePolicy and would 403 anyway — stays hidden.
     */
    private function restoreAdminOrgChart(int $tenantId): void
    {
        $orgChart = MenuItem::forTenant($tenantId)->where('key', 'organisasi')->first();

        if ($orgChart === null) {
            return;
        }

        $modules = $orgChart->modules ?? [];

        if (in_array('own', $modules, true)) {
            $orgChart->update(['modules' => array_values(array_diff($modules, ['own']))]);
        }
    }
};
