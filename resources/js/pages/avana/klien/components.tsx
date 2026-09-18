import { useForm } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import TenantController from '@/actions/App/Http/Controllers/Avana/TenantController';
import { AIcon, btnDanger, btnOut, btnSave, C } from '@/lib/avana';
import type {
    TenantMultiCompany,
    TenantRow,
    TenantTokenStanding,
} from './types';

/* ---------- shared field styles (mirror benefit/components.tsx) ---------- */

export const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

export const inputStyle: CSSProperties = {
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

export const selectStyle: CSSProperties = {
    ...inputStyle,
    color: C.muted,
    cursor: 'pointer',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

export const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
    textDecoration: 'none',
};

/** Apply the red error border to a base style when invalid. */
export function withError(
    base: CSSProperties,
    hasError: boolean,
): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

/** Inline error message rendered under a field. */
export function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={errorTextStyle}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** Badge styling per tenant status. */
const STATUS_META: Record<
    string,
    { label: string; color: string; bg: string }
> = {
    active: { label: 'Aktif', color: C.green, bg: 'rgba(22,163,74,.1)' },
    trial: { label: 'Trial', color: C.primary, bg: 'rgba(47,84,201,.1)' },
    suspended: { label: 'Suspended', color: C.red, bg: 'rgba(220,38,38,.1)' },
    inactive: {
        label: 'Nonaktif',
        color: C.muted,
        bg: 'rgba(107,114,128,.12)',
    },
};

/** Colored pill describing a tenant status. */
export function StatusBadge({ status }: { status: string }) {
    const badge = STATUS_META[status] ?? STATUS_META.inactive;

    return (
        <span
            style={{
                display: 'inline-block',
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: badge.color,
                background: badge.bg,
            }}
        >
            {badge.label}
        </span>
    );
}

/** Compact "used / limit" usage cell. */
export function Usage({ used, limit }: { used: number; limit: number }) {
    const over = limit > 0 && used > limit;

    return (
        <span
            style={{
                fontSize: 13,
                color: over ? C.red : C.text,
                fontWeight: over ? 600 : 400,
            }}
        >
            {used.toLocaleString('id-ID')}
            <span style={{ color: C.faint }}>
                {' '}
                / {limit > 0 ? limit.toLocaleString('id-ID') : '∞'}
            </span>
        </span>
    );
}

/**
 * How many AI tokens this client has bought, with the wallet left underneath —
 * bought and remaining are different numbers (usage eats the wallet, purchases
 * never shrink), so the cell shows both rather than implying one is the other.
 */
export function TokenCell({ token }: { token?: TenantTokenStanding }) {
    if (!token) {
        return <span style={{ fontSize: 13, color: C.faint }}>—</span>;
    }

    const bought = token.purchased > 0;

    return (
        <div>
            <div
                style={{
                    fontSize: 13,
                    fontWeight: bought ? 600 : 400,
                    color: bought ? C.navy : C.faint,
                }}
            >
                {bought
                    ? token.purchased.toLocaleString('id-ID')
                    : 'Belum beli'}
            </div>
            <div style={{ fontSize: 11.5, color: C.faint, marginTop: 2 }}>
                Sisa {token.balance.toLocaleString('id-ID')}
                {' · '}
                {token.quota === null
                    ? 'kuota ∞'
                    : `kuota ${token.quota.toLocaleString('id-ID')}/bln`}
            </div>
        </div>
    );
}

interface ConfirmModalProps {
    title: string;
    body: ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
}

/** Centered destructive-action confirmation modal. */
export function ConfirmModal({
    title,
    body,
    onCancel,
    onConfirm,
}: ConfirmModalProps) {
    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <div
                onClick={onCancel}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 400,
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        width: 48,
                        height: 48,
                        borderRadius: 12,
                        background: 'rgba(220,38,38,.1)',
                        color: C.red,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: 16,
                    }}
                >
                    <AIcon name="trash-2" size={22} color={C.red} />
                </div>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                <div
                    style={{
                        fontSize: 13.5,
                        color: C.muted,
                        marginTop: 8,
                        lineHeight: 1.55,
                    }}
                >
                    {body}
                </div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        onClick={onCancel}
                        style={{
                            ...btnOut,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="x" size={16} color={C.muted} />
                        Batal
                    </button>
                    <button
                        onClick={onConfirm}
                        style={{
                            ...btnDanger,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="trash-2" size={16} />
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    );
}

interface MultiCompanyModalProps {
    tenant: TenantRow;
    onClose: () => void;
}

/** Large quick-edit dialog for the platform-managed Multi Company add-on. */
export function MultiCompanyModal({ tenant, onClose }: MultiCompanyModalProps) {
    const detail: TenantMultiCompany = tenant.multi_company;
    const form = useForm({
        enabled: detail.enabled,
        company_limit: detail.company_limit,
        note: detail.note ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(TenantController.updateMultiCompanyAddon(tenant.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="multi-company-modal-title"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <button
                type="button"
                aria-label="Tutup modal Multi Company"
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    border: 0,
                    background: 'rgba(14,26,58,.45)',
                    cursor: 'default',
                }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 980,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        padding: '20px 26px',
                        borderBottom: `1px solid ${C.line}`,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 16,
                    }}
                >
                    <div>
                        <div
                            id="multi-company-modal-title"
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Add-on Multi Company
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginTop: 3,
                            }}
                        >
                            Kelola akses grup perusahaan untuk{' '}
                            <strong style={{ color: C.text }}>
                                {tenant.name}
                            </strong>
                        </div>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup"
                        onClick={onClose}
                        style={{
                            width: 34,
                            height: 34,
                            flex: 'none',
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            borderRadius: 8,
                            cursor: 'pointer',
                            color: C.muted,
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="x" size={16} />
                    </button>
                </div>

                <div
                    style={{
                        padding: 26,
                        display: 'grid',
                        gridTemplateColumns:
                            'minmax(0, 1.2fr) minmax(280px, .8fr)',
                        gap: 26,
                    }}
                >
                    <form
                        onSubmit={submit}
                        style={{ display: 'grid', gap: 16 }}
                    >
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 14,
                            }}
                        >
                            <label style={{ display: 'grid', gap: 7 }}>
                                <span style={fieldLabelStyle}>
                                    Status add-on
                                </span>
                                <span
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        minHeight: 42,
                                        fontSize: 13,
                                        color: C.text,
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.enabled}
                                        onChange={(event) =>
                                            form.setData(
                                                'enabled',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Aktifkan Multi Company
                                </span>
                            </label>
                            <label style={{ display: 'grid', gap: 7 }}>
                                <span style={fieldLabelStyle}>
                                    Maksimal perusahaan
                                </span>
                                <input
                                    type="number"
                                    min={1}
                                    max={1000}
                                    value={form.data.company_limit}
                                    onChange={(event) =>
                                        form.setData(
                                            'company_limit',
                                            Number(event.target.value),
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!form.errors.company_limit,
                                    )}
                                />
                                <FieldError
                                    message={form.errors.company_limit}
                                />
                            </label>
                        </div>
                        <label style={{ display: 'grid', gap: 7 }}>
                            <span style={fieldLabelStyle}>Catatan</span>
                            <input
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData('note', event.target.value)
                                }
                                style={withError(
                                    inputStyle,
                                    !!form.errors.note,
                                )}
                                placeholder="Contoh: Add-on disetujui oleh manajemen"
                            />
                            <FieldError message={form.errors.note} />
                        </label>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: 10,
                                paddingTop: 6,
                            }}
                        >
                            <button
                                type="button"
                                onClick={onClose}
                                disabled={form.processing}
                                style={{
                                    ...btnOut,
                                    height: 42,
                                    opacity: form.processing ? 0.6 : 1,
                                }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnSave,
                                    height: 42,
                                    opacity: form.processing ? 0.7 : 1,
                                }}
                            >
                                <AIcon name="save" size={15} color="#fff" />
                                Simpan Add-on
                            </button>
                        </div>
                    </form>

                    <div
                        style={{
                            border: `1px solid ${C.line}`,
                            borderRadius: 12,
                            padding: 18,
                            background: '#FAFBFD',
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 10,
                                marginBottom: 16,
                            }}
                        >
                            <span
                                style={{
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                Ringkasan kuota
                            </span>
                            <span
                                style={{
                                    color: detail.enabled ? C.green : C.muted,
                                    fontSize: 12,
                                    fontWeight: 600,
                                }}
                            >
                                {detail.enabled ? 'Aktif' : 'Tidak aktif'}
                            </span>
                        </div>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 12,
                                marginBottom: 18,
                            }}
                        >
                            <div>
                                <div
                                    style={{
                                        ...fieldLabelStyle,
                                        color: C.faint,
                                    }}
                                >
                                    Terpakai
                                </div>
                                <div
                                    style={{
                                        fontSize: 20,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    {detail.used} / {detail.company_limit}
                                </div>
                            </div>
                            <div>
                                <div
                                    style={{
                                        ...fieldLabelStyle,
                                        color: C.faint,
                                    }}
                                >
                                    Slot tersedia
                                </div>
                                <div
                                    style={{
                                        fontSize: 20,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    {detail.remaining}
                                </div>
                            </div>
                        </div>
                        <div
                            style={{
                                fontSize: 12,
                                fontWeight: 600,
                                color: C.text,
                                marginBottom: 9,
                            }}
                        >
                            Perusahaan dalam grup
                        </div>
                        <div style={{ display: 'grid', gap: 8 }}>
                            {detail.companies.map((company) => (
                                <div
                                    key={company.id}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 10,
                                        padding: '9px 10px',
                                        background: '#fff',
                                        border: `1px solid ${C.line}`,
                                        borderRadius: 8,
                                    }}
                                >
                                    <div
                                        style={{
                                            minWidth: 0,
                                            fontSize: 12.5,
                                            color: C.text,
                                        }}
                                    >
                                        <div
                                            style={{
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {company.name}
                                        </div>
                                        {company.is_primary && (
                                            <div
                                                style={{
                                                    fontSize: 11,
                                                    color: C.faint,
                                                    marginTop: 2,
                                                }}
                                            >
                                                Perusahaan utama
                                            </div>
                                        )}
                                    </div>
                                    <span
                                        style={{
                                            flex: 'none',
                                            fontSize: 11.5,
                                            color:
                                                company.status === 'active'
                                                    ? C.green
                                                    : C.muted,
                                        }}
                                    >
                                        {company.status === 'active'
                                            ? 'Aktif'
                                            : company.status}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
