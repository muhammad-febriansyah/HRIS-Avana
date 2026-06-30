import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import DutyTravelController from '@/actions/App/Http/Controllers/Avana/DutyTravelController';
import { AIcon, ActionBtn, btnP, C, card, rp, statusBadge } from '@/lib/avana';
import { statusOptions } from './types';
import type {
    DinasFilters,
    DinasIndexProps,
    FlashProps,
    TravelStatus,
} from './types';

const headThStyle: CSSProperties = {
    padding: '11px 16px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

export default function DinasIndex({ travels, filters }: DinasIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = travels.meta;
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const applyFilters = (overrides: Partial<DinasFilters & { page: number }>) => {
        router.get(
            window.location.pathname,
            { ...filters, search, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        applyFilters({ page: 1 });
    };

    const setStatusFilter = (status: TravelStatus | undefined) => {
        applyFilters({ status, page: 1 });
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const approveTravel = (id: number) =>
        router.post(DutyTravelController.approve(id).url, {}, { preserveScroll: true });

    const rejectTravel = (id: number) =>
        router.post(DutyTravelController.reject(id).url, {}, { preserveScroll: true });

    return (
        <>
            <Head title="Perjalanan Dinas" />
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
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Perjalanan Dinas</span>
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
                            Perjalanan Dinas
                        </h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                            Ajukan dan kelola persetujuan perjalanan dinas tim Anda
                        </div>
                    </div>
                    <Link
                        href={DutyTravelController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Ajukan Dinas
                    </Link>
                </div>

                {/* List + approval */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 12,
                        }}
                    >
                        <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                            Daftar Perjalanan Dinas
                        </div>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                            <form onSubmit={submitSearch}>
                                <input
                                    type="search"
                                    placeholder="Cari tujuan / karyawan…"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    style={{
                                        height: 36,
                                        width: 200,
                                        padding: '0 12px',
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        fontSize: 13,
                                        color: C.text,
                                        outline: 'none',
                                    }}
                                />
                            </form>
                            <select
                                value={filters.status ?? ''}
                                onChange={(event) =>
                                    setStatusFilter((event.target.value || undefined) as TravelStatus | undefined)
                                }
                                style={{
                                    height: 36,
                                    padding: '0 12px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color: C.muted,
                                    background: '#fff',
                                    outline: 'none',
                                    cursor: 'pointer',
                                }}
                            >
                                <option value="">Semua Status</option>
                                {statusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 720 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Tujuan</th>
                                    <th style={headThStyle}>Periode</th>
                                    <th style={headThStyle}>Estimasi</th>
                                    <th style={headThStyle}>Uang Saku</th>
                                    <th style={headThStyle}>Status</th>
                                    <th style={{ ...headThStyle, textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {travels.data.length === 0 && (
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
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <AIcon name="plane" size={28} color={C.faint} />
                                                <div>Tidak ada perjalanan dinas.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {travels.data.map((row) => {
                                    const badge = statusBadge(row.status_label);

                                    return (
                                        <tr key={row.id} style={{ borderTop: `1px solid ${C.line}` }}>
                                            <td style={{ padding: '12px 16px' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                    <div
                                                        style={{
                                                            width: 32,
                                                            height: 32,
                                                            borderRadius: '50%',
                                                            flex: 'none',
                                                            background: row.employee?.avatar_color ?? C.faint,
                                                            color: '#fff',
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent: 'center',
                                                            fontSize: 11.5,
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {row.employee?.initials ?? '?'}
                                                    </div>
                                                    <div>
                                                        <div
                                                            style={{
                                                                fontSize: 13,
                                                                fontWeight: 500,
                                                                color: C.text,
                                                            }}
                                                        >
                                                            {row.employee?.name ?? '—'}
                                                        </div>
                                                        <div style={{ fontSize: 11.5, color: C.faint }}>
                                                            {row.employee?.employee_number ?? ''}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                <div style={{ fontSize: 13, color: C.text }}>
                                                    {row.destination}
                                                </div>
                                                {row.purpose && (
                                                    <div style={{ fontSize: 11.5, color: C.faint }}>
                                                        {row.purpose}
                                                    </div>
                                                )}
                                            </td>
                                            <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.text }}>
                                                {row.start_date} – {row.end_date}
                                                {row.days !== null && (
                                                    <div style={{ fontSize: 11.5, color: C.faint }}>
                                                        {row.days} hari
                                                    </div>
                                                )}
                                            </td>
                                            <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.text }}>
                                                {row.estimated_cost !== null ? rp(row.estimated_cost) : '—'}
                                            </td>
                                            <td style={{ padding: '12px 16px', fontSize: 12.5, color: C.text }}>
                                                {row.per_diem !== null ? rp(row.per_diem) : '—'}
                                            </td>
                                            <td style={{ padding: '12px 16px' }}>
                                                <span
                                                    style={{
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
                                            <td style={{ padding: '12px 16px', textAlign: 'right' }}>
                                                {row.status === 'pending' ? (
                                                    <div
                                                        style={{
                                                            display: 'inline-flex',
                                                            gap: 6,
                                                            flexWrap: 'wrap',
                                                            justifyContent: 'flex-end',
                                                        }}
                                                    >
                                                        <ActionBtn
                                                            icon="check"
                                                            label="Setujui"
                                                            variant="success"
                                                            onClick={() => approveTravel(row.id)}
                                                        />
                                                        <ActionBtn
                                                            icon="x"
                                                            label="Tolak"
                                                            variant="warning"
                                                            onClick={() => rejectTravel(row.id)}
                                                        />
                                                    </div>
                                                ) : (
                                                    <span style={{ fontSize: 12.5, color: C.faint }}>—</span>
                                                )}
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
                            </span>
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
                                    gap: 5,
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
        </>
    );
}
