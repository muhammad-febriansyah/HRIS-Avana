<?php

namespace App\Support;

/**
 * Canonical section titles + display order for feature `module_group`s. Shared
 * by the Hak Akses permission matrix and the Kelola Fitur catalog so both group
 * features the same way. Unknown groups fall back to "LAINNYA".
 */
final class FeatureGroups
{
    /**
     * Section title per module_group, in display order.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'core' => 'INTI HR',
        'time' => 'WAKTU & KEHADIRAN',
        'payroll' => 'PAYROLL & KEUANGAN',
        'talent' => 'TALENTA',
        'engagement' => 'ENGAGEMENT',
        'analytics' => 'ANALITIK',
        'asset' => 'ASET',
        'crm' => 'CRM',
    ];

    /**
     * Fixed non-feature rows shown BEFORE the feature groups (always active, no
     * master switch). Mirrors AccessController's core head so the Kelola Fitur
     * catalog and the Hak Akses matrix list the same menus.
     *
     * @var array<int, array{label: string, group: string}>
     */
    public const CORE_HEAD = [
        ['label' => 'Dashboard', 'group' => 'UTAMA'],
    ];

    /**
     * Fixed non-feature rows shown AFTER the feature groups.
     *
     * @var array<int, array{label: string, group: string}>
     */
    public const CORE_TAIL = [
        ['label' => 'Pengguna', 'group' => 'SISTEM'],
        ['label' => 'Pengaturan', 'group' => 'SISTEM'],
        ['label' => 'Audit Trail', 'group' => 'SISTEM'],
    ];

    /**
     * The module_group keys in display order.
     *
     * @return array<int, string>
     */
    public static function order(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * The section title for a module_group (or "LAINNYA" when unknown).
     */
    public static function label(?string $group): string
    {
        return self::LABELS[$group] ?? 'LAINNYA';
    }

    /**
     * A sort index placing known groups in order and unknown groups last.
     */
    public static function sortIndex(?string $group): int
    {
        $index = array_search($group, self::order(), true);

        return $index === false ? count(self::LABELS) : $index;
    }
}
