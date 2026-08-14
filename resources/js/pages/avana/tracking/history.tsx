import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import {
    history,
    live,
    show,
} from '@/actions/App/Http/Controllers/Avana/TrackingController';
import { AIcon, btnOut, C, card } from '@/lib/avana';

interface SessionRow {
    id: number;
    employee: string;
    employee_number: string | null;
    department: string | null;
    status: string;
    started_at: string;
    ended_at: string | null;
    duration_seconds: number;
    distance_meters: number;
    points_count: number;
}

interface TrackingHistoryProps {
    sessions: {
        data: SessionRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: {
        employee_id: number | null;
        department_id: number | null;
        date: string | null;
    };
    employees: { id: number; full_name: string; employee_number: string }[];
    departments: { id: number; name: string }[];
}

const controlClass =
    'h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100';

function duration(seconds: number): string {
    return `${Math.floor(seconds / 3600)}j ${Math.floor((seconds % 3600) / 60)}m`;
}

export default function TrackingHistory({
    sessions,
    filters,
    employees,
    departments,
}: TrackingHistoryProps) {
    const [employeeId, setEmployeeId] = useState(
        filters.employee_id?.toString() ?? '',
    );
    const [departmentId, setDepartmentId] = useState(
        filters.department_id?.toString() ?? '',
    );
    const [date, setDate] = useState(filters.date ?? '');

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            history.url(),
            {
                employee_id: employeeId || undefined,
                department_id: departmentId || undefined,
                date: date || undefined,
            },
            { preserveScroll: true, replace: true },
        );
    };

    const pageTo = (page: number) => {
        router.get(
            history.url(),
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Riwayat Tracking" />
            <div className="px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs text-slate-400">
                            <span>Kehadiran</span>
                            <AIcon name="chevron-right" size={13} />
                            <span className="text-slate-500">
                                Riwayat Tracking
                            </span>
                        </div>
                        <h1 className="m-0 text-2xl font-semibold tracking-tight text-slate-900">
                            Riwayat Tracking
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Tinjau durasi, jarak, dan rute perjalanan karyawan.
                        </p>
                    </div>
                    <Link href={live.url()} style={btnOut}>
                        <AIcon name="radio" size={16} /> Live Tracking
                    </Link>
                </div>

                <form
                    onSubmit={applyFilters}
                    className="mb-4 flex flex-wrap gap-2"
                >
                    <select
                        className={`${controlClass} min-w-56`}
                        value={employeeId}
                        onChange={(event) => setEmployeeId(event.target.value)}
                    >
                        <option value="">Semua karyawan</option>
                        {employees.map((employee) => (
                            <option key={employee.id} value={employee.id}>
                                {employee.full_name} ·{' '}
                                {employee.employee_number}
                            </option>
                        ))}
                    </select>
                    <select
                        className={controlClass}
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
                    <input
                        type="date"
                        className={controlClass}
                        value={date}
                        onChange={(event) => setDate(event.target.value)}
                    />
                    <button type="submit" style={btnOut}>
                        <AIcon name="filter" size={16} /> Terapkan
                    </button>
                </form>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    {sessions.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <AIcon name="route-off" size={34} color={C.faint} />
                            <h2 className="mt-3 text-base font-semibold text-slate-700">
                                Belum ada riwayat
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Sesi yang selesai atau sedang aktif akan tampil
                                di sini.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[820px] border-collapse text-left">
                                <thead className="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                    <tr>
                                        <th className="px-5 py-3">Karyawan</th>
                                        <th className="px-4 py-3">Mulai</th>
                                        <th className="px-4 py-3">Selesai</th>
                                        <th className="px-4 py-3">Durasi</th>
                                        <th className="px-4 py-3">Jarak</th>
                                        <th className="px-4 py-3">Titik</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {sessions.data.map((session) => (
                                        <tr
                                            key={session.id}
                                            className="border-t border-slate-100 text-sm text-slate-700 hover:bg-slate-50/70"
                                        >
                                            <td className="px-5 py-3.5">
                                                <div className="font-semibold text-slate-800">
                                                    {session.employee}
                                                </div>
                                                <div className="text-xs text-slate-400">
                                                    {session.employee_number} ·{' '}
                                                    {session.department ?? '—'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {new Date(
                                                    session.started_at,
                                                ).toLocaleString('id-ID', {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                })}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {session.ended_at ? (
                                                    new Date(
                                                        session.ended_at,
                                                    ).toLocaleTimeString(
                                                        'id-ID',
                                                        {
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        },
                                                    )
                                                ) : (
                                                    <span className="font-medium text-green-600">
                                                        Aktif
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {duration(
                                                    session.duration_seconds,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 font-medium">
                                                {(
                                                    session.distance_meters /
                                                    1000
                                                ).toFixed(2)}{' '}
                                                km
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {session.points_count.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                <Link
                                                    href={show.url(session.id)}
                                                    className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-white"
                                                >
                                                    <AIcon
                                                        name="map"
                                                        size={14}
                                                    />{' '}
                                                    Lihat Rute
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {sessions.meta.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm text-slate-500">
                            <span>
                                {sessions.meta.total.toLocaleString('id-ID')}{' '}
                                sesi
                            </span>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    style={btnOut}
                                    disabled={sessions.meta.current_page <= 1}
                                    onClick={() =>
                                        pageTo(sessions.meta.current_page - 1)
                                    }
                                >
                                    Sebelumnya
                                </button>
                                <button
                                    type="button"
                                    style={btnOut}
                                    disabled={
                                        sessions.meta.current_page >=
                                        sessions.meta.last_page
                                    }
                                    onClick={() =>
                                        pageTo(sessions.meta.current_page + 1)
                                    }
                                >
                                    Berikutnya
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
