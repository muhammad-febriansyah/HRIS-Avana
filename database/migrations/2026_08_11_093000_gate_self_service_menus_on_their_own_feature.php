<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Close the self-service feature leak.
 *
 * Every LAYANAN SAYA row was gated on the `ess` feature alone, so a tenant that
 * enabled self-service got the self-service face of every module — Slip Gaji
 * with payroll switched off, Token AI Saya with the AI module never bought,
 * Kinerja without the performance module. The sidebar showed them and
 * `EnsureAvanaAccess` let the routes through, because both read the gate from
 * this same row.
 *
 * Each row now names `ess` plus the feature its screen belongs to; both must be
 * enabled. Rows are matched by key, so a tenant's own renamed labels survive.
 */
return new class extends Migration
{
    /**
     * Menu key => the feature list that key requires.
     *
     * @var array<string, string>
     */
    private const FEATURES = [
        'saya-absensi' => 'ess,attendance',
        'saya-koreksi' => 'ess,attendance',
        'saya-jadwal' => 'ess,attendance',
        'saya-organisasi' => 'ess,organization',
        'saya-token-ai' => 'ess,ai',
        'saya-cuti' => 'ess,leave',
        'saya-lembur' => 'ess,overtime',
        'saya-izin' => 'ess,wfh',
        'saya-kalender' => 'ess,calendar',
        'saya-kontrak' => 'ess,hr_core',
        'saya-kinerja' => 'ess,performance',
        'saya-pembelajaran' => 'ess,learning',
        'saya-benefit' => 'ess,claim',
        'saya-dinas' => 'ess,hr_core',
        'saya-slip' => 'ess,payroll',
        'saya-dokumen' => 'ess,document',
        'saya-sop' => 'ess,sop',
        'saya-sosmed' => 'ess,social',
        'saya-onboarding' => 'ess,onboarding',
    ];

    public function up(): void
    {
        foreach (self::FEATURES as $key => $feature) {
            MenuItem::query()->where('key', $key)->update(['feature' => $feature]);
        }
    }

    public function down(): void
    {
        // What each row carried before: `ess` everywhere, except the two that
        // already named their own module.
        $previous = ['saya-sop' => 'sop', 'saya-sosmed' => 'social'];

        foreach (array_keys(self::FEATURES) as $key) {
            MenuItem::query()->where('key', $key)->update(['feature' => $previous[$key] ?? 'ess']);
        }
    }
};
