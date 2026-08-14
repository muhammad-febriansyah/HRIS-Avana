import { Head, Link } from '@inertiajs/react';
import { history } from '@/actions/App/Http/Controllers/Avana/TrackingController';
import { RouteHistoryMap } from '@/components/tracking/route-history-map';
import type { RoutePoint } from '@/components/tracking/route-history-map';
import { AIcon, btnOut, C, card } from '@/lib/avana';

interface SessionDetail {
    id: number;
    employee: string;
    employee_number: string | null;
    department: string | null;
    position: string | null;
    status: string;
    started_at: string;
    ended_at: string | null;
    duration_seconds: number;
    distance_meters: number;
    points_count: number;
    last_sync: string | null;
}

function duration(seconds: number): string {
    return `${Math.floor(seconds / 3600)}j ${Math.floor((seconds % 3600) / 60)}m`;
}

export default function TrackingShow({
    session,
    points,
    sampled,
}: {
    session: SessionDetail;
    points: RoutePoint[];
    sampled: boolean;
}) {
    const stats = [
        [
            'Clock In',
            new Date(session.started_at).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            }),
        ],
        [
            'Clock Out',
            session.ended_at
                ? new Date(session.ended_at).toLocaleTimeString('id-ID', {
                      hour: '2-digit',
                      minute: '2-digit',
                  })
                : 'Masih aktif',
        ],
        ['Durasi', duration(session.duration_seconds)],
        ['Total Jarak', `${(session.distance_meters / 1000).toFixed(2)} km`],
        ['GPS Points', session.points_count.toLocaleString('id-ID')],
        [
            'Last Sync',
            session.last_sync
                ? new Date(session.last_sync).toLocaleString('id-ID', {
                      dateStyle: 'medium',
                      timeStyle: 'short',
                  })
                : '—',
        ],
    ];

    return (
        <>
            <Head title={`Rute ${session.employee}`} />
            <div className="px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs text-slate-400">
                            <span>Kehadiran</span>
                            <AIcon name="chevron-right" size={13} />
                            <Link
                                href={history.url()}
                                className="text-slate-400 no-underline"
                            >
                                Riwayat Tracking
                            </Link>
                            <AIcon name="chevron-right" size={13} />
                            <span className="text-slate-500">Detail</span>
                        </div>
                        <h1 className="m-0 text-2xl font-semibold tracking-tight text-slate-900">
                            Rute {session.employee}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {session.employee_number} ·{' '}
                            {session.position ??
                                session.department ??
                                'Karyawan'}
                        </p>
                    </div>
                    <Link href={history.url()} style={btnOut}>
                        <AIcon name="arrow-left" size={16} /> Kembali
                    </Link>
                </div>

                <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    {stats.map(([label, value]) => (
                        <div key={label} style={{ ...card, padding: 14 }}>
                            <div className="text-xs font-medium text-slate-400">
                                {label}
                            </div>
                            <div className="mt-1 text-sm font-semibold text-slate-800">
                                {value}
                            </div>
                        </div>
                    ))}
                </div>

                {sampled && (
                    <div className="mb-3 rounded-lg border border-blue-100 bg-blue-50 px-4 py-2.5 text-sm text-blue-700">
                        Rute visual disederhanakan untuk menjaga performa. Data
                        GPS mentah tetap tersimpan.
                    </div>
                )}

                {points.length === 0 ? (
                    <div style={{ ...card, padding: 56, textAlign: 'center' }}>
                        <AIcon name="map-pin-off" size={36} color={C.faint} />
                        <h2 className="mt-3 text-base font-semibold text-slate-700">
                            Belum ada titik GPS
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Perangkat belum menyinkronkan lokasi untuk sesi ini.
                        </p>
                    </div>
                ) : (
                    <RouteHistoryMap points={points} />
                )}
            </div>
        </>
    );
}
