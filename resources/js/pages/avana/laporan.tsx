import { Head } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { AIcon, C, card } from '@/lib/avana';

/** Headline HR stats rendered on each report card. */
interface LaporanStats {
    karyawan: number;
    hadir_hari_ini: number;
    cuti_pending: number;
    payroll_net: string;
}

interface LaporanProps {
    stats: LaporanStats;
}

/** A single downloadable report definition. */
interface ReportCard {
    type: string;
    title: string;
    desc: string;
    icon: string;
    color: string;
    statLabel: string;
    statValue: string;
}

const btnP: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 8,
    height: 40,
    padding: '0 16px',
    background: C.primary,
    color: '#fff',
    border: 'none',
    borderRadius: 8,
    fontSize: 13.5,
    fontWeight: 600,
    cursor: 'pointer',
    textDecoration: 'none',
    transition: 'background .15s,box-shadow .15s',
};

export default function AvanaLaporan({ stats }: LaporanProps) {
    const reports: ReportCard[] = [
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
    ];

    return (
        <>
            <Head title="Laporan" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 12.5,
                            color: C.faint,
                            marginBottom: 7,
                        }}
                    >
                        <span>Beranda</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Laporan</span>
                    </div>
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Laporan
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Unduh laporan data HR dalam format CSV.
                    </div>
                </div>

                {/* Report cards */}
                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(2,1fr)',
                        gap: 18,
                        alignItems: 'stretch',
                    }}
                >
                    {reports.map((report) => (
                        <div
                            key={report.type}
                            style={{
                                ...card,
                                padding: '22px 24px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'flex-start',
                                    gap: 14,
                                }}
                            >
                                <div
                                    style={{
                                        width: 46,
                                        height: 46,
                                        borderRadius: 12,
                                        flex: 'none',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        background: report.color + '1a',
                                        color: report.color,
                                    }}
                                >
                                    <AIcon
                                        name={report.icon}
                                        size={22}
                                        color={report.color}
                                    />
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: 16,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {report.title}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            color: C.muted,
                                            marginTop: 4,
                                            lineHeight: 1.5,
                                        }}
                                    >
                                        {report.desc}
                                    </div>
                                </div>
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'flex-end',
                                    justifyContent: 'space-between',
                                    gap: 12,
                                    marginTop: 'auto',
                                }}
                            >
                                <div>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: C.faint,
                                        }}
                                    >
                                        {report.statLabel}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 22,
                                            fontWeight: 700,
                                            color: C.navy,
                                            marginTop: 2,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {report.statValue}
                                    </div>
                                </div>
                                <a
                                    href={
                                        '/avana/laporan/export/' + report.type
                                    }
                                    download
                                    style={btnP}
                                >
                                    <AIcon
                                        name="download"
                                        size={16}
                                        color="#fff"
                                    />
                                    Unduh CSV
                                </a>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
