import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, C, card, rp, RupiahInput } from '@/lib/avana';

interface Package {
    id: number;
    name: string;
    tagline: string | null;
    code: string;
    price: number;
    billing_cycle: string;
    max_users: number | null;
    max_employees: number | null;
    max_branches: number | null;
    ai_token_quota: number | null;
    feature_list: string[];
    /** Feature modules this tier unlocks; empty means the whole catalogue. */
    feature_ids: number[];
    is_active: boolean;
    is_popular: boolean;
    tenants_count: number;
}

/** One selectable module in the entitlement picker. */
interface FeatureOption {
    id: number;
    code: string;
    name: string;
    group: string;
}

interface PageProps {
    packages: Package[];
    cycles: string[];
    featureCatalog: FeatureOption[];
    flash?: { success?: string; error?: string };
}

interface PackageForm {
    name: string;
    tagline: string;
    price: number;
    billing_cycle: string;
    max_users: number | null;
    max_employees: number | null;
    max_branches: number | null;
    ai_token_quota: number | null;
    feature_list: string[];
    features: number[];
    is_active: boolean;
    is_popular: boolean;
}

const emptyForm: PackageForm = {
    name: '',
    tagline: '',
    price: 0,
    billing_cycle: 'monthly',
    max_users: null,
    max_employees: null,
    max_branches: null,
    ai_token_quota: null,
    feature_list: [],
    features: [],
    is_active: true,
    is_popular: false,
};

const CYCLE_LABEL: Record<string, string> = {
    monthly: '/bulan',
    yearly: '/tahun',
};

export default function PaketIndex({
    packages,
    cycles,
    featureCatalog,
}: PageProps) {
    const { flash } = usePage<PageProps>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Package | null>(null);
    const [confirm, setConfirm] = useState<Package | null>(null);
    const form = useForm<PackageForm>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }

        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash?.success, flash?.error]);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setModalOpen(true);
    };

    const openEdit = (pkg: Package) => {
        setEditing(pkg);
        form.clearErrors();
        form.setData({
            name: pkg.name,
            tagline: pkg.tagline ?? '',
            price: pkg.price,
            billing_cycle: pkg.billing_cycle,
            max_users: pkg.max_users,
            max_employees: pkg.max_employees,
            max_branches: pkg.max_branches,
            ai_token_quota: pkg.ai_token_quota,
            feature_list: pkg.feature_list,
            features: pkg.feature_ids,
            is_active: pkg.is_active,
            is_popular: pkg.is_popular,
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
    };

    const submit = () => {
        const opts = {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        };

        if (editing) {
            form.put(`/avana/paket/${editing.id}`, opts);
        } else {
            form.post('/avana/paket', opts);
        }
    };

    const remove = () => {
        if (!confirm) {
            return;
        }

        router.delete(`/avana/paket/${confirm.id}`, {
            preserveScroll: true,
            onFinish: () => setConfirm(null),
        });
    };

    const setFeature = (index: number, value: string) =>
        form.setData(
            'feature_list',
            form.data.feature_list.map((f, i) => (i === index ? value : f)),
        );
    const addFeature = () =>
        form.setData('feature_list', [...form.data.feature_list, '']);
    const removeFeature = (index: number) =>
        form.setData(
            'feature_list',
            form.data.feature_list.filter((_, i) => i !== index),
        );

    return (
        <>
            <Head title="Paket Langganan" />
            <div style={{ padding: '24px 28px', maxWidth: 1240 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 700,
                                color: C.navy,
                                margin: 0,
                            }}
                        >
                            Paket Langganan
                        </h1>
                        <p
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                margin: '6px 0 0',
                            }}
                        >
                            Kelola tier harga & daftar fitur. Perubahan langsung
                            tersimpan di database.
                        </p>
                    </div>
                    <button onClick={openCreate} style={btnPrimary}>
                        <AIcon name="plus" size={15} color="#fff" />
                        Tambah Paket
                    </button>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fill, minmax(280px, 1fr))',
                        gap: 16,
                    }}
                >
                    {packages.map((pkg) => (
                        <div
                            key={pkg.id}
                            style={{
                                ...card,
                                padding: 20,
                                border: pkg.is_popular
                                    ? `1.5px solid ${C.primary}`
                                    : `1px solid ${C.border}`,
                                opacity: pkg.is_active ? 1 : 0.6,
                                display: 'flex',
                                flexDirection: 'column',
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    marginBottom: 4,
                                }}
                            >
                                {pkg.tagline ? (
                                    <span style={taglineBadge}>
                                        {pkg.tagline}
                                    </span>
                                ) : null}
                                {pkg.is_popular ? (
                                    <span style={popularBadge}>Populer</span>
                                ) : null}
                                {!pkg.is_active ? (
                                    <span style={inactiveBadge}>Nonaktif</span>
                                ) : null}
                            </div>
                            <div
                                style={{
                                    fontSize: 18,
                                    fontWeight: 700,
                                    color: C.navy,
                                }}
                            >
                                {pkg.name}
                            </div>
                            <div style={{ margin: '8px 0 12px' }}>
                                <span
                                    style={{
                                        fontSize: 22,
                                        fontWeight: 700,
                                        color: C.navy,
                                    }}
                                >
                                    {rp(pkg.price)}
                                </span>
                                <span
                                    style={{ fontSize: 12.5, color: C.muted }}
                                >
                                    {' '}
                                    {CYCLE_LABEL[pkg.billing_cycle] ?? ''}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.muted,
                                    marginBottom: 12,
                                }}
                            >
                                {pkg.max_employees ?? '∞'} karyawan ·{' '}
                                {pkg.max_users ?? '∞'} user ·{' '}
                                {pkg.tenants_count} tenant
                            </div>
                            <div
                                style={{
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color:
                                        pkg.feature_ids.length === 0
                                            ? C.green
                                            : C.primary,
                                    background:
                                        pkg.feature_ids.length === 0
                                            ? 'rgba(22,163,74,.1)'
                                            : 'rgba(47,84,201,.1)',
                                    borderRadius: 999,
                                    padding: '4px 10px',
                                    alignSelf: 'flex-start',
                                    marginBottom: 12,
                                }}
                            >
                                {pkg.feature_ids.length === 0
                                    ? 'Semua modul'
                                    : `${pkg.feature_ids.length} modul aktif`}
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 7,
                                    flex: 1,
                                    marginBottom: 16,
                                }}
                            >
                                {pkg.feature_list.map((feature, i) => (
                                    <div
                                        key={i}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'flex-start',
                                            gap: 8,
                                            fontSize: 12.5,
                                            color: C.text,
                                        }}
                                    >
                                        <AIcon
                                            name="check"
                                            size={14}
                                            color={C.primary}
                                        />
                                        {feature}
                                    </div>
                                ))}
                                {pkg.feature_list.length === 0 ? (
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: C.faint,
                                        }}
                                    >
                                        Belum ada fitur.
                                    </span>
                                ) : null}
                            </div>
                            <div style={{ display: 'flex', gap: 8 }}>
                                <button
                                    onClick={() => openEdit(pkg)}
                                    style={{ ...btnGhost, flex: 1 }}
                                >
                                    <AIcon
                                        name="pencil"
                                        size={14}
                                        color={C.text}
                                    />
                                    Edit
                                </button>
                                <button
                                    onClick={() => setConfirm(pkg)}
                                    style={btnDanger}
                                    title="Hapus"
                                >
                                    <AIcon
                                        name="trash-2"
                                        size={15}
                                        color={C.red}
                                    />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {modalOpen ? (
                <Modal onClose={closeModal} width={900}>
                    <div style={modalTitle}>
                        {editing ? 'Edit Paket' : 'Tambah Paket'}
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                            marginTop: 16,
                        }}
                    >
                        <Row>
                            <Field
                                label="Nama Paket"
                                error={form.errors.name}
                                flex={2}
                            >
                                <input
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="mis. HC Starter"
                                    style={inputStyle}
                                />
                            </Field>
                            <Field label="Label" flex={1}>
                                <input
                                    value={form.data.tagline}
                                    onChange={(e) =>
                                        form.setData('tagline', e.target.value)
                                    }
                                    placeholder="Essential"
                                    style={inputStyle}
                                />
                            </Field>
                        </Row>
                        <Row>
                            <Field
                                label="Harga"
                                error={form.errors.price}
                                flex={2}
                            >
                                <RupiahInput
                                    value={form.data.price}
                                    onChange={(raw) =>
                                        form.setData(
                                            'price',
                                            raw === '' ? 0 : Number(raw),
                                        )
                                    }
                                    style={inputStyle}
                                    placeholder="0"
                                />
                            </Field>
                            <Field label="Siklus" flex={1}>
                                <select
                                    value={form.data.billing_cycle}
                                    onChange={(e) =>
                                        form.setData(
                                            'billing_cycle',
                                            e.target.value,
                                        )
                                    }
                                    style={inputStyle}
                                >
                                    {cycles.map((c) => (
                                        <option key={c} value={c}>
                                            {c === 'monthly'
                                                ? 'Bulanan'
                                                : 'Tahunan'}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </Row>
                        <Row>
                            <Field label="Maks Karyawan">
                                <input
                                    type="number"
                                    value={form.data.max_employees ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'max_employees',
                                            e.target.value === ''
                                                ? null
                                                : Number(e.target.value),
                                        )
                                    }
                                    placeholder="∞"
                                    style={inputStyle}
                                />
                            </Field>
                            <Field label="Maks User">
                                <input
                                    type="number"
                                    value={form.data.max_users ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'max_users',
                                            e.target.value === ''
                                                ? null
                                                : Number(e.target.value),
                                        )
                                    }
                                    placeholder="∞"
                                    style={inputStyle}
                                />
                            </Field>
                            <Field label="Maks Cabang">
                                <input
                                    type="number"
                                    value={form.data.max_branches ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'max_branches',
                                            e.target.value === ''
                                                ? null
                                                : Number(e.target.value),
                                        )
                                    }
                                    placeholder="∞"
                                    style={inputStyle}
                                />
                            </Field>
                            <Field label="Kuota Token AI">
                                <input
                                    type="number"
                                    value={form.data.ai_token_quota ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'ai_token_quota',
                                            e.target.value === ''
                                                ? null
                                                : Number(e.target.value),
                                        )
                                    }
                                    placeholder="∞"
                                    style={inputStyle}
                                />
                            </Field>
                        </Row>
                        <Field label="Modul yang Didapat">
                            <ModulePicker
                                catalog={featureCatalog}
                                selected={form.data.features}
                                onChange={(ids) => form.setData('features', ids)}
                            />
                        </Field>
                        <Field label="Poin Tambahan di Pricing">
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 8,
                                }}
                            >
                                {form.data.feature_list.map((feature, i) => (
                                    <div
                                        key={i}
                                        style={{
                                            display: 'flex',
                                            gap: 8,
                                            alignItems: 'center',
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 12,
                                                color: C.faint,
                                                width: 16,
                                                textAlign: 'right',
                                                flexShrink: 0,
                                            }}
                                        >
                                            {i + 1}
                                        </span>
                                        <input
                                            value={feature}
                                            onChange={(e) =>
                                                setFeature(i, e.target.value)
                                            }
                                            placeholder="Nama fitur"
                                            style={{ ...inputStyle, flex: 1 }}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => removeFeature(i)}
                                            style={btnDanger}
                                            title="Hapus fitur"
                                        >
                                            <AIcon
                                                name="x"
                                                size={14}
                                                color={C.red}
                                            />
                                        </button>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={addFeature}
                                    style={{
                                        ...btnGhost,
                                        alignSelf: 'flex-start',
                                        padding: '7px 14px',
                                    }}
                                >
                                    <AIcon
                                        name="plus"
                                        size={14}
                                        color={C.text}
                                    />
                                    Tambah Poin
                                </button>
                            </div>
                        </Field>
                        <div style={{ display: 'flex', gap: 20 }}>
                            <Checkbox
                                label="Aktif"
                                checked={form.data.is_active}
                                onChange={(v) => form.setData('is_active', v)}
                            />
                            <Checkbox
                                label="Tandai Populer"
                                checked={form.data.is_popular}
                                onChange={(v) => form.setData('is_popular', v)}
                            />
                        </div>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            gap: 10,
                            marginTop: 22,
                        }}
                    >
                        <button onClick={closeModal} style={btnGhost}>
                            <AIcon name="x" size={15} color={C.text} />
                            Batal
                        </button>
                        <button
                            onClick={submit}
                            disabled={form.processing}
                            style={{
                                ...btnPrimary,
                                background: C.green,
                                opacity: form.processing ? 0.6 : 1,
                            }}
                        >
                            <AIcon name="save" size={15} color="#fff" />
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </button>
                    </div>
                </Modal>
            ) : null}

            {confirm ? (
                <Modal onClose={() => setConfirm(null)} width={380}>
                    <div style={modalTitle}>Hapus Paket</div>
                    <p
                        style={{
                            fontSize: 13.5,
                            color: C.muted,
                            margin: '10px 0 20px',
                        }}
                    >
                        Hapus paket <strong>{confirm.name}</strong>? Tenant yang
                        sudah memakainya tidak terpengaruh.
                    </p>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            gap: 10,
                        }}
                    >
                        <button
                            onClick={() => setConfirm(null)}
                            style={btnGhost}
                        >
                            <AIcon name="x" size={15} color={C.text} />
                            Batal
                        </button>
                        <button
                            onClick={remove}
                            style={{
                                ...btnPrimary,
                                background: C.red,
                            }}
                        >
                            <AIcon name="trash-2" size={15} color="#fff" />
                            Hapus
                        </button>
                    </div>
                </Modal>
            ) : null}
        </>
    );
}

function Modal({
    children,
    onClose,
    width = 560,
}: {
    children: React.ReactNode;
    onClose: () => void;
    width?: number;
}) {
    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(15,23,42,.35)',
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
                    background: '#fff',
                    borderRadius: 16,
                    padding: 24,
                    width,
                    maxWidth: '95vw',
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                }}
            >
                {children}
            </div>
        </div>
    );
}

function Row({ children }: { children: React.ReactNode }) {
    return <div style={{ display: 'flex', gap: 12 }}>{children}</div>;
}

function Field({
    label,
    error,
    flex = 1,
    children,
}: {
    label: string;
    error?: string;
    flex?: number;
    children: React.ReactNode;
}) {
    return (
        <div style={{ flex }}>
            <label
                style={{
                    display: 'block',
                    fontSize: 12.5,
                    fontWeight: 500,
                    color: C.text,
                    marginBottom: 6,
                }}
            >
                {label}
            </label>
            {children}
            {error ? (
                <div style={{ fontSize: 11.5, color: C.red, marginTop: 5 }}>
                    {error}
                </div>
            ) : null}
        </div>
    );
}

/**
 * Which modules the tier unlocks for a tenant. Grouped exactly like Kelola Fitur
 * so a super admin reads one taxonomy in both places. Selecting nothing means
 * "everything", which keeps older packages behaving as they always did.
 */
function ModulePicker({
    catalog,
    selected,
    onChange,
}: {
    catalog: FeatureOption[];
    selected: number[];
    onChange: (ids: number[]) => void;
}) {
    const groups = catalog.reduce<Record<string, FeatureOption[]>>(
        (acc, feature) => {
            (acc[feature.group] ??= []).push(feature);

            return acc;
        },
        {},
    );

    const has = (id: number) => selected.includes(id);
    const toggle = (id: number) =>
        onChange(
            has(id) ? selected.filter((x) => x !== id) : [...selected, id],
        );
    const toggleGroup = (features: FeatureOption[], on: boolean) => {
        const ids = features.map((f) => f.id);

        onChange(
            on
                ? [...new Set([...selected, ...ids])]
                : selected.filter((id) => !ids.includes(id)),
        );
    };

    return (
        <div style={{ display: 'grid', gap: 10 }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    flexWrap: 'wrap',
                    fontSize: 12.5,
                    color: C.muted,
                }}
            >
                <span>
                    {selected.length === 0
                        ? 'Belum dipilih — tenant mendapat semua modul.'
                        : `${selected.length} modul dipilih.`}
                </span>
                <button
                    type="button"
                    onClick={() => onChange(catalog.map((f) => f.id))}
                    style={linkBtn}
                >
                    Pilih semua
                </button>
                <button
                    type="button"
                    onClick={() => onChange([])}
                    style={linkBtn}
                >
                    Kosongkan
                </button>
            </div>

            <div
                style={{
                    maxHeight: 340,
                    overflowY: 'auto',
                    border: `1px solid ${C.border}`,
                    borderRadius: 10,
                    padding: 12,
                    display: 'grid',
                    gap: 14,
                }}
            >
                {Object.entries(groups).map(([group, features]) => {
                    const allOn = features.every((f) => has(f.id));

                    return (
                        <div key={group} style={{ display: 'grid', gap: 7 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    gap: 10,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 11,
                                        fontWeight: 700,
                                        letterSpacing: '.04em',
                                        color: C.faint,
                                    }}
                                >
                                    {group.toUpperCase()}
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        toggleGroup(features, !allOn)
                                    }
                                    style={linkBtn}
                                >
                                    {allOn ? 'Hapus grup' : 'Pilih grup'}
                                </button>
                            </div>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'repeat(auto-fill, minmax(180px, 1fr))',
                                    gap: 6,
                                }}
                            >
                                {features.map((feature) => (
                                    <label
                                        key={feature.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 8,
                                            fontSize: 12.5,
                                            color: C.text,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={has(feature.id)}
                                            onChange={() => toggle(feature.id)}
                                        />
                                        {feature.name}
                                    </label>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

const linkBtn: CSSProperties = {
    border: 'none',
    background: 'none',
    padding: 0,
    cursor: 'pointer',
    fontSize: 12,
    fontWeight: 600,
    color: C.primary,
};

function Checkbox({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <label
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 8,
                fontSize: 13,
                color: C.text,
                cursor: 'pointer',
            }}
        >
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            {label}
        </label>
    );
}

const inputStyle: CSSProperties = {
    width: '100%',
    border: `1px solid ${C.border}`,
    borderRadius: 9,
    padding: '9px 12px',
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
    boxSizing: 'border-box',
    background: '#fff',
};

const modalTitle: CSSProperties = {
    fontSize: 17,
    fontWeight: 700,
    color: C.navy,
};

const btnPrimary: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 7,
    background: C.primary,
    color: '#fff',
    border: 'none',
    borderRadius: 10,
    padding: '9px 16px',
    fontSize: 13.5,
    fontWeight: 600,
    cursor: 'pointer',
};

const btnGhost: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    background: '#fff',
    color: C.text,
    border: `1px solid ${C.border}`,
    borderRadius: 10,
    padding: '9px 16px',
    fontSize: 13.5,
    fontWeight: 600,
    cursor: 'pointer',
};

const btnDanger: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'rgba(220,38,38,.07)',
    border: `1px solid rgba(220,38,38,.3)`,
    borderRadius: 10,
    padding: '9px 12px',
    cursor: 'pointer',
};

const taglineBadge: CSSProperties = {
    fontSize: 10.5,
    fontWeight: 700,
    letterSpacing: '.04em',
    textTransform: 'uppercase',
    color: C.primary,
    background: 'rgba(47,84,201,.1)',
    borderRadius: 6,
    padding: '2px 8px',
};

const popularBadge: CSSProperties = {
    fontSize: 10.5,
    fontWeight: 700,
    color: '#fff',
    background: C.primary,
    borderRadius: 6,
    padding: '2px 8px',
};

const inactiveBadge: CSSProperties = {
    fontSize: 10.5,
    fontWeight: 600,
    color: C.muted,
    background: C.line,
    borderRadius: 6,
    padding: '2px 8px',
};
