import { AIcon, C, card } from '@/lib/avana';
import { initialsOf } from './components';
import type { Slip } from './types';

interface SlipDetailProps {
    slip: Slip;
    period: string | null;
}

/** Sample payslip card for the first active employee. */
export function SlipDetail({ slip, period }: SlipDetailProps) {
    return (
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
                            Slip gaji {period ?? ''}
                        </div>
                    </div>
                </div>
                <a
                    href="/avana/laporan/export/payroll"
                    title="Unduh data payroll"
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
