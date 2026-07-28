<?php

namespace App\Support;

use App\Models\User;

/**
 * Canonical action-level permission vocabulary for AvanaHR.
 *
 * A permission `code` is `{module}.{action}`; the Hak Akses matrix, the action
 * permission seeder, and the {@see User::hasPermissionTo()} gate all
 * read the action set and module list from here so they never drift apart.
 *
 * Action keys follow the existing convention (`update` for edit, `archive` for
 * a soft delete) — see the codes already seeded by AvanaDemoSeeder.
 */
final class PermissionCatalog
{
    /**
     * The standard per-menu actions, keyed by the `action` stored on a
     * permission row, mapped to their Indonesian button label.
     *
     * @var array<string, string>
     */
    public const ACTIONS = [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'archive' => 'Hapus',
        'export' => 'Ekspor',
        'approve' => 'Setujui',
    ];

    /**
     * Every permission module that receives the full action set. Mirrors the
     * union of the `modules` referenced by the Hak Akses matrix rows so that
     * each menu is togglable at the action level.
     *
     * @var array<int, string>
     */
    public const MODULES = [
        'employee', 'document', 'sop', 'letter', 'offboarding',
        'attendance', 'timesheet', 'shift_swap',
        'leave', 'overtime', 'wfh', 'delegation',
        'payroll', 'bpjs', 'pph21',
        'claim', 'loan', 'journal', 'budget', 'salary_structure',
        'recruitment', 'onboarding',
        'performance', 'okr', 'competency', 'talent', 'learning',
        'helpdesk', 'announcement', 'social', 'survey', 'calendar', 'ai', 'ai_topup', 'langganan', 'asset', 'crm',
        'report', 'dynamic_report', 'attrition', 'audit',
        'branch', 'department', 'position', 'organization',
        'user', 'settings', 'role', 'permission', 'appearance',
    ];

    /**
     * The action keys (`view`, `create`, ...).
     *
     * @return array<int, string>
     */
    public static function actionKeys(): array
    {
        return array_keys(self::ACTIONS);
    }

    /**
     * Every `{module}.{action}` code the catalog defines.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return self::actionCodesForModules(self::MODULES);
    }

    /**
     * The full action-set codes for the catalog modules within `$modules`.
     * Used by the fail-open baseline grant so a role that already holds a module
     * keeps every action on it (no access is lost when enforcement lands).
     *
     * @param  iterable<int, string>  $modules
     * @return array<int, string>
     */
    public static function actionCodesForModules(iterable $modules): array
    {
        $wanted = array_intersect(
            array_values(array_unique([...$modules])),
            self::MODULES,
        );

        $codes = [];

        foreach ($wanted as $module) {
            foreach (self::actionKeys() as $action) {
                $codes[] = $module.'.'.$action;
            }
        }

        return $codes;
    }
}
