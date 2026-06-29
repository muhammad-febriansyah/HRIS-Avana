<?php

namespace App\Support;

use App\Models\Feature;
use App\Models\User;

/**
 * Canonical AvanaHR sidebar navigation. Each item is tied to a tenant feature
 * code; the menu a user sees is filtered by which features the Super Admin has
 * enabled for their tenant (tenant_features) plus the user's role.
 */
final class AvanaNav
{
    /**
     * Full navigation definition. `feature` null = always shown.
     * `adminOnly` true = only super_admin / admin_tenant_hr.
     *
     * @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            ['title' => null, 'items' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => '/dashboard', 'feature' => null],
            ]],
            ['title' => 'MANAJEMEN', 'items' => [
                ['id' => 'karyawan', 'label' => 'Karyawan', 'icon' => 'users', 'href' => '/avana/employees', 'feature' => 'hr_core'],
                ['id' => 'absensi', 'label' => 'Absensi', 'icon' => 'fingerprint', 'href' => '/avana/absensi', 'feature' => 'attendance'],
                ['id' => 'cuti', 'label' => 'Cuti & Lembur', 'icon' => 'palmtree', 'href' => '/avana/cuti', 'feature' => 'leave'],
                ['id' => 'payroll', 'label' => 'Payroll', 'icon' => 'wallet', 'href' => '/avana/payroll', 'feature' => 'payroll'],
            ]],
            ['title' => 'PERSONAL', 'items' => [
                ['id' => 'ess', 'label' => 'Self-Service', 'icon' => 'circle-user-round', 'href' => '/avana/ess', 'feature' => 'ess'],
            ]],
            ['title' => 'SISTEM', 'items' => [
                ['id' => 'laporan', 'label' => 'Laporan', 'icon' => 'chart-column', 'href' => '/avana/laporan', 'feature' => 'analytics'],
                ['id' => 'hak-akses', 'label' => 'Hak Akses', 'icon' => 'shield-check', 'href' => '/avana/hak-akses', 'feature' => null, 'adminOnly' => true],
                ['id' => 'fitur', 'label' => 'Menu & Fitur', 'icon' => 'toggle-right', 'href' => '/avana/fitur', 'feature' => null, 'adminOnly' => true],
            ]],
        ];
    }

    /**
     * The navigation visible to the given user, filtered by enabled tenant
     * features and role. With no user (prototype/public pages) the full menu
     * is returned so those screens still render.
     *
     * @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}>
     */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return self::stripMeta(self::groups());
        }

        $roleCodes = $user->roles()->pluck('code');
        $isAdmin = $roleCodes->contains('super_admin') || $roleCodes->contains('admin_tenant_hr');

        $enabled = $user->tenant_id === null
            ? collect()
            : $user->tenant?->features()->where('is_enabled', true)->pluck('feature_id') ?? collect();

        $enabledCodes = $user->tenant_id === null
            ? collect()
            : Feature::whereIn('id', $enabled)->pluck('code');

        $groups = [];
        foreach (self::groups() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if (($item['adminOnly'] ?? false) && ! $isAdmin) {
                    continue;
                }
                if ($item['feature'] !== null && ! $enabledCodes->contains($item['feature'])) {
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
