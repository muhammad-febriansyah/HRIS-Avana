<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Insert the expanded AvanaHR feature catalogue (Recruitment, Performance, LMS,
 * Claim, Helpdesk, Asset, etc.) so existing tenants can toggle the new modules
 * from the Menu & Fitur screen without re-running the demo seeder. Idempotent.
 */
return new class extends Migration
{
    /**
     * New feature flags: [code => [name, module_group]].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $features = [
        'document' => ['Manajemen Dokumen', 'core'],
        'offboarding' => ['Offboarding & Clearance', 'core'],
        'helpdesk' => ['HR Helpdesk', 'core'],
        'delegation' => ['Delegasi Approval', 'core'],
        'timesheet' => ['Timesheet', 'time'],
        'shift_swap' => ['Tukar Shift', 'time'],
        'claim' => ['Klaim & Reimbursement', 'payroll'],
        'loan' => ['Pinjaman Karyawan', 'payroll'],
        'salary_structure' => ['Struktur & Skala Upah', 'payroll'],
        'journal' => ['Jurnal Akuntansi', 'payroll'],
        'performance' => ['Manajemen Kinerja', 'talent'],
        'okr' => ['OKR & Goal', 'talent'],
        'competency' => ['Kompetensi', 'talent'],
        'talent' => ['Talenta & Suksesi', 'talent'],
        'learning' => ['Pembelajaran (LMS)', 'talent'],
        'ess' => ['Employee Self-Service', 'engagement'],
        'announcement' => ['Pengumuman', 'engagement'],
        'survey' => ['Survei Karyawan', 'engagement'],
        'dynamic_report' => ['Dynamic Report', 'analytics'],
        'asset' => ['Manajemen Aset', 'asset'],
        'crm' => ['CRM', 'crm'],
    ];

    public function up(): void
    {
        // Re-group the existing flags so the Menu & Fitur page stays tidy.
        $regroup = [
            'attendance' => 'time', 'leave' => 'time', 'overtime' => 'time', 'wfh' => 'time',
            'bpjs' => 'payroll', 'pph21' => 'payroll', 'recruitment' => 'talent',
            'onboarding' => 'talent', 'analytics' => 'analytics',
        ];
        foreach ($regroup as $code => $group) {
            DB::table('features')->where('code', $code)->update(['module_group' => $group]);
        }

        foreach ($this->features as $code => [$name, $group]) {
            if (! DB::table('features')->where('code', $code)->exists()) {
                DB::table('features')->insert([
                    'code' => $code,
                    'name' => $name,
                    'module_group' => $group,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('features')->whereIn('code', array_keys($this->features))->delete();
    }
};
