<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the Hak Akses matrix data-driven: each feature declares the permission
 * modules it owns, so the per-role action columns are generated from the
 * `features` table instead of a hardcoded controller map. A new feature row
 * therefore appears in the matrix automatically.
 *
 * `permission_modules` is the set of permission module prefixes the feature owns
 * for its per-role checkboxes. An empty list marks a feature-only row (master
 * switch only) whose per-role access is governed by a related feature's modules
 * (e.g. cash_advance/reimbursement inherit from payroll/claim; ess is mobile).
 */
return new class extends Migration
{
    /**
     * Feature code => permission module prefixes it owns in the matrix.
     * Each permission module is owned by exactly one feature to avoid duplicate,
     * conflicting checkboxes across rows.
     *
     * @var array<string, array<int, string>>
     */
    private const MAP = [
        // core
        'hr_core' => ['employee'],
        'document' => ['document'],
        'letter' => ['letter'],
        'offboarding' => ['offboarding'],
        'delegation' => ['delegation'],
        'helpdesk' => ['helpdesk'],
        'organization' => ['branch', 'department', 'position', 'organization'],
        // time
        'attendance' => ['attendance'],
        'leave' => ['leave'],
        'overtime' => ['overtime'],
        'wfh' => ['wfh'],
        'timesheet' => ['timesheet'],
        'shift_swap' => ['shift_swap'],
        // payroll & finance
        'payroll' => ['payroll'],
        'bpjs' => ['bpjs'],
        'pph21' => ['pph21'],
        'claim' => ['claim'],
        'loan' => ['loan'],
        'journal' => ['journal'],
        'budget' => ['budget'],
        'salary_structure' => ['salary_structure'],
        'cash_advance' => [],
        'reimbursement' => [],
        // talent
        'recruitment' => ['recruitment'],
        'onboarding' => ['onboarding'],
        'performance' => ['performance'],
        'okr' => ['okr'],
        'competency' => ['competency'],
        'talent' => ['talent'],
        'learning' => ['learning'],
        // engagement
        'announcement' => ['announcement'],
        'survey' => ['survey'],
        'calendar' => ['calendar'],
        'ess' => [],
        // analytics
        'analytics' => ['report'],
        'dynamic_report' => ['dynamic_report'],
        'ai' => ['ai'],
        // asset & crm
        'asset' => ['asset'],
        'crm' => ['crm'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('features', 'permission_modules')) {
            Schema::table('features', function (Blueprint $table): void {
                $table->json('permission_modules')->nullable()->after('module_group');
            });
        }

        foreach (self::MAP as $code => $modules) {
            DB::table('features')->where('code', $code)->update([
                'permission_modules' => json_encode($modules),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('features', 'permission_modules')) {
            Schema::table('features', function (Blueprint $table): void {
                $table->dropColumn('permission_modules');
            });
        }
    }
};
