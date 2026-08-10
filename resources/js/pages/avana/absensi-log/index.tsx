import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { AIcon, C } from '@/lib/avana';
import { filterSelectStyle, Pagination } from '../audit/components';
import { ScanTable } from './scan-table';
import type { FaceScanLogProps } from './types';

const PLATFORM_LABELS: Record<string, string> = {
    ios: 'iPhone / iPad',
    android: 'Android',
    lainnya: 'Tidak diketahui',
};

export default function AvanaFaceScanLog({
    logs,
    filters,
    summary,
}: FaceScanLogProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstSearch = useRef(true);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, search: search || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const applyFilter = (key: string, value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, [key]: value || undefined, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const platforms = Object.entries(summary.by_platform);

    return (
        <>
            <Head title="Log Verifikasi Wajah" />
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
                        <span>Kehadiran</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>
                            Log Verifikasi Wajah
                        </span>
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
                        Log Verifikasi Wajah
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Setiap percobaan daftar wajah dan scan absen beserta
                        angka yang diukur perangkat — untuk melacak kegagalan
                        yang hanya terjadi di sebagian ponsel
                    </div>
                </div>

                {/* 7-day summary */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(240px, 1fr))',
                        gap: 12,
                        marginBottom: 18,
                    }}
                >
                    {platforms.map(([platform, counts]) => {
                        const total =
                            counts.ok + counts.fail + counts.blocked || 1;
                        const successRate = Math.round(
                            (counts.ok / total) * 100,
                        );

                        return (
                            <div
                                key={platform}
                                style={{
                                    background: '#fff',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 12,
                                    padding: '14px 16px',
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        marginBottom: 6,
                                    }}
                                >
                                    {PLATFORM_LABELS[platform] ?? platform} · 7
                                    hari
                                </div>
                                <div
                                    style={{
                                        fontSize: 22,
                                        fontWeight: 600,
                                        color:
                                            successRate < 50
                                                ? C.red
                                                : C.navy,
                                    }}
                                >
                                    {successRate}%{' '}
                                    <span
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 400,
                                            color: C.muted,
                                        }}
                                    >
                                        berhasil
                                    </span>
                                </div>
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.faint,
                                        marginTop: 4,
                                    }}
                                >
                                    {counts.ok} berhasil · {counts.fail} gagal ·{' '}
                                    {counts.blocked} ditolak
                                </div>
                            </div>
                        );
                    })}
                    {summary.top_reasons.length > 0 && (
                        <div
                            style={{
                                background: '#fff',
                                border: `1px solid ${C.border}`,
                                borderRadius: 12,
                                padding: '14px 16px',
                            }}
                        >
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginBottom: 8,
                                }}
                            >
                                Sebab kegagalan terbanyak · 7 hari
                            </div>
                            {summary.top_reasons.map((item) => (
                                <div
                                    key={item.reason}
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        fontSize: 13,
                                        color: C.text,
                                        padding: '2px 0',
                                    }}
                                >
                                    <span>{item.label}</span>
                                    <span style={{ color: C.muted }}>
                                        {item.count}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Table card */}
                <div
                    style={{
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                        overflow: 'hidden',
                    }}
                >
                    {/* Filter bar */}
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            gap: 10,
                            flexWrap: 'wrap',
                            alignItems: 'center',
                        }}
                    >
                        <div
                            style={{
                                position: 'relative',
                                flex: 1,
                                minWidth: 220,
                                maxWidth: 320,
                            }}
                        >
                            <AIcon
                                name="search"
                                size={16}
                                color={C.faint}
                                style={{
                                    position: 'absolute',
                                    left: 12,
                                    top: '50%',
                                    transform: 'translateY(-50%)',
                                }}
                            />
                            <input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama atau NIK…"
                                style={{
                                    width: '100%',
                                    height: 38,
                                    padding: '0 12px 0 36px',
                                    background: C.surface,
                                    border: '1px solid transparent',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    outline: 'none',
                                    transition: '.15s',
                                }}
                            />
                        </div>
                        <select
                            aria-label="Alur"
                            value={filters.context ?? ''}
                            onChange={(event) =>
                                applyFilter('context', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Alur</option>
                            <option value="enroll">Daftar Wajah</option>
                            <option value="verify">Scan Absen</option>
                            <option value="clock">Cocok di Server</option>
                        </select>
                        <select
                            aria-label="Hasil"
                            value={filters.outcome ?? ''}
                            onChange={(event) =>
                                applyFilter('outcome', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Hasil</option>
                            <option value="ok">Berhasil</option>
                            <option value="fail">Gagal</option>
                            <option value="blocked">Ditolak</option>
                        </select>
                        <select
                            aria-label="Platform"
                            value={filters.platform ?? ''}
                            onChange={(event) =>
                                applyFilter('platform', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Perangkat</option>
                            <option value="ios">iPhone / iPad</option>
                            <option value="android">Android</option>
                        </select>
                        <div style={{ flex: 1 }} />
                    </div>

                    <ScanTable rows={logs.data} />

                    <Pagination meta={logs.meta} onGoToPage={goToPage} />
                </div>
            </div>
        </>
    );
}
