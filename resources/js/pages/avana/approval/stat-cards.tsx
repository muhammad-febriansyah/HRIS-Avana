import { AIcon, C, card } from '@/lib/avana';
import type { ApprovalCounts, FilterKey } from './types';
import { typeMeta } from './types';

/** The six summary stat cards across the top of the approval center. */
export function StatCards({ counts }: { counts: ApprovalCounts }) {
    const stats: { key: FilterKey; label: string; value: number; icon: string; color: string }[] = [
        { key: 'all', label: 'Total Menunggu', value: counts.total, icon: 'inbox', color: C.navy },
        { key: 'leave', label: 'Cuti', value: counts.leave, icon: typeMeta.leave.icon, color: typeMeta.leave.color },
        { key: 'lembur', label: 'Lembur', value: counts.lembur, icon: typeMeta.lembur.icon, color: typeMeta.lembur.color },
        { key: 'izin', label: 'Izin', value: counts.izin, icon: typeMeta.izin.icon, color: typeMeta.izin.color },
        { key: 'wfh', label: 'WFH', value: counts.wfh, icon: typeMeta.wfh.icon, color: typeMeta.wfh.color },
        { key: 'koreksi', label: 'Koreksi', value: counts.koreksi, icon: typeMeta.koreksi.icon, color: typeMeta.koreksi.color },
    ];

    return (
        <div
            className="avn-stat"
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(6,1fr)',
                gap: 14,
                marginBottom: 20,
            }}
        >
            {stats.map((stat) => (
                <div key={stat.key} style={{ ...card, padding: '16px 18px' }}>
                    <div
                        style={{
                            width: 36,
                            height: 36,
                            borderRadius: 10,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            background: stat.color + '1a',
                            color: stat.color,
                            marginBottom: 12,
                        }}
                    >
                        <AIcon name={stat.icon} size={18} color={stat.color} />
                    </div>
                    <div style={{ fontSize: 26, fontWeight: 700, color: C.navy, lineHeight: 1 }}>
                        {stat.value}
                    </div>
                    <div style={{ fontSize: 12.5, color: C.muted, marginTop: 5 }}>
                        {stat.label}
                    </div>
                </div>
            ))}
        </div>
    );
}
