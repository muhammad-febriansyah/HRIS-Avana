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
    href: string;
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

