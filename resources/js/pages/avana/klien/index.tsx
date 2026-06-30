import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import TenantController from '@/actions/App/Http/Controllers/Avana/TenantController';
import { AIcon, btnP, C, card, thCell } from '@/lib/avana';
import { ConfirmModal, iconBtn, StatusBadge, Usage } from './components';
import { STATUS_OPTIONS } from './types';
import type { FlashProps, KlienFilters, TenantRow } from './types';
import type { FeatureOption, PackageOption, PaginationMeta } from './types';

interface KlienIndexProps {
    tenants: { data: TenantRow[]; meta: PaginationMeta };
    packages: PackageOption[];
    features: FeatureOption[];
    filters: KlienFilters;
}

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

export default function KlienIndex({
    tenants,
    features,
    filters,
}: KlienIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = tenants.meta;

    const [search, setSearch] = useState(filters.search ?? '');
    const [featureTenantId, setFeatureTenantId] = useState<number | null>(null);
    const [confirm, setConfirm] = useState<TenantRow | null>(null);
    const isFirstSearch = useRef(true);

    const featureTenant =
        tenants.data.find((tenant) => tenant.id === featureTenantId) ?? null;

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
                    <Link
                        href={TenantController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Klien
                    </Link>
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
                                {tenants.data.map((tenant) => (
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
                                            <StatusBadge status={tenant.status} />
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
                                                <Link
                                                    href={TenantController.edit(tenant.id)}
                                                    title="Edit klien"
                                                    style={iconBtn}
                                                >
                                                    <AIcon name="pencil" size={15} color={C.muted} />
                                                </Link>
                                                <button
                                                    onClick={() => setConfirm(tenant)}
                                                    title="Hapus klien"
                                                    style={iconBtn}
                                                >
                                                    <AIcon name="trash-2" size={15} color={C.red} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
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
                <ConfirmModal
                    title="Hapus klien?"
                    body={
                        <>
                            Klien{' '}
                            <strong style={{ color: C.text }}>{confirm.name}</strong>{' '}
                            akan diarsipkan. Data tenant tidak akan tampil lagi di daftar.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteTenant}
                />
            )}
        </>
    );
}
