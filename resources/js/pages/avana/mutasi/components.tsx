import type { CSSProperties } from 'react';
import { AIcon, C } from '@/lib/avana';

/* ---------- shared field styles (mirror the original mutasi modal) ---------- */

export const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
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

export const selectStyle: CSSProperties = {
    ...inputStyle,
    color: C.muted,
    cursor: 'pointer',
};

export const textareaStyle: CSSProperties = {
    ...inputStyle,
    height: 'auto',
    minHeight: 78,
    padding: '11px 13px',
    resize: 'vertical',
    fontFamily: 'inherit',
};

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

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

/** Apply the red error border to a base input/select style when invalid. */
export function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

/** Inline error message rendered under a field. */
export function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={errorTextStyle}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** Visual treatment per movement type, keyed by stored value. */
const movementMeta: Record<string, { label: string; color: string; bg: string }> = {
    promotion: { label: 'Promosi', color: C.green, bg: 'rgba(22,163,74,.1)' },
    mutation: { label: 'Mutasi', color: C.primary, bg: 'rgba(47,84,201,.1)' },
    transfer: { label: 'Transfer', color: C.primary, bg: 'rgba(47,84,201,.1)' },
    demotion: { label: 'Demosi', color: C.amber, bg: 'rgba(217,119,6,.1)' },
    resign: { label: 'Resign', color: C.red, bg: 'rgba(220,38,38,.1)' },
    terminate: { label: 'Terminasi', color: C.red, bg: 'rgba(220,38,38,.1)' },
};

/** Badge metadata for a movement type, with a neutral fallback. */
export function movementBadge(type: string) {
    return (
        movementMeta[type] ?? {
            label: type,
            color: C.muted,
            bg: 'rgba(107,114,128,.12)',
        }
    );
}

/** Pill style for a from/to value chip. */
export function chipStyle(color: string, background: string): CSSProperties {
    return {
        display: 'inline-block',
        padding: '2px 9px',
        borderRadius: 100,
        fontSize: 11.5,
        fontWeight: 500,
        color,
        background,
    };
}

/** Format an ISO date string to a short Indonesian date. */
export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
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
