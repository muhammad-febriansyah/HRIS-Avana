import { Head, Link } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { LocationMap } from '@/components/map/location-map';
import type { MapPoint } from '@/components/map/location-map';
import { AIcon, C, card } from '@/lib/avana';

interface MonitorPoint extends MapPoint {
    status: string;
}

interface ActivityRow {
    id: number;
    name: string;
    location: string;
    time: string | null;
    status: string;
    status_label: string;
}

interface MonitorProps {
    date: { value: string; display: string };
    kpis: {
        total_personnel: number;
        on_time: number;
        late: number;
        no_show: number;
    };
    points: MonitorPoint[];
    activity: ActivityRow[];
}

/** Colour a check-in status pill/dot. */
function statusColor(status: string): string {
    switch (status) {
        case 'present':
            return C.green;
        case 'late':
            return C.amber;
        case 'absent':
            return C.red;
        case 'leave':
            return C.primary;
        default:
            return C.faint;
    }
}

function KpiTile({
    label,
    value,
    sub,
    icon,
    color,
}: {
    label: string;
    value: number;
    sub: string;
    icon: string;
    color: string;
}) {
    return (
        <div style={{ ...card, padding: '18px 20px' }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'flex-start',
                }}
            >
                <span
                    style={{
                        fontSize: 11.5,
                        fontWeight: 600,
                        letterSpacing: '.04em',
                        textTransform: 'uppercase',
                        color: C.muted,
                    }}
                >
                    {label}
                </span>
                <AIcon name={icon} size={18} color={color} />
            </div>
            <div
                style={{
                    fontSize: 30,
                    fontWeight: 700,
                    color: C.navy,
                    marginTop: 8,
                    fontVariantNumeric: 'tabular-nums',
                }}
            >
                {value.toLocaleString('id-ID')}
            </div>
            <div style={{ fontSize: 12, color, marginTop: 2, fontWeight: 500 }}>
                {sub}
            </div>
        </div>
    );
}

export default function AbsensiMonitor({
    date,
    kpis,
    points,
    activity,
}: MonitorProps) {
    const rowStyle: CSSProperties = {
        display: 'flex',
        gap: 12,
        padding: '12px 0',
        borderBottom: `1px solid ${C.line}`,
    };

    return (
        <>
            <Head title="Monitor Kehadiran" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-end',
                        flexWrap: 'wrap',
                        gap: 12,
                        marginBottom: 22,
                    }}
                >
                    <div>
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
                            <Link
                                href="/avana/absensi"
                                style={{ color: C.faint, textDecoration: 'none' }}
                            >
                                Absensi
                            </Link>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Monitor</span>
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
                            Monitor Kehadiran
                        </h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                            Pelacakan kehadiran langsung lintas cabang · {date.display}
                        </div>
                    </div>
                    <div
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: C.green,
                            padding: '7px 13px',
                            borderRadius: 100,
                            background: 'rgba(22,163,74,.09)',
                        }}
                    >
                        <span
                            style={{
                                width: 8,
                                height: 8,
                                borderRadius: '50%',
                                background: C.green,
                            }}
                        />
                        Pelacakan Aktif
                    </div>
                </div>

                {/* KPIs */}
                <div
                    className="avn-kpi"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 18,
                        marginBottom: 18,
                    }}
                >
                    <KpiTile
                        label="Total Personel"
                        value={kpis.total_personnel}
                        sub="Karyawan aktif"
                        icon="users"
                        color={C.primary}
                    />
                    <KpiTile
                        label="Tepat Waktu"
                        value={kpis.on_time}
                        sub="Hadir tepat waktu"
                        icon="circle-check"
                        color={C.green}
                    />
                    <KpiTile
                        label="Terlambat"
                        value={kpis.late}
                        sub="Perlu perhatian"
                        icon="clock"
                        color={C.amber}
                    />
                    <KpiTile
                        label="Belum Absen"
                        value={kpis.no_show}
                        sub="Belum check-in"
                        icon="user-x"
                        color={C.red}
                    />
                </div>

                {/* Map + Activity */}
                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.6fr 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    <div style={{ ...card, padding: 14 }}>
                        {points.length > 0 ? (
                            <LocationMap points={points} height={480} fit />
                        ) : (
                            <div
                                style={{
                                    height: 480,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    color: C.faint,
                                    background: C.surface,
                                    borderRadius: 10,
                                }}
                            >
                                <AIcon name="map-pin-off" size={30} color={C.faint} />
                                <span style={{ fontSize: 13 }}>
                                    Belum ada titik check-in GPS untuk tanggal ini
                                </span>
                            </div>
                        )}
                    </div>

                    <div style={{ ...card, padding: '18px 20px' }}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 6,
                            }}
                        >
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Aktivitas Terbaru
                            </div>
                            <span style={{ fontSize: 12, color: C.faint }}>
                                {activity.length} entri
                            </span>
                        </div>

                        {activity.length === 0 ? (
                            <div
                                style={{
                                    padding: '28px 0',
                                    textAlign: 'center',
                                    fontSize: 13,
                                    color: C.faint,
                                }}
                            >
                                Belum ada aktivitas
                            </div>
                        ) : (
                            <div>
                                {activity.map((row) => (
                                    <div key={row.id} style={rowStyle}>
                                        <span
                                            style={{
                                                width: 9,
                                                height: 9,
                                                borderRadius: '50%',
                                                marginTop: 5,
                                                flex: 'none',
                                                background: statusColor(row.status),
                                            }}
                                        />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.text,
                                                }}
                                            >
                                                {row.name}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12,
                                                    color: C.muted,
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 4,
                                                    marginTop: 1,
                                                }}
                                            >
                                                <AIcon
                                                    name="map-pin"
                                                    size={12}
                                                    color={C.faint}
                                                />
                                                {row.location}
                                            </div>
                                        </div>
                                        <div
                                            style={{
                                                textAlign: 'right',
                                                flex: 'none',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    fontSize: 12.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                    fontVariantNumeric:
                                                        'tabular-nums',
                                                }}
                                            >
                                                {row.time ?? '—'}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11,
                                                    fontWeight: 600,
                                                    color: statusColor(row.status),
                                                }}
                                            >
                                                {row.status_label}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
