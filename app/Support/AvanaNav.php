<?php

namespace App\Support;

use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\RoleMenuVisibility;
use App\Models\User;
use Illuminate\Support\Collection;

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
            // Employee self-service. Gated on the `own` permission module, which
            // is all a plain karyawan role carries — so this is the one group
            // they see, and privileged roles see it alongside the admin menus.
            ['title' => 'LAYANAN SAYA', 'items' => [
                self::leaf('saya-profil', 'Profil', 'user', '/avana/saya/profil', 'ess', ['own']),
                self::leaf('saya-absensi', 'Absensi', 'fingerprint', '/avana/saya/absensi', 'ess', ['own']),
                self::leaf('saya-koreksi', 'Koreksi Absensi', 'clock-alert', '/avana/saya/koreksi-absensi', 'ess', ['own']),
                self::leaf('saya-jadwal', 'Jadwal', 'calendar-clock', '/avana/saya/jadwal', 'ess', ['own']),
                self::leaf('saya-organisasi', 'Struktur Organisasi', 'network', '/avana/saya/organisasi', 'ess', ['own']),
                self::leaf('saya-token-ai', 'Token AI Saya', 'wallet', '/avana/saya/token-ai', 'ess', ['own']),
                self::leaf('saya-cuti', 'Cuti', 'palmtree', '/avana/saya/cuti', 'ess', ['own']),
                self::leaf('saya-lembur', 'Lembur', 'timer', '/avana/saya/lembur', 'ess', ['own']),
                self::leaf('saya-izin', 'Izin', 'file-clock', '/avana/saya/izin', 'ess', ['own']),
                self::leaf('saya-kalender', 'Kalender', 'calendar-days', '/avana/saya/kalender', 'ess', ['own']),
                self::leaf('saya-tugas', 'Tugas', 'clipboard-list', '/avana/saya/tugas', 'ess', ['own']),
                self::leaf('saya-kontrak', 'Kontrak', 'file-text', '/avana/saya/kontrak', 'ess', ['own']),
                self::leaf('saya-kinerja', 'Kinerja', 'trending-up', '/avana/saya/kinerja', 'ess', ['own']),
                self::leaf('saya-pembelajaran', 'Pembelajaran', 'graduation-cap', '/avana/saya/pembelajaran', 'ess', ['own']),
                self::leaf('saya-benefit', 'Benefit', 'gift', '/avana/saya/benefit', 'ess', ['own']),
                self::leaf('saya-dinas', 'Perjalanan Dinas', 'plane', '/avana/saya/perjalanan-dinas', 'ess', ['own']),
                self::leaf('saya-slip', 'Slip Gaji', 'receipt', '/avana/saya/slip-gaji', 'ess', ['own']),
                self::leaf('saya-dokumen', 'Dokumen', 'folder', '/avana/saya/dokumen', 'ess', ['own']),
                self::leaf('saya-sop', 'SOP Perusahaan', 'book-open', '/avana/saya/sop', 'sop', ['own']),
                self::leaf('saya-sosmed', 'Ruang Kita', 'message-circle', '/avana/saya/sosmed', 'social', ['own']),
                self::leaf('saya-onboarding', 'Onboarding', 'clipboard-check', '/avana/saya/onboarding', 'ess', ['own']),
            ]],
            ['title' => 'MANAJEMEN', 'items' => [
                self::parent('hr', 'Karyawan', 'users', [
                    self::leaf('karyawan', 'Data Karyawan', 'users', '/avana/employees', 'hr_core', ['employee']),
                    self::leaf('organisasi', 'Struktur Organisasi', 'network', '/avana/organisasi', 'hr_core', ['employee']),
                    self::leaf('kontrak', 'Kontrak Kerja', 'file-text', '/avana/kontrak', 'hr_core', ['employee']),
                    self::leaf('mutasi', 'Mutasi & Karir', 'arrow-left-right', '/avana/mutasi', 'hr_core', ['employee']),
                    self::leaf('dokumen', 'Dokumen', 'folder', '/avana/dokumen', 'document', ['document']),
                    self::leaf('sop', 'SOP', 'book-open', '/avana/sop', 'sop', ['sop']),
                    self::leaf('surat', 'Template Surat', 'file-signature', '/avana/surat', 'letter', ['letter']),
                    self::leaf('offboarding', 'Offboarding', 'door-open', '/avana/offboarding', 'offboarding', ['offboarding']),
                ]),
                self::parent('kehadiran', 'Kehadiran', 'fingerprint', [
                    self::leaf('absensi', 'Absensi', 'fingerprint', '/avana/absensi', 'attendance', ['attendance']),
                    self::leaf('absensi-monitor', 'Monitor Kehadiran', 'map-pinned', '/avana/absensi/monitor', 'attendance', ['attendance']),
                    self::leaf('absensi-kebijakan', 'Kebijakan Absensi', 'shield-check', '/avana/absensi/kebijakan', 'attendance', ['attendance']),
                    self::leaf('roster', 'Roster Shift', 'calendar-clock', '/avana/roster', 'attendance', ['attendance']),
                    self::leaf('shift-swap', 'Tukar Shift', 'repeat', '/avana/shift-swap', 'shift_swap', ['shift_swap']),
                    self::leaf('timesheet', 'Timesheet', 'clock', '/avana/timesheet', 'timesheet', ['timesheet']),
                    self::leaf('sanksi', 'Sanksi Absensi', 'octagon-alert', '/avana/sanksi', 'attendance', ['attendance']),
                    self::leaf('visiting', 'Visiting Pekerjaan', 'map-pin', '/avana/visiting', 'attendance', ['attendance']),
                    self::leaf('mood', 'Mood Karyawan', 'smile', '/avana/mood', 'hr_core', ['employee']),
                ]),
                self::leaf('cuti', 'Cuti & Lembur', 'palmtree', '/avana/cuti', 'leave', ['leave', 'overtime', 'wfh']),
                self::leaf('dinas', 'Perjalanan Dinas', 'plane', '/avana/dinas', 'hr_core', ['employee']),
                self::parent('persetujuan', 'Persetujuan', 'check-check', [
                    self::leaf('approval', 'Pusat Persetujuan', 'check-check', '/avana/approval', null, ['leave', 'overtime', 'wfh', 'attendance', 'team']),
                    self::leaf('delegasi', 'Delegasi Approval', 'user-round-cog', '/avana/delegasi', 'delegation', ['delegation']),
                ]),
            ]],
            ['title' => 'PAYROLL & KEUANGAN', 'items' => [
                self::parent('payroll', 'Payroll', 'wallet', [
                    self::leaf('payroll', 'Payroll', 'wallet', '/avana/payroll', 'payroll', ['payroll']),
                    self::leaf('payroll-config', 'BPJS & Pajak', 'shield-check', '/avana/payroll/konfigurasi', 'payroll', ['bpjs', 'pph21', 'payroll']),
                    self::leaf('payroll-komponen', 'Master Komponen', 'layers', '/avana/payroll/komponen', 'payroll', ['payroll']),
                    self::leaf('payroll-master-gaji', 'Master Gaji', 'file-cog', '/avana/payroll/master-gaji', 'payroll', ['payroll']),
                    self::leaf('payroll-umr', 'UMR', 'map-pin', '/avana/payroll/umr', 'payroll', ['payroll']),
                    self::leaf('payroll-koreksi', 'Koreksi Gaji', 'pencil', '/avana/payroll/koreksi', 'payroll', ['payroll']),
                    self::leaf('payroll-rapel', 'Rapel Gaji', 'history', '/avana/payroll/rapel', 'payroll', ['payroll']),
                    self::leaf('payroll-sales-order', 'Sales Order', 'clipboard-list', '/avana/payroll/sales-order', 'payroll', ['payroll']),
                    self::leaf('payroll-perhitungan-hari', 'Perhitungan Hari', 'calendar-days', '/avana/payroll/perhitungan-hari', 'payroll', ['payroll']),
                    self::leaf('payroll-nilai-upah', 'Nilai Upah', 'coins', '/avana/payroll/nilai-upah', 'payroll', ['payroll']),
                    self::leaf('payroll-payday', 'Mapping Payday', 'calendar-clock', '/avana/payroll/payday', 'payroll', ['payroll']),
                    self::leaf('struktur-upah', 'Struktur & Skala Upah', 'ruler', '/avana/struktur-upah', 'salary_structure', ['salary_structure']),
                    self::leaf('jurnal', 'Jurnal Akuntansi', 'book-open', '/avana/jurnal', 'journal', ['journal']),
                    self::leaf('anggaran', 'Anggaran (Budget)', 'piggy-bank', '/avana/anggaran', 'budget', ['budget']),
                ]),
                self::parent('finance', 'Finance', 'receipt', [
                    self::leaf('reimbursement', 'Reimbursement', 'receipt', '/avana/reimbursement', 'reimbursement', ['claim']),
                    self::leaf('kasbon', 'Cash Advance', 'hand-coins', '/avana/kasbon', 'cash_advance', ['payroll']),
                    // `team` too: a line manager reviews their own reports'
                    // settlements, and their permissions all live under that
                    // module (team.claim.approve).
                    self::leaf('settlement', 'Settlement', 'file-check', '/avana/settlement', 'cash_advance', ['claim', 'team']),
                ]),
                self::parent('benefit-grp', 'Benefit & Klaim', 'gift', [
                    self::leaf('benefit', 'Benefit', 'gift', '/avana/benefit', 'hr_core', ['employee']),
                    // Superseded by Finance → Reimbursement, which covers the
                    // same ground with numbering, status guards and a payment
                    // method. Kept as an inactive row so the route stays gated
                    // (a missing menu row would leave /avana/klaim ungated) and
                    // so a tenant can switch it back on from the Menu Builder.
                    self::leaf('klaim', 'Klaim & Reimbursement', 'receipt', '/avana/klaim', 'claim', ['claim'], isActive: false),
                    self::leaf('pinjaman', 'Pinjaman', 'banknote', '/avana/pinjaman', 'loan', ['loan']),
                ]),
            ]],
            ['title' => 'TALENTA', 'items' => [
                self::parent('rekrutmen', 'Rekrutmen', 'user-plus', [
                    // 9-stage ATS flow, in order.
                    self::leaf('rekrutmen', 'Dashboard', 'layout-dashboard', '/avana/rekrutmen', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-hiring-request', 'Hiring Request', 'file-plus', '/avana/rekrutmen/hiring-request', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-requisition', 'Requisition', 'clipboard-list', '/avana/rekrutmen/requisition', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-jobs', 'Lowongan', 'briefcase', '/avana/rekrutmen/jobs', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-candidates', 'Kandidat', 'users', '/avana/rekrutmen/candidates', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-pipeline', 'Pipeline', 'git-branch', '/avana/rekrutmen/pipeline', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-interviews', 'Interview', 'calendar-days', '/avana/rekrutmen/interviews', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-offers', 'Penawaran', 'file-check', '/avana/rekrutmen/offers', 'recruitment', ['recruitment']),
                    self::leaf('onboarding', 'Onboarding', 'clipboard-check', '/avana/onboarding', 'onboarding', ['onboarding']),
                    self::leaf('rekrutmen-progress', 'Candidate Progress', 'activity', '/avana/rekrutmen/progress', 'recruitment', ['recruitment']),
                    // Supporting tools.
                    self::leaf('rekrutmen-pools', 'Talent Pool', 'layers', '/avana/rekrutmen/pools', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-ai', 'AI Intelligence', 'sparkles', '/avana/rekrutmen/ai', 'recruitment', ['recruitment']),
                    self::leaf('rekrutmen-analytics', 'Analitik', 'trending-up', '/avana/rekrutmen/analytics', 'recruitment', ['recruitment']),
                ]),
                self::parent('kinerja', 'Kinerja', 'target', [
                    self::leaf('kinerja', 'Penilaian Kinerja', 'star', '/avana/kinerja', 'performance', ['performance']),
                    self::leaf('okr', 'OKR & Goal', 'target', '/avana/okr', 'okr', ['okr']),
                    self::leaf('kompetensi', 'Kompetensi', 'brain', '/avana/kompetensi', 'competency', ['competency']),
                    self::leaf('talenta', 'Talenta & Suksesi', 'grid-3x3', '/avana/talenta', 'talent', ['talent']),
                ]),
                self::leaf('pembelajaran', 'Pembelajaran (LMS)', 'graduation-cap', '/avana/pembelajaran', 'learning', ['learning']),
            ]],
            ['title' => 'LAYANAN', 'items' => [
                self::leaf('helpdesk', 'HR Helpdesk', 'life-buoy', '/avana/helpdesk', 'helpdesk', ['helpdesk']),
                self::leaf('pengumuman', 'Pengumuman', 'megaphone', '/avana/pengumuman', 'announcement', ['announcement']),
                self::leaf('sosmed', 'Ruang Kita', 'users-round', '/avana/sosmed', 'social', ['social']),
                self::leaf('survei', 'Survei Karyawan', 'clipboard-list', '/avana/survei', 'survey', ['survey']),
                self::leaf('kalender', 'Kalender Acara', 'calendar-days', '/avana/kalender', 'calendar', ['calendar']),
                self::leaf('rapat', 'Rapat & Transkrip', 'mic', '/avana/rapat', 'meeting_ai', ['meeting']),
                self::leaf('ai', 'AI Assistant', 'sparkles', '/avana/ai', 'ai', ['ai']),
                self::leaf('aset', 'Manajemen Aset', 'package', '/avana/aset', 'asset', ['asset']),
                self::leaf('crm', 'CRM', 'briefcase', '/avana/crm', 'crm', ['crm']),
            ]],
            ['title' => 'ANALITIK', 'items' => [
                self::leaf('laporan', 'Laporan', 'chart-column', '/avana/laporan', 'analytics', ['report']),
                self::leaf('analytics', 'HR Analytics', 'chart-pie', '/avana/analytics', 'dynamic_report', ['dynamic_report']),
                self::leaf('attrition', 'Prediksi Resign', 'trending-down', '/avana/attrition', 'attrition', ['attrition']),
                self::leaf('dynamic-report', 'Dynamic Report', 'table', '/avana/dynamic-report', 'dynamic_report', ['dynamic_report']),
                self::leaf('report-studio', 'Report Studio', 'table-2', '/avana/report-studio', 'dynamic_report', ['dynamic_report']),
            ]],
            ['title' => 'SISTEM', 'items' => [
                self::leaf('perusahaan', 'Perusahaan', 'building-2', '/avana/perusahaan', 'organization', ['branch', 'department', 'position', 'organization']),
                self::leaf('pengguna', 'Pengguna', 'user-cog', '/avana/pengguna', null, ['user'], false, true),
                self::leaf('custom-fields', 'Field Kustom', 'list-plus', '/avana/custom-fields', null, self::MANAGE_MODULES, true),
                // Hak Akses now bundles per-role permissions, feature on/off, the
                // feature catalog CRUD, and the sidebar Menu Builder (as tabs) —
                // so the old Menu Builder / Menu & Fitur leaves are retired.
                self::leaf('hak-akses', 'Hak Akses', 'shield-check', '/avana/hak-akses', null, self::MANAGE_MODULES, true),
                self::leaf('approval-workflow', 'Alur Persetujuan', 'git-branch', '/avana/approval-workflow', null, self::MANAGE_MODULES, true),
                self::leaf('email-settings', 'Pengaturan Email', 'mail', '/avana/email-settings', null, self::MANAGE_MODULES, true),
                self::leaf('tampilan', 'Tampilan & Tema', 'palette', '/avana/tampilan', null, ['appearance'], false),
                self::leaf('langganan', 'Langganan', 'badge-check', '/avana/langganan', null, ['langganan'], false),
                self::leaf('token-ai', 'Token AI', 'coins', '/avana/token-ai', null, ['ai_topup'], false),
                self::leaf('audit', 'Audit Trail', 'history', '/avana/audit', null, ['audit']),
            ]],
            ['title' => 'PLATFORM', 'items' => [
                self::leaf('klien', 'Klien / Tenant', 'building-2', '/avana/klien', null, [], false, true),
                self::leaf('billing', 'Billing & Invoice', 'receipt-text', '/avana/billing', null, [], false, true),
                self::leaf('website-settings', 'Pengaturan Website', 'globe', '/avana/website-settings', null, [], false, true),
                self::leaf('ai-settings', 'Pengaturan AI', 'sparkles', '/avana/ai-settings', null, [], false, true),
                self::leaf('onboarding-slides', 'Onboarding App', 'smartphone', '/avana/onboarding-slides', null, [], false, true),
            ]],
        ];
    }

    /**
     * Build a leaf nav item.
     *
     * @param  array<int, string>  $modules
     * @return array<string, mixed>
     */
    private static function leaf(string $id, string $label, string $icon, string $href, ?string $feature = null, array $modules = [], bool $adminOnly = false, bool $superAdminOnly = false, bool $isActive = true): array
    {
        return [
            'id' => $id, 'label' => $label, 'icon' => $icon, 'href' => $href,
            'feature' => $feature, 'modules' => $modules,
            'adminOnly' => $adminOnly, 'superAdminOnly' => $superAdminOnly,
            'isActive' => $isActive,
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
    public static function forUser(?User $user, bool $platform = false): array
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

        // Self-service screens resolve the caller's own employee record, so an
        // account without one (a bare HR/admin login) would only reach a 403.
        $hasEmployee = $user->employee !== null;

        // Menus this user's roles have all hidden (Hak Akses → kolom Tampil).
        $hiddenKeys = $isSuperAdmin ? [] : RoleMenuVisibility::keysHiddenForAll($roleIds);

        $visible = function (array $leaf) use ($isSuperAdmin, $enabledCodes, $userModules, $canManage, $hasEmployee, $hiddenKeys): bool {
            if (($leaf['superAdminOnly'] ?? false) && ! $isSuperAdmin) {
                return false;
            }
            if (in_array($leaf['id'] ?? '', $hiddenKeys, true)) {
                return false;
            }
            if (($leaf['modules'] ?? []) === ['own'] && ! $hasEmployee) {
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

        // Super admin (not impersonating a tenant) gets the platform-only menu;
        // impersonating or a tenant admin gets that tenant's operational menu.
        $sourceGroups = $platform
            ? self::platformTenantGroups()
            : self::tenantGroups($user->tenant_id);

        $groups = [];
        foreach ($sourceGroups as $group) {
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
     * Permission modules that grant access to the admin-only screens.
     *
     * @return array<int, string>
     */
    public static function manageModules(): array
    {
        return self::MANAGE_MODULES;
    }

    /**
     * The navigation groups for a tenant: the DB-defined menu when the tenant
     * has customised it, otherwise the built-in default definition.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tenantGroups(?int $tenantId): array
    {
        if ($tenantId === null) {
            return self::groups();
        }

        $rows = MenuItem::forTenant($tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return self::groups();
        }

        return self::buildGroups($rows);
    }

    /**
     * Rebuild the nav group structure from flat DB rows.
     *
     * @param  Collection<int, MenuItem>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function buildGroups(Collection $rows): array
    {
        $byParent = $rows->groupBy(fn (MenuItem $row): int => (int) ($row->parent_id ?? 0));

        $sectionOrder = [];
        $sections = [];

        foreach ($byParent->get(0, collect()) as $item) {
            $children = $byParent->get($item->id, collect());

            if ($children->isNotEmpty()) {
                $leaf = [
                    'id' => $item->key,
                    'label' => $item->label,
                    'icon' => $item->icon,
                    'children' => $children->map(fn (MenuItem $child): array => self::rowToLeaf($child))->all(),
                ];
            } else {
                $leaf = self::rowToLeaf($item);
            }

            $section = $item->section;

            if (! array_key_exists($section, $sections)) {
                $sections[$section] = [];
                $sectionOrder[] = $section;
            }

            $sections[$section][] = $leaf;
        }

        return array_map(
            fn (?string $title): array => ['title' => $title, 'items' => $sections[$title]],
            $sectionOrder,
        );
    }

    /**
     * Convert a DB row into a nav leaf carrying its gating metadata.
     *
     * @return array<string, mixed>
     */
    private static function rowToLeaf(MenuItem $row): array
    {
        return [
            'id' => $row->key,
            'label' => $row->label,
            'icon' => $row->icon,
            'href' => $row->href,
            'feature' => $row->feature,
            'modules' => $row->modules ?? [],
            'adminOnly' => $row->admin_only,
            'superAdminOnly' => $row->super_admin_only,
        ];
    }

    /**
     * Every leaf across all groups, flattened (parents dropped).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allLeaves(?int $tenantId = null): array
    {
        $source = $tenantId === null ? self::groups() : self::tenantGroups($tenantId);
        $leaves = [];

        foreach ($source as $group) {
            foreach ($group['items'] as $item) {
                if (isset($item['children'])) {
                    foreach ($item['children'] as $child) {
                        $leaves[] = $child;
                    }
                } else {
                    $leaves[] = $item;
                }
            }
        }

        return $leaves;
    }

    /**
     * Every menu of a tenant as a flat list, in sidebar order, for the Hak Akses
     * matrix: one row per real menu (hidden ones included, so they can be switched
     * back on) with the section it sits under and the gating it carries.
     *
     * Platform-only menus are left out — they belong to the super admin's own
     * chrome, not to a tenant's role matrix.
     *
     * @return array<int, array{key: string, label: string, group: string, parent: string|null, href: string|null, feature: string|null, modules: array<int, string>, adminOnly: bool, isActive: bool, menuItemId: int|null}>
     */
    public static function menuRows(?int $tenantId): array
    {
        $rows = $tenantId === null
            ? collect()
            : MenuItem::forTenant($tenantId)->orderBy('sort_order')->orderBy('id')->get();

        return $rows->isNotEmpty()
            ? self::menuRowsFromDb($rows)
            : self::menuRowsFromDefaults();
    }

    /**
     * @param  Collection<int, MenuItem>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function menuRowsFromDb(Collection $rows): array
    {
        $byParent = $rows->groupBy(fn (MenuItem $row): int => (int) ($row->parent_id ?? 0));
        $flat = [];

        foreach ($byParent->get(0, collect()) as $item) {
            $children = $byParent->get($item->id, collect());

            if ($children->isEmpty()) {
                $flat[] = self::menuRow($item, $item->section, null);

                continue;
            }

            foreach ($children as $child) {
                $flat[] = self::menuRow($child, $item->section, $item->label);
            }
        }

        return array_values(array_filter($flat, fn (array $row): bool => ! $row['superAdminOnly']));
    }

    /**
     * @return array<string, mixed>
     */
    private static function menuRow(MenuItem $item, ?string $section, ?string $parentLabel): array
    {
        return [
            'key' => $item->key,
            'label' => $item->label,
            'group' => $section ?? 'LAINNYA',
            'parent' => $parentLabel,
            'href' => $item->href,
            'feature' => $item->feature,
            'modules' => $item->modules ?? [],
            'adminOnly' => (bool) $item->admin_only,
            'superAdminOnly' => (bool) $item->super_admin_only,
            'isActive' => (bool) $item->is_active,
            'menuItemId' => $item->id,
        ];
    }

    /**
     * Fallback for a context with no DB menu (a super admin with no tenant in
     * view): the built-in definition, shaped like the DB rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function menuRowsFromDefaults(): array
    {
        $flat = [];

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                $children = $item['children'] ?? [[...$item, '__self' => true]];

                foreach ($children as $leaf) {
                    if ($leaf['superAdminOnly'] ?? false) {
                        continue;
                    }

                    $flat[] = [
                        'key' => $leaf['id'],
                        'label' => $leaf['label'],
                        'group' => $group['title'] ?? 'LAINNYA',
                        'parent' => isset($item['children']) ? $item['label'] : null,
                        'href' => $leaf['href'] ?? null,
                        'feature' => $leaf['feature'] ?? null,
                        'modules' => $leaf['modules'] ?? [],
                        'adminOnly' => (bool) ($leaf['adminOnly'] ?? false),
                        'superAdminOnly' => false,
                        'isActive' => true,
                        'menuItemId' => null,
                    ];
                }
            }
        }

        return $flat;
    }

    /**
     * The access requirement for a request path, resolved by the longest leaf
     * href that prefixes it (so sub-routes like /avana/crm/{id} inherit the CRM
     * gate). Returns null when the path maps to no gated menu.
     *
     * A hidden (is_active=false) menu is reported with is_active=false so the
     * access gate can block it — hiding a menu removes both sidebar AND access.
     *
     * @return array{key: ?string, modules: array<int, string>, adminOnly: bool, superAdminOnly: bool, feature: ?string, is_active: bool}|null
     */
    public static function requirementFor(string $path, ?int $tenantId = null): ?array
    {
        $path = '/'.ltrim($path, '/');

        // When the tenant has a DB menu, resolve against ALL its items (active
        // and hidden) so a hidden menu still blocks direct-URL access.
        if ($tenantId !== null) {
            $rows = MenuItem::forTenant($tenantId)->whereNotNull('href')->get();

            if ($rows->isNotEmpty()) {
                $best = null;
                $bestLen = -1;

                foreach ($rows as $row) {
                    $href = (string) $row->href;

                    if ($href === '' || $href === '/dashboard') {
                        continue;
                    }

                    if (($path === $href || str_starts_with($path, $href.'/')) && strlen($href) > $bestLen) {
                        $best = $row;
                        $bestLen = strlen($href);
                    }
                }

                if ($best === null) {
                    return null;
                }

                return [
                    'key' => $best->key,
                    'modules' => $best->modules ?? [],
                    'adminOnly' => (bool) $best->admin_only,
                    'superAdminOnly' => (bool) $best->super_admin_only,
                    'feature' => $best->feature,
                    'is_active' => (bool) $best->is_active,
                ];
            }
        }

        $best = null;
        $bestLen = -1;

        foreach (self::allLeaves($tenantId) as $leaf) {
            $href = $leaf['href'] ?? '';

            if ($href === '' || $href === '/dashboard') {
                continue;
            }

            if (($path === $href || str_starts_with($path, $href.'/')) && strlen($href) > $bestLen) {
                $best = $leaf;
                $bestLen = strlen($href);
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'key' => $best['id'] ?? null,
            'modules' => $best['modules'] ?? [],
            'adminOnly' => $best['adminOnly'] ?? false,
            'superAdminOnly' => $best['superAdminOnly'] ?? false,
            'feature' => $best['feature'] ?? null,
            'is_active' => true,
        ];
    }

    /**
     * Populate a tenant's menu_items from the built-in default navigation.
     * Idempotent — existing customised rows are left untouched.
     *
     * A leaf is matched by `key` alone, NOT by (key, parent_id): the Menu
     * Builder lets a tenant move a leaf to another parent or to the top level,
     * and matching on the parent too would clone it back under the default
     * parent on the next re-seed. Parent (collapsible) rows are still matched
     * on (key, parent_id === null) because a parent may legitimately share its
     * key with its first child — "Payroll" > "Payroll", for example.
     */
    public static function seedDefaultsFor(int $tenantId): void
    {
        $order = 0;

        /** @var Collection<string, int> $seenLeafKeys */
        $seenLeafKeys = MenuItem::forTenant($tenantId)
            ->whereNotNull('href')
            ->pluck('key')
            ->flip();

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                $order++;

                $itemIsLeaf = ($item['href'] ?? null) !== null;

                if ($itemIsLeaf && $seenLeafKeys->has($item['id'])) {
                    continue;
                }

                $parent = MenuItem::firstOrCreate(
                    ['tenant_id' => $tenantId, 'key' => $item['id'], 'parent_id' => null],
                    [
                        'section' => $group['title'],
                        'label' => $item['label'],
                        'icon' => $item['icon'] ?? null,
                        'href' => $item['href'] ?? null,
                        'feature' => $item['feature'] ?? null,
                        'modules' => $item['modules'] ?? [],
                        'admin_only' => $item['adminOnly'] ?? false,
                        'super_admin_only' => $item['superAdminOnly'] ?? false,
                        'is_system' => true,
                        'sort_order' => $order,
                    ],
                );

                if ($itemIsLeaf) {
                    $seenLeafKeys->put($item['id'], 1);
                }

                foreach ($item['children'] ?? [] as $childOrder => $child) {
                    if ($seenLeafKeys->has($child['id'])) {
                        continue;
                    }

                    // A menu added to the canonical nav later would otherwise
                    // land on a sort_order an existing sibling already holds,
                    // making the order of the two arbitrary. Append instead.
                    $taken = MenuItem::forTenant($tenantId)
                        ->where('parent_id', $parent->id)
                        ->where('sort_order', $childOrder)
                        ->exists();

                    if ($taken) {
                        $childOrder = 1 + (int) MenuItem::forTenant($tenantId)
                            ->where('parent_id', $parent->id)
                            ->max('sort_order');
                    }

                    MenuItem::firstOrCreate(
                        ['tenant_id' => $tenantId, 'key' => $child['id'], 'parent_id' => $parent->id],
                        [
                            'section' => null,
                            'label' => $child['label'],
                            'icon' => $child['icon'] ?? null,
                            'href' => $child['href'] ?? null,
                            'feature' => $child['feature'] ?? null,
                            'modules' => $child['modules'] ?? [],
                            'admin_only' => $child['adminOnly'] ?? false,
                            'super_admin_only' => $child['superAdminOnly'] ?? false,
                            'is_active' => $child['isActive'] ?? true,
                            'is_system' => true,
                            'sort_order' => $childOrder,
                        ],
                    );

                    $seenLeafKeys->put($child['id'], 1);
                }
            }
        }
    }

    /**
     * The default platform (super-admin) navigation: only platform-management &
     * reporting screens, no tenant-operational modules. Editable via the Menu
     * Builder once seeded into the null-tenant menu rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function platformGroups(): array
    {
        return [
            ['title' => null, 'items' => [
                self::leaf('dashboard', 'Dashboard', 'layout-dashboard', '/dashboard', null, [], false, true),
            ]],
            ['title' => 'PLATFORM', 'items' => [
                self::leaf('klien', 'Klien / Tenant', 'building-2', '/avana/klien', null, [], false, true),
                self::leaf('billing', 'Billing & Invoice', 'receipt-text', '/avana/billing', null, [], false, true),
                self::leaf('paket', 'Paket Langganan', 'layers', '/avana/paket', null, [], false, true),
                self::leaf('token-packs', 'Paket Token AI', 'coins', '/avana/token-packs', null, [], false, true),
                self::leaf('dynamic-report', 'Laporan Platform', 'table', '/avana/dynamic-report', null, [], false, true),
            ]],
            ['title' => 'PENGATURAN PLATFORM', 'items' => [
                self::leaf('pengguna', 'Pengguna', 'user-cog', '/avana/pengguna', null, [], false, true),
                self::leaf('hak-akses', 'Hak Akses', 'shield-check', '/avana/hak-akses', null, [], false, true),
                self::leaf('website-settings', 'Pengaturan Website', 'globe', '/avana/website-settings', null, [], false, true),
                self::leaf('ai-settings', 'Pengaturan AI', 'sparkles', '/avana/ai-settings', null, [], false, true),
                self::leaf('email-settings', 'Pengaturan Email', 'mail', '/avana/email-settings', null, [], false, true),
                self::leaf('tampilan', 'Tampilan & Tema', 'palette', '/avana/tampilan', null, [], false, true),
                self::leaf('onboarding-slides', 'Onboarding App', 'smartphone', '/avana/onboarding-slides', null, [], false, true),
                self::leaf('audit', 'Audit Trail', 'history', '/avana/audit', null, [], false, true),
            ]],
        ];
    }

    /**
     * The platform menu groups from the null-tenant DB rows (Menu Builder), or
     * the built-in defaults when none have been seeded yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function platformTenantGroups(): array
    {
        $rows = MenuItem::forTenant(null)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $rows->isEmpty() ? self::platformGroups() : self::buildGroups($rows);
    }

    /**
     * Populate the platform (null-tenant) menu rows from {@see platformGroups()}.
     * Idempotent — existing customised rows are left untouched.
     */
    public static function seedPlatformDefaults(): void
    {
        $order = 0;

        foreach (self::platformGroups() as $group) {
            foreach ($group['items'] as $item) {
                $order++;

                MenuItem::firstOrCreate(
                    ['tenant_id' => null, 'key' => $item['id'], 'parent_id' => null],
                    [
                        'section' => $group['title'],
                        'label' => $item['label'],
                        'icon' => $item['icon'] ?? null,
                        'href' => $item['href'] ?? null,
                        'feature' => null,
                        'modules' => [],
                        'admin_only' => false,
                        'super_admin_only' => true,
                        'is_system' => true,
                        'sort_order' => $order,
                    ],
                );
            }
        }
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
