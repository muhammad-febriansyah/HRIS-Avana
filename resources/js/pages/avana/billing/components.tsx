import type { CSSProperties, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { AIcon, C, card, thCell } from '@/lib/avana';
import { INVOICE_STATUS_LABEL, SUBSCRIPTION_STATUS_LABEL } from './types';

export const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

export const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

export const selectStyle: CSSProperties = { ...inputStyle, cursor: 'pointer' };

export const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
    textDecoration: 'none',
};

const INVOICE_COLORS: Record<string, [string, string]> = {
    paid: [C.green, 'rgba(22,163,74,.1)'],
    unpaid: [C.amber, 'rgba(217,119,6,.1)'],
    overdue: [C.red, 'rgba(220,38,38,.1)'],
    cancelled: [C.muted, 'rgba(107,114,128,.12)'],
};

const SUBSCRIPTION_COLORS: Record<string, [string, string]> = {
    active: [C.green, 'rgba(22,163,74,.1)'],
    trial: [C.sky, 'rgba(110,155,230,.15)'],
    past_due: [C.amber, 'rgba(217,119,6,.1)'],
    cancelled: [C.muted, 'rgba(107,114,128,.12)'],
};

function Pill({
    color,
    bg,
    label,
}: {
    color: string;
    bg: string;
    label: string;
}) {
    return (
        <span
            style={{
                display: 'inline-block',
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color,
                background: bg,
            }}
        >
            {label}
        </span>
    );
}

export function InvoiceStatusPill({ status }: { status: string }) {
    const [color, bg] = INVOICE_COLORS[status] ?? INVOICE_COLORS.cancelled;

    return (
        <Pill
            color={color}
            bg={bg}
            label={INVOICE_STATUS_LABEL[status] ?? status}
        />
    );
}

export function SubscriptionStatusPill({ status }: { status: string }) {
    const [color, bg] =
        SUBSCRIPTION_COLORS[status] ?? SUBSCRIPTION_COLORS.cancelled;

    return (
        <Pill
            color={color}
            bg={bg}
            label={SUBSCRIPTION_STATUS_LABEL[status] ?? status}
        />
    );
}

export function KpiCard({
    icon,
    label,
    value,
    accent,
}: {
    icon: string;
    label: string;
    value: ReactNode;
    accent?: string;
}) {
    return (
        <div style={{ ...card, padding: 18, flex: 1, minWidth: 0 }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    marginBottom: 10,
                }}
            >
                <div
                    style={{
                        width: 34,
                        height: 34,
                        borderRadius: 9,
                        background: accent ? `${accent}1a` : C.surface,
                        color: accent ?? C.muted,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <AIcon name={icon} size={16} color={accent ?? C.muted} />
                </div>
                <div style={{ fontSize: 12.5, color: C.muted }}>{label}</div>
            </div>
            <div style={{ fontSize: 22, fontWeight: 700, color: C.navy }}>
                {value}
            </div>
        </div>
    );
}

export function Modal({
    title,
    onClose,
    children,
    width = 560,
}: {
    title: string;
    onClose: () => void;
    children: ReactNode;
    width?: number;
}) {
    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'center',
                padding: '48px 20px',
                overflowY: 'auto',
            }}
        >
            <div
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: width,
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginBottom: 18,
                    }}
                >
                    <div
                        style={{ fontSize: 18, fontWeight: 600, color: C.navy }}
                    >
                        {title}
                    </div>
                    <button
                        onClick={onClose}
                        style={iconBtn}
                        aria-label="Tutup"
                    >
                        <AIcon name="x" size={16} color={C.muted} />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

/** A pill-style tab bar. */
export interface TabItem {
    key: string;
    label: string;
    icon: string;
    count?: number;
}

export function Tabs({
    tabs,
    active,
    onChange,
}: {
    tabs: TabItem[];
    active: string;
    onChange: (key: string) => void;
}) {
    return (
        <div
            style={{
                display: 'flex',
                gap: 6,
                padding: 4,
                background: C.surface,
                borderRadius: 12,
                marginBottom: 18,
                width: 'fit-content',
            }}
        >
            {tabs.map((tab) => {
                const on = active === tab.key;
                return (
                    <button
                        key={tab.key}
                        type="button"
                        onClick={() => onChange(tab.key)}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '9px 16px',
                            fontSize: 13.5,
                            fontWeight: 600,
                            borderRadius: 9,
                            cursor: 'pointer',
                            border: `1px solid ${on ? C.primary : 'transparent'}`,
                            color: on ? C.primary : C.muted,
                            background: on ? '#fff' : 'transparent',
                        }}
                    >
                        <AIcon
                            name={tab.icon}
                            size={15}
                            color={on ? C.primary : C.muted}
                        />
                        {tab.label}
                        {tab.count !== undefined && (
                            <span
                                style={{
                                    fontSize: 11.5,
                                    fontWeight: 700,
                                    padding: '1px 7px',
                                    borderRadius: 999,
                                    color: on ? C.primary : C.muted,
                                    background: on ? '#EEF2FE' : C.line,
                                }}
                            >
                                {tab.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

export interface DataColumn<T> {
    header: string;
    cell: (row: T) => ReactNode;
    align?: 'left' | 'right';
    bold?: boolean;
}

const tdBase: CSSProperties = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
    borderTop: `1px solid ${C.line}`,
};

/**
 * Client-side data table with a search box and pagination, styled to the Avana
 * design system (mirrors the shadcn data-table UX without a table library).
 */
export function DataTable<T>({
    columns,
    rows,
    rowKey,
    searchText,
    searchPlaceholder = 'Cari…',
    pageSize = 8,
    empty = 'Belum ada data.',
}: {
    columns: DataColumn<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    searchText: (row: T) => string;
    searchPlaceholder?: string;
    pageSize?: number;
    empty?: string;
}) {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();
        return term === ''
            ? rows
            : rows.filter((row) =>
                  searchText(row).toLowerCase().includes(term),
              );
    }, [rows, query, searchText]);

    const total = filtered.length;
    const pages = Math.max(1, Math.ceil(total / pageSize));
    const current = Math.min(page, pages);
    const start = (current - 1) * pageSize;
    const pageRows = filtered.slice(start, start + pageSize);

    const search = (value: string) => {
        setQuery(value);
        setPage(1);
    };

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    gap: 12,
                    padding: '14px 16px',
                }}
            >
                <div style={{ position: 'relative', maxWidth: 300, flex: 1 }}>
                    <span
                        style={{
                            position: 'absolute',
                            left: 11,
                            top: '50%',
                            transform: 'translateY(-50%)',
                            display: 'flex',
                        }}
                    >
                        <AIcon name="search" size={15} color={C.faint} />
                    </span>
                    <input
                        value={query}
                        onChange={(e) => search(e.target.value)}
                        placeholder={searchPlaceholder}
                        style={{
                            width: '100%',
                            height: 38,
                            padding: '0 12px 0 34px',
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            fontSize: 13.5,
                            color: C.text,
                            outline: 'none',
                            background: '#fff',
                        }}
                    />
                </div>
                <span style={{ fontSize: 12.5, color: C.muted }}>
                    {total} data
                </span>
            </div>

            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: C.surface }}>
                            {columns.map((col, i) => (
                                <th
                                    key={i}
                                    style={{
                                        ...thCell,
                                        textAlign: col.align ?? 'left',
                                    }}
                                >
                                    {col.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {pageRows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    style={{
                                        ...tdBase,
                                        textAlign: 'center',
                                        color: C.faint,
                                        padding: '32px 0',
                                    }}
                                >
                                    {query.trim() === ''
                                        ? empty
                                        : 'Tidak ada hasil yang cocok.'}
                                </td>
                            </tr>
                        ) : (
                            pageRows.map((row) => (
                                <tr key={rowKey(row)}>
                                    {columns.map((col, i) => (
                                        <td
                                            key={i}
                                            style={{
                                                ...tdBase,
                                                textAlign: col.align ?? 'left',
                                                fontWeight: col.bold
                                                    ? 600
                                                    : undefined,
                                                color: col.bold
                                                    ? C.navy
                                                    : C.text,
                                            }}
                                        >
                                            {col.cell(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '12px 16px',
                    borderTop: `1px solid ${C.line}`,
                }}
            >
                <span style={{ fontSize: 12.5, color: C.muted }}>
                    Menampilkan {total === 0 ? 0 : start + 1}–
                    {Math.min(start + pageSize, total)} dari {total}
                </span>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <button
                        type="button"
                        onClick={() => setPage(current - 1)}
                        disabled={current <= 1}
                        style={pagerBtn(current <= 1)}
                        aria-label="Sebelumnya"
                    >
                        <AIcon name="chevron-left" size={16} color={C.text} />
                    </button>
                    <span style={{ fontSize: 12.5, color: C.muted }}>
                        {current} / {pages}
                    </span>
                    <button
                        type="button"
                        onClick={() => setPage(current + 1)}
                        disabled={current >= pages}
                        style={pagerBtn(current >= pages)}
                        aria-label="Berikutnya"
                    >
                        <AIcon name="chevron-right" size={16} color={C.text} />
                    </button>
                </div>
            </div>
        </div>
    );
}

function pagerBtn(disabled: boolean): CSSProperties {
    return {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: 30,
        height: 30,
        borderRadius: 8,
        border: `1px solid ${C.border}`,
        background: '#fff',
        cursor: disabled ? 'default' : 'pointer',
        opacity: disabled ? 0.45 : 1,
    };
}
