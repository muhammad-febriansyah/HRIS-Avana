import type { CSSProperties } from 'react';
import { AIcon, C } from '@/lib/avana';
import type { ActivityRow, AuditRow, PaginationMeta } from './types';

/* ---------- shared presentational helpers for the audit page ---------- */

export const filterSelectStyle: CSSProperties = {
    height: 38,
    padding: '0 12px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.muted,
    background: '#fff',
    cursor: 'pointer',
    outline: 'none',
};

/** Visual treatment for each audit action. */
export function actionBadge(action: AuditRow['action']): {
    label: string;
    color: string;
    bg: string;
} {
    switch (action) {
        case 'created':
            return {
                label: 'Dibuat',
                color: C.green,
                bg: 'rgba(22,163,74,.1)',
            };
        case 'deleted':
            return { label: 'Dihapus', color: C.red, bg: 'rgba(220,38,38,.1)' };
        default:
            return {
                label: 'Diubah',
                color: C.primary,
                bg: 'rgba(47,84,201,.1)',
            };
    }
}

/** Visual treatment for each activity event. */
export function activityBadge(event: ActivityRow['event']): {
    label: string;
    color: string;
    bg: string;
    icon: string;
} {
    switch (event) {
        case 'login':
            return { label: 'Masuk', color: C.green, bg: 'rgba(22,163,74,.1)', icon: 'log-in' };
        case 'logout':
            return { label: 'Keluar', color: C.muted, bg: 'rgba(107,114,128,.12)', icon: 'log-out' };
        case 'login_failed':
            return { label: 'Login Gagal', color: C.red, bg: 'rgba(220,38,38,.1)', icon: 'shield-alert' };
        case 'page_view':
            return { label: 'Buka Halaman', color: C.sky, bg: 'rgba(110,155,230,.14)', icon: 'eye' };
        case 'data_created':
            return { label: 'Buat Data', color: C.green, bg: 'rgba(22,163,74,.1)', icon: 'circle-plus' };
        case 'data_updated':
            return { label: 'Ubah Data', color: C.primary, bg: 'rgba(47,84,201,.1)', icon: 'pencil' };
        case 'data_deleted':
            return { label: 'Hapus Data', color: C.red, bg: 'rgba(220,38,38,.1)', icon: 'trash-2' };
        default:
            return { label: event, color: C.violet, bg: 'rgba(124,58,237,.1)', icon: 'activity' };
    }
}

/** Build a windowed list of page numbers with ellipsis markers. */
export function pageItems(current: number, last: number): (number | 'gap')[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const items: (number | 'gap')[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) {
        items.push('gap');
    }

    for (let page = start; page <= end; page++) {
        items.push(page);
    }

    if (end < last - 1) {
        items.push('gap');
    }

    items.push(last);

    return items;
}

interface PaginationProps {
    meta: PaginationMeta;
    onGoToPage: (page: number) => void;
}

/** Footer with record range summary and windowed page-number controls. */
export function Pagination({ meta, onGoToPage }: PaginationProps) {
    return (
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
                </span>{' '}
                catatan
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
                    onClick={() => onGoToPage(meta.current_page - 1)}
                    style={{
                        height: 34,
                        minWidth: 34,
                        padding: '0 10px',
                        border: `1px solid ${C.border}`,
                        background: '#fff',
                        borderRadius: 8,
                        fontSize: 13,
                        color: meta.current_page <= 1 ? C.faint : C.text,
                        cursor:
                            meta.current_page <= 1 ? 'not-allowed' : 'pointer',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 5,
                    }}
                >
                    <AIcon name="chevron-left" size={15} />
                </button>
                {pageItems(meta.current_page, meta.last_page).map(
                    (item, index) =>
                        item === 'gap' ? (
                            <span
                                key={`gap-${index}`}
                                style={{
                                    color: C.faint,
                                    padding: '0 4px',
                                }}
                            >
                                …
                            </span>
                        ) : (
                            <button
                                key={item}
                                onClick={() => onGoToPage(item)}
                                style={{
                                    height: 34,
                                    minWidth: 34,
                                    border:
                                        item === meta.current_page
                                            ? 'none'
                                            : `1px solid ${C.border}`,
                                    background:
                                        item === meta.current_page
                                            ? C.primary
                                            : '#fff',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color:
                                        item === meta.current_page
                                            ? '#fff'
                                            : C.text,
                                    fontWeight:
                                        item === meta.current_page ? 600 : 400,
                                    cursor: 'pointer',
                                }}
                            >
                                {item}
                            </button>
                        ),
                )}
                <button
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => onGoToPage(meta.current_page + 1)}
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
    );
}
