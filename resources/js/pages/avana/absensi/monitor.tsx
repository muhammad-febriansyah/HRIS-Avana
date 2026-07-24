import { Head, Link, router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { LocationMap } from '@/components/map/location-map';
import type { MapPoint } from '@/components/map/location-map';
import { AIcon, btnOut, C, card } from '@/lib/avana';

/** Header date/branch selector control (mirrors the Absensi index filters). */
const filterControl: CSSProperties = {
    height: 40,
    padding: '0 12px',
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
};

interface MonitorPoint extends MapPoint {
    status: string;
}

interface Branch {
    id: number;
    name: string;
}

interface ActivityRow {
    id: number;
    name: string;
    location: string;
    branch: string | null;
    time: string | null;
    status: string;
    status_label: string;
}

interface MonitorProps {
    date: { value: string; display: string };
    filters: { date: string; branch_id: number | null };
    branches: Branch[];
    kpis: {
        total_personnel: number;
        on_time: number;
        late: number;
        no_show: number;
    };
    points: MonitorPoint[];
    activity: ActivityRow[];
}

/** Map viewport height; the activity feed is capped to match it. */
const MAP_HEIGHT = 480;

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
    filters,
    branches,
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

    const changeFilter = (key: 'date' | 'branch_id', value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, [key]: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToday = () => {
        router.get(
            window.location.pathname,
            { ...filters, date: undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
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
                                style={{
                                    color: C.faint,
                                    textDecoration: 'none',
                                }}
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
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Pelacakan kehadiran langsung lintas cabang ·{' '}
                            {date.display}
                        </div>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            flexWrap: 'wrap',
                        }}
                    >
                        <select
                            value={filters.branch_id ?? ''}
                            onChange={(event) =>
                                changeFilter('branch_id', event.target.value)
                            }
                            style={filterControl}
                        >
                            <option value="">Semua cabang</option>
                            {branches.map((branch) => (
                                <option
                                    key={branch.id}
                                    value={String(branch.id)}
                                >
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                        <DatePicker
                            value={filters.date}
                            onChange={(value) => changeFilter('date', value)}
                        />
                        <button onClick={goToday} style={btnOut} type="button">
                            <AIcon name="calendar-check" size={16} />
                            Hari ini
                        </button>
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
                            <LocationMap
                                points={points}
                                height={MAP_HEIGHT}
                                fit
                                labels
                            />
                        ) : (
                            <div
                                style={{
                                    height: MAP_HEIGHT,
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
                                <AIcon
                                    name="map-pin-off"
                                    size={30}
                                    color={C.faint}
                                />
                                <span style={{ fontSize: 13 }}>
                                    Belum ada titik check-in GPS untuk tanggal
                                    ini
                                </span>
                            </div>
                        )}
                    </div>

                    <div
                        style={{
                            ...card,
                            padding: '18px 20px',
                            display: 'flex',
                            flexDirection: 'column',
                            // Cap to the map's height (480 + its card padding) so
                            // a long feed scrolls itself instead of stretching
                            // the column past the map.
                            maxHeight: MAP_HEIGHT + 28,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 6,
                                flex: 'none',
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
                            <div style={{ overflowY: 'auto', minHeight: 0 }}>
                                {activity.map((row) => (
                                    <div key={row.id} style={rowStyle}>
                                        <span
                                            style={{
                                                width: 9,
                                                height: 9,
                                                borderRadius: '50%',
                                                marginTop: 5,
                                                flex: 'none',
                                                background: statusColor(
                                                    row.status,
                                                ),
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
                                                <span
                                                    style={{
                                                        overflow: 'hidden',
                                                        textOverflow:
                                                            'ellipsis',
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    {row.location}
                                                    {row.branch ? (
                                                        <span
                                                            style={{
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {' · '}
                                                            {row.branch}
                                                        </span>
                                                    ) : null}
                                                </span>
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
                                                    color: statusColor(
                                                        row.status,
                                                    ),
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
