import { Head, Link, router, usePoll } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import {
    history,
    live,
} from '@/actions/App/Http/Controllers/Avana/TrackingController';
import { TrackingMap } from '@/components/tracking/tracking-map';
import type { LiveTrackingEmployee } from '@/components/tracking/tracking-map';
import { AIcon, btnExport, btnOut, C, card } from '@/lib/avana';

interface Option {
    id: number;
    name: string;
}

interface LiveTrackingProps {
    employees: LiveTrackingEmployee[];
    filters: {
        search: string | null;
        department_id: number | null;
        shift_id: number | null;
    };
    departments: Option[];
    shifts: Option[];
    polling_interval: number;
}

const selectClass =
    'h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-600 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100';

function formatDuration(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return `${hours}j ${minutes}m`;
}

function relativeTime(value: string | null): string {
    if (!value) {
        return 'Belum ada lokasi';
    }

    const seconds = Math.max(
        0,
        Math.floor((Date.now() - new Date(value).getTime()) / 1000),
    );

    if (seconds < 60) {
        return `${seconds} detik lalu`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} menit lalu`;
    }

    return `${Math.floor(seconds / 3600)} jam lalu`;
}

function statusMeta(status: LiveTrackingEmployee['status']) {
    if (status === 'moving') {
        return { label: 'Bergerak', color: C.green };
    }

    if (status === 'active') {
        return { label: 'Aktif', color: '#22C55E' };
    }

    if (status === 'stale') {
        return { label: 'Stale', color: C.amber };
    }

    return { label: 'Offline', color: C.faint };
}

export default function LiveTracking({
    employees,
    filters,
    departments,
    shifts,
    polling_interval,
}: LiveTrackingProps) {
    usePoll(polling_interval, { only: ['employees'] });
    // Nobody is selected until the admin actually clicks a marker/row — the
    // road route is only worth an OSRM request once someone asks for it.
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');
    const [departmentId, setDepartmentId] = useState(
        filters.department_id?.toString() ?? '',
    );
    const [shiftId, setShiftId] = useState(filters.shift_id?.toString() ?? '');

    // If the selected employee drops out of the (polled/filtered) list,
    // clear the selection instead of silently jumping to someone else.
    const effectiveSelectedId = employees.some(
        (employee) => employee.employee_id === selectedId,
    )
        ? selectedId
        : null;

    const selected = useMemo(
        () =>
            employees.find(
                (employee) => employee.employee_id === effectiveSelectedId,
            ) ?? null,
        [effectiveSelectedId, employees],
    );

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            live.url(),
            {
                search: search || undefined,
                department_id: departmentId || undefined,
                shift_id: shiftId || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Live Tracking" />
            <div className="px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs text-slate-400">
                            <span>Kehadiran</span>
                            <AIcon name="chevron-right" size={13} />
                            <span className="text-slate-500">
                                Live Tracking
                            </span>
                        </div>
                        <h1 className="m-0 text-2xl font-semibold tracking-tight text-slate-900">
                            Live Tracking Karyawan
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Posisi terbaru selama sesi kerja aktif.
                        </p>
                    </div>
                    <Link href={history.url()} style={btnOut}>
                        <AIcon name="route" size={16} />
                        Riwayat Tracking
                    </Link>
                </div>

                <form
                    onSubmit={applyFilters}
                    style={{ ...card, padding: 12 }}
                    className="mb-4 flex flex-wrap items-center gap-2"
                >
                    <div className="relative min-w-56 flex-1">
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
                            className="h-10 w-full rounded-lg border border-transparent bg-slate-50 pr-3 pl-9 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nama / nomor karyawan"
                        />
                    </div>
                    <select
                        className={selectClass}
                        value={departmentId}
                        onChange={(event) =>
                            setDepartmentId(event.target.value)
                        }
                    >
                        <option value="">Semua departemen</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className={selectClass}
                        value={shiftId}
                        onChange={(event) => setShiftId(event.target.value)}
                    >
                        <option value="">Semua shift</option>
                        {shifts.map((shift) => (
                            <option key={shift.id} value={shift.id}>
                                {shift.name}
                            </option>
                        ))}
                    </select>
                    {/* Warning-toned on purpose (not the usual green "save" tone) — the
                        admin asked this to stand out from the neutral filter controls. */}
                    <button type="submit" style={btnExport}>
                        <AIcon name="search" size={16} /> Terapkan
                    </button>
                </form>

                {employees.length === 0 ? (
                    <div style={{ ...card, padding: 48, textAlign: 'center' }}>
                        <AIcon name="map-pin-off" size={34} color={C.faint} />
                        <h2 className="mt-3 text-base font-semibold text-slate-700">
                            Belum ada tracking aktif
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Marker akan muncul setelah karyawan Clock In dan
                            mengirim lokasi.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <TrackingMap
                            employees={employees}
                            selectedId={effectiveSelectedId}
                            onSelect={setSelectedId}
                        />
                        <aside className="space-y-4">
                            <div
                                style={{
                                    ...card,
                                    padding: 0,
                                    overflow: 'hidden',
                                }}
                            >
                                <div className="border-b border-slate-100 px-4 py-3">
                                    <div className="font-semibold text-slate-800">
                                        Karyawan Live
                                    </div>
                                    <div className="text-xs text-slate-500">
                                        {employees.length} sesi kerja aktif
                                    </div>
                                </div>
                                <div className="max-h-72 overflow-y-auto">
                                    {employees.map((employee) => {
                                        const meta = statusMeta(
                                            employee.status,
                                        );
                                        const active =
                                            effectiveSelectedId ===
                                            employee.employee_id;

                                        return (
                                            <button
                                                type="button"
                                                key={employee.employee_id}
                                                onClick={() =>
                                                    setSelectedId(
                                                        employee.employee_id,
                                                    )
                                                }
                                                className={`flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left transition ${active ? 'bg-blue-50' : 'bg-white hover:bg-slate-50'}`}
                                            >
                                                <span
                                                    className="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                                    style={{
                                                        background: meta.color,
                                                    }}
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-semibold text-slate-800">
                                                        {employee.name}
                                                    </span>
                                                    <span className="block truncate text-xs text-slate-500">
                                                        {employee.department ??
                                                            employee.position ??
                                                            'Karyawan'}{' '}
                                                        · {meta.label}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs text-slate-400">
                                                        {relativeTime(
                                                            employee.recorded_at,
                                                        )}
                                                    </span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {selected && (
                                <div style={{ ...card, padding: 18 }}>
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="font-semibold text-slate-900">
                                                {selected.name}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {selected.position ??
                                                    selected.department}
                                            </div>
                                        </div>
                                        <span
                                            className="rounded-full px-2.5 py-1 text-xs font-semibold"
                                            style={{
                                                color: statusMeta(
                                                    selected.status,
                                                ).color,
                                                background: `${statusMeta(selected.status).color}14`,
                                            }}
                                        >
                                            {statusMeta(selected.status).label}
                                        </span>
                                    </div>
                                    {(selected.is_mocked ||
                                        selected.is_suspicious) && (
                                        <div className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                                            Lokasi ditandai untuk ditinjau HR.
                                        </div>
                                    )}
                                    <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt className="text-xs text-slate-400">
                                                Clock In
                                            </dt>
                                            <dd className="mt-0.5 font-medium text-slate-700">
                                                {selected.clock_in_at
                                                    ? new Date(
                                                          selected.clock_in_at,
                                                      ).toLocaleTimeString(
                                                          'id-ID',
                                                          {
                                                              hour: '2-digit',
                                                              minute: '2-digit',
                                                          },
                                                      )
                                                    : '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-400">
                                                Durasi
                                            </dt>
                                            <dd className="mt-0.5 font-medium text-slate-700">
                                                {formatDuration(
                                                    selected.duration_seconds,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-400">
                                                Jarak
                                            </dt>
                                            <dd className="mt-0.5 font-medium text-slate-700">
                                                {(
                                                    selected.distance_meters /
                                                    1000
                                                ).toFixed(2)}{' '}
                                                km
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-400">
                                                Akurasi
                                            </dt>
                                            <dd className="mt-0.5 font-medium text-slate-700">
                                                {selected.accuracy != null
                                                    ? `±${Math.round(selected.accuracy)} m`
                                                    : '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-400">
                                                Baterai
                                            </dt>
                                            <dd className="mt-0.5 font-medium text-slate-700">
                                                {selected.battery_level != null
                                                    ? `${selected.battery_level}%`
                                                    : '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-400">
                                                Update terakhir
                                            </dt>
                                            <dd className="mt-0.5 font-medium text-slate-700">
                                                {relativeTime(
                                                    selected.recorded_at,
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                    <Link
                                        href={history.url({
                                            query: {
                                                employee_id:
                                                    selected.employee_id,
                                            },
                                        })}
                                        className="mt-4 flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        <AIcon name="route" size={16} /> Lihat
                                        Riwayat
                                    </Link>
                                </div>
                            )}
                        </aside>
                    </div>
                )}
            </div>
        </>
    );
}
