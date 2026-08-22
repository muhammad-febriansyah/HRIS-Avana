import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import KpiIndicatorController from '@/actions/App/Http/Controllers/Avana/KpiIndicatorController';
import PerformanceController from '@/actions/App/Http/Controllers/Avana/PerformanceController';
import { DatePicker } from '@/components/avana/date-picker';
import {
    ActionBtn,
    AIcon,
    btnOut,
    btnP,
    btnSave,
    C,
    card,
    thCell,
} from '@/lib/avana';
import {
    ConfirmModal,
    CycleStatusBadge,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    ReviewStatusBadge,
    ScoreValue,
    textareaStyle,
    withError,
} from './components';
import { emptyCycleForm, emptyScoreForm } from './types';
import type {
    CycleFormData,
    CycleRow,
    KinerjaIndexProps,
    ReviewRow,
    ScoreFormData,
} from './types';
import type { FlashProps } from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 180px',
};

const scoreCellStyle: CSSProperties = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
};

const CYCLE_STATUS_TRANSITIONS: Record<string, string> = {
    draft: 'active',
    active: 'closed',
    closed: 'active',
};

/** The next cycle status a one-click action can advance to, or null if terminal. */
function nextCycleStatus(status: string): string | null {
    return CYCLE_STATUS_TRANSITIONS[status] ?? null;
}

/** Label for the cycle status advance action. */
function cycleStatusActionLabel(status: string): string {
    if (status === 'draft') {
        return 'Aktifkan';
    }

    if (status === 'active') {
        return 'Tutup';
    }

    return 'Buka Kembali';
}

export default function KinerjaIndex({
    can,
    reviews,
    cycles,
    kpis,
}: KinerjaIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<ReviewRow | null>(null);
    const [cycleModalOpen, setCycleModalOpen] = useState(false);
    const [scoreReview, setScoreReview] = useState<ReviewRow | null>(null);

    const [editingCycle, setEditingCycle] = useState<CycleRow | null>(null);
    const cycleForm = useForm<CycleFormData>({ ...emptyCycleForm });
    const scoreForm = useForm<ScoreFormData>({ ...emptyScoreForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const deleteReview = () => {
        if (!confirm) {
            return;
        }

        router.delete(PerformanceController.destroy(confirm.route_key).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const advanceCycleStatus = (cycle: CycleRow) => {
        const to = nextCycleStatus(cycle.status);

        if (!to) {
            return;
        }

        router.patch(
            PerformanceController.updateCycleStatus(cycle.id).url,
            { status: to },
            { preserveScroll: true },
        );
    };

    const openCycle = () => {
        cycleForm.clearErrors();
        cycleForm.setData({ ...emptyCycleForm });
        setEditingCycle(null);
        setCycleModalOpen(true);
    };

    const openCycleEdit = (cycle: CycleRow) => {
        cycleForm.clearErrors();
        cycleForm.setData({
            name: cycle.name,
            period_start: cycle.period_start ?? '',
            period_end: cycle.period_end ?? '',
            description: cycle.description ?? '',
        });
        setEditingCycle(cycle);
        setCycleModalOpen(true);
    };

    const closeCycleModal = () => {
        setCycleModalOpen(false);
        setEditingCycle(null);
        cycleForm.reset();
        cycleForm.clearErrors();
    };

    const submitCycle = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        // The status endpoint owns draft → active → closed; this form only ever
        // edits the cycle's name, period, and description.
        cycleForm.submit(
            editingCycle
                ? PerformanceController.updateCycle(editingCycle.id)
                : PerformanceController.storeCycle(),
            { onSuccess: () => closeCycleModal() },
        );
    };

    const openScore = (review: ReviewRow) => {
        scoreForm.clearErrors();
        scoreForm.setData({
            manager_score:
                review.manager_score !== null
                    ? String(review.manager_score)
                    : '',
            review_date: review.review_date ?? '',
        });
        setScoreReview(review);
    };

    const closeScoreModal = () => {
        setScoreReview(null);
        scoreForm.reset();
        scoreForm.clearErrors();
    };

    const submitScore = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!scoreReview) {
            return;
        }

        scoreForm.submit(
            PerformanceController.submitScore(scoreReview.route_key),
            {
                preserveScroll: true,
                onSuccess: () => closeScoreModal(),
            },
        );
    };

    const kpiItems = [
        {
            label: 'Total Penilaian',
            value: kpis.total_reviews,
            icon: 'clipboard-list',
            color: C.primary,
        },
        {
            label: 'Selesai',
            value: kpis.completed,
            icon: 'circle-check',
            color: C.green,
        },
        {
            label: 'Dalam Proses',
            value: kpis.in_progress,
            icon: 'loader',
            color: C.amber,
        },
        {
            label: 'Siklus Aktif',
            value: kpis.active_cycles,
            icon: 'calendar-clock',
            color: C.sky,
        },
    ];

    return (
        <>
            <Head title="Kinerja" />
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
                            <span style={{ color: C.muted }}>Kinerja</span>
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
                            Penilaian Kinerja
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola siklus &amp; penilaian kinerja karyawan.
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <Link
                            href={PerformanceController.hav()}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="gem" size={16} color={C.text} />
                            Laporan HAV
                        </Link>
                        <Link
                            href={KpiIndicatorController.index()}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon
                                name="list-checks"
                                size={16}
                                color={C.text}
                            />
                            Definisi KPI
                        </Link>
                        {can.create && (
                            <button onClick={openCycle} style={btnOut}>
                                <AIcon
                                    name="calendar-plus"
                                    size={16}
                                    color={C.text}
                                />
                                Tambah Siklus
                            </button>
                        )}
                        {can.create && (
                            <Link
                                href={PerformanceController.create()}
                                style={{ ...btnP, textDecoration: 'none' }}
                            >
                                <AIcon name="plus" size={16} color="#fff" />
                                Tambah Penilaian
                            </Link>
                        )}
                    </div>
                </div>

                {/* KPI cards */}
                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 14,
                        marginBottom: 22,
                    }}
                >
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    marginBottom: 10,
                                }}
                            >
                                <div
                                    style={{
                                        width: 34,
                                        height: 34,
                                        borderRadius: 9,
                                        background: `${item.color}1a`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon
                                        name={item.icon}
                                        size={17}
                                        color={item.color}
                                    />
                                </div>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        fontWeight: 500,
                                    }}
                                >
                                    {item.label}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 26,
                                    fontWeight: 700,
                                    color: C.navy,
                                    letterSpacing: '-.02em',
                                }}
                            >
                                {item.value}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Performance reviews table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Daftar Penilaian
                </div>
                <div style={{ ...card, overflow: 'hidden', marginBottom: 28 }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 880,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Siklus</th>
                                    <th style={thCell}>Skor Mandiri</th>
                                    <th style={thCell}>Skor Atasan</th>
                                    <th style={thCell}>Skor Akhir</th>
                                    <th style={thCell}>Status</th>
                                    <th
                                        style={{
                                            ...thCell,
                                            textAlign: 'right',
                                            padding: '12px 18px',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {reviews.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={7}
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
                                                    name="clipboard-list"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada penilaian kinerja.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {reviews.map((review) => (
                                    <tr
                                        key={review.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {review.employee ?? '—'}
                                            </div>
                                            {review.employee_number && (
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                        marginTop: 2,
                                                    }}
                                                >
                                                    {review.employee_number}
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {review.cycle ?? '—'}
                                        </td>
                                        <td style={scoreCellStyle}>
                                            <ScoreValue
                                                value={review.self_score}
                                            />
                                        </td>
                                        <td style={scoreCellStyle}>
                                            <ScoreValue
                                                value={review.manager_score}
                                            />
                                        </td>
                                        <td style={scoreCellStyle}>
                                            <ScoreValue
                                                value={review.final_score}
                                            />
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                    flexWrap: 'wrap',
                                                }}
                                            >
                                                <ReviewStatusBadge
                                                    status={review.status}
                                                />
                                                {review.is_legacy && (
                                                    <span
                                                        title="Diselesaikan sebelum kalibrasi diwajibkan. Nilainya tidak dipakai untuk insentif, attrition, maupun laporan."
                                                        style={{
                                                            fontSize: 11,
                                                            fontWeight: 600,
                                                            color: C.red,
                                                            border: `1px solid ${C.red}`,
                                                            borderRadius: 6,
                                                            padding: '2px 7px',
                                                            whiteSpace:
                                                                'nowrap',
                                                        }}
                                                    >
                                                        Belum terkalibrasi
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    gap: 6,
                                                    flexWrap: 'wrap',
                                                    justifyContent: 'flex-end',
                                                }}
                                            >
                                                {can.update &&
                                                    review.status ===
                                                        'manager_review' &&
                                                    review.cycle_status ===
                                                        'active' && (
                                                        <ActionBtn
                                                            icon="star"
                                                            label="Nilai"
                                                            variant="warning"
                                                            title="Input Nilai"
                                                            onClick={() =>
                                                                openScore(
                                                                    review,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                {can.update && (
                                                    <ActionBtn
                                                        icon="pencil"
                                                        label="Ubah"
                                                        variant="success"
                                                        onClick={() =>
                                                            router.visit(
                                                                PerformanceController.edit(
                                                                    review.route_key,
                                                                ).url,
                                                            )
                                                        }
                                                    />
                                                )}
                                                {can.archive &&
                                                    review.status !==
                                                        'completed' && (
                                                        <ActionBtn
                                                            icon="trash-2"
                                                            label="Hapus"
                                                            variant="danger"
                                                            onClick={() =>
                                                                setConfirm(
                                                                    review,
                                                                )
                                                            }
                                                        />
                                                    )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Performance cycles table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Siklus Penilaian
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 720,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Nama Siklus</th>
                                    <th style={thCell}>Mulai</th>
                                    <th style={thCell}>Selesai</th>
                                    <th style={thCell}>Penilaian</th>
                                    <th style={thCell}>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {cycles.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={5}
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
                                                    name="calendar-clock"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada siklus.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {cycles.map((cycle) => (
                                    <tr
                                        key={cycle.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {cycle.name}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {cycle.period_start ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {cycle.period_end ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {cycle.reviews_count}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 8,
                                            }}
                                        >
                                            <CycleStatusBadge
                                                status={cycle.status}
                                            />
                                            {can.update && (
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Ubah siklus"
                                                    title="Ubah siklus"
                                                    onClick={() =>
                                                        openCycleEdit(cycle)
                                                    }
                                                />
                                            )}
                                            {nextCycleStatus(cycle.status) &&
                                                can.update &&
                                                // Reopening a closed cycle is
                                                // the elevated move; the other
                                                // transitions are not.
                                                (cycle.status !== 'closed' ||
                                                    can.approve) && (
                                                    <ActionBtn
                                                        icon="arrow-right"
                                                        label={cycleStatusActionLabel(
                                                            cycle.status,
                                                        )}
                                                        title={cycleStatusActionLabel(
                                                            cycle.status,
                                                        )}
                                                        onClick={() =>
                                                            advanceCycleStatus(
                                                                cycle,
                                                            )
                                                        }
                                                    />
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Add / edit cycle modal */}
            {cycleModalOpen && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 80,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 20,
                    }}
                >
                    <div
                        onClick={closeCycleModal}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitCycle}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 460,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            {editingCycle ? 'Ubah Siklus' : 'Tambah Siklus'}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            {editingCycle
                                ? 'Ubah nama, periode, atau deskripsi siklus. Statusnya diatur lewat tombol lanjut status di tabel.'
                                : 'Siklus baru dibuat sebagai draf, lalu diaktifkan lewat tombol lanjut status di tabel.'}
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Nama Siklus{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={cycleForm.data.name}
                                    onChange={(event) =>
                                        cycleForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Penilaian Q1 2026"
                                    style={withError(
                                        inputStyle,
                                        !!cycleForm.errors.name,
                                    )}
                                />
                                <FieldError message={cycleForm.errors.name} />
                            </div>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 14,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Mulai{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <DatePicker
                                        value={cycleForm.data.period_start}
                                        onChange={(nextValue) =>
                                            cycleForm.setData(
                                                'period_start',
                                                nextValue,
                                            )
                                        }
                                        placeholder="Pilih tanggal"
                                        hasError={
                                            !!cycleForm.errors.period_start
                                        }
                                        width="100%"
                                    />
                                    <FieldError
                                        message={cycleForm.errors.period_start}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Selesai{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <DatePicker
                                        value={cycleForm.data.period_end}
                                        onChange={(nextValue) =>
                                            cycleForm.setData(
                                                'period_end',
                                                nextValue,
                                            )
                                        }
                                        placeholder="Pilih tanggal"
                                        hasError={!!cycleForm.errors.period_end}
                                        width="100%"
                                    />
                                    <FieldError
                                        message={cycleForm.errors.period_end}
                                    />
                                </div>
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Deskripsi</label>
                                <textarea
                                    value={cycleForm.data.description}
                                    onChange={(event) =>
                                        cycleForm.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Deskripsi siklus (opsional)"
                                    style={withError(
                                        textareaStyle,
                                        !!cycleForm.errors.description,
                                    )}
                                />
                                <FieldError
                                    message={cycleForm.errors.description}
                                />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeCycleModal}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon name="x" size={16} />
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={cycleForm.processing}
                                style={{
                                    ...btnSave,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: cycleForm.processing ? 0.7 : 1,
                                    cursor: cycleForm.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Submit score modal */}
            {scoreReview && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        zIndex: 80,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 20,
                    }}
                >
                    <div
                        onClick={closeScoreModal}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitScore}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 460,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Input Nilai
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Penilaian untuk{' '}
                            <strong style={{ color: C.text }}>
                                {scoreReview.employee ?? '—'}
                            </strong>
                            .
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                }}
                            >
                                {scoreReview.self_score !== null && (
                                    <>
                                        Skor mandiri: {scoreReview.self_score}
                                        .{' '}
                                    </>
                                )}
                                Mengirim skor memindahkan status ke Kalibrasi.
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Skor Atasan
                                </label>
                                <input
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.01"
                                    value={scoreForm.data.manager_score}
                                    onChange={(event) =>
                                        scoreForm.setData(
                                            'manager_score',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0 - 100"
                                    style={withError(
                                        inputStyle,
                                        !!scoreForm.errors.manager_score,
                                    )}
                                />
                                <FieldError
                                    message={scoreForm.errors.manager_score}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Tanggal Penilaian
                                </label>
                                <DatePicker
                                    value={scoreForm.data.review_date}
                                    onChange={(nextValue) =>
                                        scoreForm.setData(
                                            'review_date',
                                            nextValue,
                                        )
                                    }
                                    placeholder="Pilih tanggal"
                                    hasError={!!scoreForm.errors.review_date}
                                    width="100%"
                                />
                                <FieldError
                                    message={scoreForm.errors.review_date}
                                />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeScoreModal}
                                style={{
                                    ...btnOut,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon name="x" size={16} />
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={scoreForm.processing}
                                style={{
                                    ...btnSave,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: scoreForm.processing ? 0.7 : 1,
                                    cursor: scoreForm.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan Nilai
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete review modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus penilaian?"
                    body={
                        <>
                            Penilaian kinerja untuk{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.employee ?? 'karyawan ini'}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteReview}
                />
            )}
        </>
    );
}
