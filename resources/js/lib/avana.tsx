import { icons } from 'lucide-react';
import type { CSSProperties } from 'react';

/* ============================================================
 * AvanaHR design system: tokens, the AIcon helper, rupiah/status
 * helpers, shared button/card styles, and the sidebar nav fallback.
 * ============================================================ */

export const C = {
    primary: '#2F54C9',
    primaryHover: '#2546ad',
    navy: '#0E1A3A',
    text: '#1A2333',
    muted: '#6B7280',
    faint: '#9CA3AF',
    border: '#E5E9F2',
    line: '#F1F3F9',
    surface: '#F4F6FB',
    green: '#16A34A',
    amber: '#D97706',
    red: '#DC2626',
    sky: '#6E9BE6',
} as const;

function pascal(name: string): string {
    return name
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join('');
}

/** Aliases for icon names that were renamed/removed in lucide-react. */
const ICON_ALIASES: Record<string, string> = {
    palmtree: 'TreePalm',
    'upload-cloud': 'CloudUpload',
};

interface AIconProps {
    name: string;
    size?: number;
    color?: string;
    style?: CSSProperties;
    strokeWidth?: number;
}

/** Renders a Lucide icon by its kebab-case template name. */
export function AIcon({ name, size = 18, color, style, strokeWidth = 2 }: AIconProps) {
    const key = ICON_ALIASES[name] ?? pascal(name);
    const Cmp = (icons as Record<string, React.ComponentType<{ size?: number; color?: string; style?: CSSProperties; strokeWidth?: number }>>)[key];

    if (!Cmp) {
        return <span style={{ display: 'inline-block', width: size, height: size, ...style }} />;
    }

    return <Cmp size={size} color={color} strokeWidth={strokeWidth} style={{ flex: 'none', ...style }} />;
}

/** Format a number as Indonesian Rupiah. */
export function rp(n: number): string {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}

export interface Badge {
    label: string;
    color: string;
    bg: string;
}

export function statusBadge(st: string): Badge {
    const m: Record<string, [string, string]> = {
        Tetap: ['#16A34A', 'rgba(22,163,74,.1)'],
        Kontrak: ['#2F54C9', 'rgba(47,84,201,.1)'],
        Probation: ['#D97706', 'rgba(217,119,6,.1)'],
        Resign: ['#6B7280', 'rgba(107,114,128,.12)'],
        Draft: ['#6B7280', 'rgba(107,114,128,.12)'],
        Diproses: ['#2F54C9', 'rgba(47,84,201,.1)'],
        Menunggu: ['#D97706', 'rgba(217,119,6,.1)'],
        Disetujui: ['#16A34A', 'rgba(22,163,74,.1)'],
        Ditolak: ['#DC2626', 'rgba(220,38,38,.1)'],
        Final: ['#16A34A', 'rgba(22,163,74,.1)'],
        Terkunci: ['#0E1A3A', 'rgba(14,26,58,.08)'],
        Dibayar: ['#16A34A', 'rgba(22,163,74,.1)'],
        Terbit: ['#16A34A', 'rgba(22,163,74,.1)'],
        Dihitung: ['#2F54C9', 'rgba(47,84,201,.1)'],
        Hadir: ['#16A34A', 'rgba(22,163,74,.1)'],
        Terlambat: ['#D97706', 'rgba(217,119,6,.1)'],
        Alpa: ['#DC2626', 'rgba(220,38,38,.1)'],
        Cuti: ['#2F54C9', 'rgba(47,84,201,.1)'],
        'Belum Lengkap': ['#D97706', 'rgba(217,119,6,.1)'],
        'Perlu Koreksi': ['#D97706', 'rgba(217,119,6,.1)'],
    };
    const c = m[st] ?? m.Draft;

    return { label: st, color: c[0], bg: c[1] };
}

/* ---------- Rupiah-formatted currency input ---------- */

/** Strip everything but digits from a money string. */
export function digitsOnly(value: string | number | null | undefined): string {
    return value === null || value === undefined ? '' : String(value).replace(/[^\d]/g, '');
}

interface RupiahInputProps {
    value: string | number;
    onChange: (rawDigits: string) => void;
    style?: CSSProperties;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
}

/**
 * Text input that shows a thousand-separated Rupiah value (e.g. "1.500.000")
 * with an "Rp" prefix while reporting the raw digit string back to the form.
 */
export function RupiahInput({ value, onChange, style, placeholder, disabled, invalid }: RupiahInputProps) {
    const raw = digitsOnly(value);
    const display = raw === '' ? '' : Number(raw).toLocaleString('id-ID');

    return (
        <div style={{ position: 'relative' }}>
            <span
                style={{
                    position: 'absolute',
                    left: 13,
                    top: '50%',
                    transform: 'translateY(-50%)',
                    fontSize: 13.5,
                    color: C.faint,
                    pointerEvents: 'none',
                }}
            >
                Rp
            </span>
            <input
                type="text"
                inputMode="numeric"
                value={display}
                placeholder={placeholder ?? '0'}
                disabled={disabled}
                onChange={(event) => onChange(digitsOnly(event.target.value))}
                style={{
                    width: '100%',
                    height: 42,
                    padding: '0 13px 0 36px',
                    border: `1px solid ${invalid ? C.red : C.border}`,
                    borderRadius: 8,
                    fontSize: 13.5,
                    color: C.text,
                    background: disabled ? C.surface : '#fff',
                    outline: 'none',
                    ...style,
                }}
            />
        </div>
    );
}

/* ---------- color-coded action button (icon + label) ---------- */

export type ActionVariant = 'neutral' | 'primary' | 'success' | 'warning' | 'danger';

const ACTION_VARIANTS: Record<ActionVariant, { color: string; border: string; bg: string }> = {
    neutral: { color: C.text, border: C.border, bg: '#fff' },
    primary: { color: C.primary, border: 'rgba(47,84,201,.35)', bg: 'rgba(47,84,201,.07)' },
    success: { color: C.green, border: 'rgba(22,163,74,.35)', bg: 'rgba(22,163,74,.07)' },
    warning: { color: C.amber, border: 'rgba(217,119,6,.35)', bg: 'rgba(217,119,6,.07)' },
    danger: { color: C.red, border: 'rgba(220,38,38,.35)', bg: 'rgba(220,38,38,.07)' },
};

interface ActionBtnProps {
    icon: string;
    label: string;
    variant?: ActionVariant;
    onClick?: () => void;
    type?: 'button' | 'submit';
    disabled?: boolean;
    title?: string;
    href?: string;
    download?: boolean;
}

/** Compact table/row action that always pairs an icon with a label and is
 * color-coded by intent (neutral/primary/success/warning/danger). Renders an
 * anchor when `href` is given (for downloads/links), otherwise a button. */
export function ActionBtn({ icon, label, variant = 'neutral', onClick, type = 'button', disabled, title, href, download }: ActionBtnProps) {
    const v = ACTION_VARIANTS[variant];

    const style: CSSProperties = {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 6,
        height: 32,
        padding: '0 11px',
        border: `1px solid ${v.border}`,
        background: v.bg,
        color: v.color,
        borderRadius: 7,
        fontSize: 12.5,
        fontWeight: 600,
        cursor: disabled ? 'not-allowed' : 'pointer',
        opacity: disabled ? 0.5 : 1,
        transition: '.15s',
        whiteSpace: 'nowrap',
        textDecoration: 'none',
    };

    if (href !== undefined) {
        return (
            <a href={href} title={title ?? label} download={download} target={download ? undefined : '_blank'} rel="noreferrer" style={style}>
                <AIcon name={icon} size={14} color={v.color} />
                {label}
            </a>
        );
    }

    return (
        <button type={type} onClick={onClick} disabled={disabled} title={title ?? label} style={style}>
            <AIcon name={icon} size={14} color={v.color} />
            {label}
        </button>
    );
}

/* ---------- shared button / card styles ---------- */

export const btnP: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 8,
    height: 40,
    padding: '0 16px',
    background: C.primary,
    color: '#fff',
    border: 'none',
    borderRadius: 8,
    fontSize: 13.5,
    fontWeight: 600,
    cursor: 'pointer',
    transition: 'background .15s,box-shadow .15s',
};

export const btnOut: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 8,
    height: 40,
    padding: '0 15px',
    background: '#fff',
    color: C.text,
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    fontWeight: 500,
    cursor: 'pointer',
    transition: '.15s',
};

export const card: CSSProperties = {
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 12,
    boxShadow: '0 1px 2px rgba(15,23,42,.04)',
};

export const thCell: CSSProperties = {
    padding: '12px 16px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    letterSpacing: '.04em',
    textTransform: 'uppercase',
};

/* ---------- navigation ---------- */

export interface NavItem {
    id: string;
    label: string;
    icon: string;
    href?: string;
    children?: NavItem[];
}
export interface NavGroup {
    title: string | null;
    items: NavItem[];
}

export const NAV: NavGroup[] = [
    { title: null, items: [{ id: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', href: '/dashboard' }] },
    {
        title: 'MANAJEMEN',
        items: [
            { id: 'karyawan', label: 'Karyawan', icon: 'users', href: '/avana/employees' },
            { id: 'absensi', label: 'Absensi', icon: 'fingerprint', href: '/avana/absensi' },
            { id: 'cuti', label: 'Cuti & Lembur', icon: 'palmtree', href: '/avana/cuti' },
            { id: 'payroll', label: 'Payroll', icon: 'wallet', href: '/avana/payroll' },
        ],
    },
    {
        title: 'SISTEM',
        items: [
            { id: 'perusahaan', label: 'Perusahaan', icon: 'building-2', href: '/avana/perusahaan' },
            { id: 'laporan', label: 'Laporan', icon: 'chart-column', href: '/avana/laporan' },
            { id: 'hak-akses', label: 'Hak Akses', icon: 'shield-check', href: '/avana/hak-akses' },
            { id: 'fitur', label: 'Menu & Fitur', icon: 'toggle-right', href: '/avana/fitur' },
        ],
    },
];

