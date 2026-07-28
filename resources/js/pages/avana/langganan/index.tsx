import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import TenantSubscriptionController from '@/actions/App/Http/Controllers/Avana/TenantSubscriptionController';
import { WaIcon } from '@/components/avana-ui/wa-icon';
import { AIcon, C, card, hexA, rp } from '@/lib/avana';

/* ============================================================
 * Langganan (tenant admin/HR): current term, pricing per package
 * and duration, self-service renewal paid through Pakasir.
 * ============================================================ */

interface Quote {
    cycle: string;
    months: number;
    label: string;
    monthly_price: number;
    price: number;
    list_price: number;
    discount_percent: number;
}

interface PackageRow {
    id: number;
    name: string;
    tagline: string | null;
    code: string;
    is_popular: boolean;
    is_current: boolean;
    max_users: number | null;
    max_employees: number | null;
    max_branches: number | null;
    ai_token_quota: number | null;
    feature_list: string[];
    features: string[];
    grants_all_features: boolean;
    quotes: Quote[];
}

interface Term {
    cycle: string;
    label: string;
    discount_percent: number;
}

interface OrderRow {
    id: number;
    order_number: string;
    package_name: string;
    months: number;
    amount: number;
    status: string;
    payment_method: string | null;
    period_end: string | null;
    applied: boolean;
    created_at: string | null;
}

interface InvoiceRow {
    number: string;
    total: number;
    status: string;
    issue_date: string | null;
    period_end: string | null;
}

interface Notice {
    end_date: string;
    end_date_label: string;
    days_left: number;
    level: string;
    package: string | null;
}

interface PageProps {
    subscription: Notice | null;
    tenant: {
        package: string | null;
        max_users: number;
        max_employees: number;
        max_branches: number;
        users_count: number;
        employees_count: number;
    };
    packages: PackageRow[];
    terms: Term[];
    orders: OrderRow[];
    invoices: InvoiceRow[];
}

type FlashProps = {
    flash?: { success?: string; error?: string; info?: string };
    website?: { contact?: { phone?: string; whatsapp?: string } };
};

const ORDER_STATUS: Record<string, [string, string, string]> = {
    completed: ['Lunas', C.green, 'rgba(22,163,74,.1)'],
    pending: ['Menunggu Bayar', C.amber, 'rgba(217,119,6,.1)'],
    failed: ['Gagal', C.red, 'rgba(220,38,38,.1)'],
};

const LEVEL_TONE: Record<string, string> = {
    expired: C.red,
    critical: C.red,
    warning: C.amber,
    ok: C.green,
};

function fmt(n: number): string {
    return Number(n).toLocaleString('id-ID');
}

/** `YYYY-MM-DD` to `d Mon Y`, split by hand to avoid Date parsing quirks. */
function fmtDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const MONTHS = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ];
    const [y, m, d] = value.split(' ')[0].split('-');

    return `${Number(d)} ${MONTHS[Number(m) - 1]} ${y}`;
}

function countdown(daysLeft: number): string {
    if (daysLeft < 0) {
        return 'Sudah berakhir';
    }

    if (daysLeft === 0) {
        return 'Berakhir hari ini';
    }

    return `${daysLeft} hari lagi`;
}

const thCell = {
    padding: '10px 12px',
    textAlign: 'left' as const,
    fontSize: 11,
    fontWeight: 600,
    color: C.faint,
    letterSpacing: '.03em',
};

const tdCell = { padding: '12px', fontSize: 13, color: C.text };

export default function Langganan({
    subscription,
    tenant,
    packages,
    terms,
    orders,
    invoices,
}: PageProps) {
    const { flash, website } = usePage<FlashProps>().props;
    const [cycle, setCycle] = useState<string>(
        terms[terms.length - 1]?.cycle ?? 'monthly',
    );
    const [paying, setPaying] = useState<number | null>(null);

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

    const whatsapp =
        website?.contact?.whatsapp || website?.contact?.phone || '';
    const waHref = `https://wa.me/${whatsapp.replace(/[^\d]/g, '')}`;

    const renew = (packageId: number) => {
        setPaying(packageId);
        router.post(
            TenantSubscriptionController.purchase().url,
            { package_id: packageId, cycle },
            { onFinish: () => setPaying(null) },
        );
    };

    const tone = LEVEL_TONE[subscription?.level ?? 'ok'] ?? C.green;

    return (
        <>
            <Head title="Langganan" />
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
                        <span style={{ color: C.muted }}>Langganan</span>
                    </div>
                    <h1
                        style={{
                            fontSize: 25,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Langganan
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 5 }}>
                        Perpanjang masa aktif AvanaHR dan pilih paket sesuai
                        kebutuhan. Pembayaran diproses otomatis.
                    </div>
                </div>

                {/* ---- Current term ---- */}
                <div
                    style={{
                        ...card,
                        padding: '20px 22px',
                        marginBottom: 26,
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(190px, 1fr))',
                        gap: 18,
                        alignItems: 'center',
                        border: `1px solid ${hexA(tone, 0.25)}`,
                        background: `linear-gradient(135deg, ${hexA(tone, 0.05)}, #fff 65%)`,
                    }}
                >
                    <div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>
                            Paket Aktif
                        </div>
                        <div
                            style={{
                                fontSize: 19,
                                fontWeight: 700,
                                color: C.navy,
                                marginTop: 4,
                            }}
                        >
                            {tenant.package ?? 'Belum berpaket'}
                        </div>
                    </div>
                    <div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>
                            Masa Aktif Sampai
                        </div>
                        <div
                            style={{
                                fontSize: 19,
                                fontWeight: 700,
                                color: C.navy,
                                marginTop: 4,
                            }}
                        >
                            {subscription?.end_date_label ?? 'Tanpa batas'}
                        </div>
                    </div>
                    <div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>
                            Sisa Waktu
                        </div>
                        <div
                            style={{
                                fontSize: 19,
                                fontWeight: 700,
                                color: tone,
                                marginTop: 4,
                            }}
                        >
                            {subscription
                                ? countdown(subscription.days_left)
                                : '—'}
                        </div>
                    </div>
                    <div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>
                            Pemakaian Kuota
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.text,
                                marginTop: 6,
                                lineHeight: 1.5,
                            }}
                        >
                            {fmt(tenant.users_count)} / {fmt(tenant.max_users)}{' '}
                            pengguna
                            <br />
                            {fmt(tenant.employees_count)} /{' '}
                            {fmt(tenant.max_employees)} karyawan
                        </div>
                    </div>
                    <a
                        href={waHref}
                        target="_blank"
                        rel="noreferrer"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 8,
                            height: 40,
                            padding: '0 15px',
                            borderRadius: 10,
                            border: '1px solid rgba(37,211,102,.4)',
                            background: '#fff',
                            color: '#128C7E',
                            fontWeight: 600,
                            fontSize: 13.5,
                            textDecoration: 'none',
                        }}
                    >
                        <WaIcon size={16} color="#25D366" />
                        Tanya via WhatsApp
                    </a>
                </div>

                {/* ---- Term switch ---- */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        flexWrap: 'wrap',
                        marginBottom: 16,
                    }}
                >
                    <div style={{ fontSize: 13.5, color: C.muted }}>
                        Pilih durasi:
                    </div>
                    <div
                        style={{
                            display: 'inline-flex',
                            gap: 4,
                            padding: 4,
                            background: C.surface,
                            border: `1px solid ${C.border}`,
                            borderRadius: 11,
                        }}
                    >
                        {terms.map((term) => {
                            const active = term.cycle === cycle;

                            return (
                                <button
                                    key={term.cycle}
                                    type="button"
                                    onClick={() => setCycle(term.cycle)}
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 6,
                                        padding: '8px 14px',
                                        borderRadius: 8,
                                        border: 'none',
                                        cursor: 'pointer',
                                        fontSize: 13,
                                        fontWeight: active ? 600 : 500,
                                        background: active
                                            ? '#fff'
                                            : 'transparent',
                                        color: active ? C.navy : C.muted,
                                        boxShadow: active
                                            ? '0 1px 3px rgba(15,26,58,.12)'
                                            : 'none',
                                    }}
                                >
                                    {term.label}
                                    {term.discount_percent > 0 && (
                                        <span
                                            style={{
                                                fontSize: 11,
                                                fontWeight: 600,
                                                color: C.green,
                                                background:
                                                    'rgba(22,163,74,.12)',
                                                padding: '2px 7px',
                                                borderRadius: 999,
                                            }}
                                        >
                                            -{term.discount_percent}%
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* ---- Pricing cards ---- */}
                {packages.length === 0 ? (
                    <div
                        style={{
                            ...card,
                            padding: 28,
                            textAlign: 'center',
                            fontSize: 13.5,
                            color: C.muted,
                            marginBottom: 30,
                        }}
                    >
                        Belum ada paket yang tersedia. Hubungi tim AvanaHR.
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(260px, 1fr))',
                            gap: 16,
                            marginBottom: 30,
                        }}
                    >
                        {packages.map((pkg) => {
                            const quote =
                                pkg.quotes.find((q) => q.cycle === cycle) ??
                                pkg.quotes[0];
                            const highlight = pkg.is_current || pkg.is_popular;

                            return (
                                <div
                                    key={pkg.id}
                                    style={{
                                        ...card,
                                        padding: '22px',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 14,
                                        border: highlight
                                            ? `1.5px solid ${hexA(C.primary, 0.45)}`
                                            : `1px solid ${C.border}`,
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 8,
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        <div
                                            style={{
                                                fontSize: 16,
                                                fontWeight: 700,
                                                color: C.navy,
                                            }}
                                        >
                                            {pkg.name}
                                        </div>
                                        {pkg.is_current && (
                                            <span
                                                style={{
                                                    fontSize: 11,
                                                    fontWeight: 600,
                                                    color: C.primary,
                                                    background: hexA(
                                                        C.primary,
                                                        0.1,
                                                    ),
                                                    padding: '3px 9px',
                                                    borderRadius: 999,
                                                }}
                                            >
                                                Paket Anda
                                            </span>
                                        )}
                                        {pkg.is_popular && !pkg.is_current && (
                                            <span
                                                style={{
                                                    fontSize: 11,
                                                    fontWeight: 600,
                                                    color: C.amber,
                                                    background:
                                                        'rgba(217,119,6,.12)',
                                                    padding: '3px 9px',
                                                    borderRadius: 999,
                                                }}
                                            >
                                                Populer
                                            </span>
                                        )}
                                    </div>

                                    {pkg.tagline && (
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: C.muted,
                                                marginTop: -8,
                                            }}
                                        >
                                            {pkg.tagline}
                                        </div>
                                    )}

                                    <div>
                                        <div
                                            style={{
                                                fontSize: 26,
                                                fontWeight: 700,
                                                color: C.navy,
                                                lineHeight: 1.15,
                                            }}
                                        >
                                            {rp(quote.price)}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.faint,
                                                marginTop: 3,
                                            }}
                                        >
                                            untuk {quote.months} bulan
                                            {quote.discount_percent > 0 && (
                                                <>
                                                    {' · '}
                                                    <span
                                                        style={{
                                                            textDecoration:
                                                                'line-through',
                                                        }}
                                                    >
                                                        {rp(quote.list_price)}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    <div
                                        style={{
                                            display: 'grid',
                                            gap: 7,
                                            fontSize: 12.5,
                                            color: C.text,
                                            borderTop: `1px solid ${C.line}`,
                                            paddingTop: 13,
                                        }}
                                    >
                                        <PackageLimit
                                            label="Pengguna"
                                            value={pkg.max_users}
                                        />
                                        <PackageLimit
                                            label="Karyawan"
                                            value={pkg.max_employees}
                                        />
                                        <PackageLimit
                                            label="Cabang"
                                            value={pkg.max_branches}
                                        />
                                        <PackageLimit
                                            label="Token AI / bln"
                                            value={pkg.ai_token_quota}
                                        />
                                    </div>

                                    <PackageFeatures
                                        features={pkg.features}
                                        grantsAll={pkg.grants_all_features}
                                        extras={pkg.feature_list}
                                    />

                                    <button
                                        type="button"
                                        disabled={paying !== null}
                                        onClick={() => renew(pkg.id)}
                                        style={{
                                            marginTop: 'auto',
                                            height: 42,
                                            borderRadius: 10,
                                            border: 'none',
                                            cursor:
                                                paying !== null
                                                    ? 'wait'
                                                    : 'pointer',
                                            background: highlight
                                                ? C.primary
                                                : '#fff',
                                            color: highlight
                                                ? '#fff'
                                                : C.primary,
                                            boxShadow: highlight
                                                ? 'none'
                                                : `inset 0 0 0 1px ${hexA(C.primary, 0.4)}`,
                                            fontSize: 13.5,
                                            fontWeight: 600,
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            gap: 8,
                                            opacity: paying !== null ? 0.7 : 1,
                                        }}
                                    >
                                        <AIcon
                                            name={
                                                paying === pkg.id
                                                    ? 'loader-circle'
                                                    : 'credit-card'
                                            }
                                            size={15}
                                            color={
                                                highlight ? '#fff' : C.primary
                                            }
                                        />
                                        {paying === pkg.id
                                            ? 'Menyiapkan pembayaran…'
                                            : pkg.is_current
                                              ? 'Perpanjang Paket Ini'
                                              : 'Pilih & Bayar'}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* ---- Renewal history ---- */}
                <div style={{ ...card, marginBottom: 20 }}>
                    <div
                        style={{
                            padding: '15px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            fontSize: 14.5,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Riwayat Perpanjangan
                    </div>
                    <div style={{ padding: orders.length === 0 ? 18 : 0 }}>
                        {orders.length === 0 ? (
                            <div style={{ fontSize: 13, color: C.faint }}>
                                Belum ada perpanjangan mandiri.
                            </div>
                        ) : (
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 720,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th style={thCell}>NO. PESANAN</th>
                                            <th style={thCell}>PAKET</th>
                                            <th style={thCell}>DURASI</th>
                                            <th
                                                style={{
                                                    ...thCell,
                                                    textAlign: 'right',
                                                }}
                                            >
                                                NILAI
                                            </th>
                                            <th style={thCell}>AKTIF SAMPAI</th>
                                            <th style={thCell}>TANGGAL</th>
                                            <th style={thCell}>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {orders.map((order) => {
                                            const [label, color, bg] =
                                                ORDER_STATUS[order.status] ??
                                                ORDER_STATUS.pending;

                                            return (
                                                <tr
                                                    key={order.id}
                                                    style={{
                                                        borderTop: `1px solid ${C.line}`,
                                                    }}
                                                >
                                                    <td
                                                        style={{
                                                            ...tdCell,
                                                            fontWeight: 500,
                                                        }}
                                                    >
                                                        {order.order_number}
                                                    </td>
                                                    <td style={tdCell}>
                                                        {order.package_name}
                                                    </td>
                                                    <td style={tdCell}>
                                                        {order.months} bulan
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...tdCell,
                                                            textAlign: 'right',
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {rp(order.amount)}
                                                    </td>
                                                    <td style={tdCell}>
                                                        {fmtDate(
                                                            order.period_end,
                                                        )}
                                                    </td>
                                                    <td
                                                        style={{
                                                            ...tdCell,
                                                            color: C.muted,
                                                        }}
                                                    >
                                                        {fmtDate(
                                                            order.created_at,
                                                        )}
                                                    </td>
                                                    <td style={tdCell}>
                                                        <span
                                                            style={{
                                                                fontSize: 11,
                                                                fontWeight: 600,
                                                                color,
                                                                background: bg,
                                                                padding:
                                                                    '3px 9px',
                                                                borderRadius: 999,
                                                            }}
                                                        >
                                                            {label}
                                                        </span>
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

                {/* ---- Invoices ---- */}
                <div style={card}>
                    <div
                        style={{
                            padding: '15px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            fontSize: 14.5,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        Invoice
                    </div>
                    <div style={{ padding: invoices.length === 0 ? 18 : 0 }}>
                        {invoices.length === 0 ? (
                            <div style={{ fontSize: 13, color: C.faint }}>
                                Belum ada invoice.
                            </div>
                        ) : (
                            <div style={{ overflowX: 'auto' }}>
                                <table
                                    style={{
                                        width: '100%',
                                        borderCollapse: 'collapse',
                                        minWidth: 560,
                                    }}
                                >
                                    <thead>
                                        <tr style={{ background: '#FAFBFD' }}>
                                            <th style={thCell}>NO. INVOICE</th>
                                            <th style={thCell}>TERBIT</th>
                                            <th style={thCell}>PERIODE S/D</th>
                                            <th
                                                style={{
                                                    ...thCell,
                                                    textAlign: 'right',
                                                }}
                                            >
                                                TOTAL
                                            </th>
                                            <th style={thCell}>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {invoices.map((invoice) => (
                                            <tr
                                                key={invoice.number}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        ...tdCell,
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    {invoice.number}
                                                </td>
                                                <td
                                                    style={{
                                                        ...tdCell,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {fmtDate(
                                                        invoice.issue_date,
                                                    )}
                                                </td>
                                                <td
                                                    style={{
                                                        ...tdCell,
                                                        color: C.muted,
                                                    }}
                                                >
                                                    {fmtDate(
                                                        invoice.period_end,
                                                    )}
                                                </td>
                                                <td
                                                    style={{
                                                        ...tdCell,
                                                        textAlign: 'right',
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {rp(invoice.total)}
                                                </td>
                                                <td style={tdCell}>
                                                    <span
                                                        style={{
                                                            fontSize: 11,
                                                            fontWeight: 600,
                                                            color:
                                                                invoice.status ===
                                                                'paid'
                                                                    ? C.green
                                                                    : C.amber,
                                                            background:
                                                                invoice.status ===
                                                                'paid'
                                                                    ? 'rgba(22,163,74,.1)'
                                                                    : 'rgba(217,119,6,.1)',
                                                            padding: '3px 9px',
                                                            borderRadius: 999,
                                                        }}
                                                    >
                                                        {invoice.status ===
                                                        'paid'
                                                            ? 'Lunas'
                                                            : 'Belum Bayar'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * The modules a tier unlocks. Long lists collapse behind a "lihat semua" toggle
 * so a package with thirty modules does not bury the price and the button.
 */
function PackageFeatures({
    features,
    grantsAll,
    extras,
}: {
    features: string[];
    grantsAll: boolean;
    extras: string[];
}) {
    const [open, setOpen] = useState(false);
    const VISIBLE = 6;
    const shown = open ? features : features.slice(0, VISIBLE);
    const hidden = features.length - shown.length;

    return (
        <div
            style={{
                borderTop: `1px solid ${C.line}`,
                paddingTop: 13,
                display: 'grid',
                gap: 7,
                fontSize: 12.5,
            }}
        >
            <div style={{ fontSize: 11.5, fontWeight: 600, color: C.faint }}>
                FITUR YANG DIDAPAT
            </div>

            {grantsAll ? (
                <FeatureLine label="Semua modul AvanaHR" />
            ) : features.length === 0 ? (
                <div style={{ color: C.faint }}>
                    Belum ditentukan — hubungi tim AvanaHR.
                </div>
            ) : (
                <>
                    {shown.map((feature) => (
                        <FeatureLine key={feature} label={feature} />
                    ))}
                    {hidden > 0 && (
                        <button
                            type="button"
                            onClick={() => setOpen(true)}
                            style={{
                                justifySelf: 'start',
                                border: 'none',
                                background: 'none',
                                padding: 0,
                                cursor: 'pointer',
                                fontSize: 12,
                                fontWeight: 600,
                                color: C.primary,
                            }}
                        >
                            +{hidden} modul lainnya
                        </button>
                    )}
                </>
            )}

            {extras.map((extra) => (
                <FeatureLine key={extra} label={extra} muted />
            ))}
        </div>
    );
}

/** One ticked feature row. */
function FeatureLine({
    label,
    muted = false,
}: {
    label: string;
    muted?: boolean;
}) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
            <AIcon name="check" size={13} color={muted ? C.faint : C.green} />
            <span style={{ color: muted ? C.muted : C.text }}>{label}</span>
        </div>
    );
}

/** One limit row on a pricing card; an unset limit reads as unlimited. */
function PackageLimit({
    label,
    value,
}: {
    label: string;
    value: number | null;
}) {
    return (
        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <span style={{ color: C.muted }}>{label}</span>
            <span style={{ fontWeight: 600, color: C.navy }}>
                {value === null ? '∞' : fmt(value)}
            </span>
        </div>
    );
}
