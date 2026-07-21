import { router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import type { Recipient } from './types';

const th: CSSProperties = {
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    padding: '11px 16px',
    textTransform: 'uppercase',
    letterSpacing: '.03em',
};
const td: CSSProperties = { fontSize: 13, color: C.text, padding: '12px 16px' };

const METHOD_LABEL: Record<string, string> = {
    ter_bulanan: 'TER Bulanan',
    ter_harian: 'TER Harian',
    '50pct_pasal17': '50% × Pasal 17',
    pasal17: 'Pasal 17',
};

export function RecipientsTable({
    recipients,
    period,
    periodId,
    search,
}: {
    recipients: Recipient[];
    period: string | null;
    periodId: number | null;
    search?: string;
}) {
    const [q, setQ] = useState(search ?? '');

    const submitSearch = (value: string) => {
        router.get(
            '/avana/payroll',
            { period: periodId ?? undefined, search: value || undefined },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <div style={{ ...card, padding: 0, overflow: 'hidden' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                    flexWrap: 'wrap',
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.line}`,
                }}
            >
                <div>
                    <div
                        style={{ fontSize: 15, fontWeight: 600, color: C.navy }}
                    >
                        Daftar Penerima Gaji
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.faint,
                            marginTop: 2,
                        }}
                    >
                        {period ?? '—'} · {recipients.length} karyawan
                    </div>
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        border: `1px solid ${C.border}`,
                        borderRadius: 8,
                        padding: '6px 10px',
                        minWidth: 220,
                    }}
                >
                    <AIcon name="search" size={15} color={C.faint} />
                    <input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                submitSearch(q);
                            }
                        }}
                        onBlur={() => submitSearch(q)}
                        placeholder="Cari nama / NIP…"
                        style={{
                            border: 'none',
                            outline: 'none',
                            fontSize: 13,
                            flex: 1,
                            background: 'transparent',
                            color: C.text,
                        }}
                    />
                </div>
            </div>

            {recipients.length === 0 ? (
                <div
                    style={{
                        padding: '44px 18px',
                        textAlign: 'center',
                        color: C.faint,
                        fontSize: 13.5,
                    }}
                >
                    <AIcon name="users" size={28} color={C.faint} />
                    <div style={{ marginTop: 8 }}>
                        Belum ada penerima gaji untuk periode ini. Jalankan
                        proses gaji terlebih dahulu.
                    </div>
                </div>
            ) : (
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
                                <th style={th}>Karyawan</th>
                                <th style={{ ...th, textAlign: 'right' }}>
                                    Gross
                                </th>
                                <th style={{ ...th, textAlign: 'right' }}>
                                    Potongan
                                </th>
                                <th style={{ ...th, textAlign: 'right' }}>
                                    PPh 21
                                </th>
                                <th style={{ ...th, textAlign: 'right' }}>
                                    Take Home
                                </th>
                                <th style={th}>Skema Pajak</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recipients.map((r) => (
                                <tr
                                    key={r.id}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td style={td}>
                                        <div
                                            style={{
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {r.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                            }}
                                        >
                                            {r.employee_number ?? '—'}
                                        </div>
                                    </td>
                                    <td
                                        style={{
                                            ...td,
                                            textAlign: 'right',
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {r.gross}
                                    </td>
                                    <td
                                        style={{
                                            ...td,
                                            textAlign: 'right',
                                            color: C.amber,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {r.deduction}
                                    </td>
                                    <td
                                        style={{
                                            ...td,
                                            textAlign: 'right',
                                            color: C.red,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {r.tax}
                                    </td>
                                    <td
                                        style={{
                                            ...td,
                                            textAlign: 'right',
                                            fontWeight: 600,
                                            color: C.green,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {r.net}
                                    </td>
                                    <td style={td}>
                                        {r.tax_method ? (
                                            <span
                                                style={{
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    padding: '3px 9px',
                                                    borderRadius: 6,
                                                    color: C.primary,
                                                    background:
                                                        'rgba(47,84,201,.1)',
                                                }}
                                            >
                                                {METHOD_LABEL[r.tax_method] ??
                                                    r.tax_method}
                                            </span>
                                        ) : (
                                            <span style={{ color: C.faint }}>
                                                —
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
