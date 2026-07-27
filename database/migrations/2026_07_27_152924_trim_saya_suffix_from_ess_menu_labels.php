<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Drop the trailing " Saya" from the self-service menu labels — the group is
 * already headed "LAYANAN SAYA", so every row repeated the word.
 *
 * Only rows still carrying the original label are touched, so a tenant that
 * renamed its own menu in the Menu Builder keeps that name.
 */
return new class extends Migration
{
    /**
     * Menu key => [label before, label after].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const LABELS = [
        'saya-profil' => ['Profil Saya', 'Profil'],
        'saya-absensi' => ['Absensi Saya', 'Absensi'],
        'saya-jadwal' => ['Jadwal Saya', 'Jadwal'],
        'saya-cuti' => ['Cuti Saya', 'Cuti'],
        'saya-lembur' => ['Lembur Saya', 'Lembur'],
        'saya-izin' => ['Izin Saya', 'Izin'],
        'saya-slip' => ['Slip Gaji Saya', 'Slip Gaji'],
        'saya-dokumen' => ['Dokumen Saya', 'Dokumen'],
        'saya-onboarding' => ['Onboarding Saya', 'Onboarding'],
        'saya-kontrak' => ['Kontrak Saya', 'Kontrak'],
        'saya-kinerja' => ['Kinerja Saya', 'Kinerja'],
        'saya-kalender' => ['Kalender Saya', 'Kalender'],
        'saya-tugas' => ['Tugas Saya', 'Tugas'],
        'saya-pembelajaran' => ['Pembelajaran Saya', 'Pembelajaran'],
        'saya-benefit' => ['Benefit Saya', 'Benefit'],
        'saya-dinas' => ['Perjalanan Dinas Saya', 'Perjalanan Dinas'],
    ];

    public function up(): void
    {
        foreach (self::LABELS as $key => [$before, $after]) {
            MenuItem::query()
                ->where('key', $key)
                ->where('label', $before)
                ->update(['label' => $after]);
        }
    }

    public function down(): void
    {
        foreach (self::LABELS as $key => [$before, $after]) {
            MenuItem::query()
                ->where('key', $key)
                ->where('label', $after)
                ->update(['label' => $before]);
        }
    }
};
