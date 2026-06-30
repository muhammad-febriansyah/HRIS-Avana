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
