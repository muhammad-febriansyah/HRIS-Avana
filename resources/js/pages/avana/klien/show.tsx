import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { AIcon, btnOut, btnP, C, card, rp } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    withError,
} from './components';
import type { TenantAdmin, TenantCredentials } from './types';

type Pkg = {
    name: string;
    code: string;
    price: number;
    billing_cycle: string;
} | null;

type TenantData = {
    id: number;
    name: string;
    company_name: string | null;
    slug: string;
    logo: string | null;
    status: string;
    billing_status: string | null;
    package: Pkg;
    max_users: number;
    max_employees: number;
    max_branches: number;
    users_count: number;
    employees_count: number;
    branches_count: number;
    start_date: string | null;
    end_date: string | null;
    created_at: string | null;
};

type Invoice = {
    id: number;
    number: string;
    total: number;
    status: string;
    issue_date: string | null;
    due_date: string | null;
};

type ShowProps = {
    tenant: TenantData;
    subscription: {
        active: {
            package: string | null;
            price: number;
            billing_cycle: string;
            status: string;
            start_date: string | null;
            end_date: string | null;
        } | null;
        total: number;
    };
    billing: {
        paid_total: number;
        outstanding: number;
        overdue_count: number;
        invoice_count: number;
        recent: Invoice[];
    };
    features: string[];
    admins: TenantAdmin[];
    branches: { name: string; employees_count: number }[];
    departments: { name: string; employees_count: number }[];
    employees: {
        active: number;
        employment: {
            permanent: number;
            contract: number;
            probation: number;
            resigned: number;
        };
        recent: {
            name: string;
            position: string | null;
            join_date: string | null;
        }[];
    };
};

const STATUS_META: Record<
    string,
    { label: string; color: string; bg: string }
> = {
    active: { label: 'Aktif', color: '#16A34A', bg: 'rgba(22,163,74,.1)' },
    trial: { label: 'Trial', color: '#2F54C9', bg: 'rgba(47,84,201,.1)' },
    suspended: {
        label: 'Ditangguhkan',
        color: '#D97706',
        bg: 'rgba(217,119,6,.1)',
    },
    inactive: {
        label: 'Nonaktif',
        color: '#6B7280',
        bg: 'rgba(107,114,128,.12)',
    },
};

const INVOICE_META: Record<
    string,
    { label: string; color: string; bg: string }
> = {
    paid: { label: 'Lunas', color: '#16A34A', bg: 'rgba(22,163,74,.1)' },
    unpaid: {
        label: 'Belum Bayar',
        color: '#D97706',
        bg: 'rgba(217,119,6,.1)',
    },
    overdue: {
        label: 'Jatuh Tempo',
        color: '#DC2626',
        bg: 'rgba(220,38,38,.1)',
    },
    cancelled: {
        label: 'Dibatalkan',
        color: '#6B7280',
        bg: 'rgba(107,114,128,.12)',
    },
};

const EMPLOYMENT_META: Record<string, { label: string; color: string }> = {
    permanent: { label: 'Tetap', color: '#16A34A' },
    contract: { label: 'Kontrak', color: '#2F54C9' },
    probation: { label: 'Probation', color: '#D97706' },
    resigned: { label: 'Resign', color: '#6B7280' },
};

const TABS = [
    { id: 'ringkasan', label: 'Ringkasan', icon: 'layout-dashboard' },
    { id: 'admin', label: 'Akun Admin', icon: 'shield' },
    { id: 'tagihan', label: 'Langganan & Tagihan', icon: 'receipt' },
    { id: 'fitur', label: 'Fitur Aktif', icon: 'layers' },
    { id: 'organisasi', label: 'Organisasi', icon: 'building-2' },
];

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

/** Format an ISO/`YYYY-MM-DD` date to `d Mon Y`, or an em dash when absent. */
function fmtDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return `${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`;
}

/** Clamp a used/quota ratio to a 0–100 integer percentage. */
function pct(used: number, max: number): number {
    return max > 0 ? Math.min(100, Math.round((used / max) * 100)) : 0;
}

function cycleLabel(cycle: string): string {
    return cycle === 'yearly' ? '/thn' : '/bln';
}

const labelStyle: CSSProperties = { fontSize: 11.5, color: C.faint };
const valueStyle: CSSProperties = {
    fontSize: 14,
    color: C.text,
    marginTop: 3,
    fontWeight: 500,
};

function SectionCard({
    title,
    action,
    children,
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div style={card}>
            <div
                style={{
                    padding: '15px 18px',
                    borderBottom: `1px solid ${C.border}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <div style={{ fontSize: 14.5, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                {action}
            </div>
            <div style={{ padding: 18 }}>{children}</div>
        </div>
    );
}

function QuotaBar({
    label,
    used,
    max,
    color,
}: {
    label: string;
    used: number;
    max: number;
    color: string;
}) {
    const percentage = pct(used, max);
    const danger = percentage >= 90;

    return (
        <div style={{ marginBottom: 14 }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    marginBottom: 6,
                }}
            >
                <span style={{ fontSize: 13, color: C.text }}>{label}</span>
                <span style={{ fontSize: 13, fontWeight: 600, color: C.navy }}>
                    {used.toLocaleString('id-ID')}{' '}
                    <span style={{ color: C.faint, fontWeight: 400 }}>
                        / {max.toLocaleString('id-ID')} ({percentage}%)
                    </span>
                </span>
            </div>
            <div
                style={{
                    height: 8,
                    borderRadius: 100,
                    background: C.line,
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        width: `${percentage}%`,
                        height: '100%',
                        borderRadius: 100,
                        background: danger ? C.red : color,
                        transition: 'width .3s',
                    }}
                />
            </div>
        </div>
    );
}

/**
 * The client's own logins. A tenant with no Admin Tenant / HR account cannot be
 * used at all, so this tab both shows the state and fixes it in one place.
 */
function AdminAccountsTab({
    tenantId,
    admins,
}: {
    tenantId: number;
    admins: TenantAdmin[];
}) {
    const { flash } = usePage<{ flash?: { credentials?: TenantCredentials } }>()
        .props;
    const [adding, setAdding] = useState(admins.length === 0);
    const [resetting, setResetting] = useState<number | null>(null);

    const addForm = useForm({
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });
    const resetForm = useForm({ admin_password: '' });

    const submitAdd = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        addForm.post(`/avana/klien/${tenantId}/admin`, {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset();
                setAdding(false);
            },
        });
    };

    const submitReset = (event: FormEvent<HTMLFormElement>, userId: number) => {
        event.preventDefault();
        resetForm.post(`/avana/klien/${tenantId}/admin/${userId}/password`, {
            preserveScroll: true,
            onSuccess: () => {
                resetForm.reset();
                setResetting(null);
            },
        });
    };

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            {flash?.credentials && (
                <div
                    style={{
                        ...card,
                        padding: '16px 18px',
                        borderLeft: `3px solid ${C.green}`,
                        background: '#F0FDF4',
                    }}
                >
                    <div
                        style={{
                            fontSize: 13.5,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 4,
                        }}
                    >
                        Kredensial login — catat sekarang
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.muted,
                            marginBottom: 12,
                        }}
                    >
                        Password hanya ditampilkan sekali. Setelah halaman ini
                        ditinggalkan, satu-satunya cara adalah reset ulang.
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(200px, 1fr))',
                            gap: 12,
                        }}
                    >
                        <KeyValue label="Nama" value={flash.credentials.name} />
                        <KeyValue
                            label="Email"
                            value={flash.credentials.email}
                        />
                        <KeyValue
                            label="Password"
                            value={
                                <code
                                    style={{
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                        background: '#fff',
                                        border: `1px solid ${C.line}`,
                                        borderRadius: 6,
                                        padding: '3px 8px',
                                    }}
                                >
                                    {flash.credentials.password}
                                </code>
                            }
                        />
                    </div>
                </div>
            )}

            <SectionCard
                title="Admin Tenant / HR"
                action={
                    <button
                        type="button"
                        onClick={() => setAdding((open) => !open)}
                        style={{ ...btnOut, height: 34 }}
                    >
                        <AIcon
                            name={adding ? 'x' : 'plus'}
                            size={14}
                            color={C.text}
                        />
                        {adding ? 'Batal' : 'Tambah Admin'}
                    </button>
                }
            >
                <p
                    style={{
                        fontSize: 12.5,
                        color: C.muted,
                        margin: '0 0 14px',
                        lineHeight: 1.55,
                    }}
                >
                    Akun ini yang dipakai klien untuk masuk ke AvanaHR mereka
                    sendiri — otomatis memegang role Admin Tenant / HR beserta
                    seluruh menunya.
                </p>

                {adding && (
                    <form
                        onSubmit={submitAdd}
                        style={{
                            border: `1px solid ${C.line}`,
                            borderRadius: 10,
                            padding: 16,
                            marginBottom: 16,
                            background: '#F8FAFF',
                            display: 'grid',
                            gap: 12,
                        }}
                    >
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fit, minmax(200px, 1fr))',
                                gap: 12,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Nama Admin{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    value={addForm.data.admin_name}
                                    onChange={(event) =>
                                        addForm.setData(
                                            'admin_name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="cth. Rina Anggraeni"
                                    style={withError(
                                        inputStyle,
                                        !!addForm.errors.admin_name,
                                    )}
                                />
                                <FieldError
                                    message={addForm.errors.admin_name}
                                />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Email Admin{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="email"
                                    value={addForm.data.admin_email}
                                    onChange={(event) =>
                                        addForm.setData(
                                            'admin_email',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="admin@klien.co.id"
                                    style={withError(
                                        inputStyle,
                                        !!addForm.errors.admin_email,
                                    )}
                                />
                                <FieldError
                                    message={addForm.errors.admin_email}
                                />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>Password</label>
                                <input
                                    type="text"
                                    value={addForm.data.admin_password}
                                    onChange={(event) =>
                                        addForm.setData(
                                            'admin_password',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="kosongkan = otomatis"
                                    style={withError(
                                        inputStyle,
                                        !!addForm.errors.admin_password,
                                    )}
                                />
                                <FieldError
                                    message={addForm.errors.admin_password}
                                />
                            </div>
                        </div>
                        <div>
                            <button
                                type="submit"
                                disabled={addForm.processing}
                                style={{
                                    ...btnP,
                                    height: 38,
                                    opacity: addForm.processing ? 0.7 : 1,
                                }}
                            >
                                <AIcon name="plus" size={15} color="#fff" />
                                Buat Akun Admin
                            </button>
                        </div>
                    </form>
                )}

                {admins.length === 0 ? (
                    <div
                        style={{
                            fontSize: 13,
                            color: C.red,
                            background: '#FEF2F2',
                            border: `1px solid #FECACA`,
                            borderRadius: 8,
                            padding: '11px 13px',
                        }}
                    >
                        Klien ini belum punya akun admin — belum ada yang bisa
                        login ke tenant-nya.
                    </div>
                ) : (
                    <div style={{ display: 'grid', gap: 10 }}>
                        {admins.map((admin) => (
                            <div
                                key={admin.id}
                                style={{
                                    border: `1px solid ${C.line}`,
                                    borderRadius: 10,
                                    padding: '12px 14px',
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 12,
                                        flexWrap: 'wrap',
                                    }}
                                >
                                    <div>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 600,
                                                color: C.text,
                                            }}
                                        >
                                            {admin.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 12.5,
                                                color: C.faint,
                                                marginTop: 2,
                                            }}
                                        >
                                            {admin.email}
                                            {admin.created_at
                                                ? ` · dibuat ${admin.created_at}`
                                                : ''}
                                        </div>
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 12,
                                                fontWeight: 600,
                                                color:
                                                    admin.status === 'active'
                                                        ? '#16A34A'
                                                        : C.faint,
                                                background:
                                                    admin.status === 'active'
                                                        ? '#F0FDF4'
                                                        : C.surface,
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                            }}
                                        >
                                            {admin.status === 'active'
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setResetting((current) =>
                                                    current === admin.id
                                                        ? null
                                                        : admin.id,
                                                )
                                            }
                                            style={{ ...btnOut, height: 32 }}
                                        >
                                            <AIcon
                                                name="key-round"
                                                size={14}
                                                color={C.text}
                                            />
                                            Reset Password
                                        </button>
                                    </div>
                                </div>

                                {resetting === admin.id && (
                                    <form
                                        onSubmit={(event) =>
                                            submitReset(event, admin.id)
                                        }
                                        style={{
                                            display: 'flex',
                                            gap: 10,
                                            marginTop: 12,
                                            alignItems: 'flex-start',
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        <div style={{ flex: '1 1 220px' }}>
                                            <input
                                                type="text"
                                                value={
                                                    resetForm.data
                                                        .admin_password
                                                }
                                                onChange={(event) =>
                                                    resetForm.setData(
                                                        'admin_password',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="kosongkan = dibuat otomatis"
                                                style={withError(
                                                    inputStyle,
                                                    !!resetForm.errors
                                                        .admin_password,
                                                )}
                                            />
                                            <FieldError
                                                message={
                                                    resetForm.errors
                                                        .admin_password
                                                }
                                            />
                                        </div>
                                        <button
                                            type="submit"
                                            disabled={resetForm.processing}
                                            style={{
                                                ...btnP,
                                                background: C.green,
                                                height: 42,
                                                opacity: resetForm.processing
                                                    ? 0.7
                                                    : 1,
                                            }}
                                        >
                                            <AIcon
                                                name="check"
                                                size={15}
                                                color="#fff"
                                            />
                                            Simpan Password
                                        </button>
                                    </form>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </SectionCard>
        </div>
    );
}

function KeyValue({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <div style={labelStyle}>{label}</div>
            <div style={valueStyle}>{value}</div>
        </div>
    );
}

export default function KlienShow({
    tenant,
    subscription,
    billing,
    features,
    admins,
    branches,
    departments,
    employees,
}: ShowProps) {
    const { flash } = usePage<{ flash?: { credentials?: TenantCredentials } }>()
        .props;
    // Landing here straight after creating the client: show the credentials.
    const [activeTab, setActiveTab] = useState(
        flash?.credentials ? 'admin' : 'ringkasan',
    );
    const status = STATUS_META[tenant.status] ?? STATUS_META.inactive;
    const initials = tenant.name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');

    const headerStats = [
        {
            icon: 'users',
            label: 'Pengguna',
            value: tenant.users_count.toLocaleString('id-ID'),
            sub: `dari ${tenant.max_users.toLocaleString('id-ID')}`,
        },
        {
            icon: 'user-check',
            label: 'Karyawan',
            value: tenant.employees_count.toLocaleString('id-ID'),
            sub: `dari ${tenant.max_employees.toLocaleString('id-ID')}`,
        },
        {
            icon: 'git-branch',
            label: 'Cabang',
            value: tenant.branches_count.toLocaleString('id-ID'),
            sub: `dari ${tenant.max_branches.toLocaleString('id-ID')}`,
        },
        {
            icon: 'package',
            label: 'Paket',
            value: tenant.package?.name ?? '—',
            sub: tenant.package
                ? `${rp(tenant.package.price)} ${cycleLabel(tenant.package.billing_cycle)}`
                : 'Tanpa paket',
        },
    ];

    const billingTiles = [
        {
            label: 'Total Terbayar',
            value: rp(billing.paid_total),
            color: C.green,
            icon: 'circle-check',
        },
        {
            label: 'Outstanding',
            value: rp(billing.outstanding),
            color: C.amber,
            icon: 'clock',
        },
        {
            label: 'Invoice Jatuh Tempo',
            value: String(billing.overdue_count),
            color: C.red,
            icon: 'file-warning',
        },
        {
            label: 'Total Invoice',
            value: String(billing.invoice_count),
            color: C.primary,
            icon: 'receipt',
        },
    ];

    return (
        <>
            <Head title={tenant.name} />
            <div style={{ padding: '24px 28px' }}>
                {/* Breadcrumb */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <span>Platform</span>
                    <AIcon name="chevron-right" size={13} />
                    <Link
                        href="/avana/klien"
                        style={{ color: C.muted, textDecoration: 'none' }}
                    >
                        Klien
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{tenant.name}</span>
                </div>

                {/* Header card */}
                <div style={{ ...card, overflow: 'hidden', marginBottom: 18 }}>
                    <div style={{ padding: '22px' }}>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'flex-end',
                                justifyContent: 'space-between',
                                flexWrap: 'wrap',
                                gap: 14,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'flex-end',
                                    gap: 16,
                                }}
                            >
                                {tenant.logo ? (
                                    <img
                                        src={tenant.logo}
                                        alt={tenant.name}
                                        style={{
                                            width: 76,
                                            height: 76,
                                            borderRadius: 18,
                                            objectFit: 'cover',
                                            background: '#fff',
                                            border: `1px solid ${C.border}`,
                                            boxShadow:
                                                '0 4px 14px rgba(15,26,58,.12)',
                                        }}
                                    />
                                ) : (
                                    <div
                                        style={{
                                            width: 76,
                                            height: 76,
                                            borderRadius: 18,
                                            background: '#fff',
                                            border: `1px solid ${C.border}`,
                                            boxShadow:
                                                '0 4px 14px rgba(15,26,58,.12)',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: 26,
                                            fontWeight: 700,
                                            color: C.primary,
                                        }}
                                    >
                                        {initials || '?'}
                                    </div>
                                )}
                                <div style={{ paddingBottom: 4 }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        <h1
                                            style={{
                                                fontSize: 22,
                                                fontWeight: 700,
                                                color: C.navy,
                                                margin: 0,
                                            }}
                                        >
                                            {tenant.name}
                                        </h1>
                                        <span
                                            style={{
                                                background: status.bg,
                                                color: status.color,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                            }}
                                        >
                                            {status.label}
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            color: C.muted,
                                            marginTop: 3,
                                        }}
                                    >
                                        {tenant.company_name ?? tenant.name} ·{' '}
                                        <span style={{ color: C.faint }}>
                                            {tenant.slug}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: 9 }}>
                                <Link
                                    href={`/avana/klien/${tenant.id}/edit`}
                                    style={{
                                        ...btnOut,
                                        textDecoration: 'none',
                                    }}
                                >
                                    <AIcon name="pencil" size={15} />
                                    Edit
                                </Link>
                                <Link
                                    href="/avana/billing"
                                    style={{ ...btnP, textDecoration: 'none' }}
                                >
                                    <AIcon name="receipt" size={15} />
                                    Billing
                                </Link>
                            </div>
                        </div>

                        {/* Stat strip */}
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(4,1fr)',
                                gap: 1,
                                background: C.line,
                                border: `1px solid ${C.line}`,
                                borderRadius: 12,
                                overflow: 'hidden',
                                marginTop: 20,
                            }}
                        >
                            {headerStats.map((stat) => (
                                <div
                                    key={stat.label}
                                    style={{
                                        background: '#fff',
                                        padding: '14px 16px',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 7,
                                            color: C.muted,
                                            fontSize: 12,
                                        }}
                                    >
                                        <AIcon name={stat.icon} size={14} />
                                        {stat.label}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 20,
                                            fontWeight: 700,
                                            color: C.navy,
                                            marginTop: 6,
                                        }}
                                    >
                                        {stat.value}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                            marginTop: 1,
                                        }}
                                    >
                                        {stat.sub}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Tab bar */}
                <div
                    style={{
                        display: 'flex',
                        gap: 4,
                        borderBottom: `1px solid ${C.border}`,
                        marginBottom: 18,
                        flexWrap: 'wrap',
                    }}
                >
                    {TABS.map((tab) => {
                        const active = activeTab === tab.id;

                        return (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setActiveTab(tab.id)}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    padding: '10px 14px',
                                    border: 'none',
                                    background: active
                                        ? 'rgba(47,84,201,.07)'
                                        : C.surface,
                                    borderRadius: '8px 8px 0 0',
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    color: active ? C.primary : C.muted,
                                    borderBottom: active
                                        ? `2px solid ${C.primary}`
                                        : '2px solid transparent',
                                    cursor: 'pointer',
                                    marginBottom: -1,
                                }}
                            >
                                <AIcon name={tab.icon} size={15} />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* ---- Ringkasan ---- */}
                {activeTab === 'ringkasan' && (
                    <div
                        className="avn-2col"
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1.4fr 1fr',
                            gap: 16,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            <SectionCard title="Penggunaan Kuota">
                                <QuotaBar
                                    label="Pengguna"
                                    used={tenant.users_count}
                                    max={tenant.max_users}
                                    color={C.primary}
                                />
                                <QuotaBar
                                    label="Karyawan"
                                    used={tenant.employees_count}
                                    max={tenant.max_employees}
                                    color={C.green}
                                />
                                <QuotaBar
                                    label="Cabang"
                                    used={tenant.branches_count}
                                    max={tenant.max_branches}
                                    color={C.amber}
                                />
                            </SectionCard>

                            <SectionCard title="Komposisi Karyawan Aktif">
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: 'repeat(4,1fr)',
                                        gap: 10,
                                    }}
                                >
                                    {Object.entries(employees.employment).map(
                                        ([key, count]) => {
                                            const meta = EMPLOYMENT_META[key];

                                            return (
                                                <div
                                                    key={key}
                                                    style={{
                                                        border: `1px solid ${C.line}`,
                                                        borderRadius: 10,
                                                        padding: '12px 10px',
                                                        textAlign: 'center',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontSize: 22,
                                                            fontWeight: 700,
                                                            color: meta.color,
                                                        }}
                                                    >
                                                        {count}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 12,
                                                            color: C.muted,
                                                            marginTop: 2,
                                                        }}
                                                    >
                                                        {meta.label}
                                                    </div>
                                                </div>
                                            );
                                        },
                                    )}
                                </div>
                            </SectionCard>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            <SectionCard title="Informasi Klien">
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 16,
                                    }}
                                >
                                    <KeyValue
                                        label="Nama Perusahaan"
                                        value={tenant.company_name ?? '—'}
                                    />
                                    <KeyValue
                                        label="Slug"
                                        value={tenant.slug}
                                    />
                                    <KeyValue
                                        label="Status Billing"
                                        value={tenant.billing_status ?? '—'}
                                    />
                                    <KeyValue
                                        label="Paket"
                                        value={tenant.package?.name ?? '—'}
                                    />
                                    <KeyValue
                                        label="Mulai"
                                        value={fmtDate(tenant.start_date)}
                                    />
                                    <KeyValue
                                        label="Berakhir"
                                        value={fmtDate(tenant.end_date)}
                                    />
                                    <KeyValue
                                        label="Bergabung"
                                        value={fmtDate(tenant.created_at)}
                                    />
                                    <KeyValue
                                        label="Langganan"
                                        value={`${subscription.total} langganan`}
                                    />
                                </div>
                            </SectionCard>

                            <SectionCard title="Ringkasan Tagihan">
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 10,
                                    }}
                                >
                                    {billingTiles.map((tile) => (
                                        <div
                                            key={tile.label}
                                            style={{
                                                border: `1px solid ${C.line}`,
                                                borderRadius: 10,
                                                padding: '12px 12px',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                    fontSize: 11.5,
                                                    color: C.muted,
                                                }}
                                            >
                                                <AIcon
                                                    name={tile.icon}
                                                    size={13}
                                                    color={tile.color}
                                                />
                                                {tile.label}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 15,
                                                    fontWeight: 700,
                                                    color: C.navy,
                                                    marginTop: 5,
                                                }}
                                            >
                                                {tile.value}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </SectionCard>
                        </div>
                    </div>
                )}

                {/* ---- Langganan & Tagihan ---- */}
                {activeTab === 'tagihan' && (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        <SectionCard title="Langganan Aktif">
                            {subscription.active ? (
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: 'repeat(4,1fr)',
                                        gap: 16,
                                    }}
                                >
                                    <KeyValue
                                        label="Paket"
                                        value={
                                            subscription.active.package ?? '—'
                                        }
                                    />
                                    <KeyValue
                                        label="Harga"
                                        value={`${rp(subscription.active.price)} ${cycleLabel(subscription.active.billing_cycle)}`}
                                    />
                                    <KeyValue
                                        label="Mulai"
                                        value={fmtDate(
                                            subscription.active.start_date,
                                        )}
                                    />
                                    <KeyValue
                                        label="Berakhir"
                                        value={fmtDate(
                                            subscription.active.end_date,
                                        )}
                                    />
                                </div>
                            ) : (
                                <div style={{ fontSize: 13, color: C.faint }}>
                                    Belum ada langganan aktif.
                                </div>
                            )}
                        </SectionCard>

                        <SectionCard title="Invoice Terbaru">
                            {billing.recent.length === 0 ? (
                                <div style={{ fontSize: 13, color: C.faint }}>
                                    Belum ada invoice.
                                </div>
                            ) : (
                                <div style={{ overflowX: 'auto' }}>
                                    <table
                                        style={{
                                            width: '100%',
                                            borderCollapse: 'collapse',
                                            fontSize: 13,
                                        }}
                                    >
                                        <thead>
                                            <tr
                                                style={{
                                                    textAlign: 'left',
                                                    color: C.faint,
                                                    fontSize: 11.5,
                                                }}
                                            >
                                                <th
                                                    style={{
                                                        padding: '8px 10px',
                                                    }}
                                                >
                                                    NO. INVOICE
                                                </th>
                                                <th
                                                    style={{
                                                        padding: '8px 10px',
                                                    }}
                                                >
                                                    TERBIT
                                                </th>
                                                <th
                                                    style={{
                                                        padding: '8px 10px',
                                                    }}
                                                >
                                                    JATUH TEMPO
                                                </th>
                                                <th
                                                    style={{
                                                        padding: '8px 10px',
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    TOTAL
                                                </th>
                                                <th
                                                    style={{
                                                        padding: '8px 10px',
                                                    }}
                                                >
                                                    STATUS
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {billing.recent.map((invoice) => {
                                                const meta =
                                                    INVOICE_META[
                                                        invoice.status
                                                    ] ?? INVOICE_META.unpaid;

                                                return (
                                                    <tr
                                                        key={invoice.id}
                                                        style={{
                                                            borderTop: `1px solid ${C.line}`,
                                                        }}
                                                    >
                                                        <td
                                                            style={{
                                                                padding: '10px',
                                                                fontWeight: 500,
                                                                color: C.text,
                                                            }}
                                                        >
                                                            {invoice.number}
                                                        </td>
                                                        <td
                                                            style={{
                                                                padding: '10px',
                                                                color: C.muted,
                                                            }}
                                                        >
                                                            {fmtDate(
                                                                invoice.issue_date,
                                                            )}
                                                        </td>
                                                        <td
                                                            style={{
                                                                padding: '10px',
                                                                color: C.muted,
                                                            }}
                                                        >
                                                            {fmtDate(
                                                                invoice.due_date,
                                                            )}
                                                        </td>
                                                        <td
                                                            style={{
                                                                padding: '10px',
                                                                textAlign:
                                                                    'right',
                                                                fontWeight: 600,
                                                                color: C.navy,
                                                            }}
                                                        >
                                                            {rp(invoice.total)}
                                                        </td>
                                                        <td
                                                            style={{
                                                                padding: '10px',
                                                            }}
                                                        >
                                                            <span
                                                                style={{
                                                                    background:
                                                                        meta.bg,
                                                                    color: meta.color,
                                                                    fontSize: 11,
                                                                    fontWeight: 600,
                                                                    padding:
                                                                        '3px 9px',
                                                                    borderRadius: 100,
                                                                }}
                                                            >
                                                                {meta.label}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </SectionCard>
                    </div>
                )}

                {/* ---- Fitur Aktif ---- */}
                {activeTab === 'fitur' && (
                    <SectionCard
                        title="Modul Aktif"
                        action={
                            <span
                                style={{
                                    background: 'rgba(47,84,201,.1)',
                                    color: C.primary,
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    padding: '3px 9px',
                                    borderRadius: 100,
                                }}
                            >
                                {features.length} modul
                            </span>
                        }
                    >
                        {features.length === 0 ? (
                            <div style={{ fontSize: 13, color: C.faint }}>
                                Belum ada modul yang diaktifkan.
                            </div>
                        ) : (
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'repeat(auto-fill, minmax(200px, 1fr))',
                                    gap: 8,
                                }}
                            >
                                {features.map((feature) => (
                                    <div
                                        key={feature}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 9,
                                            padding: '9px 12px',
                                            border: `1px solid ${C.line}`,
                                            borderRadius: 9,
                                            fontSize: 13,
                                            color: C.text,
                                        }}
                                    >
                                        <AIcon
                                            name="circle-check"
                                            size={15}
                                            color={C.green}
                                        />
                                        {feature}
                                    </div>
                                ))}
                            </div>
                        )}
                    </SectionCard>
                )}

                {/* ---- Organisasi ---- */}
                {activeTab === 'organisasi' && (
                    <div
                        className="avn-2col"
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 16,
                        }}
                    >
                        <SectionCard title="Departemen">
                            <ListRows
                                rows={departments}
                                emptyText="Belum ada departemen."
                            />
                        </SectionCard>
                        <SectionCard title="Cabang">
                            <ListRows
                                rows={branches}
                                emptyText="Belum ada cabang."
                            />
                        </SectionCard>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <SectionCard title="Karyawan Terbaru">
                                {employees.recent.length === 0 ? (
                                    <div
                                        style={{ fontSize: 13, color: C.faint }}
                                    >
                                        Belum ada karyawan.
                                    </div>
                                ) : (
                                    <div
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns:
                                                'repeat(auto-fill, minmax(240px, 1fr))',
                                            gap: 10,
                                        }}
                                    >
                                        {employees.recent.map(
                                            (employee, index) => (
                                                <div
                                                    key={index}
                                                    style={{
                                                        border: `1px solid ${C.line}`,
                                                        borderRadius: 10,
                                                        padding: '11px 13px',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontSize: 13,
                                                            fontWeight: 500,
                                                            color: C.text,
                                                        }}
                                                    >
                                                        {employee.name}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 12,
                                                            color: C.faint,
                                                            marginTop: 2,
                                                        }}
                                                    >
                                                        {employee.position ??
                                                            '—'}{' '}
                                                        ·{' '}
                                                        {fmtDate(
                                                            employee.join_date,
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </SectionCard>
                        </div>
                    </div>
                )}

                {activeTab === 'admin' && (
                    <AdminAccountsTab tenantId={tenant.id} admins={admins} />
                )}
            </div>
        </>
    );
}

/** Simple name + count rows used for the department and branch lists. */
function ListRows({
    rows,
    emptyText,
}: {
    rows: { name: string; employees_count: number }[];
    emptyText: string;
}) {
    if (rows.length === 0) {
        return <div style={{ fontSize: 13, color: C.faint }}>{emptyText}</div>;
    }

    return (
        <div>
            {rows.map((row, index) => (
                <div
                    key={index}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '10px 0',
                        borderBottom:
                            index < rows.length - 1
                                ? `1px solid ${C.line}`
                                : 'none',
                    }}
                >
                    <span style={{ fontSize: 13, color: C.text }}>
                        {row.name}
                    </span>
                    <span
                        style={{
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: C.muted,
                            background: C.surface,
                            padding: '2px 10px',
                            borderRadius: 100,
                        }}
                    >
                        {row.employees_count} karyawan
                    </span>
                </div>
            ))}
        </div>
    );
}
