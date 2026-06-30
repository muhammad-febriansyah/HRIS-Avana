import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import PerformanceController from '@/actions/App/Http/Controllers/Avana/PerformanceController';
import { AIcon, btnP, C, card } from '@/lib/avana';
import {
    FeedbackTypeBadge,
    FieldError,
    fieldLabelStyle,
    iconBtn,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import { KinerjaForm } from './kinerja-form';
import { emptyFeedbackForm } from './types';
import type {
    CycleOption,
    EmployeeOption,
    FeedbackFormData,
    FeedbackRow,
    FlashProps,
    ReviewFormData,
    SelectOption,
} from './types';

/** The review record as serialized by `PerformanceController@edit`. */
interface ReviewEditRecord {
    id: number;
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
}

const sectionTitleStyle: CSSProperties = {
    fontSize: 15,
    fontWeight: 600,
    color: C.navy,
};

export default function KinerjaEdit({
    review,
    feedbacks,
    feedbackTypes,
    employees,
    cycleOptions,
    statuses,
}: KinerjaEditProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<ReviewFormData>({
        cycle_id: String(review.cycle_id),
        employee_id: String(review.employee_id),
        reviewer_id: review.reviewer_id ? String(review.reviewer_id) : '',
        self_score: review.self_score !== null ? String(review.self_score) : '',
        manager_score:
            review.manager_score !== null ? String(review.manager_score) : '',
        final_score:
            review.final_score !== null ? String(review.final_score) : '',
        status: review.status,
        notes: review.notes ?? '',
        review_date: review.review_date ?? '',
    });

    const feedbackForm = useForm<FeedbackFormData>({ ...emptyFeedbackForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(PerformanceController.update(review.id));
    };

    const submitFeedback = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        feedbackForm.submit(PerformanceController.storeFeedback(review.id), {
            preserveScroll: true,
            onSuccess: () => {
                feedbackForm.reset();
                feedbackForm.clearErrors();
            },
        });
    };

    const deleteFeedback = (feedback: FeedbackRow) => {
        router.delete(
            PerformanceController.destroyFeedback(feedback.id).url,
            { preserveScroll: true },
        );
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
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 24px',
                        letterSpacing: '-.01em',
                    }}
                >
                    Ubah Penilaian Kinerja
                </h1>

                <KinerjaForm
                    form={form}
                    employees={employees}
                    cycleOptions={cycleOptions}
                    statuses={statuses}
                    submitLabel="Simpan Perubahan"
                    submitIcon="check"
                    cancelHref={PerformanceController.index().url}
                    onSubmit={handleSubmit}
                />

                {/* 360 feedback */}
                <div style={{ ...card, maxWidth: 640, marginTop: 24 }}>
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
                                    <button
                                        type="button"
                                        title="Hapus umpan balik"
                                        onClick={() =>
                                            deleteFeedback(feedback)
                                        }
                                        style={iconBtn}
                                    >
                                        <AIcon
                                            name="trash-2"
                                            size={15}
                                            color={C.red}
                                        />
                                    </button>
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
                                <select
                                    value={feedbackForm.data.reviewer_id}
                                    onChange={(event) =>
                                        feedbackForm.setData(
                                            'reviewer_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!feedbackForm.errors.reviewer_id,
                                    )}
                                >
                                    <option value="">Anonim</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.name}
                                            {employee.employee_number
                                                ? ` (${employee.employee_number})`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
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
                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <button
                                type="submit"
                                disabled={feedbackForm.processing}
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
