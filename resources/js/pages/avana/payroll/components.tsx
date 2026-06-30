import { AIcon, C } from '@/lib/avana';

/* ---------- shared presentational helpers for the payroll page ---------- */

/** Build up-to-two-letter initials from an employee's full name. */
export function initialsOf(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

/** Amber banner shown when the latest period is locked. */
export function LockedAlert() {
    return (
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
    );
}
