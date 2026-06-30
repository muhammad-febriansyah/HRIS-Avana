/**
 * Shared types and report configuration for the AvanaHR laporan (reports)
 * module. These mirror the `LaporanController@index` payload.
 */

import { C } from '@/lib/avana';

export type { FlashProps } from '../employees/types';

/** Headline HR stats rendered on each report card. */
export interface LaporanStats {
    karyawan: number;
    hadir_hari_ini: number;
    cuti_pending: number;
    payroll_net: string;
    bpjs: number;
    pph21: number;
    turnover: number;
}

/** Props for the laporan landing page (`index.tsx`). */
export interface LaporanProps {
    stats: LaporanStats;
}

/** A single downloadable report definition. */
export interface ReportCard {
    type: string;
    title: string;
    desc: string;
    icon: string;
    color: string;
    statLabel: string;
    statValue: string;
}

/** Build the downloadable report card definitions from headline HR stats. */
export function buildReports(stats: LaporanStats): ReportCard[] {
    return [
        {
            type: 'karyawan',
            title: 'Karyawan',
            desc: 'Data lengkap seluruh karyawan beserta jabatan & status kerja.',
            icon: 'users',
            color: C.primary,
            statLabel: 'Karyawan aktif',
            statValue: stats.karyawan.toLocaleString('id-ID'),
        },
        {
            type: 'absensi',
            title: 'Absensi',
            desc: 'Rekap kehadiran, jam masuk/keluar, dan keterlambatan.',
            icon: 'fingerprint',
            color: C.green,
            statLabel: 'Hadir hari ini',
            statValue: stats.hadir_hari_ini.toLocaleString('id-ID'),
        },
        {
            type: 'cuti',
            title: 'Cuti & Lembur',
            desc: 'Pengajuan cuti tim beserta durasi dan status persetujuan.',
            icon: 'palmtree',
            color: C.amber,
            statLabel: 'Cuti menunggu',
            statValue: stats.cuti_pending.toLocaleString('id-ID'),
        },
        {
            type: 'payroll',
            title: 'Payroll',
            desc: 'Rincian gaji per karyawan: gross, potongan, dan gaji bersih.',
            icon: 'wallet',
            color: C.primary,
            statLabel: 'Payroll terbaru (net)',
            statValue: stats.payroll_net,
        },
        {
            type: 'bpjs',
            title: 'BPJS',
            desc: 'Iuran BPJS per karyawan: upah dasar, iuran karyawan & perusahaan.',
            icon: 'shield-check',
            color: C.green,
            statLabel: 'Peserta BPJS',
            statValue: stats.bpjs.toLocaleString('id-ID'),
        },
        {
            type: 'pph21',
            title: 'PPh 21',
            desc: 'Pajak penghasilan per karyawan: bruto, kategori TER, tarif, & PPh 21.',
            icon: 'receipt',
            color: C.amber,
            statLabel: 'Wajib pajak',
            statValue: stats.pph21.toLocaleString('id-ID'),
        },
        {
            type: 'turnover',
            title: 'Turnover',
            desc: 'Status kepegawaian, tanggal masuk & keluar untuk analisa turnover.',
            icon: 'user-minus',
            color: C.red,
            statLabel: 'Total karyawan',
            statValue: stats.turnover.toLocaleString('id-ID'),
        },
    ];
}
