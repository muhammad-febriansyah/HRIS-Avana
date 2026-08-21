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
} from './types';
import type {
    CycleOption,
    EmployeeOption,
    FeedbackFormData,
    FeedbackRow,
    FlashProps,
    KeyResultOption,
    KpiIndicatorOption,
    KpiItemKeyResultFormData,
    KpiItemManualFormData,
    KpiItemRow,
    ReopenFormData,
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
    notes: string | null;
    review_date: string | null;
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
    can: { approve: boolean };
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

export default function KinerjaEdit({
    review,
    feedbacks,
    feedbackTypes,
    employees,
    cycleOptions,
    kpiItems,
    kpiIndicatorOptions,
    keyResultOptions,
    can,
}: KinerjaEditProps) {
    const { flash } = usePage<FlashProps>().props;
    const isCompleted = review.status === 'completed';

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

    const calibrateForm = useForm({
        calibrated_score:
            review.final_score !== null ? String(review.final_score) : '',
        notes: review.notes ?? '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const submitCalibrate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        calibrateForm.post(PerformanceController.calibrate(review.route_key).url, {
            preserveScroll: true,
        });
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(PerformanceController.update(review.route_key));
    };

    const submitFeedback = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        feedbackForm.submit(PerformanceController.storeFeedback(review.route_key), {
            preserveScroll: true,
            onSuccess: () => {
                feedbackForm.reset();
                feedbackForm.clearErrors();
            },
        });
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
                        {can.approve && (
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

                <KinerjaForm
                    form={form}
                    employees={employees}
                    cycleOptions={cycleOptions}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={PerformanceController.index().url}
                    onSubmit={handleSubmit}
                />

                {/* Skor atasan */}
                <div style={{ ...card, marginTop: 24 }}>
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
                                : 'Belum ada item KPI pada penilaian ini; skor dapat diisi manual.'}
                            {' '}Mengirim skor memindahkan status ke Kalibrasi.
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
                                disabled={scoreForm.processing || isCompleted}
                                style={{
                                    ...btnP,
                                    height: 42,
                                    justifyContent: 'center',
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

                {/* Item KPI */}
                <div style={{ ...card, marginTop: 24 }}>
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
                            Bobot terpakai: {totalWeight.toFixed(1)}% / 100%
                            {' '}(sisa {remainingWeight.toFixed(1)}%)
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
                            <div
                                key={item.id}
                                style={{
                                    padding: '14px 0',
                                    borderBottom: `1px solid ${C.line}`,
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
                                            {item.source === 'key_result'
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
                                            item.target_value !== null &&
                                            ` · Target ${item.target_value}${item.actual_value !== null ? ` / Aktual ${item.actual_value}` : ''}`}
                                    </div>
                                </div>
                                {!isCompleted && (
                                    <ActionBtn
                                        icon="trash-2"
                                        label="Hapus"
                                        variant="danger"
                                        title="Hapus item KPI"
                                        onClick={() => deleteKpiItem(item)}
                                    />
                                )}
                            </div>
                        ))}
                    </div>

                    {!isCompleted && remainingWeight > 0 && (
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
                                            ? btnOutSmallActive
                                            : btnOutSmall
                                    }
                                >
                                    Indikator Manual
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setKpiSource('key_result')}
                                    style={
                                        kpiSource === 'key_result'
                                            ? btnOutSmallActive
                                            : btnOutSmall
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
                                                manualKpiForm.data.target_value
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
                                                manualKpiForm.data.actual_value
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
                                            value={manualKpiForm.data.weight}
                                            onChange={(event) =>
                                                manualKpiForm.setData(
                                                    'weight',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                inputStyle,
                                                !!manualKpiForm.errors.weight,
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
                                        disabled={manualKpiForm.processing}
                                        style={{
                                            ...btnP,
                                            height: 42,
                                            justifyContent: 'center',
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
                                                keyResultKpiForm.errors.weight
                                            }
                                        />
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={
                                            keyResultKpiForm.processing
                                        }
                                        style={{
                                            ...btnP,
                                            height: 42,
                                            justifyContent: 'center',
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

                {/* Kalibrasi & finalisasi (BR-19) */}
                <div style={{ ...card, marginTop: 24 }}>
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
                                Tetapkan nilai akhir hasil kalibrasi tim untuk
                                menjaga objektivitas; penilaian ditandai
                                selesai.
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
                                    value={calibrateForm.data.calibrated_score}
                                    onChange={(event) =>
                                        calibrateForm.setData(
                                            'calibrated_score',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0 - 100"
                                    style={withError(
                                        inputStyle,
                                        !!calibrateForm.errors.calibrated_score,
                                    )}
                                />
                                <FieldError
                                    message={
                                        calibrateForm.errors.calibrated_score
                                    }
                                />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Catatan Kalibrasi
                                </label>
                                <textarea
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
                                disabled={
                                    calibrateForm.processing ||
                                    review.status !== 'calibration'
                                }
                                style={{
                                    ...btnSave,
                                    height: 42,
                                    justifyContent: 'center',
                                    opacity:
                                        calibrateForm.processing ||
                                        review.status !== 'calibration'
                                            ? 0.7
                                            : 1,
                                    cursor:
                                        calibrateForm.processing ||
                                        review.status !== 'calibration'
                                            ? 'not-allowed'
                                            : 'pointer',
                                }}
                            >
                                <AIcon name="scale" size={16} color="#fff" />
                                Kalibrasi &amp; Selesaikan
                            </button>
                        </div>
                    </form>
                </div>

                {/* 360 feedback */}
                <div style={{ ...card, marginTop: 24 }}>
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
                                            {feedback.reviewer_name ?? 'Anonim'}
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
                                        onClick={() => deleteFeedback(feedback)}
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
                                <label style={fieldLabelStyle}>Penilai</label>
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
                                    message={feedbackForm.errors.reviewer_id}
                                />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>Rating</label>
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
                            <FieldError message={feedbackForm.errors.comment} />
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                            }}
                        >
                            <button
                                type="submit"
                                disabled={feedbackForm.processing || isCompleted}
                                style={{
                                    ...btnP,
                                    height: 42,
                                    justifyContent: 'center',
                                    opacity: feedbackForm.processing ? 0.7 : 1,
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
            </div>
        </>
    );
}
