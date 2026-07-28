import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import TenantAiTokenController from '@/actions/App/Http/Controllers/Avana/TenantAiTokenController';
import { AIcon, btnP, btnProcess, C, card, rp, RupiahInput } from '@/lib/avana';

/* ============================================================
 * Token AI (tenant admin/HR): wallet, buy packs (Pakasir),
 * per-role cap allocation and order history.
 * ============================================================ */

interface Usage {
    period: string;
    free_quota: number;
    free_used: number;
    free_remaining: number;
    wallet_balance: number;
    user_cap: number | null;
    user_used: number;
    effective_remaining: number | null;
}

interface Pack {
    id: number;
    name: string;
    token_amount: number;
    price: number;
    description: string | null;
}

interface Order {
    id: number;
    order_number: string;
    pack_name: string;
    token_amount: number;
    amount: number;
    status: string;
    payment_method: string | null;
    created_at: string | null;
}

interface RoleRow {
    id: number;
    name: string;
    code: string;
    members: number;
    cap: number | null;
    used: number;
}

interface PageProps {
    usage: Usage;
    defaultUserCap: number | null;
    packs: Pack[];
    orders: Order[];
    roles: RoleRow[];
}

type FlashProps = {
    flash?: { success?: string; error?: string; info?: string };
};

const ORDER_STATUS: Record<string, [string, string, string]> = {
    completed: ['Lunas', C.green, 'rgba(22,163,74,.1)'],
    pending: ['Menunggu', C.amber, 'rgba(217,119,6,.1)'],
    failed: ['Gagal', C.red, 'rgba(220,38,38,.1)'],
};

const ROLE_COLOR: Record<string, string> = {
    admin_tenant_hr: C.primary,
    manager: C.amber,
    finance: C.green,
    employee: C.sky,
};

function fmt(n: number): string {
    return Number(n).toLocaleString('id-ID');
}

/** Translucent tint of a #RRGGBB colour. */
function hexA(hex: string, a: number): string {
    const h = hex.replace('#', '');
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, ${a})`;
}

function IconBadge({ icon, color }: { icon: string; color: string }) {
    return (
        <div
            style={{
                width: 40,
                height: 40,
                borderRadius: 11,
                flex: 'none',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: hexA(color, 0.12),
            }}
        >
            <AIcon name={icon} size={20} color={color} />
        </div>
    );
}

function Bar({ pct, color }: { pct: number; color: string }) {
    return (
        <div
            style={{
                height: 6,
                borderRadius: 999,
                background: C.line,
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    width: `${Math.max(0, Math.min(100, pct))}%`,
                    height: '100%',
                    background: color,
                    borderRadius: 999,
                    transition: 'width .3s ease',
                }}
            />
        </div>
    );
}

export default function TokenAi({
    usage,
    defaultUserCap,
    packs,
    orders,
    roles,
}: PageProps) {
    const { flash } = usePage<FlashProps>().props;

    const [defaultCap, setDefaultCap] = useState<string>(
        defaultUserCap != null ? String(defaultUserCap) : '',
    );
    const [caps, setCaps] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            roles.map((r) => [r.id, r.cap != null ? String(r.cap) : '']),
        ),
    );
    const [savingAlloc, setSavingAlloc] = useState(false);
    const [buying, setBuying] = useState<number | null>(null);
    const [hoverPack, setHoverPack] = useState<number | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }

        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }

        if (flash?.info) {
            toast(flash.info, { id: flash.info });
        }
    }, [flash?.success, flash?.error, flash?.info]);

    const buy = (packId: number) => {
        setBuying(packId);
        router.post(
            TenantAiTokenController.purchase().url,
            { pack_id: packId },
            { onFinish: () => setBuying(null) },
        );
    };

    const saveAllocation = () => {
        setSavingAlloc(true);
        router.put(
            TenantAiTokenController.updateAllocation().url,
            {
                default_user_cap: defaultCap === '' ? null : Number(defaultCap),
                caps: roles.map((r) => ({
                    role_id: r.id,
                    monthly_cap:
                        caps[r.id] === '' || caps[r.id] === undefined
                            ? null
                            : Number(caps[r.id]),
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setSavingAlloc(false),
            },
        );
    };

    const totalAvailable =
        usage.effective_remaining != null
            ? usage.effective_remaining
            : usage.free_remaining + usage.wallet_balance;

    const quotaPct =
        usage.free_quota > 0
            ? Math.round((usage.free_used / usage.free_quota) * 100)
            : 0;

    return (
        <>
            <Head title="Token AI" />
            <div style={{ padding: '28px 32px', maxWidth: 1180 }}>
                <div style={{ marginBottom: 24 }}>
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
                        <span>Sistem</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Token AI</span>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            flexWrap: 'wrap',
                        }}
                    >
                        <h1
                            style={{
                                fontSize: 25,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            Token AI
                        </h1>
                        <span
                            style={{
                                fontSize: 12,
                                fontWeight: 600,
                                color: C.primary,
                                background: hexA(C.primary, 0.1),
                                padding: '4px 11px',
                                borderRadius: 999,
                            }}
                        >
                            {usage.period}
                        </span>
                    </div>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 5 }}>
                        Isi saldo token untuk AI Assistant dan atur jatah tiap
                        role.
                    </div>
                </div>

                {/* ---- Hero meter row ---- */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(240px, 1fr))',
                        gap: 16,
                        marginBottom: 30,
                    }}
                >
                    <div
                        style={{
                            ...card,
                            padding: '20px 22px',
                            background: `linear-gradient(135deg, ${hexA(
                                C.primary,
                                0.06,
                            )}, #fff 70%)`,
                            border: `1px solid ${hexA(C.primary, 0.2)}`,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <IconBadge icon="wallet" color={C.primary} />
                            <div style={{ fontSize: 13, color: C.muted }}>
                                Saldo Wallet
                            </div>
                        </div>
                        <div
                            style={{
                                fontSize: 30,
                                fontWeight: 700,
                                color: C.navy,
                                marginTop: 14,
                                lineHeight: 1.1,
                            }}
                        >
                            {fmt(usage.wallet_balance)}
                            <span
                                style={{
                                    fontSize: 14,
                                    fontWeight: 500,
                                    color: C.faint,
                                }}
                            >
                                {' '}
                                token
                            </span>
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginTop: 4,
                            }}
                        >
                            Permanen · tidak hangus
                        </div>
                    </div>

                    <div style={{ ...card, padding: '20px 22px' }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <IconBadge icon="calendar-clock" color={C.amber} />
                            <div style={{ fontSize: 13, color: C.muted }}>
                                Kuota Bulanan
                            </div>
                        </div>
                        <div
                            style={{
                                fontSize: 22,
                                fontWeight: 700,
                                color: C.navy,
                                marginTop: 14,
                            }}
                        >
                            {fmt(usage.free_used)}
                            <span style={{ color: C.faint, fontWeight: 500 }}>
                                {' / '}
                                {usage.free_quota > 0
                                    ? fmt(usage.free_quota)
                                    : 'Tanpa batas'}
                            </span>
                        </div>
                        <div style={{ marginTop: 12 }}>
                            {usage.free_quota > 0 ? (
                                <Bar pct={quotaPct} color={C.amber} />
                            ) : (
                                <div
                                    style={{
                                        height: 6,
                                        borderRadius: 999,
                                        background: C.line,
                                    }}
                                />
                            )}
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginTop: 8,
                            }}
                        >
                            Reset tiap awal bulan
                        </div>
                    </div>

                    <div style={{ ...card, padding: '20px 22px' }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <IconBadge icon="coins" color={C.green} />
                            <div style={{ fontSize: 13, color: C.muted }}>
                                Total Tersedia
                            </div>
                        </div>
                        <div
                            style={{
                                fontSize: 30,
                                fontWeight: 700,
                                color: C.navy,
                                marginTop: 14,
                                lineHeight: 1.1,
                            }}
                        >
                            {fmt(totalAvailable)}
                            <span
                                style={{
                                    fontSize: 14,
                                    fontWeight: 500,
                                    color: C.faint,
                                }}
                            >
                                {' '}
                                token
                            </span>
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.faint,
                                marginTop: 4,
                            }}
                        >
                            Kuota bulanan + wallet
                        </div>
                    </div>
                </div>

                {/* ---- Buy packs ---- */}
                <div style={{ marginBottom: 12 }}>
                    <div
                        style={{
                            fontSize: 16.5,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Beli Token
                    </div>
                    <div style={{ fontSize: 13, color: C.muted, marginTop: 2 }}>
                        Pembayaran diproses aman lewat payment gateway (QRIS /
                        VA).
                    </div>
                </div>
                {packs.length === 0 ? (
                    <div
                        style={{
                            ...card,
                            padding: '40px 18px',
                            textAlign: 'center',
                            color: C.muted,
                            fontSize: 13.5,
                            marginBottom: 30,
                        }}
                    >
                        Belum ada paket token yang tersedia.
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(250px, 1fr))',
                            gap: 16,
                            marginBottom: 30,
                        }}
                    >
                        {packs.map((pack) => {
                            const perK =
                                pack.token_amount > 0
                                    ? Math.round(
                                          pack.price /
                                              (pack.token_amount / 1000),
                                      )
                                    : 0;
                            const hovered = hoverPack === pack.id;

                            return (
                                <div
                                    key={pack.id}
                                    onMouseEnter={() => setHoverPack(pack.id)}
                                    onMouseLeave={() => setHoverPack(null)}
                                    style={{
                                        ...card,
                                        padding: 0,
                                        overflow: 'hidden',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        transform: hovered
                                            ? 'translateY(-3px)'
                                            : 'none',
                                        boxShadow: hovered
                                            ? '0 12px 28px rgba(14,26,58,.12)'
                                            : '0 1px 2px rgba(14,26,58,.04)',
                                        borderColor: hovered
                                            ? hexA(C.primary, 0.35)
                                            : C.border,
                                        transition:
                                            'transform .18s ease, box-shadow .18s ease, border-color .18s ease',
                                    }}
                                >
                                    <div
                                        style={{
                                            height: 4,
                                            background: `linear-gradient(90deg, ${C.primary}, ${C.sky})`,
                                        }}
                                    />
                                    <div
                                        style={{
                                            padding: 18,
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 6,
                                            flex: 1,
                                        }}
                                    >
                                        <div
                                            style={{
                                                fontWeight: 600,
                                                color: C.navy,
                                                fontSize: 15,
                                            }}
                                        >
                                            {pack.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 26,
                                                fontWeight: 700,
                                                color: C.primary,
                                                lineHeight: 1.1,
                                            }}
                                        >
                                            {fmt(pack.token_amount)}
                                            <span
                                                style={{
                                                    fontSize: 13,
                                                    color: C.faint,
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {' '}
                                                token
                                            </span>
                                        </div>
                                        {pack.description && (
                                            <div
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.muted,
                                                }}
                                            >
                                                {pack.description}
                                            </div>
                                        )}
                                        <div
                                            style={{
                                                marginTop: 'auto',
                                                paddingTop: 12,
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    fontSize: 18,
                                                    fontWeight: 700,
                                                    color: C.text,
                                                }}
                                            >
                                                {rp(pack.price)}
                                            </div>
                                            {perK > 0 && (
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                        marginTop: 1,
                                                    }}
                                                >
                                                    ≈ {rp(perK)} / 1.000 token
                                                </div>
                                            )}
                                            <button
                                                onClick={() => buy(pack.id)}
                                                disabled={buying === pack.id}
                                                style={{
                                                    ...btnProcess,
                                                    marginTop: 12,
                                                    width: '100%',
                                                    justifyContent: 'center',
                                                }}
                                            >
                                                <AIcon
                                                    name="shopping-cart"
                                                    size={15}
                                                    color="#fff"
                                                />
                                                {buying === pack.id
                                                    ? 'Memproses…'
                                                    : 'Beli Sekarang'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* ---- Allocation per role ---- */}
                <div style={{ ...card, padding: 0, marginBottom: 26 }}>
                    <div
                        style={{
                            padding: '18px 22px',
                            borderBottom: `1px solid ${C.line}`,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 16.5,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Alokasi per Role
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginTop: 3,
                            }}
                        >
                            Batas token per pengguna tiap bulan, diatur per
                            role. Kosong = ikut default; default kosong = tanpa
                            batas. Bila pengguna punya banyak role, batas
                            terlonggar yang berlaku.
                        </div>
                    </div>

                    <div style={{ padding: '18px 22px' }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'flex-end',
                                gap: 12,
                                flexWrap: 'wrap',
                                paddingBottom: 18,
                                marginBottom: 6,
                                borderBottom: `1px dashed ${C.border}`,
                            }}
                        >
                            <div style={{ flex: '1 1 240px', maxWidth: 300 }}>
                                <label
                                    style={{
                                        display: 'block',
                                        fontSize: 13,
                                        fontWeight: 500,
                                        color: C.text,
                                        marginBottom: 6,
                                    }}
                                >
                                    Default per pengguna / bulan
                                </label>
                                <RupiahInput
                                    value={defaultCap}
                                    onChange={setDefaultCap}
                                    prefix=""
                                    placeholder="Tanpa batas"
                                />
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.faint,
                                    paddingBottom: 12,
                                }}
                            >
                                Berlaku untuk role yang batasnya dikosongkan.
                            </div>
                        </div>

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
                                        <th style={th}>Role</th>
                                        <th style={th}>Pengguna</th>
                                        <th style={th}>Terpakai (bln ini)</th>
                                        <th style={{ ...th, width: 190 }}>
                                            Batas / pengguna / bulan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roles.map((r) => {
                                        const color =
                                            ROLE_COLOR[r.code] ?? C.muted;

                                        return (
                                            <tr
                                                key={r.id}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td style={td}>
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            alignItems:
                                                                'center',
                                                            gap: 9,
                                                        }}
                                                    >
                                                        <span
                                                            style={{
                                                                width: 9,
                                                                height: 9,
                                                                borderRadius: 999,
                                                                background:
                                                                    color,
                                                                flex: 'none',
                                                            }}
                                                        />
                                                        <span
                                                            style={{
                                                                fontWeight: 600,
                                                                color: C.text,
                                                            }}
                                                        >
                                                            {r.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td style={td}>
                                                    <span
                                                        style={{
                                                            fontSize: 12,
                                                            fontWeight: 600,
                                                            color: C.muted,
                                                            background:
                                                                C.surface,
                                                            padding: '3px 10px',
                                                            borderRadius: 999,
                                                        }}
                                                    >
                                                        {fmt(r.members)} user
                                                    </span>
                                                </td>
                                                <td
                                                    style={{
                                                        ...td,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {fmt(r.used)}
                                                </td>
                                                <td style={td}>
                                                    <div style={{ width: 170 }}>
                                                        <RupiahInput
                                                            value={
                                                                caps[r.id] ?? ''
                                                            }
                                                            onChange={(
                                                                digits,
                                                            ) =>
                                                                setCaps(
                                                                    (prev) => ({
                                                                        ...prev,
                                                                        [r.id]:
                                                                            digits,
                                                                    }),
                                                                )
                                                            }
                                                            prefix=""
                                                            placeholder="default"
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                marginTop: 18,
                            }}
                        >
                            <button
                                onClick={saveAllocation}
                                disabled={savingAlloc}
                                style={{ ...btnP, background: C.green }}
                            >
                                <AIcon name="check" size={15} color="#fff" />
                                {savingAlloc ? 'Menyimpan…' : 'Simpan Alokasi'}
                            </button>
                        </div>
                    </div>
                </div>

                {/* ---- Order history ---- */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '16px 22px',
                            fontWeight: 600,
                            color: C.navy,
                            borderBottom: `1px solid ${C.line}`,
                        }}
                    >
                        Riwayat Pembelian
                    </div>
                    {orders.length === 0 ? (
                        <div
                            style={{
                                padding: '44px 18px',
                                textAlign: 'center',
                                color: C.muted,
                                fontSize: 13.5,
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 10,
                            }}
                        >
                            <AIcon
                                name="receipt-text"
                                size={26}
                                color={C.faint}
                            />
                            Belum ada pembelian.
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
                                        <th style={th}>Paket</th>
                                        <th style={th}>Token</th>
                                        <th style={th}>Nominal</th>
                                        <th style={th}>Status</th>
                                        <th style={th}>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orders.map((order) => {
                                        const [label, color, bg] = ORDER_STATUS[
                                            order.status
                                        ] ?? [order.status, C.muted, C.line];

                                        return (
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
                                                    {order.pack_name}
                                                </td>
                                                <td style={td}>
                                                    {fmt(order.token_amount)}
                                                </td>
                                                <td style={td}>
                                                    {rp(order.amount)}
                                                </td>
                                                <td style={td}>
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
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

const th: CSSProperties = { padding: '11px 18px', fontWeight: 500 };
const td: CSSProperties = { padding: '13px 18px', color: C.text };
