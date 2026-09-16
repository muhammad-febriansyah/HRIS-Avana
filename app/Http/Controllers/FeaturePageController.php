<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeaturePageController extends Controller
{
    /** @var array<int, string> */
    public const FEATURE_SLUGS = [
        'core-hr',
        'data-karyawan',
        'struktur-organisasi',
        'administrasi-karier',
        'ess',
        'otomatisasi-alur-kerja',
        'pelatihan',
        'time-management',
        'manajemen-talenta',
        'compensation-benefits',
        'loans-management',
        'kpi',
        'ai-analytics',
        'cuti-dan-izin',
        'rekrutmen',
        'manajemen-kinerja',
        'payroll',
        'reimbursement',
        'absensi-karyawan',
        'live-tracking-karyawan',
        'kunjungan-karyawan',
        'ai-hr',
        'hr-analytics',
        'prediksi-risiko-resign',
        'transkrip-rapat-ai',
        'hr-helpdesk',
        'manajemen-aset',
        'mood-karyawan',
        'ruang-kita',
        'pengumuman-karyawan',
        'survei-karyawan',
        'kalender-perusahaan',
    ];

    public function __invoke(Request $request, string $featureSlug): Response
    {
        abort_unless(in_array($featureSlug, [...self::FEATURE_SLUGS, 'crm'], true), 404);

        return Inertia::render('public/feature', [
            'featureSlug' => $featureSlug,
            'canonicalUrl' => $request->url(),
        ]);
    }
}
