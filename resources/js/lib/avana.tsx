import { icons } from 'lucide-react';
import type { CSSProperties } from 'react';

/* ============================================================
 * AvanaHR design tokens, helpers & shared (dummy) data.
 * Ported from the AvanaHR static template.
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

interface AIconProps {
    name: string;
    size?: number;
    color?: string;
    style?: CSSProperties;
    strokeWidth?: number;
}

/** Renders a Lucide icon by its kebab-case template name. */
export function AIcon({ name, size = 18, color, style, strokeWidth = 2 }: AIconProps) {
    const Cmp = (icons as Record<string, React.ComponentType<{ size?: number; color?: string; style?: CSSProperties; strokeWidth?: number }>>)[pascal(name)];

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
    { title: 'PERSONAL', items: [{ id: 'ess', label: 'Self-Service', icon: 'circle-user-round', href: '/avana/ess' }] },
    {
        title: 'SISTEM',
        items: [
            { id: 'laporan', label: 'Laporan', icon: 'chart-column', href: '/avana/laporan' },
            { id: 'pengaturan', label: 'Hak Akses', icon: 'shield-check', href: '/avana/hak-akses' },
        ],
    },
];

/* ============================================================
 * Dummy data
 * ============================================================ */

export const kpis = [
    { label: 'Total Karyawan', value: '1.248', icon: 'users', iconBg: 'rgba(47,84,201,.1)', iconColor: '#2F54C9', delta: '+24', deltaIcon: 'trending-up', deltaColor: '#16A34A' },
    { label: 'Hadir Hari Ini', value: '1.156', icon: 'user-check', iconBg: 'rgba(22,163,74,.1)', iconColor: '#16A34A', delta: '92,6%', deltaIcon: 'trending-up', deltaColor: '#16A34A' },
    { label: 'Pending Approval', value: '18', icon: 'clock', iconBg: 'rgba(217,119,6,.1)', iconColor: '#D97706', delta: '4 baru', deltaIcon: 'arrow-up-right', deltaColor: '#D97706' },
    { label: 'Payroll Juni', value: 'Rp 4,82 M', icon: 'wallet', iconBg: 'rgba(110,155,230,.16)', iconColor: '#2F54C9', delta: 'Draft', deltaIcon: 'circle-dot', deltaColor: '#6B7280' },
];

export const activities = [
    { icon: 'user-plus', bg: 'rgba(47,84,201,.1)', color: '#2F54C9', text: 'Bagus Pratama ditambahkan sebagai Software Engineer', time: '5 menit lalu' },
    { icon: 'check-check', bg: 'rgba(22,163,74,.1)', color: '#16A34A', text: 'Cuti tahunan Dewi Lestari disetujui', time: '48 menit lalu' },
    { icon: 'wallet', bg: 'rgba(110,155,230,.16)', color: '#2F54C9', text: 'Payroll periode Mei 2026 telah dikunci', time: '2 jam lalu' },
    { icon: 'file-text', bg: 'rgba(217,119,6,.1)', color: '#D97706', text: 'Andi Wijaya mengunggah dokumen kontrak baru', time: '4 jam lalu' },
    { icon: 'fingerprint', bg: 'rgba(220,38,38,.08)', color: '#DC2626', text: '12 karyawan tercatat terlambat hari ini', time: 'Hari ini, 09:14' },
];

export const approvals = [
    { ini: 'SN', avBg: '#2F54C9', name: 'Siti Nurhaliza', type: 'Cuti Tahunan · 3 hari' },
    { ini: 'RM', avBg: '#6E9BE6', name: 'Rizki Maulana', type: 'Lembur · 4 jam' },
    { ini: 'FN', avBg: '#0E1A3A', name: 'Fajar Nugroho', type: 'Reimbursement · Rp 850.000' },
    { ini: 'IP', avBg: '#D97706', name: 'Intan Permata', type: 'Koreksi Absen' },
];

export interface Employee {
    no: number;
    nama: string;
    email: string;
    dept: string;
    jab: string;
    cabang: string;
    status: string;
    masuk: string;
    ini: string;
    av: string;
}

export const employees: Employee[] = [
    { no: 1, nama: 'Putri Anjani', email: 'putri.anjani@nusantara.co.id', dept: 'Human Resources', jab: 'HR Manager', cabang: 'Jakarta Pusat', status: 'Tetap', masuk: '12 Jan 2021', ini: 'PA', av: '#2F54C9' },
    { no: 2, nama: 'Bagus Pratama', email: 'bagus.p@nusantara.co.id', dept: 'Engineering', jab: 'Software Engineer', cabang: 'Bandung', status: 'Kontrak', masuk: '03 Mar 2024', ini: 'BP', av: '#6E9BE6' },
    { no: 3, nama: 'Siti Nurhaliza', email: 'siti.n@nusantara.co.id', dept: 'Finance', jab: 'Finance Analyst', cabang: 'Jakarta Pusat', status: 'Tetap', masuk: '19 Jul 2022', ini: 'SN', av: '#0E1A3A' },
    { no: 4, nama: 'Rizki Maulana', email: 'rizki.m@nusantara.co.id', dept: 'Sales', jab: 'Sales Executive', cabang: 'Surabaya', status: 'Probation', masuk: '02 Jun 2026', ini: 'RM', av: '#D97706' },
    { no: 5, nama: 'Dewi Lestari', email: 'dewi.l@nusantara.co.id', dept: 'Marketing', jab: 'Content Lead', cabang: 'Jakarta Pusat', status: 'Tetap', masuk: '28 Sep 2021', ini: 'DL', av: '#16A34A' },
    { no: 6, nama: 'Andi Wijaya', email: 'andi.w@nusantara.co.id', dept: 'Operations', jab: 'Ops Supervisor', cabang: 'Bandung', status: 'Tetap', masuk: '14 Feb 2020', ini: 'AW', av: '#2F54C9' },
    { no: 7, nama: 'Maya Saraswati', email: 'maya.s@nusantara.co.id', dept: 'Engineering', jab: 'QA Engineer', cabang: 'Bandung', status: 'Kontrak', masuk: '11 Nov 2023', ini: 'MS', av: '#6E9BE6' },
    { no: 8, nama: 'Fajar Nugroho', email: 'fajar.n@nusantara.co.id', dept: 'Finance', jab: 'Accountant', cabang: 'Surabaya', status: 'Tetap', masuk: '07 Agu 2022', ini: 'FN', av: '#0E1A3A' },
    { no: 9, nama: 'Intan Permata', email: 'intan.p@nusantara.co.id', dept: 'Human Resources', jab: 'Recruiter', cabang: 'Jakarta Pusat', status: 'Resign', masuk: '22 Apr 2023', ini: 'IP', av: '#9CA3AF' },
    { no: 10, nama: 'Yoga Saputra', email: 'yoga.s@nusantara.co.id', dept: 'Sales', jab: 'Account Manager', cabang: 'Surabaya', status: 'Tetap', masuk: '30 Jan 2021', ini: 'YS', av: '#D97706' },
];

export const attRows = [
    { ini: 'PA', av: '#2F54C9', nama: 'Putri Anjani', shift: 'Pagi (08:00–17:00)', masuk: '07:54', keluar: '17:08', telat: '—', status: 'Hadir' },
    { ini: 'BP', av: '#6E9BE6', nama: 'Bagus Pratama', shift: 'Pagi (08:00–17:00)', masuk: '08:21', keluar: '17:30', telat: '21 mnt', status: 'Terlambat' },
    { ini: 'SN', av: '#0E1A3A', nama: 'Siti Nurhaliza', shift: 'Pagi (08:00–17:00)', masuk: '07:48', keluar: '17:02', telat: '—', status: 'Hadir' },
    { ini: 'DL', av: '#16A34A', nama: 'Dewi Lestari', shift: 'Pagi (08:00–17:00)', masuk: '—', keluar: '—', telat: '—', status: 'Cuti' },
    { ini: 'AW', av: '#2F54C9', nama: 'Andi Wijaya', shift: 'Siang (13:00–21:00)', masuk: '12:55', keluar: '—', telat: '—', status: 'Hadir' },
    { ini: 'MS', av: '#6E9BE6', nama: 'Maya Saraswati', shift: 'Pagi (08:00–17:00)', masuk: '08:05', keluar: '17:10', telat: '5 mnt', status: 'Terlambat' },
];

export function attStatusColor(status: string): [string, string] {
    const m: Record<string, [string, string]> = {
        Hadir: ['#16A34A', 'rgba(22,163,74,.1)'],
        Terlambat: ['#D97706', 'rgba(217,119,6,.1)'],
        Cuti: ['#2F54C9', 'rgba(47,84,201,.1)'],
        Alpa: ['#DC2626', 'rgba(220,38,38,.1)'],
    };

    return m[status] ?? m.Hadir;
}

export const leaveBalances = [
    { jenis: 'Cuti Tahunan', sisa: 8, total: 12, color: '#2F54C9', icon: 'palmtree' },
    { jenis: 'Cuti Sakit', sisa: 10, total: 12, color: '#16A34A', icon: 'thermometer' },
    { jenis: 'Cuti Penting', sisa: 2, total: 2, color: '#D97706', icon: 'circle-alert' },
].map((b) => ({ ...b, pct: Math.round((b.sisa / b.total) * 100) + '%' }));

export const leaveList = [
    { ini: 'PA', av: '#2F54C9', nama: 'Putri Anjani', jenis: 'Cuti Tahunan', mulai: '01 Jul 2026', akhir: '03 Jul 2026', durasi: '3 hari', status: 'Menunggu' },
    { ini: 'SN', av: '#0E1A3A', nama: 'Siti Nurhaliza', jenis: 'Cuti Sakit', mulai: '25 Jun 2026', akhir: '26 Jun 2026', durasi: '2 hari', status: 'Disetujui' },
    { ini: 'BP', av: '#6E9BE6', nama: 'Bagus Pratama', jenis: 'Cuti Tahunan', mulai: '10 Jul 2026', akhir: '12 Jul 2026', durasi: '3 hari', status: 'Menunggu' },
    { ini: 'YS', av: '#D97706', nama: 'Yoga Saputra', jenis: 'Cuti Penting', mulai: '18 Jun 2026', akhir: '18 Jun 2026', durasi: '1 hari', status: 'Ditolak' },
].map((l) => ({ ...l, badge: statusBadge(l.status) }));

export const payPeriods = [
    { periode: 'Juni 2026', bayar: '25 Jun 2026', karyawan: 1248, gross: 5120000000, net: 4820000000, status: 'Draft' },
    { periode: 'Mei 2026', bayar: '25 Mei 2026', karyawan: 1241, gross: 5080000000, net: 4790000000, status: 'Terkunci' },
    { periode: 'April 2026', bayar: '25 Apr 2026', karyawan: 1235, gross: 5040000000, net: 4755000000, status: 'Terkunci' },
    { periode: 'Maret 2026', bayar: '25 Mar 2026', karyawan: 1228, gross: 4990000000, net: 4710000000, status: 'Terkunci' },
].map((p) => ({ ...p, badge: statusBadge(p.status), grossR: rp(p.gross), netR: rp(p.net) }));

export const earnings = [
    { k: 'Gaji Pokok', v: 8500000 },
    { k: 'Tunjangan Jabatan', v: 2000000 },
    { k: 'Tunjangan Transport', v: 750000 },
    { k: 'Tunjangan Makan', v: 600000 },
    { k: 'Lembur (6 jam)', v: 450000 },
];
export const deductions = [
    { k: 'BPJS Kesehatan (1%)', v: 85000 },
    { k: 'BPJS TK (2%)', v: 170000 },
    { k: 'PPh 21', v: 412000 },
    { k: 'Potongan Koperasi', v: 150000 },
];
export const grossTot = earnings.reduce((a, b) => a + b.v, 0);
export const dedTot = deductions.reduce((a, b) => a + b.v, 0);
export const netTot = grossTot - dedTot;
export const slipEarn = earnings.map((e) => ({ k: e.k, v: rp(e.v) }));
export const slipDed = deductions.map((e) => ({ k: e.k, v: rp(e.v) }));

export const roles = [
    { name: 'Super Admin', desc: 'Akses penuh seluruh modul & tenant', users: 2, color: '#2F54C9' },
    { name: 'HR Admin', desc: 'Kelola karyawan, absensi, cuti, payroll', users: 6, color: '#6E9BE6' },
    { name: 'Manager', desc: 'Approval tim & lihat laporan unit', users: 24, color: '#16A34A' },
    { name: 'Karyawan', desc: 'Self-service pribadi (ESS)', users: 1216, color: '#6B7280' },
];

export const permHeaders = roles.map((r) => r.name);
export const permRows = [
    { modul: 'Dashboard', perms: [true, true, true, true] },
    { modul: 'Karyawan', perms: [true, true, true, false] },
    { modul: 'Absensi', perms: [true, true, true, true] },
    { modul: 'Cuti & Lembur', perms: [true, true, true, true] },
    { modul: 'Payroll', perms: [true, true, false, false] },
    { modul: 'Laporan', perms: [true, true, true, false] },
    { modul: 'Pengaturan', perms: [true, false, false, false] },
];
