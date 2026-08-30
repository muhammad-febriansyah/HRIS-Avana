<?php

namespace App\Support;

use App\Models\MobileMenuItem;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The Flutter app's menus: the Menu Cepat carousel and the bottom navigation
 * bar, the rows each ships with, and which of them a given account sees.
 *
 * Mirrors what {@see AvanaNav} does for the web sidebar, kept separate because
 * a phone row has no href, section or parent — only a route, an icon and a
 * place in one flat list.
 */
class MobileMenu
{
    /** Menu Cepat: the carousel of tiles on the home tab. */
    public const GROUP_QUICK = 'quick';

    /** The bottom navigation bar. */
    public const GROUP_TAB = 'tab';

    /**
     * Tabs the bar cannot lose. The app needs somewhere to land after login and
     * a way to reach the profile — and so sign out — so these two stay on
     * whatever a tenant switches off.
     *
     * @var array<int, string>
     */
    public const LOCKED_TABS = ['beranda', 'profil'];

    /**
     * The tenant feature each tile depends on, so switching a module off in
     * Kelola Fitur takes it off the phone too — the web sidebar has always
     * worked that way, and a tile that only leads to a 403 reads as a bug.
     *
     * A tile missing from this map is unconditional (Dasbor).
     *
     * @var array<string, string>
     */
    public const TILE_FEATURES = [
        'cuti' => 'leave',
        'izin' => 'leave',
        'lembur' => 'overtime',
        'wfh' => 'wfh',
        'jadwal' => 'attendance',
        'riwayat' => 'attendance',
        'koreksi' => 'attendance',
        'kunjungan' => 'attendance',
        'timesheet' => 'timesheet',
        'tukar_shift' => 'shift_swap',
        'slip_gaji' => 'payroll',
        'reimburse' => 'reimbursement',
        'uang_muka' => 'cash_advance',
        'settlement' => 'claim',
        'dokumen' => 'document',
        'perasaan' => 'hr_core',
        'rapat' => 'meeting_ai',
        'token_ai' => 'ai',
    ];

    /**
     * The tenant feature behind each bottom tab. Same idea as
     * {@see self::TILE_FEATURES}: a company that switched Ruang Kita off should
     * not keep staring at its tab.
     *
     * Beranda and Profil are absent on purpose — see {@see self::LOCKED_TABS}.
     *
     * @var array<string, string>
     */
    public const TAB_FEATURES = [
        'sosmed' => 'social',
        'absensi' => 'attendance',
        'pengumuman' => 'announcement',
    ];

    /**
     * Tiles seeded switched OFF: the backend is live but the Flutter screen
     * they point at has not shipped yet, so an active tile would send whoever
     * tapped it to a route the app does not know.
     *
     * They stay in {@see self::defaults()} so the row exists, is feature-gated
     * like any other, and can be switched on from Kelola Menu the moment the
     * screen lands — no migration needed then.
     *
     * @var array<int, string>
     */
    public const PENDING_TILES = ['timesheet'];

    /**
     * The bottom bar the app is built with, in the order it lays it out.
     *
     * `key` is what the Flutter side matches on to pick the screen and the
     * icon; `route` stays empty because a tab is switched to, not navigated to.
     *
     * @return array<int, array{key: string, label: string, icon: string, color: string, route: string}>
     */
    public static function tabDefaults(): array
    {
        return [
            ['key' => 'beranda', 'label' => 'Beranda', 'icon' => 'home_2', 'color' => '#2F54C9', 'route' => ''],
            ['key' => 'sosmed', 'label' => 'Ruang Kita', 'icon' => 'people', 'color' => '#2F54C9', 'route' => ''],
            ['key' => 'absensi', 'label' => 'Absensi', 'icon' => 'finger_scan', 'color' => '#2F54C9', 'route' => ''],
            ['key' => 'pengumuman', 'label' => 'Pengumuman', 'icon' => 'volume_high', 'color' => '#2F54C9', 'route' => ''],
            ['key' => 'profil', 'label' => 'Profil', 'icon' => 'user', 'color' => '#2F54C9', 'route' => ''],
        ];
    }

    /** Whether this row is a bottom tab that must stay switched on. */
    public static function isLockedTab(MobileMenuItem $item): bool
    {
        return $item->group === self::GROUP_TAB && in_array($item->key, self::LOCKED_TABS, true);
    }

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
            ['key' => 'timesheet', 'label' => 'Timesheet', 'icon' => 'task_square', 'color' => '#0891B2', 'route' => '/timesheet'],
            ['key' => 'dokumen', 'label' => 'Dokumen', 'icon' => 'document_text', 'color' => '#9333EA', 'route' => '/dokumen'],
            ['key' => 'perasaan', 'label' => 'Perasaan', 'icon' => 'emoji_happy', 'color' => '#2547F9', 'route' => '/mood'],
            ['key' => 'rapat', 'label' => 'AI Recorder', 'icon' => 'microphone_2', 'color' => '#4F46E5', 'route' => '/meeting'],
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
                [
                    ...$tile,
                    'group' => self::GROUP_QUICK,
                    'sort_order' => $index,
                    'is_active' => ! in_array($tile['key'], self::PENDING_TILES, true),
                    'is_system' => true,
                ],
            );
        }

        foreach (self::tabDefaults() as $index => $tab) {
            MobileMenuItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $tab['key']],
                [...$tab, 'group' => self::GROUP_TAB, 'sort_order' => $index, 'is_active' => true, 'is_system' => true],
            );
        }
    }

    /**
     * The tenant's rows for one group, in display order, seeding the defaults
     * the first time anyone asks — a tenant created before this existed has no
     * rows, and an empty Menu Cepat would read as the app being broken.
     *
     * Seeding is keyed per group: a tenant set up before the bottom bar became
     * configurable already has its Menu Cepat, so only the tabs are missing.
     *
     * @return Collection<int, MobileMenuItem>
     */
    public static function forTenant(int $tenantId, string $group = self::GROUP_QUICK): Collection
    {
        if (MobileMenuItem::forTenant($tenantId)->where('group', $group)->doesntExist()) {
            self::seedDefaultsFor($tenantId);
        }

        return MobileMenuItem::forTenant($tenantId)
            ->where('group', $group)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** The tenant's bottom tabs, in bar order. */
    public static function tabsForTenant(int $tenantId): Collection
    {
        return self::forTenant($tenantId, self::GROUP_TAB);
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
        return self::resolveFor($user, self::GROUP_QUICK, self::TILE_FEATURES);
    }

    /**
     * What one account sees in the bottom bar. Same rules as Menu Cepat, minus
     * the tabs that are never dropped: hiding Beranda or Profil would leave the
     * app with no landing page and no way out.
     *
     * @return array<int, array{key: string, label: string, icon: string, color: string, route: string}>
     */
    public static function tabsForUser(User $user): array
    {
        $tabs = self::resolveFor($user, self::GROUP_TAB, self::TAB_FEATURES);

        // A tenant whose rows predate the locked-tab rule could still hold an
        // inactive Beranda; put the survivors back rather than ship a bar the
        // app cannot navigate.
        if (! empty($tabs)) {
            return $tabs;
        }

        return array_values(array_filter(
            self::tabDefaults(),
            fn (array $tab): bool => in_array($tab['key'], self::LOCKED_TABS, true),
        ));
    }

    /**
     * The rows of one group this account keeps: the tenant's active ones, minus
     * those hidden from every role they hold, minus those whose tenant feature
     * is switched off.
     *
     * @param  array<string, string>  $featureMap
     * @return array<int, array{key: string, label: string, icon: string, color: string, route: string}>
     */
    private static function resolveFor(User $user, string $group, array $featureMap): array
    {
        if ($user->tenant_id === null) {
            return [];
        }

        $roleIds = $user->roles()->pluck('roles.id');
        $hidden = self::keysHiddenForAll($roleIds);
        $features = FeatureGate::codesFor($user);

        return self::forTenant($user->tenant_id, $group)
            ->filter(fn (MobileMenuItem $tile): bool => $tile->is_active || self::isLockedTab($tile))
            ->reject(fn (MobileMenuItem $tile): bool => in_array($tile->key, $hidden, true) && ! self::isLockedTab($tile))
            ->reject(function (MobileMenuItem $tile) use ($features, $featureMap): bool {
                $feature = $featureMap[$tile->key] ?? null;

                return $features !== null && $feature !== null && ! $features->contains($feature);
            })
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
