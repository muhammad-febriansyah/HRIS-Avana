import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import PositionComponentController from '@/actions/App/Http/Controllers/Avana/PositionComponentController';
import { AIcon, btnOut, btnP, C, card, statusBadge } from '@/lib/avana';
import type { FlashProps, PaginationMeta } from './employees/types';

/** A single payroll period row as serialized by `PayrollPeriodResource`. */
interface Period {
    id: number;
    periode: string;
    bayar: string | null;
    karyawan: number;
    netR: string;
    grossR: string;
    status: string;
    status_label: string;
}

/** Latest-run summary block backing the run-summary card. */
interface PayrollSummary {
    period: string | null;
    status: string | null;
    status_label: string;
    total_gross: string;
    total_deduction: string;
    total_tax: string;
    total_net: string;
    employee_count: number;
}

/** A single earning/deduction line on the sample payslip. */
interface SlipLine {
    k: string;
    v: string;
}

/** Computed sample payslip for the first active employee. */
interface Slip {
    employee: string;
    earnings: SlipLine[];
    deductions: SlipLine[];
    gross: string;
    deduction: string;
    net: string;
}

interface PayrollFilters {
    search?: string;
    status?: string;
    per_page?: string;
}

interface PayrollProps {
    periods: {
        data: Period[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    summary: PayrollSummary;
    slip: Slip;
    filters: PayrollFilters;
}

/** Build up-to-two-letter initials from an employee's full name. */
function initialsOf(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

export default function AvanaPayroll({
    periods,
    summary,
    slip,
    filters,
}: PayrollProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = periods.meta;
    const summaryBadge = statusBadge(summary.status_label);
    const isLocked = summary.status === 'locked';

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const runPayroll = () => {
        if (isLocked) {
            return;
        }

        router.post(PayrollController.run().url, {}, { preserveScroll: true });
    };

    const lockPayroll = () => {
        if (isLocked) {
            return;
        }

        router.post(PayrollController.lock().url, {}, { preserveScroll: true });
    };

    const exportPayroll = () => {
        toast.info('Menyiapkan export', {
            description: 'File akan diunduh sebentar lagi',
        });
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
            <Head title="Payroll" />
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
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Payroll</span>
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
                            Payroll
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola penggajian &amp; slip gaji karyawan
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <Link
                            href={PositionComponentController.index().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="sliders-horizontal" size={16} />
                            Komponen per Jabatan
                        </Link>
                        <button onClick={exportPayroll} style={btnOut}>
                            <AIcon name="download" size={16} />
                            Export
                        </button>
                        <button
                            onClick={lockPayroll}
                            disabled={isLocked}
                            style={{
                                ...btnOut,
                                opacity: isLocked ? 0.5 : 1,
                                cursor: isLocked ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon name="lock" size={16} />
                            Kunci Periode
                        </button>
                        <button
                            onClick={runPayroll}
                            disabled={isLocked}
                            style={{
                                ...btnP,
                                opacity: isLocked ? 0.5 : 1,
                                cursor: isLocked ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon name="play" size={16} color="#fff" />
                            Jalankan Payroll
                        </button>
                    </div>
                </div>

                {isLocked && (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 9,
                            padding: '12px 16px',
                            borderRadius: 10,
                            background: 'rgba(217,119,6,.08)',
                            border: '1px solid rgba(217,119,6,.25)',
                            color: C.amber,
                            fontSize: 13,
                            fontWeight: 500,
                            marginBottom: 18,
                        }}
                    >
                        <AIcon name="lock" size={16} color={C.amber} />
                        Periode terkunci — data tidak bisa diubah
                    </div>
                )}

                {/* Run summary */}
                <div style={{ ...card, marginBottom: 18, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '18px 22px',
                            borderBottom: `1px solid ${C.line}`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 10,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 11,
                            }}
                        >
                            <div
                                style={{
                                    width: 40,
                                    height: 40,
                                    borderRadius: 10,
                                    background: 'rgba(47,84,201,.1)',
                                    color: C.primary,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon
                                    name="calendar"
                                    size={20}
                                    color={C.primary}
                                />
                            </div>
                            <div>
                                <div
                                    style={{
                                        fontSize: 16,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    Periode {summary.period ?? '—'}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                    }}
                                >
                                    {summary.employee_count.toLocaleString(
                                        'id-ID',
                                    )}{' '}
                                    karyawan
                                </div>
                            </div>
                        </div>
                        <span
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 6,
                                padding: '5px 12px',
                                borderRadius: 100,
                                fontSize: 12,
                                fontWeight: 600,
                                color: summaryBadge.color,
                                background: summaryBadge.bg,
                            }}
                        >
                            <AIcon
                                name="circle-dot"
                                size={13}
                                color={summaryBadge.color}
                            />
                            {summaryBadge.label}
                        </span>
                    </div>
                    <div
                        className="avn-kpi"
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(4,1fr)',
                            gap: 0,
                        }}
                    >
                        <div
                            style={{
                                padding: '20px 22px',
                                borderRight: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={{ fontSize: 12.5, color: C.muted }}>
                                Total Gross
                            </div>
                            <div
                                style={{
                                    fontSize: 21,
                                    fontWeight: 700,
                                    color: C.navy,
                                    marginTop: 5,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {summary.total_gross}
                            </div>
                        </div>
                        <div
                            style={{
                                padding: '20px 22px',
                                borderRight: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={{ fontSize: 12.5, color: C.muted }}>
                                Total Potongan
                            </div>
                            <div
                                style={{
                                    fontSize: 21,
                                    fontWeight: 700,
                                    color: C.amber,
                                    marginTop: 5,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {summary.total_deduction}
                            </div>
                        </div>
                        <div
                            style={{
                                padding: '20px 22px',
                                borderRight: `1px solid ${C.line}`,
                            }}
                        >
                            <div style={{ fontSize: 12.5, color: C.muted }}>
                                Total Pajak (PPh 21)
                            </div>
                            <div
                                style={{
                                    fontSize: 21,
                                    fontWeight: 700,
                                    color: C.red,
                                    marginTop: 5,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {summary.total_tax}
                            </div>
                        </div>
                        <div
                            style={{
                                padding: '20px 22px',
                                background: '#FAFBFE',
                            }}
                        >
                            <div style={{ fontSize: 12.5, color: C.muted }}>
                                Total Net (Take Home)
                            </div>
                            <div
                                style={{
                                    fontSize: 21,
                                    fontWeight: 700,
                                    color: C.green,
                                    marginTop: 5,
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {summary.total_net}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.25fr 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Periods */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div
                            style={{
                                padding: '16px 18px',
                                borderBottom: `1px solid ${C.border}`,
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Riwayat Periode
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 480,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th
                                            style={{
                                                padding: '11px 18px',
                                                textAlign: 'left',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        >
                                            Periode
                                        </th>
                                        <th
                                            style={{
                                                padding: '11px 16px',
                                                textAlign: 'right',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        >
                                            Net
                                        </th>
                                        <th
                                            style={{
                                                padding: '11px 16px',
                                                textAlign: 'left',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        >
                                            Status
                                        </th>
                                        <th
                                            style={{
                                                padding: '11px 18px',
                                                textAlign: 'right',
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: C.faint,
                                                textTransform: 'uppercase',
                                            }}
                                        ></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {periods.data.length === 0 && (
                                        <tr
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                colSpan={4}
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
                                                        Belum ada periode
                                                        payroll.
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {periods.data.map((period) => {
                                        const badge = statusBadge(
                                            period.status_label,
                                        );

                                        return (
                                            <tr
                                                key={period.id}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                    transition: '.15s',
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        padding: '13px 18px',
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            fontSize: 13.5,
                                                            fontWeight: 600,
                                                            color: C.navy,
                                                        }}
                                                    >
                                                        {period.periode}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {period.karyawan}{' '}
                                                        karyawan
                                                    </div>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                        textAlign: 'right',
                                                        fontSize: 13,
                                                        color: C.text,
                                                        fontVariantNumeric:
                                                            'tabular-nums',
                                                    }}
                                                >
                                                    {period.netR}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 16px',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            padding: '3px 10px',
                                                            borderRadius: 100,
                                                            fontSize: 11.5,
                                                            fontWeight: 600,
                                                            color: badge.color,
                                                            background:
                                                                badge.bg,
                                                        }}
                                                    >
                                                        {badge.label}
                                                    </span>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '13px 18px',
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    <button
                                                        style={{
                                                            width: 30,
                                                            height: 30,
                                                            border: `1px solid ${C.border}`,
                                                            background: '#fff',
                                                            borderRadius: 7,
                                                            cursor: 'pointer',
                                                            color: C.muted,
                                                            display:
                                                                'inline-flex',
                                                            alignItems:
                                                                'center',
                                                            justifyContent:
                                                                'center',
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="chevron-right"
                                                            size={16}
                                                        />
                                                    </button>
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
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
                                    {meta.from ?? 0}–{meta.to ?? 0}
                                </span>{' '}
                                dari{' '}
                                <span
                                    style={{ color: C.text, fontWeight: 500 }}
                                >
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
                                    onClick={() =>
                                        goToPage(meta.current_page - 1)
                                    }
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
                                    disabled={
                                        meta.current_page >= meta.last_page
                                    }
                                    onClick={() =>
                                        goToPage(meta.current_page + 1)
                                    }
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

                    {/* Slip gaji detail */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div
                            style={{
                                padding: '18px 22px',
                                borderBottom: `1px solid ${C.line}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 11,
                                }}
                            >
                                <div
                                    style={{
                                        width: 38,
                                        height: 38,
                                        borderRadius: '50%',
                                        background: C.primary,
                                        color: '#fff',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: 13,
                                        fontWeight: 600,
                                    }}
                                >
                                    {initialsOf(slip.employee)}
                                </div>
                                <div>
                                    <div
                                        style={{
                                            fontSize: 14,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {slip.employee}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                        }}
                                    >
                                        Slip gaji {summary.period ?? ''}
                                    </div>
                                </div>
                            </div>
                            <button
                                onClick={() =>
                                    toast.info('Menyiapkan slip gaji', {
                                        description:
                                            'File akan diunduh sebentar lagi',
                                    })
                                }
                                style={{
                                    width: 34,
                                    height: 34,
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    cursor: 'pointer',
                                    color: C.primary,
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon
                                    name="printer"
                                    size={16}
                                    color={C.primary}
                                />
                            </button>
                        </div>
                        <div style={{ padding: '18px 22px' }}>
                            <div
                                style={{
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.green,
                                    textTransform: 'uppercase',
                                    letterSpacing: '.04em',
                                    marginBottom: 8,
                                }}
                            >
                                Pendapatan
                            </div>
                            {slip.earnings.map((earning) => (
                                <div
                                    key={earning.k}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        padding: '7px 0',
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        {earning.k}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 13,
                                            color: C.text,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {earning.v}
                                    </span>
                                </div>
                            ))}
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    padding: '9px 0',
                                    borderTop: `1px solid ${C.line}`,
                                    marginTop: 4,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.text,
                                    }}
                                >
                                    Total Pendapatan
                                </span>
                                <span
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.green,
                                        fontVariantNumeric: 'tabular-nums',
                                    }}
                                >
                                    {slip.gross}
                                </span>
                            </div>

                            <div
                                style={{
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.red,
                                    textTransform: 'uppercase',
                                    letterSpacing: '.04em',
                                    margin: '14px 0 8px',
                                }}
                            >
                                Potongan
                            </div>
                            {slip.deductions.map((deduction) => (
                                <div
                                    key={deduction.k}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        padding: '7px 0',
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        {deduction.k}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 13,
                                            color: C.text,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        - {deduction.v}
                                    </span>
                                </div>
                            ))}
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    padding: '9px 0',
                                    borderTop: `1px solid ${C.line}`,
                                    marginTop: 4,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.text,
                                    }}
                                >
                                    Total Potongan
                                </span>
                                <span
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.red,
                                        fontVariantNumeric: 'tabular-nums',
                                    }}
                                >
                                    - {slip.deduction}
                                </span>
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    background:
                                        'linear-gradient(120deg,#0E1A3A,#2F54C9)',
                                    borderRadius: 10,
                                    padding: '15px 18px',
                                    marginTop: 16,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                        color: 'rgba(255,255,255,.85)',
                                    }}
                                >
                                    Gaji Bersih (Take Home Pay)
                                </span>
                                <span
                                    style={{
                                        fontSize: 19,
                                        fontWeight: 700,
                                        color: '#fff',
                                        fontVariantNumeric: 'tabular-nums',
                                    }}
                                >
                                    {slip.net}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
