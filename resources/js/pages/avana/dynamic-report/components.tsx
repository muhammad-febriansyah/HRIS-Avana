import type { CSSProperties } from 'react';
import { C, card, thCell } from '@/lib/avana';

/* ---------- shared presentational helpers for the dynamic report module ---------- */

/** Text/select input style shared by the builder form. */
export const inputStyle: CSSProperties = {
    width: '100%',
    height: 40,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

/** Small field label style. */
export const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 12.5,
    fontWeight: 600,
    color: C.muted,
    marginBottom: 6,
};

/** Empty-state placeholder for tables/sections. */
export function EmptyState({ label }: { label: string }) {
    return (
        <div
            style={{
                padding: '32px 0',
                textAlign: 'center',
                fontSize: 13,
                color: C.faint,
            }}
        >
            {label}
        </div>
    );
}

/**
 * Generic data table for a built report dataset: a header row plus arbitrary
 * scalar cell rows aligned to the headers.
 */
export function DataTable({
    headers,
    rows,
}: {
    headers: string[];
    rows: Array<Array<string | number | null>>;
}) {
    if (rows.length === 0) {
        return <EmptyState label="Tidak ada data untuk ditampilkan" />;
    }

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        fontSize: 13.5,
                    }}
                >
                    <thead>
                        <tr style={{ borderBottom: `1px solid ${C.line}` }}>
                            {headers.map((header) => (
                                <th key={header} style={thCell}>
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, rowIndex) => (
                            <tr
                                key={rowIndex}
                                style={{
                                    borderBottom: `1px solid ${C.line}`,
                                }}
                            >
                                {row.map((cell, cellIndex) => (
                                    <td
                                        key={cellIndex}
                                        style={{
                                            padding: '12px 16px',
                                            color: C.text,
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {cell ?? '—'}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
