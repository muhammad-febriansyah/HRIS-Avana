import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import SettlementController from '@/actions/App/Http/Controllers/Avana/SettlementController';
import { AIcon, ActionBtn, btnP, C, card, rp } from '@/lib/avana';
import { KpiCard, StatusPill } from './components';
import type {
    FlashProps,
    SettlementFilters,
    SettlementIndexProps,
} from './types';

const headThStyle: CSSProperties = {
    padding: '11px 16px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

const cellStyle: CSSProperties = {
    padding: '12px 16px',
    fontSize: 12.5,
    color: C.muted,
};

export default function SettlementIndex({
    settlements,
    filters,
    statusOptions,
    kpis,
    canManage,
}: SettlementIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = settlements.meta;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const applyFilters = (next: Partial<SettlementFilters>) => {
        router.get(
            window.location.pathname,
            { ...filters, ...next, page: 1 },
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

    return (
        <>
            <Head title="Settlement" />
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
                            <span>Finance</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Settlement</span>
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
                            Settlement
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Klaim biaya perjalanan dinas &amp; operasional —
                            persetujuan manager, verifikasi &amp; pembayaran
                            Finance
                        </div>
                    </div>
                    {canManage && (
                        <Link
                            href={SettlementController.create().url}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Buat Settlement
                        </Link>
                    )}
                </div>

                {/* KPIs */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(200px, 1fr))',
                        gap: 14,
                        marginBottom: 20,
                    }}
                >
                    <KpiCard
                        icon="clock"
                        label="Menunggu Manager"
                        value={String(kpis.submitted)}
                        accent="#D97706"
                    />
                    <KpiCard
                        icon="badge-check"
                        label="Menunggu Finance"
                        value={String(kpis.manager_approved)}
                        accent={C.primary}
                    />
                    <KpiCard
                        icon="file-check"
                        label="Dibayar"
                        value={String(kpis.paid)}
                        accent="#16A34A"
                    />
                    <KpiCard
                        icon="wallet"
                        label="Total Dibayar"
                        value={rp(kpis.paid_amount)}
                        accent="#0EA5E9"
                    />
                </div>

                {/* List */}
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
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Daftar Settlement
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                alignItems: 'center',
                            }}
                        >
                            <div style={{ position: 'relative' }}>
                                <span
                                    style={{
                                        position: 'absolute',
                                        left: 10,
                                        top: '50%',
                                        transform: 'translateY(-50%)',
                                        display: 'inline-flex',
                                    }}
                                >
                                    <AIcon
                                        name="search"
                                        size={15}
                                        color={C.faint}
                                    />
                                </span>
                                <input
                                    type="search"
                                    placeholder="Cari nomor / judul / karyawan"
                                    defaultValue={filters.search ?? ''}
                                    onChange={(event) =>
                                        applyFilters({
                                            search:
                                                event.target.value || undefined,
                                        })
                                    }
                                    style={{
                                        height: 36,
                                        padding: '0 12px 0 32px',
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        fontSize: 12.5,
                                        color: C.text,
                                        outline: 'none',
                                        width: 220,
                                    }}
                                />
                            </div>
                            <select
                                value={filters.status ?? ''}
                                onChange={(event) =>
                                    applyFilters({
                                        status: (event.target.value ||
                                            undefined) as SettlementFilters['status'],
                                    })
                                }
                                style={{
                                    height: 36,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    color: C.muted,
                                    background: '#fff',
                                    outline: 'none',
                                    cursor: 'pointer',
                                }}
                            >
                                <option value="">Semua Status</option>
                                {statusOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 900,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>No.</th>
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Judul</th>
                                    <th style={headThStyle}>Total</th>
                                    <th style={headThStyle}>Tanggal</th>
                                    <th style={headThStyle}>Status</th>
                                    <th
                                        style={{
                                            ...headThStyle,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {settlements.data.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
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
                                                <AIcon
                                                    name="file-check"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada settlement.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {settlements.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontFamily:
                                                    'ui-monospace, monospace',
                                                color: C.text,
                                            }}
                                        >
                                            {row.number ?? '—'}
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        width: 32,
                                                        height: 32,
                                                        borderRadius: '50%',
                                                        flex: 'none',
                                                        background:
                                                            row.employee
                                                                ?.avatar_color ??
                                                            C.faint,
                                                        color: '#fff',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {row.employee?.initials ??
                                                        '?'}
                                                </div>
                                                <div>
                                                    <div
                                                        style={{
                                                            fontSize: 13,
                                                            fontWeight: 500,
                                                            color: C.text,
                                                        }}
                                                    >
                                                        {row.employee?.name ??
                                                            '—'}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {row.employee
                                                            ?.employee_number ??
                                                            ''}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                color: C.text,
                                            }}
                                        >
                                            {row.title}
                                        </td>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                color: C.navy,
                                                fontWeight: 600,
                                                fontSize: 13,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {rp(row.total)}
                                        </td>
                                        <td style={cellStyle}>
                                            {row.submission_date ?? '—'}
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <StatusPill
                                                label={row.status_label}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <ActionBtn
                                                icon="eye"
                                                label="Detail"
                                                variant="warning"
                                                onClick={() =>
                                                    router.visit(
                                                        SettlementController.show(row.route_key,
                                                        ).url,
                                                    )
                                                }
                                            />
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
                            </span>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 6,
                                alignItems: 'center',
                            }}
                        >
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
                                    color:
                                        meta.current_page <= 1
                                            ? C.faint
                                            : C.text,
                                    cursor:
                                        meta.current_page <= 1
                                            ? 'not-allowed'
                                            : 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 5,
                                }}
                            >
                                <AIcon name="chevron-left" size={15} />
                            </button>
                            <span
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    padding: '0 4px',
                                }}
                            >
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
                                    color:
                                        meta.current_page >= meta.last_page
                                            ? C.faint
                                            : C.text,
                                    cursor:
                                        meta.current_page >= meta.last_page
                                            ? 'not-allowed'
                                            : 'pointer',
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
