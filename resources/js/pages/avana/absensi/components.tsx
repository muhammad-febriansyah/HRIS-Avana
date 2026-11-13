import { C } from '@/lib/avana';
import type { AbsensiProps } from './types';

/* ---------- shared presentational helpers for the absensi page ---------- */

interface KpiStripProps {
    kpis: AbsensiProps['kpis'];
    /** Narrows the table to the no-show rows; the card is plain when absent. */
    onShowNotYetIn?: () => void;
}

interface KpiCard {
    label: string;
    value: number;
    color: string;
    hint?: string;
    onClick?: () => void;
}

/**
 * Daily status KPI cards: hadir / terlambat / izin / alpa, plus — whenever
 * the day has people due who left no row — "Belum Absen" today or "Belum
 * Tercatat" on a past day, so the cards add up to everyone expected.
 */
export function KpiStrip({ kpis, onShowNotYetIn }: KpiStripProps) {
    const kpiCards: KpiCard[] = [
        { label: 'Hadir', value: kpis.hadir, color: C.green },
        { label: 'Terlambat', value: kpis.terlambat, color: C.amber },
        { label: 'Cuti / Izin', value: kpis.izin, color: C.primary },
        { label: 'Alpa', value: kpis.alpa, color: C.red },
    ];

    if (kpis.belum_absen !== null) {
        kpiCards.push({
            label: kpis.belum_absen_label,
            value: kpis.belum_absen,
            color: C.violet,
            hint: kpis.belum_absen_hint,
            onClick: kpis.belum_absen > 0 ? onShowNotYetIn : undefined,
        });
    }

    return (
        <div
            className="avn-kpi"
            style={{
                display: 'grid',
                gridTemplateColumns: `repeat(${kpiCards.length},1fr)`,
                gap: 12,
            }}
        >
            {kpiCards.map((kpi) => {
                const body = (
                    <>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                            }}
                        >
                            {kpi.label}
                        </div>
                        <div
                            style={{
                                fontSize: 22,
                                fontWeight: 700,
                                color: kpi.color,
                                marginTop: 3,
                            }}
                        >
                            {kpi.value.toLocaleString('id-ID')}
                        </div>
                        {kpi.hint && (
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.faint,
                                    marginTop: 2,
                                }}
                            >
                                {kpi.hint}
                                {kpi.onClick ? ' · lihat' : ''}
                            </div>
                        )}
                    </>
                );

                const cardStyle = {
                    background: '#fff',
                    border: `1px solid ${C.border}`,
                    borderRadius: 12,
                    padding: '15px 16px',
                    textAlign: 'left' as const,
                };

                return kpi.onClick ? (
                    <button
                        key={kpi.label}
                        type="button"
                        onClick={kpi.onClick}
                        style={{
                            ...cardStyle,
                            cursor: 'pointer',
                            font: 'inherit',
                        }}
                    >
                        {body}
                    </button>
                ) : (
                    <div key={kpi.label} style={cardStyle}>
                        {body}
                    </div>
                );
            })}
        </div>
    );
}

export interface LocationBadge {
    label: string;
    color: string;
    /** True only for a punch checked against an office radius. */
    geofenced: boolean;
}

/**
 * Describe where a punch happened. Only `inside` / `outside` were checked
 * against an office radius — a WFA or WFH punch answers to no office, so
 * calling it "Dalam area" would claim a geofence check that never ran.
 */
export function locationBadge(status: string | null): LocationBadge {
    switch (status) {
        case 'inside':
            return { label: 'Dalam area', color: '#059669', geofenced: true };
        case 'outside':
            return { label: 'Luar area', color: C.red, geofenced: true };
        case 'wfa':
            return {
                label: 'Luar kantor (WFA)',
                color: C.amber,
                geofenced: false,
            };
        case 'wfh':
            return {
                label: 'Kerja dari rumah',
                color: C.primary,
                geofenced: false,
            };
        case 'unverified':
            return {
                label: 'Offline · lokasi tak terverifikasi',
                color: C.amber,
                geofenced: false,
            };
        default:
            return {
                label: 'Tanpa geofence',
                color: C.faint,
                geofenced: false,
            };
    }
}
