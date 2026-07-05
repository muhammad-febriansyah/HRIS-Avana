import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import { StageBadge } from './components';
import { Empty, RecruitmentHeader, td, th } from './shell';

interface Candidate {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    stage: string;
    applied_date: string | null;
    job_title: string | null;
    pool: string | null;
    ai_confidence: number | null;
    ai_recommendation: string | null;
}

interface JobOption {
    id: number;
    title: string;
}

interface StageOption {
    value: string;
    label: string;
}

interface CandidatesProps {
    candidates: Candidate[];
    jobs: JobOption[];
    stages: StageOption[];
}

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function RecruitmentCandidates({
    candidates,
    jobs,
    stages,
}: CandidatesProps) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);

    const stageLabel = useMemo(
        () => Object.fromEntries(stages.map((s) => [s.value, s.label])),
        [stages],
    );

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return candidates;
        }
        return candidates.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                (c.email ?? '').toLowerCase().includes(q),
        );
    }, [candidates, query]);

    const form = useForm({
        name: '',
        email: '',
        phone: '',
        job_posting_id: '',
        linkedin_url: '',
        portfolio_url: '',
        source: 'manual',
        stage: 'applied',
        applied_date: todayIso(),
    });

    const submit = () => {
        form.post(RecruitmentController.storeApplicant().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Kandidat ditambahkan');
                setOpen(false);
                form.reset();
            },
            onError: () => toast.error('Periksa kembali isian form'),
        });
    };

    return (
        <>
            <Head title="Kandidat" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Kandidat"
                    subtitle="Basis data terpusat seluruh pelamar."
                    action={
                        <button style={btnP} onClick={() => setOpen(true)}>
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Kandidat
                        </button>
                    }
                />

                <div style={{ ...card, padding: 14, marginBottom: 18 }}>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '0 6px',
                        }}
                    >
                        <AIcon name="search" size={17} color={C.faint} />
                        <input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Cari kandidat…"
                            style={{
                                flex: 1,
                                border: 'none',
                                outline: 'none',
                                fontSize: 14,
                                color: C.text,
                                background: 'transparent',
                            }}
                        />
                    </div>
                </div>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    {filtered.length === 0 ? (
                        <Empty
                            icon="users"
                            title="Belum ada kandidat"
                            hint="Tambah kandidat manual atau terima lamaran via email."
                        />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                }}
                            >
                                <thead>
                                    <tr>
                                        <th style={{ ...th, paddingTop: 16 }}>
                                            Profil Kandidat
                                        </th>
                                        <th style={{ ...th, paddingTop: 16 }}>
                                            Talent Pool
                                        </th>
                                        <th style={{ ...th, paddingTop: 16 }}>
                                            Rekomendasi AI
                                        </th>
                                        <th style={{ ...th, paddingTop: 16 }}>
                                            Status
                                        </th>
                                        <th style={{ ...th, paddingTop: 16 }}>
                                            Kontak
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((c) => (
                                        <tr key={c.id}>
                                            <td style={td}>
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 11,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            width: 34,
                                                            height: 34,
                                                            borderRadius: '50%',
                                                            flex: 'none',
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent:
                                                                'center',
                                                            background:
                                                                C.primary + '18',
                                                            color: C.primary,
                                                            fontSize: 12,
                                                            fontWeight: 700,
                                                        }}
                                                    >
                                                        {c.name
                                                            .slice(0, 2)
                                                            .toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <div
                                                            style={{
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                            }}
                                                        >
                                                            {c.name}
                                                        </div>
                                                        <div
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                                textTransform:
                                                                    'uppercase',
                                                            }}
                                                        >
                                                            {c.job_title
                                                                ? `Melamar: ${c.job_title}`
                                                                : 'Umum'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style={td}>
                                                {c.pool ? (
                                                    <span
                                                        style={{
                                                            fontWeight: 600,
                                                            color: C.primary,
                                                        }}
                                                    >
                                                        {c.pool}
                                                    </span>
                                                ) : (
                                                    <span
                                                        style={{
                                                            color: C.faint,
                                                            fontStyle: 'italic',
                                                        }}
                                                    >
                                                        Belum ada pool
                                                    </span>
                                                )}
                                            </td>
                                            <td style={td}>
                                                {c.ai_recommendation ? (
                                                    <span
                                                        style={{
                                                            fontSize: 12,
                                                            fontWeight: 700,
                                                            padding: '4px 10px',
                                                            borderRadius: 6,
                                                            color: C.green,
                                                            background:
                                                                'rgba(22,163,74,.1)',
                                                        }}
                                                    >
                                                        {c.ai_recommendation.toUpperCase()}
                                                        {c.ai_confidence !== null
                                                            ? ` · ${c.ai_confidence}%`
                                                            : ''}
                                                    </span>
                                                ) : (
                                                    <button
                                                        onClick={() =>
                                                            toast.info(
                                                                'Fitur AI belum aktif',
                                                            )
                                                        }
                                                        style={{
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            gap: 6,
                                                            fontSize: 12,
                                                            fontWeight: 600,
                                                            color: C.muted,
                                                            padding: '5px 11px',
                                                            borderRadius: 6,
                                                            border: `1px dashed ${C.line}`,
                                                            background:
                                                                'transparent',
                                                            cursor: 'pointer',
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="sparkles"
                                                            size={13}
                                                            color={C.muted}
                                                        />
                                                        Generate AI
                                                    </button>
                                                )}
                                            </td>
                                            <td style={td}>
                                                <StageBadge
                                                    stage={c.stage}
                                                    label={
                                                        stageLabel[c.stage] ??
                                                        c.stage
                                                    }
                                                />
                                            </td>
                                            <td style={td}>
                                                <div
                                                    style={{
                                                        fontSize: 12.5,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {c.email ?? '—'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {c.applied_date ?? ''}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {open && (
                <AddCandidateModal
                    form={form}
                    jobs={jobs}
                    onClose={() => setOpen(false)}
                    onSubmit={submit}
                />
            )}
        </>
    );
}

function AddCandidateModal({
    form,
    jobs,
    onClose,
    onSubmit,
}: {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    form: any;
    jobs: JobOption[];
    onClose: () => void;
    onSubmit: () => void;
}) {
    const label: React.CSSProperties = {
        fontSize: 13,
        fontWeight: 600,
        color: C.text,
        marginBottom: 6,
        display: 'block',
    };
    const input: React.CSSProperties = {
        width: '100%',
        padding: '10px 12px',
        borderRadius: 8,
        border: `1px solid ${C.line}`,
        fontSize: 14,
        outline: 'none',
        color: C.text,
    };

    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(15,23,42,.45)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 50,
                padding: 20,
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    ...card,
                    width: 560,
                    maxWidth: '100%',
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    padding: 26,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginBottom: 20,
                    }}
                >
                    <div
                        style={{
                            fontSize: 18,
                            fontWeight: 700,
                            color: C.navy,
                        }}
                    >
                        Tambah Kandidat Manual
                    </div>
                    <button
                        onClick={onClose}
                        style={{
                            border: 'none',
                            background: 'transparent',
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="x" size={20} color={C.faint} />
                    </button>
                </div>

                <div style={{ marginBottom: 14 }}>
                    <label style={label}>Nama Lengkap</label>
                    <input
                        style={input}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    {form.errors.name && (
                        <div style={{ fontSize: 12, color: C.red, marginTop: 4 }}>
                            {form.errors.name}
                        </div>
                    )}
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                        marginBottom: 14,
                    }}
                >
                    <div>
                        <label style={label}>Email</label>
                        <input
                            style={input}
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        {form.errors.email && (
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.red,
                                    marginTop: 4,
                                }}
                            >
                                {form.errors.email}
                            </div>
                        )}
                    </div>
                    <div>
                        <label style={label}>Telepon (opsional)</label>
                        <input
                            style={input}
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                        />
                    </div>
                </div>

                <div style={{ marginBottom: 14 }}>
                    <label style={label}>Lowongan Dilamar</label>
                    <select
                        style={input}
                        value={form.data.job_posting_id}
                        onChange={(e) =>
                            form.setData('job_posting_id', e.target.value)
                        }
                    >
                        <option value="">Pilih lowongan</option>
                        {jobs.map((j) => (
                            <option key={j.id} value={j.id}>
                                {j.title}
                            </option>
                        ))}
                    </select>
                    {form.errors.job_posting_id && (
                        <div style={{ fontSize: 12, color: C.red, marginTop: 4 }}>
                            {form.errors.job_posting_id}
                        </div>
                    )}
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <label style={label}>LinkedIn (opsional)</label>
                        <input
                            style={input}
                            placeholder="https://linkedin.com/in/…"
                            value={form.data.linkedin_url}
                            onChange={(e) =>
                                form.setData('linkedin_url', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <label style={label}>Portfolio (opsional)</label>
                        <input
                            style={input}
                            placeholder="https://portfolio.me/…"
                            value={form.data.portfolio_url}
                            onChange={(e) =>
                                form.setData('portfolio_url', e.target.value)
                            }
                        />
                    </div>
                </div>

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                    }}
                >
                    <button style={btnOut} onClick={onClose}>
                        Batal
                    </button>
                    <button
                        style={btnP}
                        disabled={form.processing}
                        onClick={onSubmit}
                    >
                        Tambah Kandidat
                    </button>
                </div>
            </div>
        </div>
    );
}
