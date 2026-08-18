import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { AIcon, C } from '@/lib/avana';
import { ActivityTable } from './activity-table';
import { AuditTable } from './audit-table';
import { filterSelectStyle, Pagination } from './components';
import type { AuditProps, AuditTab } from './types';

const TAB_META: Record<AuditTab, { label: string; icon: string; color: string }> = {
    changes: { label: 'Perubahan Data', icon: 'history', color: C.primary },
    activity: { label: 'Aktivitas Pengguna', icon: 'activity', color: C.violet },
};

export default function AvanaAudit({ tab, logs, activity, tenants, filters }: AuditProps) {
    const meta = tab === 'changes' ? logs?.meta : activity?.meta;
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstSearch = useRef(true);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { ...filters, tab, search: search || undefined, page: 1 },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const applyFilter = (key: string, value: string) => {
        router.get(
            window.location.pathname,
            { ...filters, tab, [key]: value || undefined, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, tab, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const switchTab = (nextTab: AuditTab) => {
        if (nextTab === tab) {
            return;
        }

        router.get(
            window.location.pathname,
            { tenant_id: filters.tenant_id ?? undefined, tab: nextTab, page: 1 },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Audit Trail" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
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
                        <span>Pengaturan</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Audit Trail</span>
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
                        Audit Trail
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Catatan perubahan data sensitif dan aktivitas pengguna
                        {tenants.length > 0 ? ' di seluruh tenant' : ' di tenant Anda'}
                    </div>
                </div>

                {/* Tabs */}
                <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                    {(Object.keys(TAB_META) as AuditTab[]).map((key) => {
                        const isActive = key === tab;
                        const meta_ = TAB_META[key];

                        return (
                            <button
                                key={key}
                                onClick={() => switchTab(key)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    height: 38,
                                    padding: '0 16px',
                                    borderRadius: 9,
                                    border: 'none',
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    color: isActive ? '#fff' : meta_.color,
                                    background: isActive
                                        ? meta_.color
                                        : meta_.color === C.primary
                                          ? 'rgba(47,84,201,.1)'
                                          : 'rgba(124,58,237,.1)',
                                    boxShadow: isActive
                                        ? '0 2px 6px rgba(15,23,42,.14)'
                                        : 'none',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon name={meta_.icon} size={15} />
                                {meta_.label}
                            </button>
                        );
                    })}
                </div>

                {/* Table card */}
                <div
                    style={{
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                        overflow: 'hidden',
                    }}
                >
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
                        <div
                            style={{
                                position: 'relative',
                                flex: 1,
                                minWidth: 220,
                                maxWidth: 320,
                            }}
                        >
                            <AIcon
                                name="search"
                                size={16}
                                color={C.faint}
                                style={{
                                    position: 'absolute',
                                    left: 12,
                                    top: '50%',
                                    transform: 'translateY(-50%)',
                                }}
                            />
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder={
                                    tab === 'changes'
                                        ? 'Cari entitas atau aksi…'
                                        : 'Cari keterangan atau halaman…'
                                }
                                style={{
                                    width: '100%',
                                    height: 38,
                                    padding: '0 12px 0 36px',
                                    background: C.surface,
                                    border: '1px solid transparent',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    outline: 'none',
                                    transition: '.15s',
                                }}
                            />
                        </div>

                        {tab === 'changes' ? (
                            <select
                                aria-label="Aksi"
                                value={filters.action ?? ''}
                                onChange={(event) => applyFilter('action', event.target.value)}
                                style={filterSelectStyle}
                            >
                                <option value="">Semua Aksi</option>
                                <option value="created">Dibuat</option>
                                <option value="updated">Diubah</option>
                                <option value="deleted">Dihapus</option>
                            </select>
                        ) : (
                            <select
                                aria-label="Aktivitas"
                                value={filters.event ?? ''}
                                onChange={(event) => applyFilter('event', event.target.value)}
                                style={filterSelectStyle}
                            >
                                <option value="">Semua Aktivitas</option>
                                <option value="login">Masuk</option>
                                <option value="logout">Keluar</option>
                                <option value="login_failed">Login Gagal</option>
                                <option value="page_view">Buka Halaman</option>
                                <option value="data_created">Buat Data</option>
                                <option value="data_updated">Ubah Data</option>
                                <option value="data_deleted">Hapus Data</option>
                            </select>
                        )}

                        {tenants.length > 0 && (
                            <select
                                aria-label="Tenant"
                                value={filters.tenant_id ?? ''}
                                onChange={(event) => applyFilter('tenant_id', event.target.value)}
                                style={filterSelectStyle}
                            >
                                <option value="">Semua Tenant</option>
                                {tenants.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        )}

                        <div style={{ flex: 1 }} />
                    </div>

                    {/* Table */}
                    {tab === 'changes' ? (
                        <AuditTable rows={logs?.data ?? []} />
                    ) : (
                        <ActivityTable rows={activity?.data ?? []} />
                    )}

                    {/* Pagination footer */}
                    {meta && <Pagination meta={meta} onGoToPage={goToPage} />}
                </div>
            </div>
        </>
    );
}
