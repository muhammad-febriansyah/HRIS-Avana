import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import FieldVisitController from '@/actions/App/Http/Controllers/Avana/FieldVisitController';
import { ActionBtn, AIcon, btnP, C, card } from '@/lib/avana';
import { ConfirmModal, filterSelectStyle, headThStyle } from './components';
import type {
    FlashProps,
    VisitEmployee,
    VisitingIndexProps,
    VisitRow,
    VisitTask,
    VisitTaskProgress,
} from './types';

/** How many attendee avatars to show before collapsing into a "+N" bubble. */
const MAX_VISIBLE_AVATARS = 3;

/** Overlapping avatar stack naming everyone who attended a visit. */
function VisitAttendees({ employees }: { employees: VisitEmployee[] }) {
    if (employees.length === 0) {
        return <span style={{ fontSize: 12.5, color: C.faint }}>—</span>;
    }

    const shown = employees.slice(0, MAX_VISIBLE_AVATARS);
    const overflow = employees.length - shown.length;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{ display: 'flex', flex: 'none' }}>
                {shown.map((employee, index) => (
                    <div
                        key={employee.id}
                        title={`${employee.name}${employee.employee_number ? ` (${employee.employee_number})` : ''}`}
                        style={{
                            width: 32,
                            height: 32,
                            borderRadius: '50%',
                            background: employee.avatar_color,
                            color: '#fff',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: 11.5,
                            fontWeight: 600,
                            border: '2px solid #fff',
                            marginLeft: index === 0 ? 0 : -10,
                        }}
                    >
                        {employee.initials}
                    </div>
                ))}
                {overflow > 0 && (
                    <div
                        title={employees
                            .slice(MAX_VISIBLE_AVATARS)
                            .map((employee) => employee.name)
                            .join(', ')}
                        style={{
                            width: 32,
                            height: 32,
                            borderRadius: '50%',
                            background: C.line,
                            color: C.muted,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: 11,
                            fontWeight: 600,
                            border: '2px solid #fff',
                            marginLeft: -10,
                        }}
                    >
                        +{overflow}
                    </div>
                )}
            </div>
            <div style={{ minWidth: 0 }}>
                <div
                    style={{
                        fontSize: 13,
                        fontWeight: 500,
                        color: C.text,
                    }}
                >
                    {employees[0].name}
                </div>
                <div style={{ fontSize: 11.5, color: C.faint }}>
                    {employees.length > 1
                        ? `+${employees.length - 1} karyawan lain`
                        : (employees[0].employee_number ?? '')}
                </div>
            </div>
        </div>
    );
}

/** The visit's tasklist: a progress count plus tickable lines. */
function VisitTasksCell({
    visitId,
    tasks,
    progress,
}: {
    visitId: number;
    tasks: VisitTask[];
    progress: VisitTaskProgress;
}) {
    if (tasks.length === 0) {
        return <span style={{ fontSize: 12.5, color: C.faint }}>—</span>;
    }

    const toggle = (taskId: number) => {
        router.post(
            FieldVisitController.toggleTask({
                visit: visitId,
                task: taskId,
            }).url,
            {},
            { preserveScroll: true },
        );
    };

    const complete = progress.done === progress.total;

    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 6,
                minWidth: 190,
            }}
        >
            <span
                style={{
                    alignSelf: 'flex-start',
                    padding: '2px 9px',
                    borderRadius: 100,
                    fontSize: 11,
                    fontWeight: 600,
                    color: complete ? '#16A34A' : '#D97706',
                    background: complete
                        ? 'rgba(22,163,74,.1)'
                        : 'rgba(217,119,6,.1)',
                }}
            >
                {progress.done}/{progress.total} Selesai
            </span>
            {tasks.map((task) => (
                <label
                    key={task.id}
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 7,
                        fontSize: 12,
                        color: task.is_done ? C.faint : C.text,
                        cursor: 'pointer',
                        lineHeight: 1.4,
                    }}
                >
                    <input
                        type="checkbox"
                        checked={task.is_done}
                        onChange={() => toggle(task.id)}
                        style={{ marginTop: 2, cursor: 'pointer' }}
                    />
                    <span
                        style={{
                            textDecoration: task.is_done
                                ? 'line-through'
                                : 'none',
                        }}
                    >
                        {task.title}
                    </span>
                </label>
            ))}
        </div>
    );
}

export default function VisitingIndex({
    visits,
    branches,
    filters,
}: VisitingIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = visits.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [confirm, setConfirm] = useState<VisitRow | null>(null);
    const isFirstSearch = useRef(true);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
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

    const applyFilter = (key: string, value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, [key]: value || undefined, page: 1 },
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

    const deleteVisit = () => {
        if (!confirm) {
            return;
        }

        router.delete(FieldVisitController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Visiting Pekerjaan" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
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
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Visiting Pekerjaan
                            </span>
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
                            Visiting Pekerjaan
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Catat kunjungan lapangan &amp; klien karyawan Anda
                        </div>
                    </div>
                    <Link
                        href={FieldVisitController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Catat Kunjungan
                    </Link>
                </div>

                {/* Table */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    {/* Filter bar */}
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            gap: 10,
                            flexWrap: 'wrap',
                            alignItems: 'center',
                        }}
                    >
                        <div
                            style={{
                                position: 'relative',
                                flex: 1,
                                minWidth: 200,
                                maxWidth: 320,
                            }}
                        >
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
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari lokasi atau klien…"
                                style={{
                                    width: '100%',
                                    height: 38,
                                    padding: '0 12px 0 36px',
                                    background: C.surface,
                                    border: '1px solid transparent',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    outline: 'none',
                                    transition: '.15s',
                                }}
                            />
                        </div>
                        <input
                            type="date"
                            aria-label="Tanggal"
                            value={filters.date ?? ''}
                            onChange={(event) =>
                                applyFilter('date', event.target.value)
                            }
                            style={filterSelectStyle}
                        />
                        <select
                            aria-label="Cabang"
                            value={filters.branch_id ?? ''}
                            onChange={(event) =>
                                applyFilter('branch_id', event.target.value)
                            }
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Cabang</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                        <div style={{ flex: 1 }} />
                    </div>

                    {/* Table */}
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 760,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Cabang</th>
                                    <th style={headThStyle}>Tanggal</th>
                                    <th style={headThStyle}>Lokasi</th>
                                    <th style={headThStyle}>Klien</th>
                                    <th style={headThStyle}>Tugas</th>
                                    <th style={headThStyle}>Foto</th>
                                    <th
                                        style={{
                                            ...headThStyle,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visits.data.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={8}
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
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon
                                                    name="map-pin"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada kunjungan
                                                    tercatat.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {visits.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td style={{ padding: '12px 16px' }}>
                                            <VisitAttendees
                                                employees={row.employees}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.muted,
                                            }}
                                        >
                                            {row.branch ?? '—'}
                                            {row.status === 'draft' && (
                                                <span
                                                    style={{
                                                        display: 'inline-block',
                                                        marginLeft: 8,
                                                        padding: '2px 8px',
                                                        borderRadius: 100,
                                                        background: '#EEF2FF',
                                                        color: C.primary,
                                                        fontSize: 10.5,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    Draft
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.text,
                                            }}
                                        >
                                            {row.visit_date ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.text,
                                            }}
                                        >
                                            {row.location}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.muted,
                                            }}
                                        >
                                            {row.client_name ?? '—'}
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <VisitTasksCell
                                                visitId={row.id}
                                                tasks={row.tasks}
                                                progress={row.task_progress}
                                            />
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
                                            {row.photo_urls.length > 0 ? (
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        gap: 4,
                                                        alignItems: 'center',
                                                    }}
                                                >
                                                    {row.photo_urls.map(
                                                        (url, index) => (
                                                            <a
                                                                key={url}
                                                                href={url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                title={`Foto ${index + 1} dari ${row.photo_urls.length}`}
                                                            >
                                                                <img
                                                                    src={url}
                                                                    alt={`Foto kunjungan ${index + 1}`}
                                                                    style={{
                                                                        width: 40,
                                                                        height: 40,
                                                                        borderRadius: 8,
                                                                        objectFit:
                                                                            'cover',
                                                                        border: `1px solid ${C.border}`,
                                                                    }}
                                                                />
                                                            </a>
                                                        ),
                                                    )}
                                                </div>
                                            ) : (
                                                <span
                                                    style={{
                                                        fontSize: 12.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 18px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <ActionBtn
                                                icon="eye"
                                                label="Detail"
                                                variant="primary"
                                                onClick={() =>
                                                    router.visit(
                                                        `/avana/visiting/${row.id}`,
                                                    )
                                                }
                                            />
                                            {row.status === 'draft' && (
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Lanjutkan"
                                                    variant="success"
                                                    onClick={() =>
                                                        router.visit(
                                                            FieldVisitController.edit(
                                                                row.id,
                                                            ).url,
                                                        )
                                                    }
                                                />
                                            )}
                                            <ActionBtn
                                                icon="trash-2"
                                                label="Hapus"
                                                variant="danger"
                                                onClick={() => setConfirm(row)}
                                            />
                                        </td>
                                    </tr>
                                ))}
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
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.from ?? 0}–{meta.to ?? 0}
                            </span>{' '}
                            dari{' '}
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.total.toLocaleString('id-ID')}
                            </span>{' '}
                            kunjungan
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
                                onClick={() => goToPage(meta.current_page - 1)}
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
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => goToPage(meta.current_page + 1)}
                                style={{
                                    height: 34,
                                    minWidth: 34,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color:
                                        meta.current_page >= meta.last_page
                                            ? C.faint
                                            : C.text,
                                    cursor:
                                        meta.current_page >= meta.last_page
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

            {/* Confirm delete modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus kunjungan?"
                    body={
                        <>
                            Kunjungan ke{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.location}
                            </strong>{' '}
                            akan dihapus permanen. Tindakan ini tidak dapat
                            dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteVisit}
                />
            )}
        </>
    );
}
