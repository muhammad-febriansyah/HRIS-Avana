import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, btnProcess, C, card } from '@/lib/avana';
import {
    EmptyState,
    Field,
    formatDate,
    inputStyle,
    PageHeader,
    PageShell,
    Panel,
    Pill,
    StatCard,
    textareaStyle,
    withError,
} from './components';

interface Feedback {
    id: number;
    type: string | null;
    rating: number | null;
    comment: string | null;
}

interface Review {
    id: number;
    cycle: string | null;
    period_start: string | null;
    period_end: string | null;
    self_score: number | null;
    manager_score: number | null;
    final_score: number | null;
    calibrated_score: number | null;
    effective_score: number | null;
    status: string;
    status_label: string;
    review_date: string | null;
    notes: string | null;
    can_submit_self: boolean;
    feedbacks: Feedback[];
}

interface Props {
    reviews: Review[];
    summary: {
        total: number;
        completed: number;
        awaiting_self: number;
        latest_score: number | null;
        average_score: number | null;
    };
}

type FlashProps = { flash?: { success?: string } };

/** Colour per review stage. */
const STATUS_TONE: Record<string, string> = {
    pending: C.faint,
    self_review: C.amber,
    manager_review: C.sky,
    calibration: C.primary,
    completed: C.green,
};

/** Band colour for a 0–100 score. */
function scoreTone(score: number): string {
    if (score >= 85) {
        return C.green;
    }

    if (score >= 70) {
        return C.primary;
    }

    if (score >= 55) {
        return C.amber;
    }

    return C.red;
}

export default function SayaKinerja({ reviews, summary }: Props) {
    const { flash } = usePage<FlashProps>().props;
    const [openId, setOpenId] = useState<number | null>(null);

    const form = useForm({ self_score: '', notes: '' });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const openSelfReview = (review: Review) => {
        form.clearErrors();
        form.setData({
            self_score:
                review.self_score !== null ? String(review.self_score) : '',
            notes: review.notes ?? '',
        });
        setOpenId(review.id);
    };

    const submit = (event: FormEvent, review: Review) => {
        event.preventDefault();
        form.post(`/avana/saya/kinerja/${review.id}/nilai-mandiri`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpenId(null);
                form.reset();
            },
        });
    };

    return (
        <>
            <Head title="Kinerja Saya" />
            <PageShell>
                <PageHeader
                    title="Kinerja Saya"
                    subtitle="Hasil penilaian kinerjamu per siklus, dan penilaian mandiri yang perlu kamu isi."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(210px, 1fr))',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <StatCard
                        label="Nilai Terbaru"
                        value={
                            summary.latest_score !== null
                                ? summary.latest_score.toLocaleString('id-ID')
                                : '—'
                        }
                        unit={summary.latest_score !== null ? '/ 100' : ''}
                        icon="star"
                        tone={
                            summary.latest_score !== null
                                ? scoreTone(summary.latest_score)
                                : C.muted
                        }
                    />
                    <StatCard
                        label="Rata-rata"
                        value={
                            summary.average_score !== null
                                ? summary.average_score.toLocaleString('id-ID')
                                : '—'
                        }
                        unit={summary.average_score !== null ? '/ 100' : ''}
                        icon="trending-up"
                        tone={C.primary}
                    />
                    <StatCard
                        label="Siklus Selesai"
                        value={summary.completed}
                        unit={`dari ${summary.total}`}
                        icon="circle-check"
                        tone={C.green}
                    />
                    <StatCard
                        label="Perlu Nilai Mandiri"
                        value={summary.awaiting_self}
                        unit="siklus"
                        icon="pencil"
                        tone={C.amber}
                    />
                </div>

                {reviews.length === 0 ? (
                    <Panel padded={false}>
                        <EmptyState
                            icon="target"
                            message="Belum ada penilaian kinerja untukmu."
                        />
                    </Panel>
                ) : (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        {reviews.map((review) => (
                            <div
                                key={review.id}
                                style={{ ...card, padding: '20px 22px' }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        justifyContent: 'space-between',
                                        flexWrap: 'wrap',
                                        gap: 14,
                                    }}
                                >
                                    <div>
                                        <div
                                            style={{
                                                fontSize: 15.5,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {review.cycle ?? 'Siklus Penilaian'}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: C.muted,
                                                marginTop: 3,
                                            }}
                                        >
                                            {formatDate(review.period_start)} –{' '}
                                            {formatDate(review.period_end)}
                                            {review.review_date &&
                                                ` · dinilai ${formatDate(review.review_date)}`}
                                        </div>
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 12,
                                        }}
                                    >
                                        <Pill
                                            label={review.status_label}
                                            color={
                                                STATUS_TONE[review.status] ??
                                                C.muted
                                            }
                                        />
                                        {review.effective_score !== null && (
                                            <span
                                                style={{
                                                    fontSize: 24,
                                                    fontWeight: 700,
                                                    color: scoreTone(
                                                        review.effective_score,
                                                    ),
                                                }}
                                            >
                                                {review.effective_score.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Score breakdown */}
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns:
                                            'repeat(auto-fit, minmax(140px, 1fr))',
                                        gap: 12,
                                        marginTop: 16,
                                        paddingTop: 16,
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    <ScoreCell
                                        label="Nilai Mandiri"
                                        value={review.self_score}
                                    />
                                    <ScoreCell
                                        label="Nilai Atasan"
                                        value={review.manager_score}
                                    />
                                    <ScoreCell
                                        label="Nilai Akhir"
                                        value={review.final_score}
                                    />
                                    <ScoreCell
                                        label="Setelah Kalibrasi"
                                        value={review.calibrated_score}
                                    />
                                </div>

                                {review.notes && (
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            marginTop: 14,
                                            padding: '10px 12px',
                                            borderRadius: 8,
                                            background: C.surface,
                                        }}
                                    >
                                        {review.notes}
                                    </div>
                                )}

                                {review.feedbacks.length > 0 && (
                                    <div style={{ marginTop: 16 }}>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                                letterSpacing: '.04em',
                                                marginBottom: 8,
                                            }}
                                        >
                                            Masukan ({review.feedbacks.length})
                                        </div>
                                        <div
                                            style={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 8,
                                            }}
                                        >
                                            {review.feedbacks.map(
                                                (feedback) => (
                                                    <div
                                                        key={feedback.id}
                                                        style={{
                                                            border: `1px solid ${C.border}`,
                                                            borderRadius: 9,
                                                            padding:
                                                                '11px 13px',
                                                        }}
                                                    >
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                justifyContent:
                                                                    'space-between',
                                                                gap: 10,
                                                                marginBottom: 4,
                                                            }}
                                                        >
                                                            <span
                                                                style={{
                                                                    fontSize: 11.5,
                                                                    fontWeight: 600,
                                                                    color: C.muted,
                                                                }}
                                                            >
                                                                {feedback.type ??
                                                                    'Masukan'}
                                                            </span>
                                                            {feedback.rating !==
                                                                null && (
                                                                <span
                                                                    style={{
                                                                        fontSize: 12,
                                                                        fontWeight: 700,
                                                                        color: scoreTone(
                                                                            feedback.rating,
                                                                        ),
                                                                    }}
                                                                >
                                                                    {feedback.rating.toLocaleString(
                                                                        'id-ID',
                                                                    )}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div
                                                            style={{
                                                                fontSize: 13,
                                                                color: C.text,
                                                            }}
                                                        >
                                                            {feedback.comment ??
                                                                '—'}
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Self-assessment */}
                                {review.can_submit_self &&
                                    (openId === review.id ? (
                                        <form
                                            onSubmit={(event) =>
                                                submit(event, review)
                                            }
                                            style={{
                                                marginTop: 16,
                                                paddingTop: 16,
                                                borderTop: `1px solid ${C.line}`,
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 12,
                                            }}
                                        >
                                            <Field
                                                label="Nilai Mandiri (0–100)"
                                                required
                                                error={form.errors.self_score}
                                            >
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    placeholder="cth. 85"
                                                    value={form.data.self_score}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'self_score',
                                                            event.target.value,
                                                        )
                                                    }
                                                    style={withError(
                                                        inputStyle,
                                                        !!form.errors
                                                            .self_score,
                                                    )}
                                                />
                                            </Field>
                                            <Field
                                                label="Catatan"
                                                error={form.errors.notes}
                                                hint="Setelah dikirim, penilaian diteruskan ke atasanmu."
                                            >
                                                <textarea
                                                    value={form.data.notes}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'notes',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Pencapaian utama, kendala, rencana perbaikan…"
                                                    style={withError(
                                                        textareaStyle,
                                                        !!form.errors.notes,
                                                    )}
                                                />
                                            </Field>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 10,
                                                }}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setOpenId(null)
                                                    }
                                                    style={{
                                                        ...btnOut,
                                                        height: 42,
                                                    }}
                                                >
                                                    <AIcon
                                                        name="x"
                                                        size={16}
                                                        color={C.text}
                                                    />
                                                    Batal
                                                </button>
                                                <button
                                                    type="submit"
                                                    disabled={form.processing}
                                                    style={{
                                                        ...btnProcess,
                                                        height: 42,
                                                        opacity: form.processing
                                                            ? 0.7
                                                            : 1,
                                                    }}
                                                >
                                                    <AIcon
                                                        name="send"
                                                        size={16}
                                                        color="#fff"
                                                    />
                                                    Kirim Penilaian Mandiri
                                                </button>
                                            </div>
                                        </form>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                openSelfReview(review)
                                            }
                                            style={{
                                                ...btnP,
                                                height: 42,
                                                marginTop: 16,
                                            }}
                                        >
                                            <AIcon
                                                name="pencil"
                                                size={16}
                                                color="#fff"
                                            />
                                            Isi Penilaian Mandiri
                                        </button>
                                    ))}
                            </div>
                        ))}
                    </div>
                )}
            </PageShell>
        </>
    );
}

function ScoreCell({ label, value }: { label: string; value: number | null }) {
    return (
        <div>
            <div style={{ fontSize: 11.5, color: C.faint }}>{label}</div>
            <div
                style={{
                    fontSize: 17,
                    fontWeight: 700,
                    color: value !== null ? C.navy : C.faint,
                    marginTop: 2,
                }}
            >
                {value !== null ? value.toLocaleString('id-ID') : '—'}
            </div>
        </div>
    );
}
