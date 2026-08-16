import { router } from '@inertiajs/react';
import { useState } from 'react';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, C, card } from '@/lib/avana';
import { initialsOf } from './components';
import type { Slip, SlipLine } from './types';

interface SlipDetailProps {
    slip: Slip;
    period: string | null;
    /** Active employees selectable for the preview; empty hides the picker. */
    employees?: { id: number; name: string }[];
}

/**
 * One payslip line. A line that carries a `why` opens on click and explains
 * the number in plain words — which screen sets it and through which formula.
 */
function SlipRow({
    line,
    negative,
}: {
    line: SlipLine;
    negative?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const explainable = Boolean(line.why);

    return (
        <div style={{ borderBottom: 'none' }}>
            <div
                onClick={explainable ? () => setOpen(!open) : undefined}
                title={explainable ? 'Klik untuk lihat asal angka ini' : undefined}
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '7px 0',
                    cursor: explainable ? 'pointer' : 'default',
                }}
            >
                <span
                    style={{
                        fontSize: 13,
                        color: C.muted,
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 5,
                    }}
                >
                    {line.k}
                    {explainable && (
                        <AIcon
                            name={open ? 'chevron-up' : 'info'}
                            size={13}
                            color={C.faint}
                        />
                    )}
                </span>
                <span
                    style={{
                        fontSize: 13,
                        color: C.text,
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {negative ? `- ${line.v}` : line.v}
                </span>
            </div>
            {open && line.why && (
                <div
                    style={{
                        fontSize: 11.5,
                        lineHeight: 1.55,
                        color: C.muted,
                        background: '#F6F8FC',
                        border: `1px solid ${C.border}`,
                        borderRadius: 8,
                        padding: '8px 11px',
                        margin: '0 0 8px',
                    }}
                >
                    {line.why}
                </div>
            )}
        </div>
    );
}

/**
 * Payslip preview card. Picking an employee recomputes their slip live from
 * the current configuration — a dry run, nothing is saved — so HR can sanity
 * check one person before running the whole payroll.
 */
export function SlipDetail({ slip, period, employees = [] }: SlipDetailProps) {
    const previewFor = (employeeId: string) => {
        if (!employeeId) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('slip_employee', employeeId);
        router.visit(url.pathname + url.search, {
            only: ['slip', 'filters'],
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            {employees.length > 0 && (
                <div
                    style={{
                        padding: '12px 22px',
                        borderBottom: `1px solid ${C.line}`,
                        background: '#F8FAFD',
                    }}
                >
                    <SearchableSelect
                        value={slip.employee_id ? String(slip.employee_id) : ''}
                        onChange={previewFor}
                        options={employees.map((employee) => ({
                            value: String(employee.id),
                            label: employee.name,
                        }))}
                        placeholder="Pratinjau slip karyawan…"
                        searchPlaceholder="Cari nama karyawan…"
                        style={{ width: '100%' }}
                    />
                    <div
                        style={{
                            fontSize: 11,
                            color: C.faint,
                            marginTop: 6,
                        }}
                    >
                        Dihitung live dari konfigurasi saat ini — belum
                        tersimpan; hasil run bisa berbeda jika konfigurasi
                        berubah.
                    </div>
                </div>
            )}
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
                            Slip gaji {period ?? ''}
                        </div>
                    </div>
                </div>
                <a
                    href={
                        slip.payslip_id
                            ? `/avana/payroll/payslip/${slip.payslip_id}/pdf`
                            : '/avana/laporan/export/payroll'
                    }
                    title={
                        slip.payslip_id
                            ? 'Unduh slip gaji (PDF terproteksi kata sandi)'
                            : 'Unduh data payroll'
                    }
                    style={{
                        width: 34,
                        height: 34,
                        border: `1px solid ${C.border}`,
                        background: '#fff',
                        borderRadius: 8,
                        cursor: 'pointer',
                        color: C.primary,
                        textDecoration: 'none',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <AIcon name="printer" size={16} color={C.primary} />
                </a>
            </div>
            <div style={{ padding: '18px 22px' }}>
                {slip.notice && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 8,
                            alignItems: 'flex-start',
                            padding: '10px 12px',
                            marginBottom: 14,
                            borderRadius: 8,
                            border: `1px solid ${C.amber}33`,
                            background: `${C.amber}14`,
                            color: C.amber,
                            fontSize: 12.5,
                            lineHeight: 1.5,
                        }}
                    >
                        <AIcon name="triangle-alert" size={15} color={C.amber} />
                        <span>{slip.notice}</span>
                    </div>
                )}
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
                    <SlipRow key={earning.k} line={earning} />
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
                    <SlipRow key={deduction.k} line={deduction} negative />
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
                        background: 'linear-gradient(120deg,#0E1A3A,#2F54C9)',
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
    );
}
