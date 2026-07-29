import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AIcon, C, rp } from '@/lib/avana';
import { EmptyState, PageHeader, PageShell, Panel } from './components';

interface Summary {
    /** Left of the company's pools after this user's monthly cap. */
    company_remaining: number | null;
    /** Tokens this user bought and still owns. */
    personal_balance: number;
    /** The two added together — what they can actually spend today. */
    effective_remaining: number | null;
    user_cap: number | null;
    user_used: number;
    period: string;
}

interface Pack {
    id: number;
    name: string;
    token_amount: number;
    price: number;
    description: string | null;
}

interface Order {
    order_number: string;
    pack_name: string;
    token_amount: number;
    amount: number;
    status: string;
    created_at: string | null;
}

const STATUS_LABEL: Record<string, { label: string; color: string }> = {
    pending: { label: 'Menunggu pembayaran', color: C.amber },
    completed: { label: 'Lunas', color: C.green },
    failed: { label: 'Gagal', color: C.red },
};

const num = (value: number): string =>
    new Intl.NumberFormat('id-ID').format(value);

/**
 * Buying AI tokens for yourself.
 *
 * The company's allowance and the personal wallet are shown apart before they
 * are shown together: "sisa 7.000" alone cannot tell somebody whether topping up
 * would help, or whether they are simply waiting for next month.
 */
export default function SayaTokenAi({
    summary,
    packs,
    orders,
}: {
    summary: Summary;
    packs: Pack[];
    orders: Order[];
}) {
    const [buying, setBuying] = useState<number | null>(null);

    const buy = (packId: number) => {
        setBuying(packId);
        router.post(
            '/avana/saya/token-ai/beli',
            { pack_id: packId },
            { onFinish: () => setBuying(null) },
        );
    };

    return (
        <>
            <Head title="Token AI Saya" />
            <PageShell>
                <PageHeader
                    title="Token AI Saya"
                    subtitle="Jatah dari perusahaan, dan token yang Anda beli sendiri."
                />

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(220px, 1fr))',
                        gap: 14,
                        marginBottom: 18,
                    }}
                >
                    <BalanceCard
                        icon="building-2"
                        tone={C.primary}
                        label="Jatah perusahaan"
                        value={
                            summary.company_remaining === null
                                ? 'Tanpa batas'
                                : num(summary.company_remaining)
                        }
                        note={
                            summary.user_cap === null
                                ? `Terpakai ${num(summary.user_used)} bulan ini`
                                : `Terpakai ${num(summary.user_used)} dari ${num(summary.user_cap)} · ${summary.period}`
                        }
                    />
                    <BalanceCard
                        icon="wallet"
                        tone={C.green}
                        label="Token pribadi"
                        value={num(summary.personal_balance)}
                        note="Milik Anda, tidak hangus tiap bulan"
                    />
                    <BalanceCard
                        icon="sparkles"
                        tone={C.navy}
                        label="Bisa dipakai sekarang"
                        value={
                            summary.effective_remaining === null
                                ? 'Tanpa batas'
                                : num(summary.effective_remaining)
                        }
                        note="Token pribadi dipakai lebih dulu"
                    />
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 10,
                        padding: '12px 14px',
                        borderRadius: 10,
                        background: 'rgba(47,84,201,.05)',
                        border: `1px solid ${C.border}`,
                        fontSize: 12.5,
                        color: C.text,
                        lineHeight: 1.6,
                        marginBottom: 18,
                    }}
                >
                    <AIcon name="info" size={16} color={C.primary} />
                    <span>
                        Token pribadi Anda dipakai lebih dulu; jatah perusahaan
                        baru dipakai setelah token pribadi habis. Token pribadi
                        tidak dibatasi jatah bulanan dari admin dan tidak hangus
                        tiap bulan.
                    </span>
                </div>

                <Panel
                    title="Beli token"
                    subtitle="Pembayaran lewat Pakasir. Token masuk otomatis setelah lunas."
                >
                    {packs.length === 0 ? (
                        <EmptyState
                            icon="package"
                            message="Belum ada paket token yang dijual."
                        />
                    ) : (
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fill, minmax(240px, 1fr))',
                                gap: 12,
                            }}
                        >
                            {packs.map((pack) => (
                                <div
                                    key={pack.id}
                                    style={{
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 12,
                                        padding: 16,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 4,
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 14,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {pack.name}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 20,
                                            fontWeight: 700,
                                            color: C.primary,
                                        }}
                                    >
                                        {num(pack.token_amount)}
                                        <span
                                            style={{
                                                fontSize: 12,
                                                fontWeight: 500,
                                                color: C.muted,
                                                marginLeft: 5,
                                            }}
                                        >
                                            token
                                        </span>
                                    </div>
                                    {pack.description && (
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.muted,
                                                lineHeight: 1.5,
                                            }}
                                        >
                                            {pack.description}
                                        </div>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => buy(pack.id)}
                                        disabled={buying !== null}
                                        style={{
                                            marginTop: 10,
                                            height: 38,
                                            border: 'none',
                                            borderRadius: 9,
                                            background: C.primary,
                                            color: '#fff',
                                            fontSize: 13,
                                            fontWeight: 600,
                                            cursor:
                                                buying !== null
                                                    ? 'not-allowed'
                                                    : 'pointer',
                                            opacity: buying !== null ? 0.7 : 1,
                                        }}
                                    >
                                        {buying === pack.id
                                            ? 'Menyiapkan…'
                                            : `Beli ${rp(pack.price)}`}
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>

                <div style={{ marginTop: 16 }}>
                    <Panel title="Riwayat pembelian" padded={false}>
                        {orders.length === 0 ? (
                            <div style={{ padding: 18 }}>
                                <EmptyState
                                    icon="receipt"
                                    message="Belum ada pembelian token pribadi."
                                />
                            </div>
                        ) : (
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                    }}
                                >
                                    <tbody>
                                        {orders.map((order) => {
                                            const status = STATUS_LABEL[
                                                order.status
                                            ] ?? {
                                                label: order.status,
                                                color: C.muted,
                                            };

                                            return (
                                                <tr key={order.order_number}>
                                                    <td style={cell}>
                                                        <div
                                                            style={{
                                                                fontSize: 13,
                                                                fontWeight: 500,
                                                                color: C.text,
                                                            }}
                                                        >
                                                            {order.pack_name}
                                                        </div>
                                                        <div
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            {order.order_number}
                                                            {order.created_at
                                                                ? ` · ${order.created_at}`
                                                                : ''}
                                                        </div>
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...cell,
                                                            textAlign: 'right',
                                                            whiteSpace:
                                                                'nowrap',
                                                        }}
                                                    >
                                                        {num(
                                                            order.token_amount,
                                                        )}{' '}
                                                        token
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...cell,
                                                            textAlign: 'right',
                                                            whiteSpace:
                                                                'nowrap',
                                                        }}
                                                    >
                                                        {rp(order.amount)}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...cell,
                                                            textAlign: 'right',
                                                            color: status.color,
                                                            fontWeight: 600,
                                                            whiteSpace:
                                                                'nowrap',
                                                        }}
                                                    >
                                                        {status.label}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                </div>
            </PageShell>
        </>
    );
}

const cell = {
    padding: '12px 16px',
    borderBottom: `1px solid ${C.line}`,
    fontSize: 13,
    color: C.text,
} as const;

/** One of the three balances across the top. */
function BalanceCard({
    icon,
    tone,
    label,
    value,
    note,
}: {
    icon: string;
    tone: string;
    label: string;
    value: string;
    note: string;
}) {
    return (
        <div
            style={{
                border: `1px solid ${C.border}`,
                borderRadius: 12,
                background: '#fff',
                padding: 16,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    marginBottom: 10,
                }}
            >
                <span
                    style={{
                        width: 28,
                        height: 28,
                        borderRadius: 8,
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: `${tone}1f`,
                    }}
                >
                    <AIcon name={icon} size={15} color={tone} />
                </span>
                <span style={{ fontSize: 12.5, color: C.muted }}>{label}</span>
            </div>
            <div style={{ fontSize: 24, fontWeight: 700, color: C.navy }}>
                {value}
            </div>
            <div style={{ fontSize: 11.5, color: C.faint, marginTop: 4 }}>
                {note}
            </div>
        </div>
    );
}
