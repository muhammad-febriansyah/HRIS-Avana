import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PerformanceController from '@/actions/App/Http/Controllers/Avana/PerformanceController';
import PerformanceKpiItemController from '@/actions/App/Http/Controllers/Avana/PerformanceKpiItemController';
import { SearchableSelect } from '@/components/searchable-select';
import { ActionBtn, AIcon, btnOut, btnP, btnSave, C, card } from '@/lib/avana';
import {
    FeedbackTypeBadge,
    FieldError,
    ReviewStatusBadge,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import { KinerjaForm } from './kinerja-form';
import {
    emptyFeedbackForm,
    emptyKpiItemKeyResultForm,
    emptyKpiItemManualForm,
    emptyReopenForm,
    emptyScoreForm,
    kpiDirectionLabel,
    reviewStatusLabel,
} from './types';
import type {
    CycleOption,
    EmployeeOption,
    FeedbackFormData,
    FeedbackRow,
    FlashProps,
    KeyResultOption,
    KpiIndicatorOption,
    KpiItemEditFormData,
    KpiItemKeyResultFormData,
    KpiItemManualFormData,
    KpiItemRow,
    ReopenFormData,
    RevisionRow,
    ReviewFormData,
    ScoreFormData,
    SelectOption,
} from './types';

/** The review record as serialized by `PerformanceController@edit`. */
interface ReviewEditRecord {
    id: number;
    route_key: string;
    cycle_id: number;
    employee_id: number;
    reviewer_id: number | null;
    self_score: number | null;
    manager_score: number | null;
    final_score: number | null;
    status: string;
    scoring_mode: string;
    is_legacy: boolean;
    is_publishable: boolean;
    manager_scored_by: number | null;
    notes: string | null;
    self_notes: string | null;
    calibration_notes: string | null;
    review_date: string | null;
    cycle_status: string | null;
    period_start: string | null;
    period_end: string | null;
}

/** What the acting user may do to *this* review, resolved server-side. */
interface ReviewAbilities {
    approve: boolean;
    update: boolean;
    archive: boolean;
    edit_kpi: boolean;
    submit_score: boolean;
    calibrate: boolean;
    reopen: boolean;
}

interface KinerjaEditProps {
    review: ReviewEditRecord;
    feedbacks: FeedbackRow[];
    feedbackTypes: SelectOption[];
    employees: EmployeeOption[];
    cycleOptions: CycleOption[];
    statuses: SelectOption[];
    kpiItems: KpiItemRow[];
    kpiIndicatorOptions: KpiIndicatorOption[];
    keyResultOptions: KeyResultOption[];
    revisions: RevisionRow[];
    can: ReviewAbilities;
    /** Why calibration is unavailable to this user right now, if it is. */
    calibrateBlockedReason: string | null;
    /** False when nobody else in the tenant could sign this review off. */
    hasSecondCalibrator: boolean;
}

const sectionTitleStyle: CSSProperties = {
    fontSize: 15,
    fontWeight: 600,
    color: C.navy,
};

const btnOutSmall: CSSProperties = {
    ...btnOut,
    height: 38,
    padding: '0 14px',
    fontSize: 12.5,
    justifyContent: 'center',
};

const btnOutSmallActive: CSSProperties = {
    ...btnOutSmall,
    borderColor: C.primary,
    color: C.primary,
    background: 'rgba(47,84,201,.06)',
};

type EditTab =
    'details' | 'score' | 'kpi' | 'calibration' | 'feedback' | 'history';

const editTabs: Array<{ key: EditTab; label: string; icon: string }> = [
    { key: 'details', label: 'Detail Penilaian', icon: 'file-text' },
    { key: 'score', label: 'Skor Atasan', icon: 'gauge' },
    { key: 'kpi', label: 'Item KPI', icon: 'target' },
    { key: 'calibration', label: 'Kalibrasi', icon: 'scale' },
    { key: 'feedback', label: 'Umpan Balik 360', icon: 'message-circle' },
    { key: 'history', label: 'Riwayat', icon: 'history' },
];

const editTabColors: Record<EditTab, string> = {
    details: '#2F54C9',
    score: '#6D28D9',
    kpi: '#0F766E',
    calibration: '#166534',
    feedback: '#9A3412',
    history: '#0E1A3A',
};

export default function KinerjaEdit({
    review,
    feedbacks,
    feedbackTypes,
    employees,
    cycleOptions,
    kpiItems,
    kpiIndicatorOptions,
    keyResultOptions,
    revisions,
    can,
    calibrateBlockedReason,
    hasSecondCalibrator,
}: KinerjaEditProps) {
    const { flash } = usePage<FlashProps>().props;
    const isCompleted = review.status === 'completed';
    const [activeTab, setActiveTab] = useState<EditTab>('details');
    const activeTabIndex = editTabs.findIndex((tab) => tab.key === activeTab);

    const form = useForm<ReviewFormData>({
        cycle_id: String(review.cycle_id),
        employee_id: String(review.employee_id),
        reviewer_id: review.reviewer_id ? String(review.reviewer_id) : '',
        notes: review.notes ?? '',
        review_date: review.review_date ?? '',
    });

    const feedbackForm = useForm<FeedbackFormData>({ ...emptyFeedbackForm });

    const scoreForm = useForm<ScoreFormData>({
        ...emptyScoreForm,
        manager_score:
            review.manager_score !== null ? String(review.manager_score) : '',
        review_date: review.review_date ?? '',
    });

    const reopenForm = useForm<ReopenFormData>({ ...emptyReopenForm });

    const [kpiSource, setKpiSource] = useState<'manual' | 'key_result'>(
        'manual',
    );
    const manualKpiForm = useForm<KpiItemManualFormData>({
        ...emptyKpiItemManualForm,
    });
    const keyResultKpiForm = useForm<KpiItemKeyResultFormData>({
        ...emptyKpiItemKeyResultForm,
    });

    // Seeded from the calibration's own note, never from `review.notes`: that
    // field carries the appraisal note and, before the split, the employee's
    // self-assessment comment — which pre-filled this box with the reviewee's
    // own words as the calibrator's justification.
    const calibrateForm = useForm({
        calibrated_score:
            review.final_score !== null ? String(review.final_score) : '',
        notes: review.calibration_notes ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submitCalibrate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        calibrateForm.post(
            PerformanceController.calibrate(review.route_key).url,
            {
                preserveScroll: true,
            },
        );
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(PerformanceController.update(review.route_key));
    };

    const submitFeedback = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        feedbackForm.submit(
            PerformanceController.storeFeedback(review.route_key),
            {
                preserveScroll: true,
                onSuccess: () => {
                    feedbackForm.reset();
                    feedbackForm.clearErrors();
                },
            },
        );
    };

    const deleteFeedback = (feedback: FeedbackRow) => {
        router.delete(PerformanceController.destroyFeedback(feedback.id).url, {
            preserveScroll: true,
        });
    };

    const submitScore = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        scoreForm.submit(PerformanceController.submitScore(review.route_key), {
            preserveScroll: true,
        });
    };

    const submitReopen = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        reopenForm.post(PerformanceController.reopen(review.route_key).url, {
            preserveScroll: true,
            onSuccess: () => reopenForm.reset(),
        });
    };

    const totalWeight = kpiItems.reduce((sum, item) => sum + item.weight, 0);
    const remainingWeight = Math.max(0, 100 - totalWeight);

    const [editingKpiId, setEditingKpiId] = useState<number | null>(null);
    const kpiEditForm = useForm<KpiItemEditFormData>({
        weight: '',
        kpi_indicator_id: '',
        target_value: '',
        actual_value: '',
    });

    const openKpiEdit = (item: KpiItemRow) => {
        kpiEditForm.clearErrors();
        kpiEditForm.setData({
            weight: String(item.weight),
            kpi_indicator_id: item.kpi_indicator_id
                ? String(item.kpi_indicator_id)
                : '',
            target_value:
                item.target_value !== null ? String(item.target_value) : '',
            actual_value:
                item.actual_value !== null ? String(item.actual_value) : '',
        });
        setEditingKpiId(item.id);
    };

    const submitKpiEdit = (
        event: FormEvent<HTMLFormElement>,
        item: KpiItemRow,
    ) => {
        event.preventDefault();
        kpiEditForm.submit(PerformanceKpiItemController.update(item.id), {
            preserveScroll: true,
            onSuccess: () => setEditingKpiId(null),
        });
    };

    const submitManualKpiItem = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        manualKpiForm.submit(
            PerformanceKpiItemController.store(review.route_key),
            {
                preserveScroll: true,
                onSuccess: () => manualKpiForm.reset(),
            },
        );
    };

    const submitKeyResultKpiItem = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        keyResultKpiForm.submit(
            PerformanceKpiItemController.store(review.route_key),
            {
                preserveScroll: true,
                onSuccess: () => keyResultKpiForm.reset(),
            },
        );
    };

    const deleteKpiItem = (item: KpiItemRow) => {
        router.delete(PerformanceKpiItemController.destroy(item.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Ubah Penilaian" />
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
                        href={PerformanceController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Kinerja
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Ubah Penilaian</span>
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        margin: '0 0 24px',
                    }}
                >
                    <h1
                        style={{
                            fontSize: 24,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Ubah Penilaian Kinerja
                    </h1>
                    <ReviewStatusBadge status={review.status} />
                </div>

                {review.is_legacy && (
                    <div
                        style={{
                            ...card,
                            marginBottom: 24,
                            padding: '16px 20px',
                            background: 'rgba(220,38,38,.05)',
                            border: `1px solid ${C.red}`,
                            fontSize: 13.5,
                            color: C.text,
                        }}
                    >
                        <strong style={{ color: C.red }}>
                            Belum terkalibrasi.
                        </strong>{' '}
                        Penilaian ini diselesaikan sebelum kalibrasi diwajibkan,
                        sehingga nilainya tidak dipakai untuk insentif, prediksi
                        resign, Report Studio, maupun HAV. Jalankan{' '}
                        <code>avana:remediate-performance-legacy</code> untuk
                        mengembalikannya ke penilaian atasan dan menilai ulang.
                    </div>
                )}

                {isCompleted && (
                    <div
                        style={{
                            ...card,
                            marginBottom: 24,
                            padding: '16px 20px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: 16,
                            background: 'rgba(22,163,74,.05)',
                        }}
                    >
                        <div style={{ fontSize: 13.5, color: C.muted }}>
                            <AIcon name="lock" size={14} color={C.green} />{' '}
                            Penilaian ini sudah selesai dan terkunci. Buka
                            kembali untuk mengubah nilai atau item KPI.
                        </div>
                        {can.reopen && (
                            <form
                                onSubmit={submitReopen}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <select
                                    value={reopenForm.data.to}
                                    onChange={(event) =>
                                        reopenForm.setData(
                                            'to',
                                            event.target.value,
                                        )
                                    }
                                    style={{ ...selectStyle, width: 170 }}
                                >
                                    <option value="manager_review">
                                        Ke Penilaian Atasan
                                    </option>
                                    <option value="calibration">
                                        Ke Kalibrasi
                                    </option>
                                </select>
                                <input
                                    type="text"
                                    required
                                    value={reopenForm.data.reason}
                                    onChange={(event) =>
                                        reopenForm.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Alasan membuka kembali"
                                    style={{ ...inputStyle, width: 220 }}
                                />
                                <button
                                    type="submit"
                                    disabled={reopenForm.processing}
                                    style={{
                                        ...btnOutSmall,
                                        borderColor: '#B45309',
                                        color: '#92400E',
                                        background: '#FFFBEB',
                                        opacity: reopenForm.processing
                                            ? 0.7
                                            : 1,
                                    }}
                                >
                                    Buka Kembali
                                </button>
                            </form>
                        )}
                    </div>
                )}

                <div
                    style={{
                        ...card,
                        marginBottom: 24,
                        padding: 8,
                        overflowX: 'auto',
                    }}
                >
                    <div
                        role="tablist"
                        aria-label="Bagian formulir penilaian kinerja"
                        style={{
                            display: 'flex',
                            gap: 4,
                            minWidth: 'max-content',
                        }}
                    >
                        {editTabs.map((tab, index) => {
                            const isActive = activeTab === tab.key;
                            const accent = editTabColors[tab.key];

                            return (
                                <button
                                    key={tab.key}
                                    type="button"
                                    role="tab"
                                    aria-selected={isActive}
                                    onClick={() => setActiveTab(tab.key)}
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        height: 42,
                                        padding: '0 14px',
                                        border: 'none',
                                        borderRadius: 7,
                                        background: isActive
                                            ? accent
                                            : `${accent}12`,
                                        color: isActive ? '#fff' : C.muted,
                                        fontSize: 12.5,
                                        fontWeight: isActive ? 600 : 500,
                                        cursor: 'pointer',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            width: 20,
                                            height: 20,
                                            borderRadius: 999,
                                            background: isActive
                                                ? 'rgba(255,255,255,.18)'
                                                : `${accent}18`,
                                            color: isActive ? '#fff' : accent,
                                            fontSize: 11,
                                            fontWeight: 700,
                                        }}
                                    >
                                        {index + 1}
                                    </span>
                                    <AIcon
                                        name={tab.icon}
                                        size={15}
                                        color={isActive ? '#fff' : accent}
                                    />
                                    {tab.label}
                                    {tab.key === 'kpi' && (
                                        <span style={{ opacity: 0.75 }}>
                                            ({kpiItems.length})
                                        </span>
                                    )}
                                    {tab.key === 'feedback' && (
                                        <span style={{ opacity: 0.75 }}>
                                            ({feedbacks.length})
                                        </span>
                                    )}
                                    {tab.key === 'history' &&
                                        revisions.length > 0 && (
                                            <span style={{ opacity: 0.75 }}>
                                                ({revisions.length})
                                            </span>
                                        )}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {activeTab === 'details' && (
                    <div>
                        {can.update ? (
                            <KinerjaForm
                                form={form}
                                employees={employees}
                                cycleOptions={cycleOptions}
                                submitLabel="Simpan Perubahan"
                                submitIcon="check"
                                cancelHref={PerformanceController.index().url}
                                onSubmit={handleSubmit}
                            />
                        ) : (
                            // A calibrator holding only "Setujui" reaches this
                            // screen for the calibration form. Metadata is not
                            // theirs to change, so it is shown, not offered.
                            <ReviewSummary
                                review={review}
                                employees={employees}
                                cycleOptions={cycleOptions}
                            />
                        )}
                    </div>
                )}

                {/* Skor atasan */}
                {activeTab === 'score' && (
                    <div style={{ ...card }}>
                        <div
                            style={{
                                padding: '20px 24px',
                                borderBottom: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={sectionTitleStyle}>Skor Atasan</div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    marginTop: 4,
                                }}
                            >
                                {kpiItems.length > 0
                                    ? 'Dihitung otomatis dari bobot item KPI di bawah — tidak dapat diisi manual.'
                                    : 'Belum ada item KPI pada penilaian ini; skor dapat diisi manual.'}{' '}
                                Mengirim skor memindahkan status ke Kalibrasi.
                            </div>
                        </div>
                        <form
                            onSubmit={submitScore}
                            style={{
                                padding: '16px 24px 22px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            {!hasSecondCalibrator && !isCompleted && (
                                <div
                                    role="alert"
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: 9,
                                        padding: '11px 13px',
                                        borderRadius: 8,
                                        border: `1px solid ${C.amber}`,
                                        background: 'rgba(217,119,6,.06)',
                                        fontSize: 12.5,
                                        color: C.text,
                                        lineHeight: 1.55,
                                    }}
                                >
                                    <AIcon
                                        name="triangle-alert"
                                        size={15}
                                        color={C.amber}
                                    />
                                    Belum ada pengguna lain yang berizin
                                    mengkalibrasi. Kalibrasi wajib
                                    ditandatangani orang kedua, jadi penilaian
                                    ini akan tertahan di tahap Kalibrasi sampai
                                    izin &ldquo;Setujui&rdquo; diberikan ke
                                    peran lain di Hak Akses.
                                </div>
                            )}
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '200px 1fr',
                                    gap: 14,
                                    alignItems: 'start',
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Skor Atasan
                                    </label>
                                    <input
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        disabled={
                                            kpiItems.length > 0 || isCompleted
                                        }
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
                                    <input
                                        type="date"
                                        disabled={isCompleted}
                                        value={scoreForm.data.review_date}
                                        onChange={(event) =>
                                            scoreForm.setData(
                                                'review_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!scoreForm.errors.review_date,
                                        )}
                                    />
                                    <FieldError
                                        message={scoreForm.errors.review_date}
                                    />
                                </div>
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'flex-end',
                                }}
                            >
                                <button
                                    type="submit"
                                    disabled={
                                        scoreForm.processing ||
                                        !can.submit_score
                                    }
                                    style={{
                                        ...btnP,
                                        height: 42,
                                        justifyContent: 'center',
                                        background: editTabColors.score,
                                        opacity: scoreForm.processing ? 0.7 : 1,
                                        cursor: scoreForm.processing
                                            ? 'not-allowed'
                                            : 'pointer',
                                    }}
                                >
                                    <AIcon name="send" size={16} color="#fff" />
                                    Kirim ke Kalibrasi
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Item KPI */}
                {activeTab === 'kpi' && (
                    <div style={{ ...card }}>
                        <div
                            style={{
                                padding: '20px 24px',
                                borderBottom: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={sectionTitleStyle}>Item KPI</div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    marginTop: 4,
                                }}
                            >
                                Bobot terpakai: {totalWeight.toFixed(1)}% / 100%{' '}
                                (sisa {remainingWeight.toFixed(1)}%)
                            </div>
                        </div>

                        <div style={{ padding: '8px 24px' }}>
                            {kpiItems.length === 0 && (
                                <div
                                    style={{
                                        fontSize: 13.5,
                                        color: C.muted,
                                        padding: '20px 0',
                                        textAlign: 'center',
                                    }}
                                >
                                    Belum ada item KPI.
                                </div>
                            )}
                            {kpiItems.map((item) => (
                                <div key={item.id}>
                                    <div
                                        style={{
                                            padding: '14px 0',
                                            borderBottom:
                                                editingKpiId === item.id
                                                    ? 'none'
                                                    : `1px solid ${C.line}`,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 12,
                                        }}
                                    >
                                        <div>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {item.label}{' '}
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        fontWeight: 500,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    (
                                                    {item.source ===
                                                    'key_result'
                                                        ? 'dari Key Result'
                                                        : kpiDirectionLabel(
                                                              item.direction,
                                                          )}
                                                    )
                                                </span>
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.muted,
                                                    marginTop: 3,
                                                }}
                                            >
                                                Bobot {item.weight}% · Capaian{' '}
                                                {item.achievement_pct}%
                                                {item.source === 'manual' &&
                                                    item.target_value !==
                                                        null &&
                                                    ` · Target ${item.target_value}${item.actual_value !== null ? ` / Aktual ${item.actual_value}` : ''}`}
                                            </div>
                                        </div>
                                        {can.edit_kpi && (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 6,
                                                }}
                                            >
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Ubah"
                                                    variant="success"
                                                    title="Ubah item KPI"
                                                    onClick={() =>
                                                        openKpiEdit(item)
                                                    }
                                                />
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    title="Hapus item KPI"
                                                    onClick={() =>
                                                        deleteKpiItem(item)
                                                    }
                                                />
                                            </div>
                                        )}
                                    </div>

                                    {editingKpiId === item.id && (
                                        <form
                                            onSubmit={(event) =>
                                                submitKpiEdit(event, item)
                                            }
                                            style={{
                                                display: 'flex',
                                                flexWrap: 'wrap',
                                                alignItems: 'flex-end',
                                                gap: 12,
                                                padding: '0 0 16px',
                                                borderBottom: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <div>
                                                <label style={fieldLabelStyle}>
                                                    Bobot (%)
                                                </label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value={
                                                        kpiEditForm.data.weight
                                                    }
                                                    onChange={(event) =>
                                                        kpiEditForm.setData(
                                                            'weight',
                                                            event.target.value,
                                                        )
                                                    }
                                                    style={{
                                                        ...inputStyle,
                                                        width: 110,
                                                    }}
                                                />
                                                <FieldError
                                                    message={
                                                        kpiEditForm.errors
                                                            .weight
                                                    }
                                                />
                                            </div>

                                            {/* Target and realisation belong to manual
                                        indicators only: a key_result item reads
                                        both live from its Key Result. */}
                                            {item.source === 'manual' && (
                                                <>
                                                    <div>
                                                        <label
                                                            style={
                                                                fieldLabelStyle
                                                            }
                                                        >
                                                            Target
                                                        </label>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            value={
                                                                kpiEditForm.data
                                                                    .target_value
                                                            }
                                                            onChange={(event) =>
                                                                kpiEditForm.setData(
                                                                    'target_value',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            style={{
                                                                ...inputStyle,
                                                                width: 130,
                                                            }}
                                                        />
                                                        <FieldError
                                                            message={
                                                                kpiEditForm
                                                                    .errors
                                                                    .target_value
                                                            }
                                                        />
                                                    </div>
                                                    <div>
                                                        <label
                                                            style={
                                                                fieldLabelStyle
                                                            }
                                                        >
                                                            Realisasi
                                                        </label>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            value={
                                                                kpiEditForm.data
                                                                    .actual_value
                                                            }
                                                            onChange={(event) =>
                                                                kpiEditForm.setData(
                                                                    'actual_value',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            style={{
                                                                ...inputStyle,
                                                                width: 130,
                                                            }}
                                                        />
                                                        <FieldError
                                                            message={
                                                                kpiEditForm
                                                                    .errors
                                                                    .actual_value
                                                            }
                                                        />
                                                    </div>
                                                </>
                                            )}

                                            <button
                                                type="submit"
                                                disabled={
                                                    kpiEditForm.processing
                                                }
                                                style={{
                                                    ...btnSave,
                                                    height: 40,
                                                }}
                                            >
                                                <AIcon
                                                    name="check"
                                                    size={15}
                                                    color="#fff"
                                                />
                                                Simpan
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setEditingKpiId(null)
                                                }
                                                style={{
                                                    ...btnOut,
                                                    height: 40,
                                                    background: C.surface,
                                                }}
                                            >
                                                Batal
                                            </button>
                                        </form>
                                    )}
                                </div>
                            ))}
                        </div>

                        {can.edit_kpi && remainingWeight > 0 && (
                            <div
                                style={{
                                    padding: '16px 24px 22px',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                    }}
                                >
                                    <button
                                        type="button"
                                        onClick={() => setKpiSource('manual')}
                                        style={
                                            kpiSource === 'manual'
                                                ? {
                                                      ...btnOutSmallActive,
                                                      borderColor: '#0F766E',
                                                      color: '#0F766E',
                                                      background: '#0F766E12',
                                                  }
                                                : {
                                                      ...btnOutSmall,
                                                      borderColor: '#0F766E30',
                                                      color: '#0F766E',
                                                      background: '#0F766E08',
                                                  }
                                        }
                                    >
                                        Indikator Manual
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setKpiSource('key_result')
                                        }
                                        style={
                                            kpiSource === 'key_result'
                                                ? {
                                                      ...btnOutSmallActive,
                                                      borderColor: '#6D28D9',
                                                      color: '#6D28D9',
                                                      background: '#6D28D912',
                                                  }
                                                : {
                                                      ...btnOutSmall,
                                                      borderColor: '#6D28D930',
                                                      color: '#6D28D9',
                                                      background: '#6D28D908',
                                                  }
                                        }
                                    >
                                        Dari Key Result
                                    </button>
                                </div>

                                {kpiSource === 'manual' ? (
                                    <form
                                        onSubmit={submitManualKpiItem}
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns:
                                                '1.5fr 1fr 1fr 1fr auto',
                                            gap: 10,
                                            alignItems: 'start',
                                        }}
                                    >
                                        <div>
                                            <select
                                                value={
                                                    manualKpiForm.data
                                                        .kpi_indicator_id
                                                }
                                                onChange={(event) =>
                                                    manualKpiForm.setData(
                                                        'kpi_indicator_id',
                                                        event.target.value,
                                                    )
                                                }
                                                style={withError(
                                                    selectStyle,
                                                    !!manualKpiForm.errors
                                                        .kpi_indicator_id,
                                                )}
                                            >
                                                <option value="">
                                                    Pilih indikator
                                                </option>
                                                {kpiIndicatorOptions.map(
                                                    (indicator) => (
                                                        <option
                                                            key={indicator.id}
                                                            value={String(
                                                                indicator.id,
                                                            )}
                                                        >
                                                            {indicator.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <FieldError
                                                message={
                                                    manualKpiForm.errors
                                                        .kpi_indicator_id
                                                }
                                            />
                                        </div>
                                        <div>
                                            <input
                                                type="number"
                                                step="0.01"
                                                placeholder="Target"
                                                value={
                                                    manualKpiForm.data
                                                        .target_value
                                                }
                                                onChange={(event) =>
                                                    manualKpiForm.setData(
                                                        'target_value',
                                                        event.target.value,
                                                    )
                                                }
                                                style={withError(
                                                    inputStyle,
                                                    !!manualKpiForm.errors
                                                        .target_value,
                                                )}
                                            />
                                            <FieldError
                                                message={
                                                    manualKpiForm.errors
                                                        .target_value
                                                }
                                            />
                                        </div>
                                        <div>
                                            <input
                                                type="number"
                                                step="0.01"
                                                placeholder="Aktual"
                                                value={
                                                    manualKpiForm.data
                                                        .actual_value
                                                }
                                                onChange={(event) =>
                                                    manualKpiForm.setData(
                                                        'actual_value',
                                                        event.target.value,
                                                    )
                                                }
                                                style={inputStyle}
                                            />
                                        </div>
                                        <div>
                                            <input
                                                type="number"
                                                step="0.01"
                                                placeholder="Bobot %"
                                                value={
                                                    manualKpiForm.data.weight
                                                }
                                                onChange={(event) =>
                                                    manualKpiForm.setData(
                                                        'weight',
                                                        event.target.value,
                                                    )
                                                }
                                                style={withError(
                                                    inputStyle,
                                                    !!manualKpiForm.errors
                                                        .weight,
                                                )}
                                            />
                                            <FieldError
                                                message={
                                                    manualKpiForm.errors.weight
                                                }
                                            />
                                        </div>
                                        <button
                                            type="submit"
                                            // Icon-only, so it needs a name of
                                            // its own for screen readers and
                                            // for `getByRole` in tests.
                                            aria-label="Tambah item KPI manual"
                                            title="Tambah item KPI manual"
                                            disabled={manualKpiForm.processing}
                                            style={{
                                                ...btnP,
                                                height: 42,
                                                justifyContent: 'center',
                                                background: '#0F766E',
                                            }}
                                        >
                                            <AIcon
                                                name="plus"
                                                size={16}
                                                color="#fff"
                                            />
                                        </button>
                                    </form>
                                ) : (
                                    <form
                                        onSubmit={submitKeyResultKpiItem}
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns: '2fr 1fr auto',
                                            gap: 10,
                                            alignItems: 'start',
                                        }}
                                    >
                                        <div>
                                            <select
                                                value={
                                                    keyResultKpiForm.data
                                                        .key_result_id
                                                }
                                                onChange={(event) =>
                                                    keyResultKpiForm.setData(
                                                        'key_result_id',
                                                        event.target.value,
                                                    )
                                                }
                                                style={withError(
                                                    selectStyle,
                                                    !!keyResultKpiForm.errors
                                                        .key_result_id,
                                                )}
                                            >
                                                <option value="">
                                                    Pilih Key Result
                                                </option>
                                                {keyResultOptions.map((kr) => (
                                                    <option
                                                        key={kr.id}
                                                        value={String(kr.id)}
                                                    >
                                                        {kr.objective_title
                                                            ? `${kr.objective_title} — ${kr.title}`
                                                            : kr.title}{' '}
                                                        ({kr.progress}%)
                                                    </option>
                                                ))}
                                            </select>
                                            <FieldError
                                                message={
                                                    keyResultKpiForm.errors
                                                        .key_result_id
                                                }
                                            />
                                        </div>
                                        <div>
                                            <input
                                                type="number"
                                                step="0.01"
                                                placeholder="Bobot %"
                                                value={
                                                    keyResultKpiForm.data.weight
                                                }
                                                onChange={(event) =>
                                                    keyResultKpiForm.setData(
                                                        'weight',
                                                        event.target.value,
                                                    )
                                                }
                                                style={withError(
                                                    inputStyle,
                                                    !!keyResultKpiForm.errors
                                                        .weight,
                                                )}
                                            />
                                            <FieldError
                                                message={
                                                    keyResultKpiForm.errors
                                                        .weight
                                                }
                                            />
                                        </div>
                                        <button
                                            type="submit"
                                            aria-label="Tambah item KPI dari Key Result"
                                            title="Tambah item KPI dari Key Result"
                                            disabled={
                                                keyResultKpiForm.processing
                                            }
                                            style={{
                                                ...btnP,
                                                height: 42,
                                                justifyContent: 'center',
                                                background: '#6D28D9',
                                            }}
                                        >
                                            <AIcon
                                                name="plus"
                                                size={16}
                                                color="#fff"
                                            />
                                        </button>
                                    </form>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* Kalibrasi & finalisasi (BR-19) */}
                {activeTab === 'calibration' && (
                    <div style={{ ...card }}>
                        <div
                            style={{
                                padding: '20px 24px',
                                borderBottom: `1px solid ${C.line}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 12,
                            }}
                        >
                            <div>
                                <div style={sectionTitleStyle}>
                                    Kalibrasi &amp; Finalisasi
                                </div>
                                <div
                                    style={{
                                        fontSize: 13,
                                        color: C.muted,
                                        marginTop: 4,
                                    }}
                                >
                                    Tetapkan nilai akhir hasil kalibrasi tim
                                    untuk menjaga objektivitas; penilaian
                                    ditandai selesai.
                                </div>
                            </div>
                            {review.status === 'completed' && (
                                <span
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 5,
                                        padding: '4px 10px',
                                        borderRadius: 100,
                                        fontSize: 12,
                                        fontWeight: 600,
                                        color: C.green,
                                        background: 'rgba(22,163,74,.1)',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    <AIcon
                                        name="circle-check"
                                        size={13}
                                        color={C.green}
                                    />
                                    Terkalibrasi
                                </span>
                            )}
                        </div>
                        <form
                            onSubmit={submitCalibrate}
                            style={{
                                padding: '16px 24px 22px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            {calibrateBlockedReason !== null && (
                                <div
                                    role="alert"
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: 9,
                                        padding: '11px 13px',
                                        borderRadius: 8,
                                        border: `1px solid ${C.amber}`,
                                        background: 'rgba(217,119,6,.06)',
                                        fontSize: 12.5,
                                        color: C.text,
                                        lineHeight: 1.55,
                                    }}
                                >
                                    <AIcon
                                        name="triangle-alert"
                                        size={15}
                                        color={C.amber}
                                    />
                                    {calibrateBlockedReason}
                                </div>
                            )}

                            {review.self_notes && (
                                <div
                                    style={{
                                        padding: '11px 13px',
                                        borderRadius: 8,
                                        background: C.surface,
                                        fontSize: 12.5,
                                        color: C.muted,
                                        lineHeight: 1.55,
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            fontWeight: 600,
                                            color: C.faint,
                                            marginBottom: 4,
                                        }}
                                    >
                                        Catatan penilaian mandiri karyawan
                                    </div>
                                    {review.self_notes}
                                </div>
                            )}

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '200px 1fr',
                                    gap: 14,
                                    alignItems: 'start',
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Nilai Kalibrasi{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        disabled={!can.calibrate}
                                        value={
                                            calibrateForm.data.calibrated_score
                                        }
                                        onChange={(event) =>
                                            calibrateForm.setData(
                                                'calibrated_score',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="0 - 100"
                                        style={withError(
                                            inputStyle,
                                            !!calibrateForm.errors
                                                .calibrated_score,
                                        )}
                                    />
                                    <FieldError
                                        message={
                                            calibrateForm.errors
                                                .calibrated_score
                                        }
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Catatan Kalibrasi
                                    </label>
                                    <textarea
                                        disabled={!can.calibrate}
                                        value={calibrateForm.data.notes}
                                        onChange={(event) =>
                                            calibrateForm.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Alasan penyesuaian nilai (opsional)"
                                        style={withError(
                                            textareaStyle,
                                            !!calibrateForm.errors.notes,
                                        )}
                                    />
                                    <FieldError
                                        message={calibrateForm.errors.notes}
                                    />
                                </div>
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'flex-end',
                                }}
                            >
                                <button
                                    type="submit"
                                    // Mirrors every rule the endpoint applies,
                                    // so the button is never live for someone
                                    // the server is certain to turn away.
                                    disabled={
                                        calibrateForm.processing ||
                                        !can.calibrate
                                    }
                                    title={calibrateBlockedReason ?? undefined}
                                    style={{
                                        ...btnSave,
                                        height: 42,
                                        justifyContent: 'center',
                                        opacity:
                                            calibrateForm.processing ||
                                            !can.calibrate
                                                ? 0.7
                                                : 1,
                                        cursor:
                                            calibrateForm.processing ||
                                            !can.calibrate
                                                ? 'not-allowed'
                                                : 'pointer',
                                    }}
                                >
                                    <AIcon
                                        name="scale"
                                        size={16}
                                        color="#fff"
                                    />
                                    Kalibrasi &amp; Selesaikan
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* 360 feedback */}
                {activeTab === 'feedback' && (
                    <div style={{ ...card }}>
                        <div
                            style={{
                                padding: '20px 24px',
                                borderBottom: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={sectionTitleStyle}>Umpan Balik 360</div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    marginTop: 4,
                                }}
                            >
                                Kumpulkan umpan balik dari rekan, atasan, dan
                                bawahan.
                            </div>
                        </div>

                        <div style={{ padding: '8px 24px' }}>
                            {feedbacks.length === 0 && (
                                <div
                                    style={{
                                        fontSize: 13.5,
                                        color: C.muted,
                                        padding: '20px 0',
                                        textAlign: 'center',
                                    }}
                                >
                                    Belum ada umpan balik.
                                </div>
                            )}
                            {feedbacks.map((feedback) => (
                                <div
                                    key={feedback.id}
                                    style={{
                                        padding: '14px 0',
                                        borderBottom: `1px solid ${C.line}`,
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 12,
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                                flexWrap: 'wrap',
                                            }}
                                        >
                                            <FeedbackTypeBadge
                                                type={feedback.type}
                                            />
                                            <span
                                                style={{
                                                    fontSize: 13,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {feedback.reviewer_name ??
                                                    'Anonim'}
                                            </span>
                                            {feedback.rating !== null && (
                                                <span
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 4,
                                                        fontSize: 12.5,
                                                        fontWeight: 600,
                                                        color: C.amber,
                                                    }}
                                                >
                                                    <AIcon
                                                        name="star"
                                                        size={13}
                                                        color={C.amber}
                                                    />
                                                    {feedback.rating}
                                                </span>
                                            )}
                                        </div>
                                        <ActionBtn
                                            icon="trash-2"
                                            label="Hapus"
                                            variant="danger"
                                            title="Hapus umpan balik"
                                            onClick={() =>
                                                deleteFeedback(feedback)
                                            }
                                        />
                                    </div>
                                    {feedback.comment && (
                                        <div
                                            style={{
                                                fontSize: 13,
                                                color: C.muted,
                                                marginTop: 8,
                                                lineHeight: 1.55,
                                            }}
                                        >
                                            {feedback.comment}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>

                        {/* Add feedback */}
                        <form
                            onSubmit={submitFeedback}
                            style={{
                                padding: '16px 24px 22px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr 1fr',
                                    gap: 14,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Jenis{' '}
                                        <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <select
                                        value={feedbackForm.data.type}
                                        onChange={(event) =>
                                            feedbackForm.setData(
                                                'type',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            selectStyle,
                                            !!feedbackForm.errors.type,
                                        )}
                                    >
                                        {feedbackTypes.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError
                                        message={feedbackForm.errors.type}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Penilai
                                    </label>
                                    <SearchableSelect
                                        value={feedbackForm.data.reviewer_id}
                                        onChange={(value) =>
                                            feedbackForm.setData(
                                                'reviewer_id',
                                                value,
                                            )
                                        }
                                        options={employees.map((employee) => ({
                                            value: String(employee.id),
                                            label: employee.employee_number
                                                ? `${employee.name} (${employee.employee_number})`
                                                : employee.name,
                                        }))}
                                        placeholder="Anonim"
                                        searchPlaceholder="Cari nama karyawan…"
                                        allowClear
                                        style={withError(
                                            selectStyle,
                                            !!feedbackForm.errors.reviewer_id,
                                        )}
                                    />
                                    <FieldError
                                        message={
                                            feedbackForm.errors.reviewer_id
                                        }
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Rating
                                    </label>
                                    <input
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        value={feedbackForm.data.rating}
                                        onChange={(event) =>
                                            feedbackForm.setData(
                                                'rating',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="0 - 100"
                                        style={withError(
                                            inputStyle,
                                            !!feedbackForm.errors.rating,
                                        )}
                                    />
                                    <FieldError
                                        message={feedbackForm.errors.rating}
                                    />
                                </div>
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>Komentar</label>
                                <textarea
                                    value={feedbackForm.data.comment}
                                    onChange={(event) =>
                                        feedbackForm.setData(
                                            'comment',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Tulis umpan balik (opsional)"
                                    style={withError(
                                        textareaStyle,
                                        !!feedbackForm.errors.comment,
                                    )}
                                />
                                <FieldError
                                    message={feedbackForm.errors.comment}
                                />
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'flex-end',
                                }}
                            >
                                <button
                                    type="submit"
                                    disabled={
                                        feedbackForm.processing ||
                                        isCompleted ||
                                        review.cycle_status !== 'active'
                                    }
                                    style={{
                                        ...btnP,
                                        height: 42,
                                        justifyContent: 'center',
                                        background: editTabColors.feedback,
                                        opacity: feedbackForm.processing
                                            ? 0.7
                                            : 1,
                                        cursor: feedbackForm.processing
                                            ? 'not-allowed'
                                            : 'pointer',
                                    }}
                                >
                                    <AIcon name="plus" size={16} color="#fff" />
                                    Tambah Umpan Balik
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Revision history — what a reopen superseded, and why. The
                    live row no longer carries these numbers, so this panel is
                    the only place the previous rating is visible. */}
                {activeTab === 'history' && (
                    <div style={{ ...card, marginTop: 24, padding: 0 }}>
                        <div
                            style={{
                                padding: '18px 24px 12px',
                                borderBottom: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={sectionTitleStyle}>
                                Riwayat Pembukaan Kembali ({revisions.length})
                            </div>
                            <div
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    marginTop: 4,
                                }}
                            >
                                Nilai yang digantikan setiap kali penilaian ini
                                dibuka kembali.
                            </div>
                        </div>

                        <div style={{ padding: '8px 24px 20px' }}>
                            {revisions.length === 0 && (
                                <div
                                    style={{
                                        padding: '20px 0',
                                        color: C.muted,
                                        fontSize: 13.5,
                                        textAlign: 'center',
                                    }}
                                >
                                    Belum ada riwayat pembukaan kembali.
                                </div>
                            )}
                            {revisions.map((revision: RevisionRow) => (
                                <div
                                    key={revision.id}
                                    style={{
                                        padding: '14px 0',
                                        borderBottom: `1px solid ${C.line}`,
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexWrap: 'wrap',
                                            gap: 10,
                                            alignItems: 'center',
                                            fontSize: 13,
                                            color: C.navy,
                                            fontWeight: 600,
                                        }}
                                    >
                                        <span>
                                            {reviewStatusLabel(
                                                revision.from_status,
                                            )}{' '}
                                            ke{' '}
                                            {reviewStatusLabel(
                                                revision.to_status,
                                            )}
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 12,
                                                fontWeight: 500,
                                                color: C.faint,
                                            }}
                                        >
                                            {revision.created_at ?? '—'}
                                            {revision.reopened_by
                                                ? ` · oleh ${revision.reopened_by}`
                                                : ''}
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginTop: 4,
                                        }}
                                    >
                                        Nilai sebelumnya — mandiri{' '}
                                        {revision.self_score ?? '—'} · atasan{' '}
                                        {revision.manager_score ?? '—'} ·
                                        kalibrasi{' '}
                                        {revision.calibrated_score ?? '—'} ·
                                        akhir {revision.final_score ?? '—'}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            color: C.text,
                                            marginTop: 6,
                                        }}
                                    >
                                        {revision.reason}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 12,
                        marginTop: 20,
                    }}
                >
                    <button
                        type="button"
                        onClick={() =>
                            setActiveTab(editTabs[activeTabIndex - 1].key)
                        }
                        disabled={activeTabIndex === 0}
                        style={{
                            ...btnOut,
                            height: 42,
                            justifyContent: 'center',
                            borderColor: `${editTabColors[editTabs[Math.max(0, activeTabIndex - 1)].key]}40`,
                            background: `${editTabColors[activeTab]}10`,
                            color: editTabColors[activeTab],
                            opacity: activeTabIndex === 0 ? 0.45 : 1,
                            cursor:
                                activeTabIndex === 0
                                    ? 'not-allowed'
                                    : 'pointer',
                        }}
                    >
                        <AIcon name="arrow-left" size={15} />
                        Sebelumnya
                    </button>
                    <span style={{ fontSize: 12.5, color: C.faint }}>
                        Langkah {activeTabIndex + 1} dari {editTabs.length}
                    </span>
                    <button
                        type="button"
                        onClick={() =>
                            setActiveTab(editTabs[activeTabIndex + 1].key)
                        }
                        disabled={activeTabIndex === editTabs.length - 1}
                        style={{
                            ...btnP,
                            height: 42,
                            justifyContent: 'center',
                            background: editTabColors[activeTab],
                            opacity:
                                activeTabIndex === editTabs.length - 1
                                    ? 0.45
                                    : 1,
                            cursor:
                                activeTabIndex === editTabs.length - 1
                                    ? 'not-allowed'
                                    : 'pointer',
                        }}
                    >
                        Lanjutkan
                        <AIcon name="arrow-right" size={15} color="#fff" />
                    </button>
                </div>
            </div>
        </>
    );
}

/**
 * Read-only view of the review's metadata, for a user who may calibrate but
 * not edit — `approve` opens this screen, `update` is what unlocks the form.
 */
function ReviewSummary({
    review,
    employees,
    cycleOptions,
}: {
    review: ReviewEditRecord;
    employees: EmployeeOption[];
    cycleOptions: CycleOption[];
}) {
    const nameOf = (id: number | null): string =>
        employees.find((employee) => employee.id === id)?.name ?? '—';

    const rows: Array<[string, string]> = [
        [
            'Siklus',
            cycleOptions.find((cycle) => cycle.id === review.cycle_id)?.name ??
                '—',
        ],
        ['Karyawan', nameOf(review.employee_id)],
        ['Penilai', nameOf(review.reviewer_id)],
        ['Tanggal Penilaian', review.review_date ?? '—'],
        ['Catatan Penilaian', review.notes ?? '—'],
        ['Catatan Penilaian Mandiri', review.self_notes ?? '—'],
    ];

    return (
        <div style={{ ...card }}>
            <div
                style={{
                    padding: '20px 24px',
                    borderBottom: `1px solid ${C.line}`,
                }}
            >
                <div style={sectionTitleStyle}>Detail Penilaian</div>
                <div style={{ fontSize: 13, color: C.muted, marginTop: 4 }}>
                    Peran Anda dapat mengkalibrasi penilaian ini, tetapi tidak
                    mengubah datanya.
                </div>
            </div>
            <div style={{ padding: '8px 24px 20px' }}>
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '200px 1fr',
                            gap: 14,
                            padding: '12px 0',
                            borderBottom: `1px solid ${C.line}`,
                            fontSize: 13.5,
                        }}
                    >
                        <span style={{ color: C.faint }}>{label}</span>
                        <span style={{ color: C.text }}>{value}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
