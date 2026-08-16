import { Head, Link, router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import IncentiveController from '@/actions/App/Http/Controllers/Avana/IncentiveController';
import { ActionBtn, C, card } from '@/lib/avana';
import { rupiah, STATUS_LABELS } from './types';
import type { IncentiveCalculationRow } from './types';

interface Props {
    rows: {
        data: IncentiveCalculationRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
    schemes: { id: number; code: string; name: string }[];
    filters: { employee_id?: string; scheme_id?: string; status?: string };
}

const th: CSSProperties = {
    textAlign: 'left',
    fontSize: 12,
    fontWeight: 600,
    color: C.muted,
    padding: '11px 14px',
    borderBottom: `1px solid ${C.line}`,
    whiteSpace: 'nowrap',
};

const td: CSSProperties = {
    fontSize: 13.5,
    color: C.text,
    padding: '11px 14px',
    borderBottom: `1px solid ${C.line}`,
};

const input: CSSProperties = {
    height: 36,
    padding: '0 12px',
    borderRadius: 8,
    border: `1px solid ${C.line}`,
    fontSize: 13,
    background: '#fff',
    color: C.text,
};

/** Every incentive ever computed, with what happened to it. */
export default function InsentifHistory({ rows, schemes, filters }: Props) {
    const filter = (key: string, value: string) =>
        router.get(
            IncentiveController.history().url,
            { ...filters, [key]: value },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Riwayat Insentif" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginBottom: 16,
                    }}
                >
                    <div>
                        <div style={{ fontSize: 12.5, color: C.faint }}>
                            Payroll · Insentif · Riwayat
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: '4px 0 0',
                            }}
                        >
                            Riwayat Insentif
                        </h1>
                    </div>
                    <Link
                        href={IncentiveController.index().url}
                        style={{ textDecoration: 'none' }}
                    >
                        <ActionBtn
                            icon="arrow-left"
                            label="Kembali"
                            variant="neutral"
                        />
                    </Link>
                </div>

                <div style={{ display: 'flex', gap: 10, marginBottom: 14 }}>
                    <select
                        style={input}
                        value={filters.scheme_id ?? ''}
                        onChange={(event) =>
                            filter('scheme_id', event.target.value)
                        }
                    >
                        <option value="">Semua skema</option>
                        {schemes.map((scheme) => (
                            <option key={scheme.id} value={scheme.id}>
                                {scheme.code} · {scheme.name}
                            </option>
                        ))}
                    </select>
                    <select
                        style={input}
                        value={filters.status ?? ''}
                        onChange={(event) =>
                            filter('status', event.target.value)
                        }
                    >
                        <option value="">Semua status</option>
                        {Object.entries(STATUS_LABELS).map(([value, text]) => (
                            <option key={value} value={value}>
                                {text}
                            </option>
                        ))}
                    </select>
                </div>

                <div style={{ ...card, overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: '#FAFBFD' }}>
                                <th style={th}>Periode</th>
                                <th style={th}>Karyawan</th>
                                <th style={th}>Skema</th>
                                <th style={th}>Terukur</th>
                                <th style={th}>Nominal</th>
                                <th style={th}>Status</th>
                                <th style={th}>Disetujui</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length === 0 && (
                                <tr>
                                    <td
                                        style={{ ...td, color: C.faint }}
                                        colSpan={7}
                                    >
                                        Belum ada riwayat insentif.
                                    </td>
                                </tr>
                            )}
                            {rows.data.map((row) => (
                                <tr key={row.id}>
                                    <td style={td}>{row.period ?? '—'}</td>
                                    <td style={td}>
                                        {row.employee}
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: C.faint,
                                            }}
                                        >
                                            {row.employee_number}
                                        </div>
                                    </td>
                                    <td style={td}>{row.scheme}</td>
                                    <td style={td}>{row.measured_value}</td>
                                    <td style={td}>{rupiah(row.amount)}</td>
                                    <td style={td}>
                                        {STATUS_LABELS[row.status] ?? row.status}
                                    </td>
                                    <td style={td}>{row.approver ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div style={{ marginTop: 12, fontSize: 12.5, color: C.faint }}>
                    Halaman {rows.meta.current_page} dari {rows.meta.last_page} ·{' '}
                    {rows.meta.total} baris
                </div>
            </div>
        </>
    );
}
