import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import RecruitmentController from '@/actions/App/Http/Controllers/Avana/RecruitmentController';
import { usePermission } from '@/hooks/use-permission';
import { AIcon, C, card } from '@/lib/avana';

interface Order {
    id: number;
    code: string;
    client_name: string;
    position_name: string;
    headcount: number;
    salary_master: string;
    shift: string | null;
    leave_type: string | null;
    status: string;
    forwarded_by: string | null;
    benefit_decided_by: string | null;
    benefit_decided_at: string | null;
}

interface Props {
    orders: Order[];
}

const th: React.CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};
const td: React.CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '12px 14px',
    borderBottom: `1px solid ${C.line}`,
};

export default function RecruitmentSalesOrder({ orders }: Props) {
    const { can } = usePermission();
    const canApprove = can('recruitment.approve');
    const [deciding, setDeciding] = useState<Order | null>(null);

    return (
        <>
            <Head title="Approval Benefit Sales Order" />
            <div style={{ padding: '28px 32px' }}>
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
                    <span>Rekrutmen</span>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Approval Benefit SO</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 4px',
                    }}
                >
                    Approval Benefit Sales Order
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginBottom: 18 }}>
                    Sales Order yang sudah di-mapping payroll. Setujui benefit
                    (Master Gaji / shift / cuti) atau tolak untuk dikembalikan
                    ke payroll.
                </div>

                <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
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
                                        'Kode',
                                        'Klien',
                                        'Posisi',
                                        'Qty',
                                        'Benefit (Master / Shift / Cuti)',
                                        'Diteruskan oleh',
                                        'Status',
                                        '',
                                    ].map((h, i) => (
                                        <th key={i} style={th}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {orders.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            style={{
                                                ...td,
                                                textAlign: 'center',
                                                color: C.faint,
                                            }}
                                        >
                                            Belum ada Sales Order dari payroll.
                                        </td>
                                    </tr>
                                ) : (
                                    orders.map((o) => (
                                        <tr key={o.id}>
                                            <td style={td}>{o.code}</td>
                                            <td
                                                style={{
                                                    ...td,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {o.client_name}
                                            </td>
                                            <td style={td}>
                                                {o.position_name}
                                            </td>
                                            <td style={td}>{o.headcount}</td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                    fontSize: 12.5,
                                                }}
                                            >
                                                {`${o.salary_master} · ${o.shift ?? '—'} · ${o.leave_type ?? '—'}`}
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                    fontSize: 12.5,
                                                }}
                                            >
                                                {o.forwarded_by ?? '—'}
                                            </td>
                                            <td style={td}>
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        fontWeight: 700,
                                                        padding: '3px 9px',
                                                        borderRadius: 6,
                                                        color:
                                                            o.status ===
                                                            'approved'
                                                                ? '#15803D'
                                                                : '#B45309',
                                                        background:
                                                            o.status ===
                                                            'approved'
                                                                ? '#DCFCE7'
                                                                : '#FEF3C7',
                                                    }}
                                                >
                                                    {o.status === 'approved'
                                                        ? 'Disetujui'
                                                        : 'Menunggu'}
                                                </span>
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    textAlign: 'right',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {canApprove &&
                                                o.status === 'forwarded' ? (
                                                    <button
                                                        onClick={() =>
                                                            setDeciding(o)
                                                        }
                                                        style={{
                                                            display:
                                                                'inline-flex',
                                                            alignItems:
                                                                'center',
                                                            gap: 6,
                                                            padding: '6px 12px',
                                                            borderRadius: 7,
                                                            fontSize: 12,
                                                            fontWeight: 600,
                                                            cursor: 'pointer',
                                                            border: 'none',
                                                            background:
                                                                C.primary,
                                                            color: '#fff',
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="gavel"
                                                            size={13}
                                                            color="#fff"
                                                        />
                                                        Review
                                                    </button>
                                                ) : (
                                                    <span
                                                        style={{
                                                            fontSize: 12,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {o.benefit_decided_by ??
                                                            ''}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {deciding && (
                <DecisionModal
                    order={deciding}
                    onClose={() => setDeciding(null)}
                />
            )}
        </>
    );
}

function DecisionModal({
    order,
    onClose,
}: {
    order: Order;
    onClose: () => void;
}) {
    const form = useForm<{ decision: 'approve' | 'reject'; note: string }>({
        decision: 'approve',
        note: '',
    });

    const decide = (decision: 'approve' | 'reject') => {
        if (decision === 'reject' && form.data.note.trim().length < 3) {
            toast.error('Alasan penolakan minimal 3 karakter.');

            return;
        }

        router.post(
            RecruitmentController.decideSalesOrder(order.id).url,
            { decision, note: form.data.note },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        decision === 'approve'
                            ? 'Benefit disetujui'
                            : 'Benefit ditolak',
                    );
                    onClose();
                },
            },
        );
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
                zIndex: 80,
                padding: 20,
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    width: 440,
                    maxWidth: '100%',
                    background: '#fff',
                    borderRadius: 14,
                    padding: 24,
                    boxShadow: '0 24px 60px rgba(15,23,42,.24)',
                }}
            >
                <div
                    style={{
                        fontSize: 16,
                        fontWeight: 700,
                        color: C.navy,
                        marginBottom: 2,
                    }}
                >
                    Review Benefit Sales Order
                </div>
                <div
                    style={{
                        fontSize: 12.5,
                        color: C.muted,
                        marginBottom: 14,
                    }}
                >
                    {order.code} · {order.client_name} · {order.position_name}
                </div>

                <div
                    style={{
                        background: C.surface,
                        borderRadius: 10,
                        padding: 14,
                        fontSize: 13,
                        color: C.text,
                        marginBottom: 16,
                    }}
                >
                    <div>
                        <strong>Master Gaji:</strong> {order.salary_master}
                    </div>
                    <div>
                        <strong>Shift:</strong> {order.shift ?? '—'}
                    </div>
                    <div>
                        <strong>Jenis Cuti:</strong> {order.leave_type ?? '—'}
                    </div>
                </div>

                <label
                    style={{
                        display: 'block',
                        fontSize: 13,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 6,
                    }}
                >
                    Catatan{' '}
                    <span style={{ color: C.faint, fontWeight: 400 }}>
                        (wajib bila menolak)
                    </span>
                </label>
                <textarea
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                    rows={3}
                    placeholder="Catatan / alasan penolakan…"
                    style={{
                        width: '100%',
                        padding: '10px 12px',
                        borderRadius: 8,
                        border: `1px solid ${C.line}`,
                        fontSize: 13.5,
                        color: C.text,
                        outline: 'none',
                        resize: 'vertical',
                        fontFamily: 'inherit',
                    }}
                />

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        gap: 10,
                        marginTop: 20,
                    }}
                >
                    <button
                        onClick={() => decide('reject')}
                        disabled={form.processing}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            padding: '9px 16px',
                            borderRadius: 8,
                            border: `1px solid ${C.red}`,
                            background: '#FEF2F2',
                            color: C.red,
                            fontSize: 13,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="x" size={15} color={C.red} />
                        Tolak
                    </button>
                    <button
                        onClick={() => decide('approve')}
                        disabled={form.processing}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            padding: '9px 20px',
                            borderRadius: 8,
                            border: 'none',
                            background: C.green,
                            color: '#fff',
                            fontSize: 13,
                            fontWeight: 600,
                            cursor: 'pointer',
                            opacity: form.processing ? 0.6 : 1,
                        }}
                    >
                        <AIcon name="check-check" size={15} color="#fff" />
                        Setujui
                    </button>
                </div>
            </div>
        </div>
    );
}
