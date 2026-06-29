<?php

namespace App\Support;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\User;

/**
 * Canonical AvanaHR sidebar navigation. Each item is tied to a tenant feature
 * code AND the permission module(s) it requires, so the menu a user sees stays
 * in sync with BOTH the Super Admin's feature toggles (tenant_features) and the
 * role permissions configured on the Hak Akses screen.
 */
final class AvanaNav
{
    /**
     * Permission-module prefixes that grant access to the admin-only screens
     * (Hak Akses, Menu & Fitur). Mirrors the "Pengaturan" matrix row.
     *
     * @var array<int, string>
     */
    private const MANAGE_MODULES = ['settings', 'role', 'permission'];

    /**
     * Full navigation definition. `feature` null = no feature gate.
     * `modules` = permission modules required (empty = always, for everyone).
     * `adminOnly` = requires manage permission (or super_admin).
     *
     * @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            ['title' => null, 'items' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => '/dashboard', 'feature' => null, 'modules' => []],
            ]],
            ['title' => 'MANAJEMEN', 'items' => [
                ['id' => 'karyawan', 'label' => 'Karyawan', 'icon' => 'users', 'href' => '/avana/employees', 'feature' => 'hr_core', 'modules' => ['employee']],
                ['id' => 'absensi', 'label' => 'Absensi', 'icon' => 'fingerprint', 'href' => '/avana/absensi', 'feature' => 'attendance', 'modules' => ['attendance']],
                ['id' => 'cuti', 'label' => 'Cuti & Lembur', 'icon' => 'palmtree', 'href' => '/avana/cuti', 'feature' => 'leave', 'modules' => ['leave', 'overtime', 'wfh']],
                ['id' => 'approval', 'label' => 'Persetujuan', 'icon' => 'check-check', 'href' => '/avana/approval', 'feature' => null, 'modules' => ['leave', 'overtime', 'wfh', 'attendance', 'team']],
                ['id' => 'payroll', 'label' => 'Payroll', 'icon' => 'wallet', 'href' => '/avana/payroll', 'feature' => 'payroll', 'modules' => ['payroll']],
                ['id' => 'payroll-config', 'label' => 'BPJS & Pajak', 'icon' => 'shield-check', 'href' => '/avana/payroll/konfigurasi', 'feature' => 'payroll', 'modules' => ['bpjs', 'pph21', 'payroll']],
            ]],
            ['title' => 'SISTEM', 'items' => [
                ['id' => 'perusahaan', 'label' => 'Perusahaan', 'icon' => 'building-2', 'href' => '/avana/perusahaan', 'feature' => 'organization', 'modules' => ['branch', 'department', 'position', 'organization']],
                ['id' => 'pengguna', 'label' => 'Pengguna', 'icon' => 'user-cog', 'href' => '/avana/pengguna', 'feature' => null, 'modules' => ['user']],
                ['id' => 'laporan', 'label' => 'Laporan', 'icon' => 'chart-column', 'href' => '/avana/laporan', 'feature' => 'analytics', 'modules' => ['report']],
                ['id' => 'hak-akses', 'label' => 'Hak Akses', 'icon' => 'shield-check', 'href' => '/avana/hak-akses', 'feature' => null, 'modules' => self::MANAGE_MODULES, 'adminOnly' => true],
                ['id' => 'fitur', 'label' => 'Menu & Fitur', 'icon' => 'toggle-right', 'href' => '/avana/fitur', 'feature' => null, 'modules' => self::MANAGE_MODULES, 'adminOnly' => true],
                ['id' => 'audit', 'label' => 'Audit Trail', 'icon' => 'history', 'href' => '/avana/audit', 'feature' => null, 'modules' => ['audit']],
            ]],
            ['title' => 'PLATFORM', 'items' => [
                ['id' => 'klien', 'label' => 'Klien / Tenant', 'icon' => 'building-2', 'href' => '/avana/klien', 'feature' => null, 'modules' => [], 'superAdminOnly' => true],
            ]],
        ];
    }

    /**
     * The navigation visible to the given user, filtered by enabled tenant
     * features AND the user's role permissions. With no user (prototype/public
     * pages) the full menu is returned so those screens still render.
     *
     * @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}>
     */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return self::stripMeta(self::groups());
        }

        $roleCodes = $user->roles()->pluck('code');
        $isSuperAdmin = $roleCodes->contains('super_admin');

        $roleIds = $user->roles()->pluck('roles.id');
        $userModules = Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->distinct()
            ->pluck('module');

        $enabledCodes = $user->tenant_id === null
            ? collect()
            : Feature::whereIn('id', $user->tenant?->features()->where('is_enabled', true)->pluck('feature_id') ?? collect())->pluck('code');

        $canManage = $isSuperAdmin || $userModules->intersect(self::MANAGE_MODULES)->isNotEmpty();

        $groups = [];
        foreach (self::groups() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                // Super-admin-only platform screens (e.g. tenant management).
                if (($item['superAdminOnly'] ?? false) && ! $isSuperAdmin) {
                    continue;
                }

                // Feature gate (Super Admin's Menu & Fitur toggles).
                if ($item['feature'] !== null && ! $enabledCodes->contains($item['feature'])) {
                    continue;
                }

                // Admin-only screens require manage permission.
                if ($item['adminOnly'] ?? false) {
                    if (! $canManage) {
                        continue;
                    }
                } elseif (! $isSuperAdmin && $item['modules'] !== [] && $userModules->intersect($item['modules'])->isEmpty()) {
                    // Permission gate (Hak Akses matrix) for non-super-admins.
                    continue;
                }

                $items[] = ['id' => $item['id'], 'label' => $item['label'], 'icon' => $item['icon'], 'href' => $item['href']];
            }
            if ($items !== []) {
                $groups[] = ['title' => $group['title'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @param  array<int, array{title: ?string, items: array<int, array<string, mixed>>}>  $groups
     * @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}>
     */
    private static function stripMeta(array $groups): array
    {
        return array_map(fn (array $group): array => [
            'title' => $group['title'],
            'items' => array_map(fn (array $item): array => [
                'id' => $item['id'], 'label' => $item['label'], 'icon' => $item['icon'], 'href' => $item['href'],
            ], $group['items']),
        ], $groups);
    }
}
