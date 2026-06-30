import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import CashAdvanceController from '@/actions/App/Http/Controllers/Avana/CashAdvanceController';
import { AIcon, btnP, C, card, rp } from '@/lib/avana';
import { StatusPill } from './components';
import { STATUS_OPTIONS } from './types';
import type { FlashProps, KasbonFilters, KasbonIndexProps } from './types';

const headThStyle: CSSProperties = {
    padding: '11px 16px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

export default function KasbonIndex({ requests, filters }: KasbonIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = requests.meta;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const applyFilters = (next: Partial<KasbonFilters>) => {
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

    const approve = (id: number) => {
        router.post(
            CashAdvanceController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    };

    const reject = (id: number) => {
        router.post(
            CashAdvanceController.reject(id).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Kasbon" />
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
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Kasbon</span>
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
                            Kasbon / Cash Advance
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Ajukan dan kelola persetujuan kasbon karyawan
                        </div>
                    </div>
                    <Link
                        href={CashAdvanceController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Ajukan Kasbon
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
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Daftar Kasbon
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
                                    placeholder="Cari karyawan"
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
                                        width: 180,
                                    }}
                                />
                            </div>
                            <select
                                value={filters.status ?? ''}
                                onChange={(event) =>
                                    applyFilters({
                                        status: (event.target.value ||
                                            undefined) as KasbonFilters['status'],
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
                                {STATUS_OPTIONS.map((option) => (
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
                                minWidth: 760,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Jumlah</th>
                                    <th style={headThStyle}>Cicilan</th>
                                    <th style={headThStyle}>Potongan/bln</th>
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
                                {requests.data.length === 0 && (
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
                                                    name="wallet"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Tidak ada pengajuan kasbon.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {requests.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
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
                                                padding: '12px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.text,
                                            }}
                                        >
                                            {rp(row.amount)}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.muted,
                                            }}
                                        >
                                            {row.installments}x bln
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.text,
                                            }}
                                        >
                                            {rp(row.monthly_deduction)}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.muted,
                                            }}
                                        >
                                            {row.request_date ?? '—'}
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
                                            {row.status === 'pending' ? (
                                                <div
                                                    style={{
                                                        display: 'inline-flex',
                                                        gap: 6,
                                                    }}
                                                >
                                                    <button
                                                        onClick={() =>
                                                            approve(row.id)
                                                        }
                                                        style={{
                                                            display:
                                                                'inline-flex',
                                                            alignItems: 'center',
                                                            gap: 5,
                                                            height: 30,
                                                            padding: '0 11px',
                                                            border: 'none',
                                                            borderRadius: 7,
                                                            background:
                                                                'rgba(22,163,74,.1)',
                                                            color: C.green,
                                                            fontSize: 12,
                                                            fontWeight: 600,
                                                            cursor: 'pointer',
                                                            transition: '.15s',
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="check"
                                                            size={14}
                                                            color={C.green}
                                                        />
                                                        Setujui
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            reject(row.id)
                                                        }
                                                        style={{
                                                            width: 30,
                                                            height: 30,
                                                            border: 'none',
                                                            borderRadius: 7,
                                                            background:
                                                                'rgba(220,38,38,.08)',
                                                            color: C.red,
                                                            cursor: 'pointer',
                                                            display:
                                                                'inline-flex',
                                                            alignItems: 'center',
                                                            justifyContent:
                                                                'center',
                                                            transition: '.15s',
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="x"
                                                            size={14}
                                                            color={C.red}
                                                        />
                                                    </button>
                                                </div>
                                            ) : (
                                                <span
                                                    style={{
                                                        fontSize: 12.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    —
                                                </span>
                                            )}
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
