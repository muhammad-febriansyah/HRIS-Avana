import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, C, statusBadge } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

interface AttendanceEmployee {
    id: number;
    name: string;
    employee_number: string;
    initials: string;
    avatar_color: string;
}

interface AttendanceShift {
    id: number;
    name: string;
    label: string;
}

/** A single attendance row as serialized by `AttendanceResource`. */
interface Attendance {
    id: number;
    employee: AttendanceEmployee | null;
    shift: AttendanceShift | null;
    date: string;
    date_raw: string | null;
    clock_in: string;
    clock_out: string;
    late_minutes: number;
    telat: string;
    status: string;
    status_label: string;
}

interface BranchOption {
    id: number;
    name: string;
}

interface AbsensiFilters {
    search?: string;
    status?: string;
    branch_id?: string;
    sort?: string;
    direction?: string;
    per_page?: string;
    date: string;
}

interface AbsensiProps {
    attendances: {
        data: Attendance[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: AbsensiFilters;
    date: { value: string; display: string };
    kpis: { hadir: number; terlambat: number; izin: number; alpa: number };
    branches: BranchOption[];
}

export default function AvanaAbsensi({
    attendances,
    filters,
    date,
    kpis,
}: AbsensiProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = attendances.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstSearch = useRef(true);

    /** KPI cards mirror the prototype layout, fed by the daily status counts. */
    const kpiCards: { label: string; value: number; color: string }[] = [
        { label: 'Hadir', value: kpis.hadir, color: C.green },
        { label: 'Terlambat', value: kpis.terlambat, color: C.amber },
        { label: 'Cuti / Izin', value: kpis.izin, color: C.primary },
        { label: 'Alpa', value: kpis.alpa, color: C.red },
    ];

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, search: search || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const changeDate = (value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, date: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Absensi" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
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
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Absensi</span>
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
                            Absensi
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            {date.display} · Periode harian
                        </div>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <input
                            type="date"
                            value={filters.date}
                            onChange={(event) => changeDate(event.target.value)}
                            style={{
                                height: 40,
                                padding: '0 12px',
                                background: '#fff',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13.5,
                                color: C.text,
                                outline: 'none',
                                cursor: 'pointer',
                            }}
                        />
                        <button
                            onClick={() =>
                                toast.info('Menyiapkan export', {
                                    description:
                                        'File akan diunduh sebentar lagi',
                                })
                            }
                            style={btnOut}
                        >
                            <AIcon name="download" size={16} />
                            Export Rekap
                        </button>
                    </div>
                </div>

                <div
                    className="avn-abs"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '340px 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Clock panel — static employee-app decoration */}
                    <div
                        style={{
                            background: '#fff',
                            border: `1px solid ${C.border}`,
                            borderRadius: 12,
                            boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                            overflow: 'hidden',
                        }}
                    >
                        <div
                            style={{
                                position: 'relative',
                                height: 170,
                                background:
                                    'linear-gradient(135deg,#eef2fb,#dde6f7)',
                                overflow: 'hidden',
                            }}
                        >
                            <div
                                style={{
                                    position: 'absolute',
                                    inset: 0,
                                    backgroundImage:
                                        'linear-gradient(rgba(47,84,201,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(47,84,201,.07) 1px,transparent 1px)',
                                    backgroundSize: '26px 26px',
                                }}
                            />
                            <div
                                style={{
                                    position: 'absolute',
                                    top: 42,
                                    left: 60,
                                    width: 120,
                                    height: 14,
                                    background: 'rgba(110,155,230,.45)',
                                    borderRadius: 7,
                                    transform: 'rotate(-18deg)',
                                }}
                            />
                            <div
                                style={{
                                    position: 'absolute',
                                    top: 96,
                                    left: 130,
                                    width: 160,
                                    height: 12,
                                    background: 'rgba(110,155,230,.35)',
                                    borderRadius: 6,
                                    transform: 'rotate(8deg)',
                                }}
                            />
                            <div
                                style={{
                                    position: 'absolute',
                                    top: 62,
                                    left: '50%',
                                    transform: 'translateX(-50%)',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                }}
                            >
                                <div
                                    style={{
                                        width: 42,
                                        height: 42,
                                        borderRadius: '50% 50% 50% 0',
                                        background: C.red,
                                        transform: 'rotate(-45deg)',
                                        boxShadow:
                                            '0 4px 10px rgba(220,38,38,.35)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <div
                                        style={{
                                            width: 14,
                                            height: 14,
                                            borderRadius: '50%',
                                            background: '#fff',
                                            transform: 'rotate(45deg)',
                                        }}
                                    />
                                </div>
                            </div>
                            <div
                                style={{
                                    position: 'absolute',
                                    bottom: 12,
                                    left: 12,
                                    right: 12,
                                    background: 'rgba(255,255,255,.92)',
                                    borderRadius: 8,
                                    padding: '8px 11px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <AIcon
                                    name="map-pin"
                                    size={15}
                                    color={C.primary}
                                />
                                <span style={{ fontSize: 12, color: C.text }}>
                                    Kantor Pusat — Jl. Sudirman Kav. 52, Jakarta
                                </span>
                            </div>
                        </div>
                        <div style={{ padding: 22, textAlign: 'center' }}>
                            <div style={{ fontSize: 13, color: C.muted }}>
                                Waktu saat ini
                            </div>
                            <div
                                style={{
                                    fontSize: 38,
                                    fontWeight: 700,
                                    color: C.navy,
                                    letterSpacing: '-.02em',
                                    fontVariantNumeric: 'tabular-nums',
                                    marginTop: 2,
                                }}
                            >
                                07:54
                                <span style={{ fontSize: 18, color: C.faint }}>
                                    :21
                                </span>
                            </div>
                            <div
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    background: 'rgba(22,163,74,.1)',
                                    color: C.green,
                                    fontSize: 12,
                                    fontWeight: 600,
                                    padding: '4px 11px',
                                    borderRadius: 100,
                                    marginTop: 10,
                                }}
                            >
                                <span
                                    style={{
                                        width: 7,
                                        height: 7,
                                        borderRadius: '50%',
                                        background: C.green,
                                    }}
                                />
                                Dalam radius kantor
                            </div>
                            <button
                                onClick={() =>
                                    toast.info(
                                        'Fitur clock-in ada di aplikasi karyawan',
                                    )
                                }
                                style={{
                                    width: '100%',
                                    height: 54,
                                    marginTop: 18,
                                    background: C.primary,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 10,
                                    fontSize: 15.5,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 9,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="fingerprint" size={21} />
                                Clock In Sekarang
                            </button>
                            <button
                                onClick={() =>
                                    toast.info(
                                        'Fitur clock-in ada di aplikasi karyawan',
                                    )
                                }
                                style={{
                                    width: '100%',
                                    height: 46,
                                    marginTop: 10,
                                    background: '#fff',
                                    color: C.red,
                                    border: '1px solid #F1D5D5',
                                    borderRadius: 10,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name="log-out" size={18} />
                                Clock Out
                            </button>
                        </div>
                    </div>

                    {/* Rekap */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
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

                        <div
                            style={{
                                background: '#fff',
                                border: `1px solid ${C.border}`,
                                borderRadius: 12,
                                boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                                overflow: 'hidden',
                            }}
                        >
                            <div
                                style={{
                                    padding: '16px 18px',
                                    borderBottom: `1px solid ${C.border}`,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 15,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    Rekap Kehadiran Harian
                                </div>
                                <div style={{ position: 'relative' }}>
                                    <span
                                        style={{
                                            position: 'absolute',
                                            left: 11,
                                            top: '50%',
                                            transform: 'translateY(-50%)',
                                            display: 'flex',
                                        }}
                                    >
                                        <AIcon
                                            name="search"
                                            size={15}
                                            color={C.faint}
                                        />
                                    </span>
                                    <input
                                        placeholder="Cari karyawan…"
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        style={{
                                            height: 36,
                                            width: 180,
                                            padding: '0 12px 0 34px',
                                            background: C.surface,
                                            border: '1px solid transparent',
                                            borderRadius: 8,
                                            fontSize: 13,
                                            outline: 'none',
                                        }}
                                    />
                                </div>
                            </div>
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 620,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th
                                                style={{
                                                    padding: '11px 18px',
                                                    textAlign: 'left',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: C.faint,
                                                    textTransform: 'uppercase',
                                                }}
                                            >
                                                Karyawan
                                            </th>
                                            <th
                                                style={{
                                                    padding: '11px 16px',
                                                    textAlign: 'left',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: C.faint,
                                                    textTransform: 'uppercase',
                                                }}
                                            >
                                                Shift
                                            </th>
                                            <th
                                                style={{
                                                    padding: '11px 16px',
                                                    textAlign: 'left',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: C.faint,
                                                    textTransform: 'uppercase',
                                                }}
                                            >
                                                Masuk
                                            </th>
                                            <th
                                                style={{
                                                    padding: '11px 16px',
                                                    textAlign: 'left',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: C.faint,
                                                    textTransform: 'uppercase',
                                                }}
                                            >
                                                Keluar
                                            </th>
                                            <th
                                                style={{
                                                    padding: '11px 16px',
                                                    textAlign: 'left',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: C.faint,
                                                    textTransform: 'uppercase',
                                                }}
                                            >
                                                Telat
                                            </th>
                                            <th
                                                style={{
                                                    padding: '11px 18px',
                                                    textAlign: 'left',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    color: C.faint,
                                                    textTransform: 'uppercase',
                                                }}
                                            >
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {attendances.data.length === 0 && (
                                            <tr
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    colSpan={6}
                                                    style={{
                                                        padding: '48px 18px',
                                                        textAlign: 'center',
                                                        fontSize: 13.5,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            flexDirection:
                                                                'column',
                                                            alignItems:
                                                                'center',
                                                            gap: 10,
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="fingerprint"
                                                            size={28}
                                                            color={C.faint}
                                                        />
                                                        <div>
                                                            Belum ada data
                                                            kehadiran untuk
                                                            tanggal ini.
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                        {attendances.data.map((row) => {
                                            const badge = statusBadge(
                                                row.status_label,
                                            );

                                            return (
                                                <tr
                                                    key={row.id}
                                                    style={{
                                                        borderTop: `1px solid ${C.line}`,
                                                    }}
                                                >
                                                    <td
                                                        style={{
                                                            padding:
                                                                '12px 18px',
                                                        }}
                                                    >
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 10,
                                                            }}
                                                        >
                                                            <div
                                                                style={{
                                                                    width: 32,
                                                                    height: 32,
                                                                    borderRadius:
                                                                        '50%',
                                                                    flex: 'none',
                                                                    background:
                                                                        row
                                                                            .employee
                                                                            ?.avatar_color ??
                                                                        C.faint,
                                                                    color: '#fff',
                                                                    display:
                                                                        'flex',
                                                                    alignItems:
                                                                        'center',
                                                                    justifyContent:
                                                                        'center',
                                                                    fontSize: 11.5,
                                                                    fontWeight: 600,
                                                                }}
                                                            >
                                                                {row.employee
                                                                    ?.initials ??
                                                                    '?'}
                                                            </div>
                                                            <span
                                                                style={{
                                                                    fontSize: 13,
                                                                    fontWeight: 500,
                                                                    color: C.text,
                                                                }}
                                                            >
                                                                {row.employee
                                                                    ?.name ??
                                                                    '—'}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td
                                                        style={{
                                                            padding:
                                                                '12px 16px',
                                                            fontSize: 12.5,
                                                            color: C.muted,
                                                        }}
                                                    >
                                                        {row.shift?.label ??
                                                            '—'}
                                                    </td>
                                                    <td
                                                        style={{
                                                            padding:
                                                                '12px 16px',
                                                            fontSize: 13,
                                                            color: C.text,
                                                            fontVariantNumeric:
                                                                'tabular-nums',
                                                        }}
                                                    >
                                                        {row.clock_in}
                                                    </td>
                                                    <td
                                                        style={{
                                                            padding:
                                                                '12px 16px',
                                                            fontSize: 13,
                                                            color: C.text,
                                                            fontVariantNumeric:
                                                                'tabular-nums',
                                                        }}
                                                    >
                                                        {row.clock_out}
                                                    </td>
                                                    <td
                                                        style={{
                                                            padding:
                                                                '12px 16px',
                                                            fontSize: 13,
                                                            color: C.muted,
                                                        }}
                                                    >
                                                        {row.telat}
                                                    </td>
                                                    <td
                                                        style={{
                                                            padding:
                                                                '12px 18px',
                                                        }}
                                                    >
                                                        <span
                                                            style={{
                                                                padding:
                                                                    '3px 10px',
                                                                borderRadius: 100,
                                                                fontSize: 11.5,
                                                                fontWeight: 600,
                                                                color: badge.color,
                                                                background:
                                                                    badge.bg,
                                                            }}
                                                        >
                                                            {badge.label}
                                                        </span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination footer */}
                            <div
                                style={{
                                    padding: '14px 18px',
                                    borderTop: `1px solid ${C.border}`,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    flexWrap: 'wrap',
                                    gap: 12,
                                }}
                            >
                                <div style={{ fontSize: 13, color: C.muted }}>
                                    Menampilkan{' '}
                                    <span
                                        style={{
                                            color: C.text,
                                            fontWeight: 500,
                                        }}
                                    >
                                        {meta.from ?? 0}–{meta.to ?? 0}
                                    </span>{' '}
                                    dari{' '}
                                    <span
                                        style={{
                                            color: C.text,
                                            fontWeight: 500,
                                        }}
                                    >
                                        {meta.total.toLocaleString('id-ID')}
                                    </span>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 6,
                                        alignItems: 'center',
                                    }}
                                >
                                    <button
                                        disabled={meta.current_page <= 1}
                                        onClick={() =>
                                            goToPage(meta.current_page - 1)
                                        }
                                        style={{
                                            height: 34,
                                            minWidth: 34,
                                            padding: '0 10px',
                                            border: `1px solid ${C.border}`,
                                            background: '#fff',
                                            borderRadius: 8,
                                            fontSize: 13,
                                            color:
                                                meta.current_page <= 1
                                                    ? C.faint
                                                    : C.text,
                                            cursor:
                                                meta.current_page <= 1
                                                    ? 'not-allowed'
                                                    : 'pointer',
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 5,
                                        }}
                                    >
                                        <AIcon name="chevron-left" size={15} />
                                    </button>
                                    <span
                                        style={{
                                            fontSize: 13,
                                            color: C.muted,
                                            padding: '0 4px',
                                        }}
                                    >
                                        {meta.current_page} / {meta.last_page}
                                    </span>
                                    <button
                                        disabled={
                                            meta.current_page >= meta.last_page
                                        }
                                        onClick={() =>
                                            goToPage(meta.current_page + 1)
                                        }
                                        style={{
                                            height: 34,
                                            minWidth: 34,
                                            padding: '0 10px',
                                            border: `1px solid ${C.border}`,
                                            background: '#fff',
                                            borderRadius: 8,
                                            fontSize: 13,
                                            color:
                                                meta.current_page >=
                                                meta.last_page
                                                    ? C.faint
                                                    : C.text,
                                            cursor:
                                                meta.current_page >=
                                                meta.last_page
                                                    ? 'not-allowed'
                                                    : 'pointer',
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                        }}
                                    >
                                        <AIcon name="chevron-right" size={15} />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
