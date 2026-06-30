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
 *
 * Items may be either a leaf (has `href`) or a collapsible parent (has
 * `children`). A parent is shown only when at least one child survives gating.
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
     * `children` = nested sub-menu of leaves.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function groups(): array
    {
        return [
            ['title' => null, 'items' => [
                self::leaf('dashboard', 'Dashboard', 'layout-dashboard', '/dashboard'),
            ]],
            ['title' => 'MANAJEMEN', 'items' => [
                self::parent('hr', 'Karyawan', 'users', [
                    self::leaf('karyawan', 'Data Karyawan', 'users', '/avana/employees', 'hr_core', ['employee']),
                    self::leaf('kontrak', 'Kontrak Kerja', 'file-text', '/avana/kontrak', 'hr_core', ['employee']),
                    self::leaf('mutasi', 'Mutasi & Karir', 'arrow-left-right', '/avana/mutasi', 'hr_core', ['employee']),
                    self::leaf('dokumen', 'Dokumen', 'folder', '/avana/dokumen', 'document'),
                    self::leaf('surat', 'Template Surat', 'file-signature', '/avana/surat', 'letter'),
                    self::leaf('offboarding', 'Offboarding', 'door-open', '/avana/offboarding', 'offboarding'),
                ]),
                self::parent('kehadiran', 'Kehadiran', 'fingerprint', [
                    self::leaf('absensi', 'Absensi', 'fingerprint', '/avana/absensi', 'attendance', ['attendance']),
                    self::leaf('roster', 'Roster Shift', 'calendar-clock', '/avana/roster', 'attendance', ['attendance']),
                    self::leaf('shift-swap', 'Tukar Shift', 'repeat', '/avana/shift-swap', 'shift_swap'),
                    self::leaf('timesheet', 'Timesheet', 'clock', '/avana/timesheet', 'timesheet'),
                    self::leaf('sanksi', 'Sanksi Absensi', 'octagon-alert', '/avana/sanksi', 'attendance', ['attendance']),
                    self::leaf('visiting', 'Visiting Pekerjaan', 'map-pin', '/avana/visiting', 'attendance', ['attendance']),
                ]),
                self::leaf('cuti', 'Cuti & Lembur', 'palmtree', '/avana/cuti', 'leave', ['leave', 'overtime', 'wfh']),
                self::leaf('dinas', 'Perjalanan Dinas', 'plane', '/avana/dinas', 'hr_core', ['employee']),
                self::parent('persetujuan', 'Persetujuan', 'check-check', [
                    self::leaf('approval', 'Pusat Persetujuan', 'check-check', '/avana/approval', null, ['leave', 'overtime', 'wfh', 'attendance', 'team']),
                    self::leaf('delegasi', 'Delegasi Approval', 'user-round-cog', '/avana/delegasi', 'delegation'),
                ]),
            ]],
            ['title' => 'PAYROLL & KEUANGAN', 'items' => [
                self::parent('payroll', 'Payroll', 'wallet', [
                    self::leaf('payroll', 'Payroll', 'wallet', '/avana/payroll', 'payroll', ['payroll']),
                    self::leaf('payroll-config', 'BPJS & Pajak', 'shield-check', '/avana/payroll/konfigurasi', 'payroll', ['bpjs', 'pph21', 'payroll']),
                    self::leaf('payroll-components', 'Komponen Gaji', 'sliders-horizontal', '/avana/payroll/components', 'payroll', ['payroll']),
                    self::leaf('struktur-upah', 'Struktur & Skala Upah', 'ruler', '/avana/struktur-upah', 'salary_structure'),
                    self::leaf('jurnal', 'Jurnal Akuntansi', 'book-open', '/avana/jurnal', 'journal'),
                    self::leaf('anggaran', 'Anggaran (Budget)', 'piggy-bank', '/avana/anggaran', 'budget'),
                ]),
                self::parent('benefit-grp', 'Benefit & Klaim', 'gift', [
                    self::leaf('benefit', 'Benefit', 'gift', '/avana/benefit', 'hr_core', ['employee']),
                    self::leaf('klaim', 'Klaim & Reimbursement', 'receipt', '/avana/klaim', 'claim'),
                    self::leaf('pinjaman', 'Pinjaman', 'banknote', '/avana/pinjaman', 'loan'),
                    self::leaf('kasbon', 'Kasbon', 'hand-coins', '/avana/kasbon', 'payroll', ['payroll']),
                ]),
            ]],
            ['title' => 'TALENTA', 'items' => [
                self::parent('rekrutmen', 'Rekrutmen', 'user-plus', [
                    self::leaf('rekrutmen', 'Lowongan & Pelamar', 'user-plus', '/avana/rekrutmen', 'recruitment'),
                    self::leaf('onboarding', 'Onboarding', 'clipboard-check', '/avana/onboarding', 'onboarding'),
                ]),
                self::parent('kinerja', 'Kinerja', 'target', [
                    self::leaf('kinerja', 'Penilaian Kinerja', 'star', '/avana/kinerja', 'performance'),
                    self::leaf('okr', 'OKR & Goal', 'target', '/avana/okr', 'okr'),
                    self::leaf('kompetensi', 'Kompetensi', 'brain', '/avana/kompetensi', 'competency'),
                    self::leaf('talenta', 'Talenta & Suksesi', 'grid-3x3', '/avana/talenta', 'talent'),
                ]),
                self::leaf('pembelajaran', 'Pembelajaran (LMS)', 'graduation-cap', '/avana/pembelajaran', 'learning'),
            ]],
            ['title' => 'LAYANAN', 'items' => [
                self::leaf('helpdesk', 'HR Helpdesk', 'life-buoy', '/avana/helpdesk', 'helpdesk'),
                self::leaf('pengumuman', 'Pengumuman', 'megaphone', '/avana/pengumuman', 'announcement'),
                self::leaf('survei', 'Survei Karyawan', 'clipboard-list', '/avana/survei', 'survey'),
                self::leaf('kalender', 'Kalender Acara', 'calendar-days', '/avana/kalender', 'calendar'),
                self::leaf('ai', 'AI Assistant', 'sparkles', '/avana/ai', 'ai'),
                self::leaf('aset', 'Manajemen Aset', 'package', '/avana/aset', 'asset'),
                self::leaf('crm', 'CRM', 'briefcase', '/avana/crm', 'crm'),
            ]],
            ['title' => 'ANALITIK', 'items' => [
                self::leaf('laporan', 'Laporan', 'chart-column', '/avana/laporan', 'analytics', ['report']),
                self::leaf('analytics', 'HR Analytics', 'chart-pie', '/avana/analytics', 'dynamic_report'),
                self::leaf('dynamic-report', 'Dynamic Report', 'table', '/avana/dynamic-report', 'dynamic_report'),
            ]],
            ['title' => 'SISTEM', 'items' => [
                self::leaf('perusahaan', 'Perusahaan', 'building-2', '/avana/perusahaan', 'organization', ['branch', 'department', 'position', 'organization']),
                self::leaf('pengguna', 'Pengguna', 'user-cog', '/avana/pengguna', null, ['user']),
                self::leaf('hak-akses', 'Hak Akses', 'shield-check', '/avana/hak-akses', null, self::MANAGE_MODULES, true),
                self::leaf('fitur', 'Menu & Fitur', 'toggle-right', '/avana/fitur', null, self::MANAGE_MODULES, true),
                self::leaf('audit', 'Audit Trail', 'history', '/avana/audit', null, ['audit']),
            ]],
            ['title' => 'PLATFORM', 'items' => [
                self::leaf('klien', 'Klien / Tenant', 'building-2', '/avana/klien', null, [], false, true),
                self::leaf('billing', 'Billing & Invoice', 'receipt-text', '/avana/billing', null, [], false, true),
                self::leaf('website-settings', 'Pengaturan Website', 'globe', '/avana/website-settings', null, [], false, true),
            ]],
        ];
    }

    /**
     * Build a leaf nav item.
     *
     * @param  array<int, string>  $modules
     * @return array<string, mixed>
     */
    private static function leaf(string $id, string $label, string $icon, string $href, ?string $feature = null, array $modules = [], bool $adminOnly = false, bool $superAdminOnly = false): array
    {
        return [
            'id' => $id, 'label' => $label, 'icon' => $icon, 'href' => $href,
            'feature' => $feature, 'modules' => $modules,
            'adminOnly' => $adminOnly, 'superAdminOnly' => $superAdminOnly,
        ];
    }

    /**
     * Build a collapsible parent item with nested leaves.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private static function parent(string $id, string $label, string $icon, array $children): array
    {
        return ['id' => $id, 'label' => $label, 'icon' => $icon, 'children' => $children];
    }

    /**
     * The navigation visible to the given user, filtered by enabled tenant
     * features AND the user's role permissions. With no user (prototype/public
     * pages) the full menu is returned so those screens still render.
     *
     * @return array<int, array<string, mixed>>
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

        $visible = function (array $leaf) use ($isSuperAdmin, $enabledCodes, $userModules, $canManage): bool {
            if (($leaf['superAdminOnly'] ?? false) && ! $isSuperAdmin) {
                return false;
            }
            if (($leaf['feature'] ?? null) !== null && ! $enabledCodes->contains($leaf['feature'])) {
                return false;
            }
            if ($leaf['adminOnly'] ?? false) {
                return $canManage;
            }
            if (! $isSuperAdmin && ($leaf['modules'] ?? []) !== [] && $userModules->intersect($leaf['modules'])->isEmpty()) {
                return false;
            }

            return true;
        };

        $groups = [];
        foreach (self::groups() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if (isset($item['children'])) {
                    $children = [];
                    foreach ($item['children'] as $child) {
                        if ($visible($child)) {
                            $children[] = self::pluck($child);
                        }
                    }
                    if ($children !== []) {
                        $items[] = ['id' => $item['id'], 'label' => $item['label'], 'icon' => $item['icon'], 'children' => $children];
                    }
                } elseif ($visible($item)) {
                    $items[] = self::pluck($item);
                }
            }
            if ($items !== []) {
                $groups[] = ['title' => $group['title'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $leaf
     * @return array{id: string, label: string, icon: string, href: string}
     */
    private static function pluck(array $leaf): array
    {
        return ['id' => $leaf['id'], 'label' => $leaf['label'], 'icon' => $leaf['icon'], 'href' => $leaf['href']];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private static function stripMeta(array $groups): array
    {
        return array_map(fn (array $group): array => [
            'title' => $group['title'],
            'items' => array_map(function (array $item): array {
                if (isset($item['children'])) {
                    return ['id' => $item['id'], 'label' => $item['label'], 'icon' => $item['icon'],
                        'children' => array_map(fn (array $c): array => self::pluck($c), $item['children'])];
                }

                return self::pluck($item);
            }, $group['items']),
        ], $groups);
    }
}
