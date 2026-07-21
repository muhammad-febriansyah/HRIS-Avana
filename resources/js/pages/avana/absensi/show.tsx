import { Head, Link, router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { LocationMap } from '@/components/map/location-map';
import type { MapPoint } from '@/components/map/location-map';
import { AIcon, ActionBtn, C, card, statusBadge } from '@/lib/avana';

interface LatLng {
    lat: number;
    lng: number;
}

interface AttendanceDetail {
    id: number;
    date: string | null;
    status: string;
    status_label: string;
    location_status: string | null;
    late_minutes: number;
    work_minutes: number;
    distance_meter: number | null;
    employee: {
        id: number;
        name: string;
        employee_number: string;
        initials: string;
        avatar_color: string;
        department: string | null;
        position: string | null;
    } | null;
    branch: string | null;
    shift: {
        name: string;
        start_time: string | null;
        end_time: string | null;
    } | null;
    clock_in: {
        time: string | null;
        coords: LatLng | null;
        photo_url: string | null;
    };
    clock_out: { time: string | null; coords: LatLng | null };
    work_location: {
        name: string;
        address: string | null;
        latitude: number | null;
        longitude: number | null;
        radius_meter: number;
    } | null;
    selfies: {
        url: string;
        captured_at: string | null;
        coords: LatLng | null;
    }[];
}

const sectionHead: CSSProperties = {
    padding: '16px 20px',
    borderBottom: `1px solid ${C.line}`,
    fontSize: 14,
    fontWeight: 600,
    color: C.navy,
    display: 'flex',
    alignItems: 'center',
    gap: 8,
};

const metaLabel: CSSProperties = {
    fontSize: 12,
    color: C.faint,
    marginBottom: 3,
};
const metaValue: CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    fontWeight: 500,
};

function ClockCard({
    title,
    icon,
    time,
    coords,
    photoUrl,
}: {
    title: string;
    icon: string;
    time: string | null;
    coords: LatLng | null;
    photoUrl?: string | null;
}) {
    return (
        <div style={{ ...card, flex: 1, minWidth: 220 }}>
            <div style={sectionHead}>
                <AIcon name={icon} size={16} color={C.primary} />
                {title}
            </div>
            <div style={{ padding: 18, display: 'flex', gap: 16 }}>
                {photoUrl ? (
                    <img
                        src={photoUrl}
                        alt="Selfie absen"
                        style={{
                            width: 84,
                            height: 84,
                            borderRadius: 10,
                            objectFit: 'cover',
                            border: `1px solid ${C.border}`,
                            flex: 'none',
                        }}
                    />
                ) : (
                    <div
                        style={{
                            width: 84,
                            height: 84,
                            borderRadius: 10,
                            background: C.surface,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            flex: 'none',
                        }}
                    >
                        <AIcon name="camera-off" size={22} color={C.faint} />
                    </div>
                )}
                <div>
                    <div
                        style={{
                            fontSize: 26,
                            fontWeight: 700,
                            color: C.navy,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {time ?? '—'}
                    </div>
                    <div style={{ fontSize: 12, color: C.muted, marginTop: 6 }}>
                        {coords
                            ? `${coords.lat.toFixed(5)}, ${coords.lng.toFixed(5)}`
                            : 'Tanpa koordinat'}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AbsensiShow({
    attendance,
}: {
    attendance: AttendanceDetail;
}) {
    const badge = statusBadge(attendance.status_label);
    const outside = attendance.location_status === 'outside';

    const points: MapPoint[] = [];

    if (attendance.clock_in.coords) {
        points.push({
            ...attendance.clock_in.coords,
            label: `Clock-in ${attendance.clock_in.time ?? ''}`,
        });
    }

    if (attendance.clock_out.coords) {
        points.push({
            ...attendance.clock_out.coords,
            label: `Clock-out ${attendance.clock_out.time ?? ''}`,
        });
    }

    const wl = attendance.work_location;
    const area =
        wl && wl.latitude !== null && wl.longitude !== null
            ? { lat: wl.latitude, lng: wl.longitude, radius: wl.radius_meter }
            : null;

    const workHours =
        attendance.work_minutes > 0
            ? `${Math.floor(attendance.work_minutes / 60)}j ${attendance.work_minutes % 60}m`
            : '—';

    return (
        <>
            <Head title={`Absensi · ${attendance.employee?.name ?? ''}`} />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href="/avana/absensi"
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Absensi
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>
                        {attendance.employee?.name ?? 'Detail'}
                    </span>
                    <div style={{ flex: 1 }} />
                    <ActionBtn
                        icon="trash-2"
                        label="Hapus"
                        variant="danger"
                        onClick={() => {
                            if (
                                window.confirm(
                                    'Hapus data absensi ini? Foto selfie ikut terhapus permanen.',
                                )
                            ) {
                                router.delete(
                                    `/avana/absensi/${attendance.id}`,
                                );
                            }
                        }}
                    />
                </div>

                {/* Header */}
                <div style={{ ...card, marginBottom: 18 }}>
                    <div
                        style={{
                            padding: 20,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 16,
                            flexWrap: 'wrap',
                        }}
                    >
                        <div
                            style={{
                                width: 52,
                                height: 52,
                                borderRadius: '50%',
                                flex: 'none',
                                background:
                                    attendance.employee?.avatar_color ??
                                    C.faint,
                                color: '#fff',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: 18,
                                fontWeight: 600,
                            }}
                        >
                            {attendance.employee?.initials ?? '?'}
                        </div>
                        <div style={{ flex: 1, minWidth: 180 }}>
                            <div
                                style={{
                                    fontSize: 18,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                {attendance.employee?.name ?? '—'}
                            </div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                {attendance.employee?.employee_number}
                                {attendance.employee?.position
                                    ? ` · ${attendance.employee.position}`
                                    : ''}
                                {attendance.employee?.department
                                    ? ` · ${attendance.employee.department}`
                                    : ''}
                            </div>
                        </div>
                        <span
                            style={{
                                padding: '5px 12px',
                                borderRadius: 100,
                                fontSize: 12,
                                fontWeight: 600,
                                color: badge.color,
                                background: badge.bg,
                            }}
                        >
                            {badge.label}
                        </span>
                    </div>
                    <div
                        style={{
                            borderTop: `1px solid ${C.line}`,
                            padding: '14px 20px',
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(120px, 1fr))',
                            gap: 16,
                        }}
                    >
                        <div>
                            <div style={metaLabel}>Tanggal</div>
                            <div style={metaValue}>
                                {attendance.date ?? '—'}
                            </div>
                        </div>
                        <div>
                            <div style={metaLabel}>Cabang</div>
                            <div style={metaValue}>
                                {attendance.branch ?? '—'}
                            </div>
                        </div>
                        <div>
                            <div style={metaLabel}>Shift</div>
                            <div style={metaValue}>
                                {attendance.shift?.name ?? '—'}
                            </div>
                        </div>
                        <div>
                            <div style={metaLabel}>Telat</div>
                            <div style={metaValue}>
                                {attendance.late_minutes > 0
                                    ? `${attendance.late_minutes} mnt`
                                    : '—'}
                            </div>
                        </div>
                        <div>
                            <div style={metaLabel}>Durasi Kerja</div>
                            <div style={metaValue}>{workHours}</div>
                        </div>
                    </div>
                </div>

                {/* Clock in / out */}
                <div
                    style={{
                        display: 'flex',
                        gap: 18,
                        flexWrap: 'wrap',
                        marginBottom: 18,
                    }}
                >
                    <ClockCard
                        title="Clock In"
                        icon="log-in"
                        time={attendance.clock_in.time}
                        coords={attendance.clock_in.coords}
                        photoUrl={attendance.clock_in.photo_url}
                    />
                    <ClockCard
                        title="Clock Out"
                        icon="log-out"
                        time={attendance.clock_out.time}
                        coords={attendance.clock_out.coords}
                    />
                </div>

                {/* Geofence map */}
                <div style={{ ...card, marginBottom: 18 }}>
                    <div style={sectionHead}>
                        <AIcon name="map-pin" size={16} color={C.primary} />
                        Lokasi Absen
                    </div>
                    <div style={{ padding: 16 }}>
                        {points.length > 0 ? (
                            <LocationMap
                                points={points}
                                area={area}
                                height={340}
                            />
                        ) : (
                            <div
                                style={{
                                    padding: '40px 0',
                                    textAlign: 'center',
                                    color: C.muted,
                                    fontSize: 13.5,
                                }}
                            >
                                Tidak ada koordinat absen untuk ditampilkan.
                            </div>
                        )}
                        <div
                            style={{
                                marginTop: 14,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                flexWrap: 'wrap',
                                gap: 10,
                            }}
                        >
                            <div>
                                <div style={metaLabel}>Lokasi Kerja</div>
                                <div style={metaValue}>{wl?.name ?? '—'}</div>
                                {wl?.address ? (
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: C.muted,
                                            marginTop: 2,
                                        }}
                                    >
                                        {wl.address}
                                    </div>
                                ) : null}
                            </div>
                            {attendance.clock_in.coords && wl ? (
                                <span
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 7,
                                        padding: '6px 12px',
                                        borderRadius: 100,
                                        fontSize: 12.5,
                                        fontWeight: 600,
                                        color: outside ? C.red : '#059669',
                                        background: outside
                                            ? 'rgba(220,38,38,.08)'
                                            : 'rgba(5,150,105,.08)',
                                    }}
                                >
                                    <AIcon
                                        name="map-pin"
                                        size={14}
                                        color={outside ? C.red : '#059669'}
                                    />
                                    {outside ? 'Di luar area' : 'Di dalam area'}
                                    {attendance.distance_meter !== null && (
                                        <span style={{ fontWeight: 400 }}>
                                            · {attendance.distance_meter} m
                                            {wl.radius_meter > 0
                                                ? ` / radius ${wl.radius_meter} m`
                                                : ''}
                                        </span>
                                    )}
                                </span>
                            ) : null}
                        </div>
                    </div>
                </div>

                {/* Selfie gallery */}
                {attendance.selfies.length > 0 && (
                    <div style={card}>
                        <div style={sectionHead}>
                            <AIcon name="camera" size={16} color={C.primary} />
                            Foto Selfie ({attendance.selfies.length})
                        </div>
                        <div
                            style={{
                                padding: 18,
                                display: 'flex',
                                gap: 14,
                                flexWrap: 'wrap',
                            }}
                        >
                            {attendance.selfies.map((selfie, index) => (
                                <a
                                    key={index}
                                    href={selfie.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    style={{ textDecoration: 'none' }}
                                >
                                    <img
                                        src={selfie.url}
                                        alt="Selfie absen"
                                        style={{
                                            width: 120,
                                            height: 120,
                                            borderRadius: 10,
                                            objectFit: 'cover',
                                            border: `1px solid ${C.border}`,
                                        }}
                                    />
                                    {selfie.captured_at ? (
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: C.muted,
                                                marginTop: 5,
                                                textAlign: 'center',
                                            }}
                                        >
                                            {selfie.captured_at}
                                        </div>
                                    ) : null}
                                </a>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
