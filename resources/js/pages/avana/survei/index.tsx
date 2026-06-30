import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import SurveyController from '@/actions/App/Http/Controllers/Avana/SurveyController';
import { AIcon, ActionBtn, btnOut, btnP, C, card, statusBadge } from '@/lib/avana';

/* ---------- types (mirror SurveyController payloads) ---------- */

type QuestionType = 'rating' | 'text' | 'choice';

interface SurveyQuestion {
    id: number;
    question: string;
    type: QuestionType;
    options: string[] | null;
    response_count: number;
    avg_rating: number | null;
}

interface SurveyItem {
    id: number;
    title: string;
    description: string | null;
    status: 'draft' | 'active' | 'closed';
    is_anonymous: boolean;
    responses_count: number;
    questions: SurveyQuestion[];
}

interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string | null;
}

interface SurveiIndexProps {
    surveys: SurveyItem[];
    employees: EmployeeOption[];
    kpis: {
        active_surveys: number;
        total_responses: number;
        total_surveys: number;
    };
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

const STATUS_LABELS: Record<string, string> = { draft: 'Draft', active: 'Aktif', closed: 'Ditutup' };
const STATUS_BADGE_KEY: Record<string, string> = { draft: 'Draft', active: 'Disetujui', closed: 'Terkunci' };
const TYPE_LABELS: Record<QuestionType, string> = { rating: 'Rating (1-5)', text: 'Teks', choice: 'Pilihan' };

const fieldLabelStyle: CSSProperties = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7, color: C.text };
const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};
const selectStyle: CSSProperties = { ...inputStyle, color: C.muted, cursor: 'pointer' };
const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
    resize: 'vertical',
    minHeight: 64,
};

function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError ? { ...base, border: `1px solid ${C.red}`, boxShadow: '0 0 0 3px rgba(220,38,38,.08)' } : base;
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 6, display: 'flex', alignItems: 'center', gap: 5 }}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

function StatusPill({ status }: { status: string }) {
    const badge = statusBadge(STATUS_BADGE_KEY[status] ?? 'Draft');

    return (
        <span style={{ padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: badge.color, background: badge.bg }}>
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

export default function SurveiIndex({ surveys, employees, kpis }: SurveiIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [selectedId, setSelectedId] = useState<number | null>(surveys[0]?.id ?? null);
    const [createOpen, setCreateOpen] = useState(false);
    const [respondOpen, setRespondOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState<SurveyItem | null>(null);

    const selected = useMemo(() => surveys.find((survey) => survey.id === selectedId) ?? null, [surveys, selectedId]);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        if (selectedId === null && surveys[0]) {
            setSelectedId(surveys[0].id);
        }
    }, [surveys, selectedId]);

    /* ----- create survey form ----- */
    const createForm = useForm<{ title: string; description: string; status: string; is_anonymous: boolean }>({
        title: '',
        description: '',
        status: 'draft',
        is_anonymous: true,
    });

    const submitCreate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        createForm.post(SurveyController.store().url, {
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
                createForm.clearErrors();
            },
        });
    };

    /* ----- add-question form ----- */
    const questionForm = useForm<{ question: string; type: QuestionType; optionsText: string }>({
        question: '',
        type: 'rating',
        optionsText: '',
    });

    const submitQuestion = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selected) {
            return;
        }

        questionForm.transform((data) => ({
            question: data.question,
            type: data.type,
            options:
                data.type === 'choice'
                    ? data.optionsText
                          .split('\n')
                          .map((line) => line.trim())
                          .filter((line) => line.length > 0)
                    : [],
        }));
        questionForm.post(SurveyController.storeQuestion(selected.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                questionForm.reset();
                questionForm.clearErrors();
            },
        });
    };

    /* ----- respond ----- */
    const [answers, setAnswers] = useState<Record<number, string>>({});
    const [respondEmployee, setRespondEmployee] = useState('');
    const [responding, setResponding] = useState(false);

    const openRespond = () => {
        setAnswers({});
        setRespondEmployee('');
        setRespondOpen(true);
    };

    const submitRespond = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selected) {
            return;
        }

        setResponding(true);
        router.post(
            SurveyController.respond(selected.id).url,
            {
                employee_id: selected.is_anonymous ? null : respondEmployee || null,
                answers: selected.questions.map((question) => ({
                    survey_question_id: question.id,
                    answer: answers[question.id] ?? null,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setResponding(false),
                onSuccess: () => setRespondOpen(false),
            },
        );
    };

    const deleteSurvey = () => {
        if (!confirmDelete) {
            return;
        }

        router.delete(SurveyController.destroy(confirmDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedId === confirmDelete.id) {
                    setSelectedId(null);
                }
                setConfirmDelete(null);
            },
        });
    };

    const kpiItems = [
        { label: 'Survei Aktif', value: kpis.active_surveys, icon: 'clipboard-check', color: C.green },
        { label: 'Total Respons', value: kpis.total_responses, icon: 'message-square', color: C.primary },
        { label: 'Total Survei', value: kpis.total_surveys, icon: 'clipboard-list', color: C.sky },
    ];

    return (
        <>
            <Head title="Survei Karyawan" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Engagement</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Survei</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Survei Karyawan</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Buat survei, susun pertanyaan, dan pantau respons.</div>
                    </div>
                    <button
                        onClick={() => {
                            createForm.clearErrors();
                            setCreateOpen(true);
                        }}
                        style={btnP}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Buat Survei
                    </button>
                </div>

                {/* KPI cards */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    {kpiItems.map((item) => (
                        <div key={item.label} style={{ ...card, padding: '18px 20px', flex: '1 1 200px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                                <div style={{ width: 34, height: 34, borderRadius: 9, background: `${item.color}1a`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name={item.icon} size={17} color={item.color} />
                                </div>
                                <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>{item.label}</span>
                            </div>
                            <div style={{ fontSize: 26, fontWeight: 700, color: C.navy, letterSpacing: '-.02em' }}>{item.value}</div>
                        </div>
                    ))}
                </div>

                {surveys.length === 0 ? (
                    <div style={{ ...card, padding: '56px 18px', textAlign: 'center', color: C.muted }}>
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                            <AIcon name="clipboard-list" size={30} color={C.faint} />
                            <div style={{ fontSize: 14 }}>Belum ada survei. Buat survei pertama Anda.</div>
                        </div>
                    </div>
                ) : (
                    <div style={{ display: 'flex', gap: 18, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                        {/* Survey list */}
                        <div style={{ flex: '1 1 300px', minWidth: 280, display: 'flex', flexDirection: 'column', gap: 10 }}>
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy, marginBottom: 2 }}>Daftar Survei</div>
                            {surveys.map((survey) => {
                                const active = survey.id === selectedId;

                                return (
                                    <button
                                        key={survey.id}
                                        onClick={() => setSelectedId(survey.id)}
                                        style={{
                                            ...card,
                                            textAlign: 'left',
                                            padding: '14px 16px',
                                            cursor: 'pointer',
                                            border: `1px solid ${active ? C.primary : C.border}`,
                                            boxShadow: active ? '0 0 0 3px rgba(47,84,201,.08)' : card.boxShadow,
                                        }}
                                    >
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                            <span style={{ fontSize: 14, fontWeight: 600, color: C.navy }}>{survey.title}</span>
                                            <StatusPill status={survey.status} />
                                        </div>
                                        <div style={{ display: 'flex', gap: 14, marginTop: 8, fontSize: 12, color: C.muted }}>
                                            <span>{survey.questions.length} pertanyaan</span>
                                            <span>{survey.responses_count} respons</span>
                                            <span>{survey.is_anonymous ? 'Anonim' : 'Teridentifikasi'}</span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Builder + summary */}
                        <div style={{ flex: '2 1 480px', minWidth: 320 }}>
                            {selected && (
                                <div style={{ ...card, padding: 20 }}>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                                        <div>
                                            <div style={{ fontSize: 17, fontWeight: 600, color: C.navy }}>{selected.title}</div>
                                            {selected.description && <div style={{ fontSize: 13, color: C.muted, marginTop: 4 }}>{selected.description}</div>}
                                        </div>
                                        <div style={{ display: 'inline-flex', gap: 6 }}>
                                            <ActionBtn icon="pen-line" label="Isi Survei" variant="primary" onClick={openRespond} />
                                            <ActionBtn icon="trash-2" label="Hapus" variant="danger" onClick={() => setConfirmDelete(selected)} />
                                        </div>
                                    </div>

                                    {/* Questions + summary */}
                                    <div style={{ marginTop: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
                                        <div style={{ fontSize: 13, fontWeight: 600, color: C.text }}>Pertanyaan &amp; Ringkasan Respons</div>
                                        {selected.questions.length === 0 && (
                                            <div style={{ fontSize: 13, color: C.faint, padding: '10px 0' }}>Belum ada pertanyaan. Tambahkan di bawah.</div>
                                        )}
                                        {selected.questions.map((question, index) => (
                                            <div key={question.id} style={{ border: `1px solid ${C.border}`, borderRadius: 10, padding: '12px 14px' }}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
                                                    <div style={{ fontSize: 13.5, color: C.text, fontWeight: 500 }}>
                                                        {index + 1}. {question.question}
                                                    </div>
                                                    <span style={{ fontSize: 11.5, color: C.muted, whiteSpace: 'nowrap' }}>{TYPE_LABELS[question.type]}</span>
                                                </div>
                                                <div style={{ display: 'flex', gap: 16, marginTop: 8, fontSize: 12, color: C.muted }}>
                                                    <span>{question.response_count} respons</span>
                                                    {question.type === 'rating' && (
                                                        <span style={{ color: C.primary, fontWeight: 600 }}>
                                                            Rata-rata: {question.avg_rating !== null ? question.avg_rating.toFixed(2) : '—'}
                                                        </span>
                                                    )}
                                                    {question.type === 'choice' && question.options && (
                                                        <span>Opsi: {question.options.join(', ')}</span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Add question form */}
                                    <form onSubmit={submitQuestion} style={{ marginTop: 18, borderTop: `1px solid ${C.line}`, paddingTop: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
                                        <div style={{ fontSize: 13, fontWeight: 600, color: C.text }}>Tambah Pertanyaan</div>
                                        <div>
                                            <input
                                                type="text"
                                                placeholder="Tulis pertanyaan"
                                                value={questionForm.data.question}
                                                onChange={(event) => questionForm.setData('question', event.target.value)}
                                                style={withError(inputStyle, !!questionForm.errors.question)}
                                            />
                                            <FieldError message={questionForm.errors.question} />
                                        </div>
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                            <div>
                                                <label style={fieldLabelStyle}>Tipe</label>
                                                <select
                                                    value={questionForm.data.type}
                                                    onChange={(event) => questionForm.setData('type', event.target.value as QuestionType)}
                                                    style={withError(selectStyle, !!questionForm.errors.type)}
                                                >
                                                    <option value="rating">Rating (1-5)</option>
                                                    <option value="text">Teks</option>
                                                    <option value="choice">Pilihan</option>
                                                </select>
                                                <FieldError message={questionForm.errors.type} />
                                            </div>
                                            {questionForm.data.type === 'choice' && (
                                                <div>
                                                    <label style={fieldLabelStyle}>Opsi (satu per baris)</label>
                                                    <textarea
                                                        placeholder={'Setuju\nNetral\nTidak Setuju'}
                                                        value={questionForm.data.optionsText}
                                                        onChange={(event) => questionForm.setData('optionsText', event.target.value)}
                                                        style={textareaStyle}
                                                    />
                                                </div>
                                            )}
                                        </div>
                                        <div>
                                            <button
                                                type="submit"
                                                disabled={questionForm.processing}
                                                style={{ ...btnOut, height: 40, color: C.primary, borderColor: 'rgba(47,84,201,.35)', background: 'rgba(47,84,201,.06)' }}
                                            >
                                                <AIcon name="plus" size={15} color={C.primary} />
                                                Tambah Pertanyaan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Create survey modal */}
            {createOpen && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={() => setCreateOpen(false)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <form
                        onSubmit={submitCreate}
                        style={{ position: 'relative', width: '100%', maxWidth: 460, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26, animation: 'toastIn .2s ease' }}
                    >
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>Buat Survei</div>
                        <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>Mulai survei baru untuk karyawan.</div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Judul <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={createForm.data.title}
                                    onChange={(event) => createForm.setData('title', event.target.value)}
                                    placeholder="Judul survei"
                                    style={withError(inputStyle, !!createForm.errors.title)}
                                />
                                <FieldError message={createForm.errors.title} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>Deskripsi</label>
                                <textarea
                                    value={createForm.data.description}
                                    onChange={(event) => createForm.setData('description', event.target.value)}
                                    placeholder="Deskripsi (opsional)"
                                    style={textareaStyle}
                                />
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                <div>
                                    <label style={fieldLabelStyle}>Status</label>
                                    <select
                                        value={createForm.data.status}
                                        onChange={(event) => createForm.setData('status', event.target.value)}
                                        style={withError(selectStyle, !!createForm.errors.status)}
                                    >
                                        <option value="draft">Draft</option>
                                        <option value="active">Aktif</option>
                                        <option value="closed">Ditutup</option>
                                    </select>
                                    <FieldError message={createForm.errors.status} />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>Anonim</label>
                                    <select
                                        value={createForm.data.is_anonymous ? '1' : '0'}
                                        onChange={(event) => createForm.setData('is_anonymous', event.target.value === '1')}
                                        style={selectStyle}
                                    >
                                        <option value="1">Ya (anonim)</option>
                                        <option value="0">Tidak</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button type="button" onClick={() => setCreateOpen(false)} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={createForm.processing}
                                style={{ ...btnP, flex: 1, height: 44, justifyContent: 'center', opacity: createForm.processing ? 0.7 : 1 }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Respond modal */}
            {respondOpen && selected && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={() => setRespondOpen(false)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <form
                        onSubmit={submitRespond}
                        style={{ position: 'relative', width: '100%', maxWidth: 500, maxHeight: '90vh', overflowY: 'auto', background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26, animation: 'toastIn .2s ease' }}
                    >
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>Isi Survei</div>
                        <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>{selected.title}</div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {!selected.is_anonymous && (
                                <div>
                                    <label style={fieldLabelStyle}>Karyawan</label>
                                    <select value={respondEmployee} onChange={(event) => setRespondEmployee(event.target.value)} style={selectStyle}>
                                        <option value="">Pilih karyawan</option>
                                        {employees.map((employee) => (
                                            <option key={employee.id} value={String(employee.id)}>
                                                {employee.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            {selected.questions.length === 0 && (
                                <div style={{ fontSize: 13, color: C.faint }}>Survei ini belum punya pertanyaan.</div>
                            )}
                            {selected.questions.map((question, index) => (
                                <div key={question.id}>
                                    <label style={fieldLabelStyle}>
                                        {index + 1}. {question.question}
                                    </label>
                                    {question.type === 'rating' && (
                                        <div style={{ display: 'flex', gap: 8 }}>
                                            {[1, 2, 3, 4, 5].map((value) => {
                                                const chosen = answers[question.id] === String(value);

                                                return (
                                                    <button
                                                        key={value}
                                                        type="button"
                                                        onClick={() => setAnswers((prev) => ({ ...prev, [question.id]: String(value) }))}
                                                        style={{
                                                            width: 40,
                                                            height: 40,
                                                            borderRadius: 8,
                                                            border: `1px solid ${chosen ? C.primary : C.border}`,
                                                            background: chosen ? C.primary : '#fff',
                                                            color: chosen ? '#fff' : C.text,
                                                            fontSize: 14,
                                                            fontWeight: 600,
                                                            cursor: 'pointer',
                                                        }}
                                                    >
                                                        {value}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    )}
                                    {question.type === 'text' && (
                                        <textarea
                                            value={answers[question.id] ?? ''}
                                            onChange={(event) => setAnswers((prev) => ({ ...prev, [question.id]: event.target.value }))}
                                            placeholder="Jawaban Anda"
                                            style={textareaStyle}
                                        />
                                    )}
                                    {question.type === 'choice' && (
                                        <select
                                            value={answers[question.id] ?? ''}
                                            onChange={(event) => setAnswers((prev) => ({ ...prev, [question.id]: event.target.value }))}
                                            style={selectStyle}
                                        >
                                            <option value="">Pilih jawaban</option>
                                            {(question.options ?? []).map((option) => (
                                                <option key={option} value={option}>
                                                    {option}
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                </div>
                            ))}
                        </div>

                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button type="button" onClick={() => setRespondOpen(false)} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={responding || selected.questions.length === 0}
                                style={{ ...btnP, flex: 1, height: 44, justifyContent: 'center', opacity: responding || selected.questions.length === 0 ? 0.6 : 1 }}
                            >
                                <AIcon name="send" size={16} color="#fff" />
                                Kirim Respons
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Delete confirmation */}
            {confirmDelete && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={() => setConfirmDelete(null)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <div style={{ position: 'relative', width: '100%', maxWidth: 400, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26, animation: 'toastIn .2s ease' }}>
                        <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(220,38,38,.1)', color: C.red, display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                            <AIcon name="trash-2" size={22} color={C.red} />
                        </div>
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>Hapus survei?</div>
                        <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>
                            Survei <strong style={{ color: C.text }}>{confirmDelete.title}</strong> beserta pertanyaan dan responsnya akan dihapus permanen.
                        </div>
                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button onClick={() => setConfirmDelete(null)} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>
                                Batal
                            </button>
                            <button
                                onClick={deleteSurvey}
                                style={{ flex: 1, height: 44, background: C.red, color: '#fff', border: 'none', borderRadius: 9, fontSize: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}
                            >
                                <AIcon name="trash-2" size={16} />
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
