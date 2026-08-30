import { C } from '@/lib/avana';
import type { AbsensiProps } from './types';

/* ---------- shared presentational helpers for the absensi page ---------- */

interface KpiStripProps {
    kpis: AbsensiProps['kpis'];
}

/**
 * Daily status KPI cards. Mirrors the prototype layout, fed by the daily
 * status counts (hadir / terlambat / izin / alpa).
 */
export function KpiStrip({ kpis }: KpiStripProps) {
    const kpiCards: { label: string; value: number; color: string }[] = [
        { label: 'Hadir', value: kpis.hadir, color: C.green },
        { label: 'Terlambat', value: kpis.terlambat, color: C.amber },
        { label: 'Cuti / Izin', value: kpis.izin, color: C.primary },
        { label: 'Alpa', value: kpis.alpa, color: C.red },
    ];

    return (
        <div
            className="avn-kpi"
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(4,1fr)',
                gap: 12,
            }}
        >
            {kpiCards.map((kpi) => (
                <div
                    key={kpi.label}
                    style={{
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        padding: '15px 16px',
                    }}
                >
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
                </div>
            ))}
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
            return { label: 'Tanpa geofence', color: C.faint, geofenced: false };
    }
}
