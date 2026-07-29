import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { usePermission } from '@/hooks/use-permission';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import { Empty, RecruitmentHeader } from './shell';

interface Pool {
    id: number;
    name: string;
    description: string | null;
    is_auto: boolean;
    members_count: number;
}

export default function RecruitmentPools({ pools }: { pools: Pool[] }) {
    const { can } = usePermission();
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', description: '' });

    const submit = () => {
        form.post(RecruitmentController.storePool().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Talent pool dibuat');
                setOpen(false);
                form.reset();
            },
            onError: () => toast.error('Periksa kembali isian'),
        });
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
        <>
            <Head title="Talent Pool" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Talent Pool"
                    subtitle="Kelompokkan kandidat ke pool talenta untuk kebutuhan mendatang."
                    action={
                        can('recruitment.create') ? (
                            <button style={btnP} onClick={() => setOpen(true)}>
                                <AIcon name="plus" size={16} color="#fff" />
                                Buat Pool
                            </button>
                        ) : null
                    }
                />

                <div
                    style={{
                        ...card,
                        padding: '14px 18px',
                        marginBottom: 18,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        background: '#EFF6FF',
                        border: '1px solid #DBEAFE',
                    }}
                >
                    <AIcon name="info" size={18} color={C.primary} />
                    <span style={{ fontSize: 13, color: '#1E3A8A' }}>
                        Pool dapat dibuat manual atau otomatis. Kelola anggota
                        pool dari detail kandidat.
                    </span>
                </div>

                {pools.length === 0 ? (
                    <div style={{ ...card, padding: 20 }}>
                        <Empty
                            icon="layers"
                            title="Belum ada talent pool"
                            hint="Buat pool pertama untuk mengelompokkan kandidat."
                        />
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(280px, 1fr))',
                            gap: 18,
                        }}
                    >
                        {pools.map((p) => (
                            <div key={p.id} style={{ ...card, padding: 0 }}>
                                <div style={{ padding: '20px 20px 16px' }}>
                                    <div
                                        style={{
                                            width: 42,
                                            height: 42,
                                            borderRadius: 11,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            background: C.primary + '14',
                                            marginBottom: 14,
                                        }}
                                    >
                                        <AIcon
                                            name="layers"
                                            size={20}
                                            color={C.primary}
                                        />
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 16,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {p.name}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            color: C.muted,
                                            marginTop: 4,
                                        }}
                                    >
                                        {p.description ??
                                            (p.is_auto
                                                ? 'Dibuat otomatis oleh sistem.'
                                                : 'Pool manual.')}
                                    </div>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        padding: '13px 20px',
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    <span
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 6,
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        <AIcon
                                            name="users"
                                            size={15}
                                            color={C.faint}
                                        />
                                        {p.members_count} anggota
                                    </span>
                                    {p.is_auto && (
                                        <span
                                            style={{
                                                fontSize: 11,
                                                fontWeight: 700,
                                                padding: '3px 9px',
                                                borderRadius: 6,
                                                color: '#7C3AED',
                                                background: '#F3E8FF',
                                            }}
                                        >
                                            AUTO
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {open && (
                <div
                    onClick={() => setOpen(false)}
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
                            width: 440,
                            maxWidth: '100%',
                            padding: 26,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 700,
                                color: C.navy,
                                marginBottom: 18,
                            }}
                        >
                            Buat Talent Pool
                        </div>
                        <div style={{ marginBottom: 14 }}>
                            <label
                                style={{
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: C.text,
                                    display: 'block',
                                    marginBottom: 6,
                                }}
                            >
                                Nama Pool
                            </label>
                            <input
                                style={input}
                                placeholder="cth. Talent Pool Engineering 2026"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            {form.errors.name && (
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: C.red,
                                        marginTop: 4,
                                    }}
                                >
                                    {form.errors.name}
                                </div>
                            )}
                        </div>
                        <div style={{ marginBottom: 22 }}>
                            <label
                                style={{
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: C.text,
                                    display: 'block',
                                    marginBottom: 6,
                                }}
                            >
                                Deskripsi (opsional)
                            </label>
                            <textarea
                                style={{
                                    ...input,
                                    minHeight: 72,
                                    resize: 'vertical',
                                }}
                                placeholder="cth. Kandidat lolos tahap akhir yang belum dapat slot"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                            }}
                        >
                            <button
                                style={btnOut}
                                onClick={() => setOpen(false)}
                            >
                                <AIcon name="x" size={15} color={C.text} />
                                Batal
                            </button>
                            <button
                                style={btnP}
                                disabled={form.processing}
                                onClick={submit}
                            >
                                <AIcon name="plus" size={15} color="#fff" />
                                Buat Pool
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
