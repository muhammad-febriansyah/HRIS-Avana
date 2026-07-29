<?php

namespace App\Support;

use App\Models\MobileMenuItem;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The Flutter app's Menu Cepat: the tiles it ships with, and which of them a
 * given account actually sees.
 *
 * Mirrors what {@see AvanaNav} does for the web sidebar, kept separate because
 * a phone tile has no href, section or parent — only a route, an icon and a
 * place in one flat carousel.
 */
class MobileMenu
{
    /**
     * The tiles the app is built with, in the order it lays them out.
     *
     * `key` matches the identifier the Flutter side sends back; `icon` is an
     * Iconsax name and `route` a GetX route, both written exactly as the app
     * spells them, so a row can be rendered without translation.
     *
     * @return array<int, array{key: string, label: string, icon: string, color: string, route: string}>
     */
    public static function defaults(): array
    {
        return [
            ['key' => 'dasbor', 'label' => 'Dasbor', 'icon' => 'category', 'color' => '#7C3AED', 'route' => '/dashboard'],
            ['key' => 'cuti', 'label' => 'Cuti', 'icon' => 'sun_1', 'color' => '#22C55E', 'route' => '/leave'],
            ['key' => 'izin', 'label' => 'Izin', 'icon' => 'calendar_remove', 'color' => '#9333EA', 'route' => '/permission'],
            ['key' => 'lembur', 'label' => 'Lembur', 'icon' => 'timer_1', 'color' => '#F59E0B', 'route' => '/overtime'],
            ['key' => 'wfh', 'label' => 'WFH', 'icon' => 'house', 'color' => '#0EA5E9', 'route' => '/wfh'],
            ['key' => 'jadwal', 'label' => 'Jadwal', 'icon' => 'calendar_1', 'color' => '#0D9488', 'route' => '/schedule'],
            ['key' => 'riwayat', 'label' => 'Riwayat', 'icon' => 'clock', 'color' => '#64748B', 'route' => '/riwayat'],
            ['key' => 'koreksi', 'label' => 'Koreksi', 'icon' => 'clock', 'color' => '#4F46E5', 'route' => '/attendance-correction'],
            ['key' => 'tukar_shift', 'label' => 'Tukar Shift', 'icon' => 'arrow_swap_horizontal', 'color' => '#0D9488', 'route' => '/shift-swap'],
            ['key' => 'slip_gaji', 'label' => 'Slip Gaji', 'icon' => 'receipt_2', 'color' => '#0891B2', 'route' => '/payslip'],
            ['key' => 'reimburse', 'label' => 'Reimburse', 'icon' => 'wallet_money', 'color' => '#DB2777', 'route' => '/reimbursement'],
            ['key' => 'uang_muka', 'label' => 'Uang Muka', 'icon' => 'wallet_add', 'color' => '#7C3AED', 'route' => '/kasbon'],
            ['key' => 'settlement', 'label' => 'Settlement', 'icon' => 'receipt_2_1', 'color' => '#2563EB', 'route' => '/settlement'],
            ['key' => 'kunjungan', 'label' => 'Kunjungan', 'icon' => 'location', 'color' => '#E11D48', 'route' => '/visiting'],
            ['key' => 'dokumen', 'label' => 'Dokumen', 'icon' => 'document_text', 'color' => '#9333EA', 'route' => '/dokumen'],
            ['key' => 'perasaan', 'label' => 'Perasaan', 'icon' => 'emoji_happy', 'color' => '#2547F9', 'route' => '/mood'],
            ['key' => 'token_ai', 'label' => 'Token AI', 'icon' => 'flash_1', 'color' => '#7C3AED', 'route' => '/ai-tokens'],
        ];
    }

    /**
     * Give a tenant the tiles it is missing, leaving anything already there —
     * including a renamed label or a reordered row — untouched.
     */
    public static function seedDefaultsFor(int $tenantId): void
    {
        foreach (self::defaults() as $index => $tile) {
            MobileMenuItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $tile['key']],
                [...$tile, 'sort_order' => $index, 'is_active' => true, 'is_system' => true],
            );
        }
    }

    /**
     * The tenant's tiles in display order, seeding the defaults the first time
     * anyone asks — a tenant created before this existed has no rows, and an
     * empty Menu Cepat would read as the app being broken.
     *
     * @return Collection<int, MobileMenuItem>
     */
    public static function forTenant(int $tenantId): Collection
    {
        if (MobileMenuItem::forTenant($tenantId)->doesntExist()) {
            self::seedDefaultsFor($tenantId);
        }

        return MobileMenuItem::forTenant($tenantId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * What one account sees in Menu Cepat: the tenant's active tiles, minus the
     * ones hidden from every role they hold.
     *
     * Hidden-from-every-role rather than from any: somebody with two roles keeps
     * a tile while either role still shows it, which is how the web sidebar
     * already resolves a multi-role account.
     *
     * @return array<int, array{key: string, label: string, icon: string, color: string, route: string}>
     */
    public static function forUser(User $user): array
    {
        if ($user->tenant_id === null) {
            return [];
        }

        $roleIds = $user->roles()->pluck('roles.id');
        $hidden = self::keysHiddenForAll($roleIds);

        return self::forTenant($user->tenant_id)
            ->where('is_active', true)
            ->reject(fn (MobileMenuItem $tile): bool => in_array($tile->key, $hidden, true))
            ->map(fn (MobileMenuItem $tile): array => [
                'key' => $tile->key,
                'label' => $tile->label,
                'icon' => $tile->icon,
                'color' => $tile->color,
                'route' => $tile->route,
            ])
            ->values()
            ->all();
    }

    /**
     * Tile keys hidden from every one of the given roles.
     *
     * @param  Collection<int, int>  $roleIds
     * @return array<int, string>
     */
    private static function keysHiddenForAll(Collection $roleIds): array
    {
        if ($roleIds->isEmpty()) {
            return [];
        }

        return RoleMenuVisibility::query()
            ->whereIn('role_id', $roleIds)
            ->where('is_visible', false)
            ->where('menu_key', 'like', MobileMenuItem::VISIBILITY_PREFIX.'%')
            ->get(['role_id', 'menu_key'])
            ->groupBy('menu_key')
            ->filter(fn (Collection $rows): bool => $rows->pluck('role_id')->unique()->count() === $roleIds->count())
            ->keys()
            ->map(fn (string $key): string => substr($key, strlen(MobileMenuItem::VISIBILITY_PREFIX)))
            ->all();
    }

    /** Whether one role currently shows the given tile. */
    public static function visibleFor(Role $role, MobileMenuItem $tile): bool
    {
        return ! RoleMenuVisibility::query()
            ->where('role_id', $role->id)
            ->where('menu_key', $tile->visibilityKey())
            ->where('is_visible', false)
            ->exists();
    }
}
