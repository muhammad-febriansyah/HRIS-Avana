import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { usePermission } from '@/hooks/use-permission';
import { AIcon, ActionBtn, btnOut, btnP, C, card } from '@/lib/avana';
import { Empty, RecruitmentHeader, td, th } from './shell';

interface Req {
    id: number;
    requester: string;
    position: string;
    unit: string;
    count: number;
    reason: string | null;
    status: string;
    decided_by: string | null;
    decided_at: string | null;
}

interface Dept {
    id: number;
    name: string;
}

interface HeadcountProps {
    requests: Req[];
    departments: Dept[];
}

const STATUS_STYLE: Record<string, { c: string; bg: string; label: string }> = {
    pending: { c: '#B45309', bg: '#FEF3C7', label: 'Menunggu' },
    approved: { c: '#15803D', bg: '#DCFCE7', label: 'Disetujui' },
    rejected: { c: '#B91C1C', bg: '#FEE2E2', label: 'Ditolak' },
};

export default function RecruitmentHeadcount({
    requests,
    departments,
}: HeadcountProps) {
    const { can } = usePermission();
    const canApprove = can('recruitment.approve');
    const [open, setOpen] = useState(false);

    const form = useForm({
        position_title: '',
        department_id: '',
        count: 1,
        reason: '',
    });

    const submit = () => {
        form.post(RecruitmentController.storeHeadcount().url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Permintaan headcount diajukan');
                setOpen(false);
                form.reset();
            },
            onError: () => toast.error('Periksa kembali isian'),
        });
    };

    const decide = (id: number, status: 'approved' | 'rejected') => {
        router.post(
            RecruitmentController.decideHeadcount(id).url,
            { status },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        status === 'approved'
                            ? 'Permintaan disetujui'
                            : 'Permintaan ditolak',
                    ),
            },
        );
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
            <Head title="Headcount Approval" />
            <div style={{ padding: '28px 32px' }}>
                <RecruitmentHeader
                    title="Headcount Approval"
                    subtitle="Tinjau & setujui permintaan headcount dari manajer."
                    action={
                        can('recruitment.create') ? (
                            <button style={btnP} onClick={() => setOpen(true)}>
                                <AIcon name="plus" size={16} color="#fff" />
                                Ajukan Headcount
                            </button>
                        ) : null
                    }
                />

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '18px 20px 4px',
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Pending & Riwayat
                    </div>
                    {requests.length === 0 ? (
                        <Empty
                            icon="user-check"
                            title="Belum ada permintaan"
                            hint="Permintaan headcount dari manajer akan tampil di sini."
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
                                        {[
                                            'Pemohon',
                                            'Posisi',
                                            'Unit',
                                            'Jumlah',
                                            'Alasan',
                                            'Status',
                                            'Aksi',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                style={{
                                                    ...th,
                                                    paddingTop: 14,
                                                }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.map((r) => {
                                        const st =
                                            STATUS_STYLE[r.status] ??
                                            STATUS_STYLE.pending;

                                        return (
                                            <tr key={r.id}>
                                                <td style={td}>
                                                    {r.requester}
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {r.position}
                                                </td>
                                                <td style={td}>{r.unit}</td>
                                                <td style={td}>{r.count}</td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                        maxWidth: 220,
                                                    }}
                                                >
                                                    {r.reason ?? '—'}
                                                </td>
                                                <td style={td}>
                                                    <span
                                                        style={{
                                                            fontSize: 12,
                                                            fontWeight: 700,
                                                            padding: '4px 10px',
                                                            borderRadius: 6,
                                                            color: st.c,
                                                            background: st.bg,
                                                        }}
                                                    >
                                                        {st.label}
                                                    </span>
                                                </td>
                                                <td style={td}>
                                                    {canApprove &&
                                                    r.status === 'pending' ? (
                                                        <div
                                                            style={{
                                                                display: 'flex',
                                                                gap: 6,
                                                                flexWrap:
                                                                    'wrap',
                                                            }}
                                                        >
                                                            <ActionBtn
                                                                icon="check"
                                                                label="Setujui"
                                                                variant="success"
                                                                onClick={() =>
                                                                    decide(
                                                                        r.id,
                                                                        'approved',
                                                                    )
                                                                }
                                                            />
                                                            <ActionBtn
                                                                icon="x"
                                                                label="Tolak"
                                                                variant="danger"
                                                                onClick={() =>
                                                                    decide(
                                                                        r.id,
                                                                        'rejected',
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                    ) : (
                                                        <span
                                                            style={{
                                                                fontSize: 12,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {r.decided_by ?? ''}
                                                            {r.decided_at
                                                                ? ` · ${r.decided_at}`
                                                                : ''}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
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
                            width: 480,
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
                            Ajukan Headcount
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
                                Posisi
                            </label>
                            <input
                                style={input}
                                placeholder="cth. Software Engineer"
                                value={form.data.position_title}
                                onChange={(e) =>
                                    form.setData(
                                        'position_title',
                                        e.target.value,
                                    )
                                }
                            />
                            {form.errors.position_title && (
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: C.red,
                                        marginTop: 4,
                                    }}
                                >
                                    {form.errors.position_title}
                                </div>
                            )}
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '2fr 1fr',
                                gap: 12,
                                marginBottom: 14,
                            }}
                        >
                            <div>
                                <label
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.text,
                                        display: 'block',
                                        marginBottom: 6,
                                    }}
                                >
                                    Unit / Departemen
                                </label>
                                <select
                                    style={input}
                                    value={form.data.department_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'department_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">—</option>
                                    {departments.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            {d.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.text,
                                        display: 'block',
                                        marginBottom: 6,
                                    }}
                                >
                                    Jumlah
                                </label>
                                <input
                                    type="number"
                                    min={1}
                                    style={input}
                                    placeholder="cth. 2"
                                    value={form.data.count}
                                    onChange={(e) =>
                                        form.setData(
                                            'count',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
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
                                Alasan
                            </label>
                            <textarea
                                style={{
                                    ...input,
                                    minHeight: 72,
                                    resize: 'vertical',
                                }}
                                placeholder="cth. Ekspansi tim produksi cabang Bandung"
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
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
                                <AIcon name="send" size={15} color="#fff" />
                                Ajukan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
