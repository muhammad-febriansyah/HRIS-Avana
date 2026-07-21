import { router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import type { PaginationMeta } from '../employees/types';
import type { PayrollFilters, Recipient } from './types';

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

const SCHEME_OPTIONS: { value: string; label: string }[] = [
    { value: '', label: 'Semua skema' },
    { value: 'ter_bulanan', label: 'TER Bulanan' },
    { value: 'ter_harian', label: 'TER Harian' },
    { value: 'pasal17', label: 'Pasal 17' },
    { value: '50pct_pasal17', label: '50% × Pasal 17' },
];

export function RecipientsTable({
    recipients,
    meta,
    period,
    periodId,
    filters,
}: {
    recipients: Recipient[];
    meta: PaginationMeta | null;
    period: string | null;
    periodId: number | null;
    filters: PayrollFilters;
}) {
    const [q, setQ] = useState(filters.search ?? '');
    const scheme = filters.scheme ?? '';
    const onlyPaid = filters.only_paid === '1' || filters.only_paid === true;

    /** Reload the recipient list, merging one changed filter over the rest. */
    const apply = (patch: Partial<Record<string, string | number | undefined>>) => {
        router.get(
            '/avana/payroll',
            {
                period: periodId ?? undefined,
                search: q || undefined,
                scheme: scheme || undefined,
                only_paid: onlyPaid ? '1' : undefined,
                ...patch,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const total = meta?.total ?? recipients.length;

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
                        {period ?? '—'} · {total} karyawan
                    </div>
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        flexWrap: 'wrap',
                    }}
                >
                    {/* Tax scheme filter */}
                    <select
                        value={scheme}
                        onChange={(e) => apply({ scheme: e.target.value || undefined })}
                        style={{
                            height: 34,
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            padding: '0 10px',
                            fontSize: 13,
                            color: C.text,
                            background: '#fff',
                            cursor: 'pointer',
                        }}
                    >
                        {SCHEME_OPTIONS.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    {/* Only-paid toggle */}
                    <button
                        type="button"
                        onClick={() => apply({ only_paid: onlyPaid ? undefined : '1' })}
                        style={{
                            height: 34,
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            padding: '0 12px',
                            border: `1px solid ${onlyPaid ? C.primary : C.border}`,
                            borderRadius: 8,
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: onlyPaid ? C.primary : C.muted,
                            background: onlyPaid ? 'rgba(47,84,201,.08)' : '#fff',
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon
                            name={onlyPaid ? 'check-square' : 'square'}
                            size={15}
                        />
                        Hanya dibayar
                    </button>
                    {/* Search */}
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            padding: '6px 10px',
                            minWidth: 200,
                        }}
                    >
                        <AIcon name="search" size={15} color={C.faint} />
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    apply({ search: q || undefined });
                                }
                            }}
                            onBlur={() => apply({ search: q || undefined })}
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
                        {filters.search || scheme || onlyPaid
                            ? 'Tidak ada penerima yang cocok dengan filter.'
                            : 'Belum ada penerima gaji untuk periode ini. Jalankan proses gaji terlebih dahulu.'}
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

            {/* Pagination footer */}
            {meta && meta.last_page > 1 && (
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
                            onClick={() => apply({ page: meta.current_page - 1 })}
                            style={{
                                height: 34,
                                minWidth: 34,
                                padding: '0 10px',
                                border: `1px solid ${C.border}`,
                                background: '#fff',
                                borderRadius: 8,
                                color:
                                    meta.current_page <= 1 ? C.faint : C.text,
                                cursor:
                                    meta.current_page <= 1
                                        ? 'not-allowed'
                                        : 'pointer',
                                display: 'inline-flex',
                                alignItems: 'center',
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
                            onClick={() => apply({ page: meta.current_page + 1 })}
                            style={{
                                height: 34,
                                minWidth: 34,
                                padding: '0 10px',
                                border: `1px solid ${C.border}`,
                                background: '#fff',
                                borderRadius: 8,
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
            )}
        </div>
    );
}
