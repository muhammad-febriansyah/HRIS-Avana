import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import AiTokenPackController from '@/actions/App/Http/Controllers/Avana/AiTokenPackController';
import {
    ActionBtn,
    AIcon,
    btnCreate,
    btnDanger,
    btnOut,
    btnSave,
    C,
    card,
    rp,
    RupiahInput,
} from '@/lib/avana';

/* ============================================================
 * Paket Token AI (super admin): price catalogue + tenant orders.
 * ============================================================ */

interface Pack {
    id: number;
    name: string;
    token_amount: number;
    price: number;
    description: string | null;
    is_active: boolean;
    sort_order: number;
}

interface Order {
    id: number;
    order_number: string;
    tenant: string | null;
    pack_name: string;
    token_amount: number;
    amount: number;
    status: string;
    payment_method: string | null;
    completed_at: string | null;
    created_at: string | null;
}

interface Kpis {
    revenue: number;
    tokens_sold: number;
    pending_count: number;
    completed_count: number;
}

interface PageProps {
    packs: Pack[];
    orders: Order[];
    kpis: Kpis;
}

type FlashProps = { flash?: { success?: string } };

interface PackForm {
    name: string;
    token_amount: number | string;
    price: string;
    description: string;
    is_active: boolean;
    sort_order: number;
}

const emptyForm = (order: number): PackForm => ({
    name: '',
    token_amount: '',
    price: '',
    description: '',
    is_active: true,
    sort_order: order,
});

const ORDER_STATUS: Record<string, [string, string, string]> = {
    completed: ['Lunas', '#16A34A', 'rgba(22,163,74,.1)'],
    pending: ['Menunggu', '#D97706', 'rgba(217,119,6,.1)'],
    failed: ['Gagal', '#DC2626', 'rgba(220,38,38,.1)'],
};

const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

const input: CSSProperties = {
    width: '100%',
    padding: '9px 12px',
    borderRadius: 9,
    border: `1px solid ${C.border}`,
    fontSize: 14,
    color: C.text,
    outline: 'none',
    boxSizing: 'border-box',
};

function fmt(n: number): string {
    return Number(n).toLocaleString('id-ID');
}

function StatusPill({ status }: { status: string }) {
    const [label, color, bg] = ORDER_STATUS[status] ?? [
        status,
        C.muted,
        C.line,
    ];

    return (
        <span
            style={{
                fontSize: 12,
                fontWeight: 600,
                color,
                background: bg,
                padding: '3px 10px',
                borderRadius: 999,
            }}
        >
            {label}
        </span>
    );
}

function Kpi({ label, value }: { label: string; value: string }) {
    return (
        <div style={{ ...card, padding: '16px 18px', flex: '1 1 180px' }}>
            <div style={{ fontSize: 12.5, color: C.muted }}>{label}</div>
            <div
                style={{
                    fontSize: 22,
                    fontWeight: 700,
                    color: C.navy,
                    marginTop: 4,
                }}
            >
                {value}
            </div>
        </div>
    );
}

export default function TokenPacks({ packs, orders, kpis }: PageProps) {
    const { flash } = usePage<FlashProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Pack | null>(null);
    const [confirm, setConfirm] = useState<Pack | null>(null);

    const form = useForm<PackForm>(emptyForm(0));

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyForm(packs.length));
        setModalOpen(true);
    };

    const openEdit = (pack: Pack) => {
        setEditing(pack);
        form.clearErrors();
        form.setData({
            name: pack.name,
            token_amount: pack.token_amount,
            price: String(pack.price),
            description: pack.description ?? '',
            is_active: pack.is_active,
            sort_order: pack.sort_order,
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const url = editing
            ? AiTokenPackController.update(editing.id).url
            : AiTokenPackController.store().url;
        form.transform((data) => ({
            ...data,
            token_amount: Number(data.token_amount) || 0,
            price: Number(data.price) || 0,
        }));
        form.post(url, {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    const remove = () => {
        if (!confirm) {
            return;
        }

        router.delete(AiTokenPackController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Paket Token AI" />
            <div style={{ padding: '28px 32px' }}>
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
                            <span>Platform</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Paket Token AI
                            </span>
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
                            Paket Token AI
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Atur paket token yang bisa dibeli tenant untuk
                            mengisi saldo AI Assistant.
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnCreate}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Paket
                    </button>
                </div>

                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 14,
                        marginBottom: 22,
                    }}
                >
                    <Kpi label="Pendapatan" value={rp(kpis.revenue)} />
                    <Kpi label="Token terjual" value={fmt(kpis.tokens_sold)} />
                    <Kpi
                        label="Order lunas"
                        value={fmt(kpis.completed_count)}
                    />
                    <Kpi
                        label="Order menunggu"
                        value={fmt(kpis.pending_count)}
                    />
                </div>

                <div style={{ ...card, overflow: 'hidden', marginBottom: 26 }}>
                    <div
                        style={{
                            padding: '14px 18px',
                            fontWeight: 600,
                            color: C.navy,
                            borderBottom: `1px solid ${C.line}`,
                        }}
                    >
                        Daftar Paket
                    </div>
                    {packs.length === 0 ? (
                        <div
                            style={{
                                padding: '40px 18px',
                                textAlign: 'center',
                                color: C.muted,
                                fontSize: 13.5,
                            }}
                        >
                            Belum ada paket token.
                        </div>
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    fontSize: 13.5,
                                }}
                            >
                                <thead>
                                    <tr
                                        style={{
                                            color: C.muted,
                                            textAlign: 'left',
                                        }}
                                    >
                                        <th style={th}>Nama</th>
                                        <th style={th}>Token</th>
                                        <th style={th}>Harga</th>
                                        <th style={th}>Status</th>
                                        <th
                                            style={{
                                                ...th,
                                                textAlign: 'right',
                                            }}
                                        >
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {packs.map((pack) => (
                                        <tr
                                            key={pack.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td style={td}>
                                                <div
                                                    style={{
                                                        fontWeight: 600,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {pack.name}
                                                </div>
                                                {pack.description && (
                                                    <div
                                                        style={{
                                                            fontSize: 12.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {pack.description}
                                                    </div>
                                                )}
                                            </td>
                                            <td style={td}>
                                                {fmt(pack.token_amount)}
                                            </td>
                                            <td style={td}>{rp(pack.price)}</td>
                                            <td style={td}>
                                                <span
                                                    style={{
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                        color: pack.is_active
                                                            ? C.green
                                                            : C.muted,
                                                        background:
                                                            pack.is_active
                                                                ? 'rgba(22,163,74,.1)'
                                                                : C.line,
                                                        padding: '3px 10px',
                                                        borderRadius: 999,
                                                    }}
                                                >
                                                    {pack.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    textAlign: 'right',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                <span
                                                    style={{
                                                        display: 'inline-flex',
                                                        gap: 6,
                                                    }}
                                                >
                                                    <ActionBtn
                                                        icon="pencil"
                                                        label="Edit"
                                                        variant="warning"
                                                        iconOnly
                                                        title={`Ubah ${pack.name}`}
                                                        onClick={() =>
                                                            openEdit(pack)
                                                        }
                                                    />
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        iconOnly
                                                        title={`Hapus ${pack.name}`}
                                                        onClick={() =>
                                                            setConfirm(pack)
                                                        }
                                                    />
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '14px 18px',
                            fontWeight: 600,
                            color: C.navy,
                            borderBottom: `1px solid ${C.line}`,
                        }}
                    >
                        Pesanan Terbaru
                    </div>
                    {orders.length === 0 ? (
                        <div
                            style={{
                                padding: '40px 18px',
                                textAlign: 'center',
                                color: C.muted,
                                fontSize: 13.5,
                            }}
                        >
                            Belum ada pesanan.
                        </div>
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    fontSize: 13.5,
                                }}
                            >
                                <thead>
                                    <tr
                                        style={{
                                            color: C.muted,
                                            textAlign: 'left',
                                        }}
                                    >
                                        <th style={th}>No. Order</th>
                                        <th style={th}>Tenant</th>
                                        <th style={th}>Paket</th>
                                        <th style={th}>Token</th>
                                        <th style={th}>Nominal</th>
                                        <th style={th}>Status</th>
                                        <th style={th}>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orders.map((order) => (
                                        <tr
                                            key={order.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{
                                                    ...td,
                                                    fontFamily: 'monospace',
                                                }}
                                            >
                                                {order.order_number}
                                            </td>
                                            <td style={td}>
                                                {order.tenant ?? '-'}
                                            </td>
                                            <td style={td}>
                                                {order.pack_name}
                                            </td>
                                            <td style={td}>
                                                {fmt(order.token_amount)}
                                            </td>
                                            <td style={td}>
                                                {rp(order.amount)}
                                            </td>
                                            <td style={td}>
                                                <StatusPill
                                                    status={order.status}
                                                />
                                            </td>
                                            <td
                                                style={{
                                                    ...td,
                                                    color: C.muted,
                                                }}
                                            >
                                                {order.created_at ?? '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {modalOpen && (
                <div style={overlay} onClick={closeModal}>
                    <div
                        style={modalCard}
                        onClick={(event) => event.stopPropagation()}
                    >
                        <form onSubmit={submit}>
                            <div
                                style={{
                                    fontSize: 17,
                                    fontWeight: 600,
                                    color: C.navy,
                                    marginBottom: 18,
                                }}
                            >
                                {editing ? 'Ubah Paket' : 'Tambah Paket'}
                            </div>

                            <div style={{ marginBottom: 14 }}>
                                <label style={labelStyle}>Nama Paket</label>
                                <input
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    style={input}
                                    placeholder="Paket Hemat"
                                />
                                {form.errors.name && (
                                    <div style={errStyle}>
                                        {form.errors.name}
                                    </div>
                                )}
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    gap: 12,
                                    marginBottom: 14,
                                }}
                            >
                                <div style={{ flex: 1 }}>
                                    <label style={labelStyle}>
                                        Jumlah Token
                                    </label>
                                    <RupiahInput
                                        value={form.data.token_amount}
                                        onChange={(digits) =>
                                            form.setData('token_amount', digits)
                                        }
                                        prefix=""
                                        placeholder="100.000"
                                        invalid={!!form.errors.token_amount}
                                    />
                                    {form.errors.token_amount && (
                                        <div style={errStyle}>
                                            {form.errors.token_amount}
                                        </div>
                                    )}
                                </div>
                                <div style={{ flex: 1 }}>
                                    <label style={labelStyle}>Harga</label>
                                    <RupiahInput
                                        value={form.data.price}
                                        onChange={(digits) =>
                                            form.setData('price', digits)
                                        }
                                        invalid={!!form.errors.price}
                                    />
                                    {form.errors.price && (
                                        <div style={errStyle}>
                                            {form.errors.price}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div style={{ marginBottom: 14 }}>
                                <label style={labelStyle}>
                                    Deskripsi (opsional)
                                </label>
                                <input
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    style={input}
                                    placeholder="Cocok untuk tim kecil"
                                />
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    gap: 12,
                                    marginBottom: 20,
                                }}
                            >
                                <div style={{ flex: 1 }}>
                                    <label style={labelStyle}>Urutan</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={form.data.sort_order}
                                        onChange={(e) =>
                                            form.setData(
                                                'sort_order',
                                                Number(e.target.value) || 0,
                                            )
                                        }
                                        style={input}
                                    />
                                </div>
                                <label
                                    style={{
                                        flex: 1,
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        marginTop: 26,
                                        fontSize: 14,
                                        color: C.text,
                                        cursor: 'pointer',
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_active}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_active',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Aktif (bisa dibeli tenant)
                                </label>
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'flex-end',
                                    gap: 10,
                                }}
                            >
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    style={btnOut}
                                >
                                    <AIcon name="x" size={15} color={C.text} />
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    style={btnSave}
                                    disabled={form.processing}
                                >
                                    <AIcon name="save" size={15} color="#fff" />
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {confirm && (
                <div style={overlay} onClick={() => setConfirm(null)}>
                    <div
                        style={{ ...modalCard, maxWidth: 400 }}
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div
                            style={{
                                fontSize: 16,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 8,
                            }}
                        >
                            Hapus paket?
                        </div>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginBottom: 20,
                            }}
                        >
                            "{confirm.name}" tidak akan lagi bisa dibeli tenant.
                            Riwayat pesanan tetap tersimpan.
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                            }}
                        >
                            <button
                                onClick={() => setConfirm(null)}
                                style={btnOut}
                            >
                                <AIcon name="x" size={15} color={C.text} />
                                Batal
                            </button>
                            <button onClick={remove} style={btnDanger}>
                                <AIcon name="trash-2" size={15} color="#fff" />
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

const th: CSSProperties = { padding: '11px 18px', fontWeight: 500 };
const td: CSSProperties = { padding: '12px 18px', color: C.text };
const errStyle: CSSProperties = { fontSize: 12.5, color: C.red, marginTop: 5 };
const overlay: CSSProperties = {
    position: 'fixed',
    inset: 0,
    background: 'rgba(14,26,58,.4)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 50,
    padding: 20,
};
const modalCard: CSSProperties = {
    background: '#fff',
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 520,
    boxShadow: '0 20px 60px rgba(14,26,58,.2)',
};
