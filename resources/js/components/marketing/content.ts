import {
    BarChart3,
    Building2,
    CalendarClock,
    ClipboardCheck,
    Coins,
    Fingerprint,
    GraduationCap,
    Layers,
    LineChart,
    Receipt,
    ScrollText,
    Sparkles,
    Target,
    TrendingUp,
    UserCheck,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import type { ComponentType } from 'react';

export type Icon = ComponentType<{ className?: string }>;

export const NAV_ITEMS: { name: string; link: string; badge?: string }[] = [
    { name: 'Produk & Modul', link: '#platform' },
    { name: 'Solusi Terpadu', link: '#solusi' },
    { name: 'AI & Analytics', link: '#ai', badge: 'Native' },
];

/**
 * Footer "Jelajahi" column. Wider than the navbar on purpose: the sections
 * dropped from the top bar stay reachable down here.
 */
export const FOOTER_EXPLORE: { name: string; link: string }[] = [
    { name: 'Produk & Modul', link: '#platform' },
    { name: 'Solusi Terpadu', link: '#solusi' },
    { name: 'AI & Analytics', link: '#ai' },
    { name: 'Harga', link: '#harga' },
];

/**
 * Starting price shown as micro-copy in the hero and the closing CTA. Supplied
 * by the client; clicking it opens WhatsApp (see `PriceNote`).
 */
export const PRICE_NOTE = {
    prefix: 'Mulai',
    amount: 'Rp8.000',
    period: 'per bulan',
} as const;

/** Section 12 — the fragmented-HR problem the platform answers. */
export const PAIN_POINTS: { icon: Icon; title: string; body: string }[] = [
    {
        icon: Layers,
        title: 'Data tersebar di banyak sistem',
        body: 'Data karyawan, absensi, payroll, cuti dan performance berada di aplikasi yang berbeda-beda.',
    },
    {
        icon: ScrollText,
        title: 'Proses masih administratif',
        body: 'Banyak proses HR masih membutuhkan pekerjaan administratif dan manual.',
    },
    {
        icon: ClipboardCheck,
        title: 'Pengajuan sulit dipantau',
        body: 'HR harus memantau berbagai pengajuan dan approval dari beberapa kanal sekaligus.',
    },
    {
        icon: LineChart,
        title: 'Data belum jadi insight',
        body: 'Data HR tersedia, tetapi belum selalu mudah diubah menjadi informasi untuk keputusan.',
    },
];

export const LEGACY_STACK = [
    'HRIS',
    'Spreadsheet',
    'Attendance',
    'Payroll',
    'LMS',
    'Recruitment',
    'Komunikasi manual',
];

export const AVANA_STACK = [
    'HR Management',
    'Attendance',
    'ESS',
    'Payroll',
    'Finance',
    'Talent',
    'Learning',
    'Analytics',
    'AI',
];

/** Section 13 — employee journey, rendered as a connected flow. */
export const EMPLOYEE_JOURNEY: { label: string; icon: Icon; note: string }[] = [
    { label: 'Recruitment', icon: UserPlus, note: 'Lowongan & kandidat' },
    { label: 'Onboarding', icon: UserCheck, note: 'Checklist karyawan baru' },
    { label: 'Employee', icon: Users, note: 'Data & dokumen' },
    { label: 'Attendance', icon: Fingerprint, note: 'Absensi & jadwal' },
    { label: 'Payroll', icon: Wallet, note: 'Hitung sampai slip' },
    { label: 'Performance', icon: Target, note: 'Penilaian & OKR' },
    { label: 'Learning', icon: GraduationCap, note: 'Materi & progres' },
    { label: 'Career', icon: TrendingUp, note: 'Mutasi & suksesi' },
];

/** Section 14 — the six product areas, shown as a bento grid. */
export type Solution = {
    id: string;
    number: string;
    icon: Icon;
    title: string;
    copy: string;
    highlights: string[];
    more: string[];
};

export const SOLUTIONS: Solution[] = [
    {
        id: 'hr-management',
        number: '01',
        icon: Building2,
        title: 'HR Management',
        copy: 'Kelola data karyawan dari satu sumber.',
        highlights: ['Employee Database', 'Struktur Organisasi', 'Kontrak'],
        more: ['Dokumen', 'Mutasi & Karir', 'Manajemen Aset', 'SOP'],
    },
    {
        id: 'ess',
        number: '02',
        icon: Users,
        title: 'Employee Self Service',
        copy: 'Biarkan karyawan mengurus kebutuhan HR-nya sendiri.',
        highlights: ['Absensi & Koreksi', 'Cuti, Lembur & Izin', 'Slip Gaji'],
        more: [
            'Profil',
            'Jadwal',
            'Kalender',
            'Benefit',
            'Perjalanan Dinas',
            'Dokumen',
            'Kinerja',
            'Pembelajaran',
        ],
    },
    {
        id: 'payroll-finance',
        number: '03',
        icon: Wallet,
        title: 'Payroll & Finance',
        copy: 'Payroll lebih terstruktur, dari perhitungan hingga slip gaji.',
        highlights: ['Payroll', 'BPJS & PPh 21', 'Payday & Cut-off'],
        more: [
            'Lembur',
            'Potongan',
            'Slip Gaji',
            'Benefit',
            'Reimbursement',
            'Perjalanan Dinas',
        ],
    },
    {
        id: 'talent',
        number: '04',
        icon: Target,
        title: 'Talent Management',
        copy: 'Jangan berhenti setelah karyawan diterima. Bangun talent dari dalam.',
        highlights: ['Recruitment', 'Performance', 'OKR & Goal'],
        more: ['Onboarding', 'Learning / LMS', 'Talent & Succession'],
    },
    {
        id: 'analytics',
        number: '05',
        icon: BarChart3,
        title: 'Analytics',
        copy: 'Ubah data HR menjadi informasi untuk membantu management mengambil keputusan.',
        highlights: ['Absensi & Cuti', 'Payroll', 'Demografi'],
        more: ['Recruitment Analytics', 'Filter & Export Report'],
    },
    {
        id: 'ai',
        number: '06',
        icon: Sparkles,
        title: 'AI Assistant',
        copy: 'HR Anda sekarang punya AI Assistant.',
        highlights: ['AI Assistant', 'SOP sebagai sumber jawaban'],
        more: ['Provider & model AI yang dapat dikonfigurasi'],
    },
];

/** Section 15 — attendance-to-take-home-pay flow. */
export const PAYROLL_FLOW: { label: string; icon: Icon }[] = [
    { label: 'Attendance', icon: Fingerprint },
    { label: 'Overtime', icon: CalendarClock },
    { label: 'Cut-off', icon: ClipboardCheck },
    { label: 'Payroll', icon: Wallet },
    { label: 'BPJS + PPh 21', icon: Receipt },
    { label: 'Review', icon: ScrollText },
    { label: 'Approval', icon: UserCheck },
    { label: 'Payment', icon: Coins },
    { label: 'Slip Gaji', icon: ScrollText },
];

/** Section 16 — request types that share one approval path. */
export const APPROVAL_TYPES = [
    'Cuti',
    'Lembur',
    'Izin',
    'Koreksi Absensi',
    'Perjalanan Dinas',
    'Reimbursement',
    'Perubahan Data',
];

export const APPROVAL_STEPS: { label: string; caption: string }[] = [
    { label: 'Employee Request', caption: 'Karyawan mengajukan' },
    { label: 'Manager Review', caption: 'Atasan meninjau' },
    { label: 'HR Approval', caption: 'HR menyetujui' },
    { label: 'Completed', caption: 'Status & efek transaksi diperbarui' },
];

/** Section 17 — talent sub-features, driven by a tab control. */
export const TALENT_FEATURES: {
    id: string;
    icon: Icon;
    title: string;
    body: string;
    points: string[];
    /** Screenshot of the matching screen, when one has been captured. */
    image?: string;
}[] = [
    {
        id: 'recruitment',
        icon: UserPlus,
        title: 'Recruitment',
        body: 'Lowongan, kandidat, interview dan offering.',
        points: [
            'Hiring request sampai publikasi lowongan',
            'Progres kandidat per tahap',
            'Jadwal interview dan offering',
        ],
        image: '/avana/landing/screenshots/rekrutmen.png',
    },
    {
        id: 'onboarding',
        icon: UserCheck,
        title: 'Onboarding',
        body: 'Checklist karyawan baru dan pemantauan progres.',
        points: [
            'Checklist tugas onboarding',
            'Pemantauan progres per karyawan',
            'Lanjut otomatis ke data karyawan',
        ],
    },
    {
        id: 'performance',
        icon: Target,
        title: 'Performance',
        body: 'Self-assessment, penilaian atasan dan finalisasi HR.',
        points: [
            'Self-assessment karyawan',
            'Penilaian atasan',
            'Finalisasi oleh HR',
        ],
        image: '/avana/landing/screenshots/kinerja.png',
    },
    {
        id: 'okr',
        icon: TrendingUp,
        title: 'OKR & Goal',
        body: 'Target kerja karyawan/tim dengan pembaruan progres.',
        points: [
            'Objective dan key result',
            'Pembaruan progres berkala',
            'Target per karyawan atau tim',
        ],
    },
    {
        id: 'learning',
        icon: GraduationCap,
        title: 'Learning',
        body: 'Kursus/materi pelatihan dan progres belajar.',
        points: [
            'Materi dan kursus internal',
            'Progres belajar per karyawan',
            'Terhubung dengan pengembangan talent',
        ],
    },
    {
        id: 'succession',
        icon: Layers,
        title: 'Talent & Succession',
        body: 'Talent mapping / nine-box dan perencanaan suksesi.',
        points: [
            'Pemetaan talent nine-box',
            'Kandidat suksesi per posisi',
            'Dasar rencana pengembangan',
        ],
    },
];

/** Section 18 — what the analytics area reports on. */
export const ANALYTICS_TOPICS = [
    'Laporan absensi',
    'Cuti',
    'Payroll',
    'Demografi karyawan',
    'Filter & export report',
    'Recruitment analytics',
    'Time-to-hire',
    'Sumber kandidat',
    'Conversion antar tahap',
];

/** Section 20 — what an employee can do from the phone. */
export const MOBILE_CAPABILITIES: { label: string; icon: Icon }[] = [
    { label: 'Absensi', icon: Fingerprint },
    { label: 'Cuti', icon: CalendarClock },
    { label: 'Lembur', icon: CalendarClock },
    { label: 'Slip Gaji', icon: Receipt },
    { label: 'Jadwal', icon: CalendarClock },
    { label: 'Pengajuan', icon: ClipboardCheck },
];

/** Section 21 — the same platform seen from five roles. */
export const ROLES: {
    id: string;
    label: string;
    icon: Icon;
    copy: string;
    points: string[];
}[] = [
    {
        id: 'hr',
        icon: Users,
        label: 'HR Manager',
        copy: 'Kelola proses HR dari satu platform.',
        points: [
            'Data karyawan, kontrak dan dokumen',
            'Absensi, cuti dan lembur',
            'Payroll sampai penerbitan slip',
        ],
    },
    {
        id: 'finance',
        icon: Wallet,
        label: 'Finance',
        copy: 'Kelola payroll, reimbursement dan proses keuangan terkait karyawan.',
        points: [
            'Review dan approval payroll',
            'Reimbursement dan perjalanan dinas',
            'Komponen potongan dan benefit',
        ],
    },
    {
        id: 'manager',
        icon: ClipboardCheck,
        label: 'Manager',
        copy: 'Review dan approve pengajuan serta pantau tim.',
        points: [
            'Approval cuti, lembur dan izin tim',
            'Pantau kehadiran anggota tim',
            'Penilaian kinerja dan target kerja',
        ],
    },
    {
        id: 'employee',
        icon: UserCheck,
        label: 'Employee',
        copy: 'Akses kebutuhan HR melalui Employee Self Service.',
        points: [
            'Absensi dan riwayat kehadiran',
            'Pengajuan cuti, lembur dan izin',
            'Slip gaji, jadwal dan dokumen',
        ],
    },
    {
        id: 'management',
        icon: LineChart,
        label: 'Management',
        copy: 'Dapatkan laporan dan insight untuk membantu pengambilan keputusan.',
        points: [
            'Ringkasan people di perusahaan',
            'Laporan absensi, payroll dan demografi',
            'Analitik recruitment',
        ],
    },
];

/** Section 23 — control the platform keeps while staying flexible. */
export const CONTROL_POINTS: { icon: Icon; title: string; body: string }[] = [
    {
        icon: UserCheck,
        title: 'Role-based access',
        body: 'Akses menu dan aksi mengikuti peran masing-masing pengguna.',
    },
    {
        icon: ClipboardCheck,
        title: 'Approval workflow',
        body: 'Alur persetujuan pengajuan dapat disusun sesuai proses perusahaan.',
    },
    {
        icon: Layers,
        title: 'Connected employee data',
        body: 'Satu data karyawan dipakai lintas absensi, payroll dan talent.',
    },
    {
        icon: LineChart,
        title: 'Clear process status',
        body: 'Status setiap pengajuan dan proses terlihat jelas di sistem.',
    },
];

/** Section 24 — modules featured in the product tour. */
export const TOUR_MODULES: {
    id: string;
    label: string;
    icon: Icon;
    caption: string;
    /** Screenshot of the real screen, captured from the demo tenant. */
    image: string;
}[] = [
    {
        id: 'dashboard',
        label: 'Dashboard HR',
        icon: BarChart3,
        caption:
            'Ringkasan headcount, kehadiran, pengajuan dan payroll berjalan.',
        image: '/avana/landing/screenshots/dashboard.png',
    },
    {
        id: 'employees',
        label: 'Data Karyawan',
        icon: Users,
        caption:
            'Data karyawan, departemen, jabatan, status kerja dan dokumen dalam satu daftar.',
        image: '/avana/landing/screenshots/employees.png',
    },
    {
        id: 'attendance',
        label: 'Attendance',
        icon: Fingerprint,
        caption: 'Kehadiran, jadwal, koreksi absensi dan rekap per periode.',
        image: '/avana/landing/screenshots/absensi.png',
    },
    {
        id: 'payroll',
        label: 'Payroll',
        icon: Wallet,
        caption:
            'Periode payroll, komponen, BPJS & PPh 21, review sampai slip gaji.',
        image: '/avana/landing/screenshots/payroll.png',
    },
    {
        id: 'performance',
        label: 'Performance & OKR',
        icon: Target,
        caption: 'Penilaian kinerja, target kerja dan pembaruan progres.',
        image: '/avana/landing/screenshots/kinerja.png',
    },
    {
        id: 'recruitment',
        label: 'Recruitment',
        icon: UserPlus,
        caption: 'Lowongan, kandidat, tahapan seleksi sampai onboarding.',
        image: '/avana/landing/screenshots/rekrutmen.png',
    },
    {
        id: 'analytics',
        label: 'Analytics',
        icon: LineChart,
        caption:
            'Metrik people: attrition, komposisi departemen, usia dan distribusi gaji.',
        image: '/avana/landing/screenshots/analytics.png',
    },
];

export type Faq = { q: string; a: string };

/** Section 25 — FAQ, rendered as an accessible accordion. */
export const FAQS: Faq[] = [
    {
        q: 'Apa itu AvanaHR?',
        a: 'AvanaHR adalah platform yang mencakup berbagai proses HR seperti employee management, attendance, payroll, finance, talent, employee self service, analytics dan AI.',
    },
    {
        q: 'Siapa yang dapat menggunakan AvanaHR?',
        a: 'HR, Finance, Manager, Employee dan Management dapat menggunakan AvanaHR dengan akses yang disesuaikan dengan role.',
    },
    {
        q: 'Apakah karyawan dapat menggunakan AvanaHR?',
        a: 'Ya. Avana memiliki Employee Self Service untuk kebutuhan karyawan dan pengalaman yang terhubung dengan aplikasi mobile.',
    },
    {
        q: 'Apakah Avana memiliki payroll?',
        a: 'Ya. Payroll mencakup proses periode, perhitungan, review, approval, pembayaran dan penerbitan slip.',
    },
    {
        q: 'Apakah Avana memiliki recruitment dan performance?',
        a: 'Ya. Avana mencakup recruitment, onboarding, performance, OKR & Goal, learning serta talent & succession.',
    },
];

/** Section 27 — footer product links point at the sections above. */
export const FOOTER_PRODUCT: { label: string; href: string }[] = [
    { label: 'HR Management', href: '#solusi' },
    { label: 'Attendance', href: '#platform' },
    { label: 'Payroll & Finance', href: '#platform' },
    { label: 'Talent Management', href: '#platform' },
    { label: 'AI Assistant', href: '#ai' },
];
