import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import TenantController from '@/actions/App/Http/Controllers/Avana/TenantController';
import { AIcon, btnP, C, card, thCell } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** A single tenant row as serialized by `TenantController::index`. */
interface TenantRow {
    id: number;
    name: string;
    slug: string;
    company_name: string | null;
    status: string;
    billing_status: string | null;
    package: { id: number; name: string } | null;
    max_users: number;
    max_employees: number;
    max_branches: number;
    users_count: number;
    employees_count: number;
    branches_count: number;
    start_date: string | null;
    end_date: string | null;
    feature_codes: string[];
}

interface PackageOption {
    id: number;
    name: string;
    code: string;
    max_users: number;
    max_employees: number;
    max_branches: number;
}

interface FeatureOption {
    id: number;
    code: string;
    name: string;
}

interface KlienFilters {
    search?: string;
    status?: string;
}

interface KlienProps {
    tenants: { data: TenantRow[]; meta: PaginationMeta };
    packages: PackageOption[];
    features: FeatureOption[];
    filters: KlienFilters;
}

/** Flat string-only payload backing the create/edit tenant form. */
interface TenantFormData {
    name: string;
    company_name: string;
    slug: string;
    package_id: string;
    status: string;
    max_users: string;
    max_employees: string;
    max_branches: string;
    billing_status: string;
    start_date: string;
    end_date: string;
}

const STATUS_OPTIONS: { value: string; label: string }[] = [
    { value: 'trial', label: 'Trial' },
    { value: 'active', label: 'Aktif' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'inactive', label: 'Nonaktif' },
];

const STATUS_META: Record<string, { label: string; color: string; bg: string }> = {
    active: { label: 'Aktif', color: C.green, bg: 'rgba(22,163,74,.1)' },
    trial: { label: 'Trial', color: C.primary, bg: 'rgba(47,84,201,.1)' },
    suspended: { label: 'Suspended', color: C.red, bg: 'rgba(220,38,38,.1)' },
    inactive: { label: 'Nonaktif', color: C.muted, bg: 'rgba(107,114,128,.12)' },
};

const emptyForm: TenantFormData = {
    name: '',
    company_name: '',
    slug: '',
    package_id: '',
    status: 'trial',
    max_users: '',
    max_employees: '',
    max_branches: '',
    billing_status: '',
    start_date: '',
    end_date: '',
};

const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

const inputStyle: CSSProperties = {
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

const selectStyle: CSSProperties = { ...inputStyle, color: C.muted, cursor: 'pointer' };

const filterSelectStyle: CSSProperties = {
    height: 38,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.muted,
    background: '#fff',
    cursor: 'pointer',
    outline: 'none',
};

function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? { ...base, border: `1px solid ${C.red}`, boxShadow: '0 0 0 3px rgba(220,38,38,.08)' }
        : base;
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 6, display: 'flex', alignItems: 'center', gap: 5 }}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** Compact "used / limit" usage cell. */
function Usage({ used, limit }: { used: number; limit: number }) {
    const over = limit > 0 && used > limit;

    return (
        <span style={{ fontSize: 13, color: over ? C.red : C.text, fontWeight: over ? 600 : 400 }}>
            {used.toLocaleString('id-ID')}
            <span style={{ color: C.faint }}> / {limit > 0 ? limit.toLocaleString('id-ID') : '∞'}</span>
        </span>
    );
}

export default function AvanaKlien({ tenants, packages, features, filters }: KlienProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = tenants.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<TenantRow | null>(null);
    const [featureTenantId, setFeatureTenantId] = useState<number | null>(null);
    const [confirm, setConfirm] = useState<TenantRow | null>(null);
    const isFirstSearch = useRef(true);

    const form = useForm<TenantFormData>({ ...emptyForm });

    const featureTenant = tenants.data.find((tenant) => tenant.id === featureTenantId) ?? null;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, search: search || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const applyFilter = (key: string, value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, [key]: value || undefined, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setModalOpen(true);
    };

    const openEdit = (tenant: TenantRow) => {
        setEditing(tenant);
        form.clearErrors();
        form.setData({
            name: tenant.name,
            company_name: tenant.company_name ?? '',
            slug: tenant.slug,
            package_id: tenant.package ? String(tenant.package.id) : '',
            status: tenant.status,
            max_users: String(tenant.max_users ?? ''),
            max_employees: String(tenant.max_employees ?? ''),
            max_branches: String(tenant.max_branches ?? ''),
            billing_status: tenant.billing_status ?? '',
            start_date: tenant.start_date ?? '',
            end_date: tenant.end_date ?? '',
        });
        setModalOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                setEditing(null);
                form.reset();
            },
        };

        if (editing) {
            form.put(TenantController.update(editing.id).url, options);
        } else {
            form.post(TenantController.store().url, options);
        }
    };

    const toggleFeature = (tenantId: number, featureId: number) => {
        router.post(
            TenantController.toggleFeature(tenantId).url,
            { feature_id: featureId },
            { preserveScroll: true, preserveState: true },
        );
    };

    const deleteTenant = () => {
        if (!confirm) {
            return;
        }

        router.delete(TenantController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Klien / Tenant" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
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
                            <span style={{ color: C.muted }}>Klien</span>
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
                            Klien / Tenant
                        </h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                            Kelola perusahaan pelanggan, paket langganan, dan modul yang aktif
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Klien
                    </button>
                </div>

                {/* Table card */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    {/* Filter bar */}
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            gap: 10,
                            flexWrap: 'wrap',
                            alignItems: 'center',
                        }}
                    >
                        <div style={{ position: 'relative', flex: 1, minWidth: 220, maxWidth: 340 }}>
                            <AIcon
                                name="search"
                                size={16}
                                color={C.faint}
                                style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)' }}
                            />
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Cari nama klien atau perusahaan…"
                                style={{
                                    width: '100%',
                                    height: 38,
                                    padding: '0 12px 0 36px',
                                    background: C.surface,
                                    border: '1px solid transparent',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    outline: 'none',
                                }}
                            />
                        </div>
                        <select
                            aria-label="Status"
                            value={filters.status ?? ''}
                            onChange={(event) => applyFilter('status', event.target.value)}
                            style={filterSelectStyle}
                        >
                            <option value="">Semua Status</option>
                            {STATUS_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Table */}
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 900 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Klien</th>
                                    <th style={thCell}>Paket</th>
                                    <th style={thCell}>Status</th>
                                    <th style={thCell}>Pengguna</th>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Cabang</th>
                                    <th style={{ ...thCell, textAlign: 'right', padding: '12px 18px' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tenants.data.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td
                                            colSpan={7}
                                            style={{
                                                padding: '48px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                                                <AIcon name="building-2" size={28} color={C.faint} />
                                                <div>Belum ada klien yang terdaftar.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {tenants.data.map((tenant) => {
                                    const badge = STATUS_META[tenant.status] ?? STATUS_META.inactive;

                                    return (
                                        <tr key={tenant.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                            <td style={{ padding: '13px 16px' }}>
                                                <div style={{ fontSize: 13.5, fontWeight: 600, color: C.navy }}>
                                                    {tenant.name}
                                                </div>
                                                <div style={{ fontSize: 12, color: C.faint }}>
                                                    {tenant.company_name ?? '—'} · {tenant.slug}
                                                </div>
                                            </td>
                                            <td style={{ padding: '13px 16px', fontSize: 13, color: C.text }}>
                                                {tenant.package?.name ?? '—'}
                                            </td>
                                            <td style={{ padding: '13px 16px' }}>
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
                                            </td>
                                            <td style={{ padding: '13px 16px' }}>
                                                <Usage used={tenant.users_count} limit={tenant.max_users} />
                                            </td>
                                            <td style={{ padding: '13px 16px' }}>
                                                <Usage used={tenant.employees_count} limit={tenant.max_employees} />
                                            </td>
                                            <td style={{ padding: '13px 16px' }}>
                                                <Usage used={tenant.branches_count} limit={tenant.max_branches} />
                                            </td>
                                            <td style={{ padding: '13px 18px', textAlign: 'right' }}>
                                                <div style={{ display: 'inline-flex', gap: 6 }}>
                                                    <button
                                                        onClick={() => setFeatureTenantId(tenant.id)}
                                                        title="Kelola fitur"
                                                        style={{
                                                            display: 'inline-flex',
                                                            alignItems: 'center',
                                                            gap: 6,
                                                            height: 32,
                                                            padding: '0 11px',
                                                            border: `1px solid ${C.border}`,
                                                            background: '#fff',
                                                            borderRadius: 8,
                                                            fontSize: 12.5,
                                                            fontWeight: 500,
                                                            color: C.text,
                                                            cursor: 'pointer',
                                                        }}
                                                    >
                                                        <AIcon name="layout-grid" size={15} color={C.primary} />
                                                        Fitur
                                                    </button>
                                                    <button
                                                        onClick={() => openEdit(tenant)}
                                                        title="Edit klien"
                                                        style={{
                                                            width: 32,
                                                            height: 32,
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
                                                        <AIcon name="pencil" size={15} />
                                                    </button>
                                                    <button
                                                        onClick={() => setConfirm(tenant)}
                                                        title="Hapus klien"
                                                        style={{
                                                            width: 32,
                                                            height: 32,
                                                            border: `1px solid ${C.border}`,
                                                            background: '#fff',
                                                            borderRadius: 8,
                                                            cursor: 'pointer',
                                                            color: C.red,
                                                            display: 'inline-flex',
                                                            alignItems: 'center',
                                                            justifyContent: 'center',
                                                        }}
                                                    >
                                                        <AIcon name="trash-2" size={15} color={C.red} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination footer */}
                    <div
                        style={{
                            padding: '14px 18px',
                            borderTop: `1px solid ${C.border}`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 12,
                        }}
                    >
                        <div style={{ fontSize: 13, color: C.muted }}>
                            Menampilkan{' '}
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.from ?? 0}–{meta.to ?? 0}
                            </span>{' '}
                            dari{' '}
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.total.toLocaleString('id-ID')}
                            </span>{' '}
                            klien
                        </div>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <button
                                disabled={meta.current_page <= 1}
                                onClick={() => goToPage(meta.current_page - 1)}
                                style={{
                                    height: 34,
                                    minWidth: 34,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color: meta.current_page <= 1 ? C.faint : C.text,
                                    cursor: meta.current_page <= 1 ? 'not-allowed' : 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                }}
                            >
                                <AIcon name="chevron-left" size={15} />
                            </button>
                            <span style={{ fontSize: 13, color: C.muted, padding: '0 4px' }}>
                                {meta.current_page} / {meta.last_page}
                            </span>
                            <button
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => goToPage(meta.current_page + 1)}
                                style={{
                                    height: 34,
                                    minWidth: 34,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color: meta.current_page >= meta.last_page ? C.faint : C.text,
                                    cursor: meta.current_page >= meta.last_page ? 'not-allowed' : 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                }}
                            >
                                <AIcon name="chevron-right" size={15} />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Create / edit modal */}
            {modalOpen && (
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
                        onClick={() => setModalOpen(false)}
                        style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }}
                    />
                    <div
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 620,
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
                                padding: '20px 24px',
                                borderBottom: `1px solid ${C.line}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                            }}
                        >
                            <div>
                                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                                    {editing ? 'Edit Klien' : 'Tambah Klien'}
                                </div>
                                <div style={{ fontSize: 12.5, color: C.muted, marginTop: 3 }}>
                                    Lengkapi data perusahaan pelanggan dan batas langganannya.
                                </div>
                            </div>
                            <button
                                onClick={() => setModalOpen(false)}
                                style={{
                                    width: 34,
                                    height: 34,
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

                        <form onSubmit={submit} style={{ padding: '20px 24px' }}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                                <div>
                                    <label style={labelStyle}>
                                        Nama Klien <span style={{ color: C.red }}>*</span>
                                    </label>
                                    <input
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        placeholder="PT Nusantara Jaya"
                                        style={withError(inputStyle, !!form.errors.name)}
                                    />
                                    <FieldError message={form.errors.name} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Nama Perusahaan</label>
                                    <input
                                        value={form.data.company_name}
                                        onChange={(event) => form.setData('company_name', event.target.value)}
                                        placeholder="PT Nusantara Jaya"
                                        style={withError(inputStyle, !!form.errors.company_name)}
                                    />
                                    <FieldError message={form.errors.company_name} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Slug</label>
                                    <input
                                        value={form.data.slug}
                                        onChange={(event) => form.setData('slug', event.target.value)}
                                        placeholder="otomatis dari nama"
                                        style={withError(inputStyle, !!form.errors.slug)}
                                    />
                                    <FieldError message={form.errors.slug} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Paket</label>
                                    <select
                                        value={form.data.package_id}
                                        onChange={(event) => form.setData('package_id', event.target.value)}
                                        style={withError(selectStyle, !!form.errors.package_id)}
                                    >
                                        <option value="">Tanpa paket</option>
                                        {packages.map((pkg) => (
                                            <option key={pkg.id} value={String(pkg.id)}>
                                                {pkg.name}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError message={form.errors.package_id} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Status</label>
                                    <select
                                        value={form.data.status}
                                        onChange={(event) => form.setData('status', event.target.value)}
                                        style={withError(selectStyle, !!form.errors.status)}
                                    >
                                        {STATUS_OPTIONS.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError message={form.errors.status} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Status Tagihan</label>
                                    <input
                                        value={form.data.billing_status}
                                        onChange={(event) => form.setData('billing_status', event.target.value)}
                                        placeholder="active / overdue"
                                        style={withError(inputStyle, !!form.errors.billing_status)}
                                    />
                                    <FieldError message={form.errors.billing_status} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Maks. Pengguna</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={form.data.max_users}
                                        onChange={(event) => form.setData('max_users', event.target.value)}
                                        style={withError(inputStyle, !!form.errors.max_users)}
                                    />
                                    <FieldError message={form.errors.max_users} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Maks. Karyawan</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={form.data.max_employees}
                                        onChange={(event) => form.setData('max_employees', event.target.value)}
                                        style={withError(inputStyle, !!form.errors.max_employees)}
                                    />
                                    <FieldError message={form.errors.max_employees} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Maks. Cabang</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={form.data.max_branches}
                                        onChange={(event) => form.setData('max_branches', event.target.value)}
                                        style={withError(inputStyle, !!form.errors.max_branches)}
                                    />
                                    <FieldError message={form.errors.max_branches} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Mulai Langganan</label>
                                    <input
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(event) => form.setData('start_date', event.target.value)}
                                        style={withError(inputStyle, !!form.errors.start_date)}
                                    />
                                    <FieldError message={form.errors.start_date} />
                                </div>
                                <div>
                                    <label style={labelStyle}>Selesai Langganan</label>
                                    <input
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(event) => form.setData('end_date', event.target.value)}
                                        style={withError(inputStyle, !!form.errors.end_date)}
                                    />
                                    <FieldError message={form.errors.end_date} />
                                </div>
                            </div>

                            <div style={{ display: 'flex', gap: 10, marginTop: 24 }}>
                                <button
                                    type="button"
                                    onClick={() => setModalOpen(false)}
                                    style={{
                                        flex: 1,
                                        height: 44,
                                        background: '#fff',
                                        color: C.text,
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 9,
                                        fontSize: 14,
                                        fontWeight: 500,
                                        cursor: 'pointer',
                                    }}
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    style={{
                                        flex: 1,
                                        height: 44,
                                        background: C.primary,
                                        color: '#fff',
                                        border: 'none',
                                        borderRadius: 9,
                                        fontSize: 14,
                                        fontWeight: 600,
                                        cursor: form.processing ? 'not-allowed' : 'pointer',
                                        opacity: form.processing ? 0.7 : 1,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        gap: 8,
                                    }}
                                >
                                    <AIcon name="check" size={16} color="#fff" />
                                    {editing ? 'Simpan Perubahan' : 'Tambah Klien'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Kelola Fitur modal */}
            {featureTenant && (
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
                        onClick={() => setFeatureTenantId(null)}
                        style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }}
                    />
                    <div
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 520,
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
                                padding: '20px 24px',
                                borderBottom: `1px solid ${C.line}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                            }}
                        >
                            <div>
                                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>Kelola Fitur</div>
                                <div style={{ fontSize: 12.5, color: C.muted, marginTop: 3 }}>
                                    Modul aktif untuk{' '}
                                    <strong style={{ color: C.text }}>{featureTenant.name}</strong>
                                </div>
                            </div>
                            <button
                                onClick={() => setFeatureTenantId(null)}
                                style={{
                                    width: 34,
                                    height: 34,
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

                        <div style={{ padding: '8px 24px 20px' }}>
                            {features.map((feature, index) => {
                                const enabled = featureTenant.feature_codes.includes(feature.code);

                                return (
                                    <div
                                        key={feature.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            padding: '14px 0',
                                            borderTop: index === 0 ? 'none' : `1px solid ${C.line}`,
                                        }}
                                    >
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 13 }}>
                                            <div
                                                style={{
                                                    width: 36,
                                                    height: 36,
                                                    borderRadius: 10,
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    background: enabled ? 'rgba(47,84,201,.1)' : '#F1F3F9',
                                                    color: enabled ? C.primary : C.faint,
                                                }}
                                            >
                                                <AIcon name="layout-grid" size={17} color={enabled ? C.primary : C.faint} />
                                            </div>
                                            <div>
                                                <div style={{ fontSize: 14, fontWeight: 600, color: C.navy }}>
                                                    {feature.name}
                                                </div>
                                                <div style={{ fontSize: 12, color: C.faint }}>{feature.code}</div>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked={enabled}
                                            onClick={() => toggleFeature(featureTenant.id, feature.id)}
                                            style={{
                                                width: 46,
                                                height: 26,
                                                borderRadius: 100,
                                                border: 'none',
                                                cursor: 'pointer',
                                                position: 'relative',
                                                transition: 'background .15s',
                                                background: enabled ? C.primary : '#D5DCEA',
                                                flex: 'none',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    position: 'absolute',
                                                    top: 3,
                                                    left: enabled ? 23 : 3,
                                                    width: 20,
                                                    height: 20,
                                                    borderRadius: '50%',
                                                    background: '#fff',
                                                    transition: 'left .15s',
                                                    boxShadow: '0 1px 3px rgba(15,23,42,.2)',
                                                }}
                                            />
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            )}

            {/* Confirm delete modal */}
            {confirm && (
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
                        onClick={() => setConfirm(null)}
                        style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }}
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
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>Hapus klien?</div>
                        <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>
                            Klien <strong style={{ color: C.text }}>{confirm.name}</strong> akan diarsipkan. Data
                            tenant tidak akan tampil lagi di daftar.
                        </div>
                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button
                                onClick={() => setConfirm(null)}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 500,
                                    cursor: 'pointer',
                                }}
                            >
                                Batal
                            </button>
                            <button
                                onClick={deleteTenant}
                                style={{
                                    flex: 1,
                                    height: 44,
                                    background: C.red,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 9,
                                    fontSize: 14,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 8,
                                }}
                            >
                                <AIcon name="trash-2" size={16} />
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
