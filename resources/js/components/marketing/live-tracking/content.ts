import {
    Activity,
    Clock,
    Eye,
    Gauge,
    History,
    MapPin,
    Radio,
    Route,
    ShieldCheck,
    Truck,
    UserCheck,
    Users,
    Wrench,
} from 'lucide-react';
import type { Faq } from '../content';
import type { Icon } from '../content';

/**
 * Copy and sample data for the Live Tracking feature page.
 *
 * Every employee, distance and timestamp here is invented demo material for the
 * marketing mockups — nothing is read from production. See `docs/design.md` §9.
 */

/** Section 9 — short value strip under the hero. */
export const VALUE_STRIP: { label: string; icon: Icon }[] = [
    { label: 'Live Position', icon: MapPin },
    { label: 'Route History', icon: Route },
    { label: 'Distance Tracking', icon: Gauge },
    { label: 'Clock In → Clock Out', icon: Clock },
    { label: 'Realtime Dashboard', icon: Radio },
];

/** Section 10 — why field visibility is hard today. */
export const TRACKING_PROBLEMS: { icon: Icon; title: string; body: string }[] =
    [
        {
            icon: MapPin,
            title: 'Posisi Sulit Dipantau',
            body: 'HR tidak selalu mengetahui posisi terakhir employee lapangan.',
        },
        {
            icon: Route,
            title: 'Perjalanan Tidak Tercatat',
            body: 'Rute dan jarak perjalanan kerja sulit dilihat kembali.',
        },
        {
            icon: Eye,
            title: 'Monitoring Manual',
            body: 'HR atau manager harus menanyakan update secara manual.',
        },
        {
            icon: Activity,
            title: 'Data Terpisah',
            body: 'Attendance dan aktivitas lapangan sering berada dalam proses berbeda.',
        },
    ];

/** Section 11 — the tracking lifecycle, drawn as a connected timeline. */
export const TRACKING_FLOW: { label: string; note: string }[] = [
    { label: 'Clock In', note: 'Karyawan memulai sesi kerja' },
    { label: 'Tracking Aktif', note: 'Sesi tracking berjalan' },
    { label: 'GPS Dicatat', note: 'Lokasi direkam berkala' },
    { label: 'Posisi Diperbarui', note: 'Titik terakhir diperbarui' },
    { label: 'HR Melihat Live Map', note: 'Dashboard menampilkan posisi' },
    { label: 'Clock Out', note: 'Sesi kerja diakhiri' },
    { label: 'Tracking Selesai', note: 'Riwayat tersimpan' },
];

/** Section 18 — what the dashboard actually surfaces. */
export const TRACKING_INSIGHTS: { icon: Icon; title: string; body: string }[] =
    [
        {
            icon: MapPin,
            title: 'Current Location',
            body: 'Posisi terakhir employee.',
        },
        {
            icon: Route,
            title: 'Route History',
            body: 'Riwayat perjalanan dalam satu sesi kerja.',
        },
        {
            icon: Gauge,
            title: 'Total Distance',
            body: 'Total jarak perjalanan yang tercatat.',
        },
        {
            icon: Clock,
            title: 'Tracking Duration',
            body: 'Durasi sesi tracking.',
        },
        {
            icon: History,
            title: 'Last Update',
            body: 'Waktu lokasi terakhir diterima.',
        },
        {
            icon: UserCheck,
            title: 'Employee Status',
            body: 'Status tracking employee.',
        },
    ];

/** Section 17 — how tracking stays bounded to the work session. */
export const PRIVACY_POINTS: { icon: Icon; title: string; body: string }[] = [
    {
        icon: Clock,
        title: 'Work-Hour Tracking',
        body: 'Tracking hanya selama sesi kerja.',
    },
    {
        icon: Radio,
        title: 'Transparent Status',
        body: 'Karyawan dapat melihat ketika tracking sedang aktif.',
    },
    {
        icon: ShieldCheck,
        title: 'Role-Based Access',
        body: 'Data tracking hanya tersedia bagi role yang memiliki izin.',
    },
    {
        icon: UserCheck,
        title: 'Controlled Access',
        body: 'Akses mengikuti permission dan perusahaan/tenant.',
    },
];

/** Section 20 — teams the feature is meant for. */
export const USE_CASES: { icon: Icon; title: string; body: string }[] = [
    {
        icon: Users,
        title: 'Sales Lapangan',
        body: 'Pantau aktivitas employee selama melakukan pekerjaan di lapangan.',
    },
    {
        icon: Wrench,
        title: 'Field Service',
        body: 'Lihat posisi dan perjalanan employee yang menjalankan pekerjaan di luar kantor.',
    },
    {
        icon: Activity,
        title: 'Operational Team',
        body: 'Monitor tim operasional dengan lebih mudah.',
    },
    {
        icon: Truck,
        title: 'Delivery / Driver',
        body: 'Pantau perjalanan selama sesi kerja.',
    },
];

/** Sample employees for the dashboard mockups — demo data only. */
export type DemoEmployee = {
    id: string;
    name: string;
    department: string;
    status: 'active' | 'idle' | 'ended';
    updated: string;
    initials: string;
};

export const DEMO_EMPLOYEES: DemoEmployee[] = [
    {
        id: 'andra',
        name: 'Andra Wijaya',
        department: 'Sales',
        status: 'active',
        updated: '8 detik lalu',
        initials: 'AW',
    },
    {
        id: 'bima',
        name: 'Bima',
        department: 'Field Service',
        status: 'active',
        updated: '1 menit lalu',
        initials: 'BI',
    },
    {
        id: 'yoga',
        name: 'Yoga',
        department: 'Delivery',
        status: 'idle',
        updated: '4 menit lalu',
        initials: 'YO',
    },
    {
        id: 'sari',
        name: 'Sari Wulandari',
        department: 'Sales',
        status: 'ended',
        updated: 'Clock Out 16:48',
        initials: 'SW',
    },
];

/** Selected-employee panel content — demo data only. */
export const DEMO_DETAIL = {
    name: 'Andra Wijaya',
    department: 'Sales',
    status: 'Tracking Active',
    rows: [
        { label: 'Clock In', value: '08:02 WIB' },
        { label: 'Distance', value: '8.42 km' },
        { label: 'Duration', value: '4j 21m' },
        { label: 'Last Update', value: '8 detik lalu' },
        { label: 'Accuracy', value: '±8 m' },
    ],
} as const;

/** Route-history summary — demo data only. */
export const HISTORY_SUMMARY = {
    date: '14 Agustus 2026',
    rows: [
        { label: 'Clock In', value: '08:02 WIB' },
        { label: 'Clock Out', value: '17:04 WIB' },
        { label: 'Duration', value: '9j 02m' },
        { label: 'Distance', value: '23.84 km' },
    ],
    timeline: [
        { time: '08:02', label: 'Clock In' },
        { time: '08:34', label: 'Mulai bergerak' },
        { time: '09:15', label: 'Berhenti' },
        { time: '10:02', label: 'Perjalanan dilanjutkan' },
        { time: '17:04', label: 'Clock Out' },
    ],
} as const;

/** Section 21 — how tracking sits inside the attendance flow. */
export const INTEGRATION_STEPS: {
    label: string;
    items?: string[];
    tone: 'brand' | 'neutral';
}[] = [
    { label: 'Attendance — Clock In', tone: 'neutral' },
    {
        label: 'Live Tracking',
        items: ['Location', 'Distance', 'Duration'],
        tone: 'brand',
    },
    { label: 'Clock Out', tone: 'neutral' },
    { label: 'Tracking History', tone: 'neutral' },
];

/** Section 24 — questions limited to what the feature actually does. */
export const TRACKING_FAQS: Faq[] = [
    {
        q: 'Apa itu AvanaHR Live Tracking?',
        a: 'AvanaHR Live Tracking membantu HR melihat posisi dan riwayat perjalanan karyawan selama sesi kerja.',
    },
    {
        q: 'Kapan tracking mulai berjalan?',
        a: 'Tracking dimulai setelah karyawan melakukan Clock In.',
    },
    {
        q: 'Kapan tracking berhenti?',
        a: 'Tracking berhenti ketika karyawan melakukan Clock Out.',
    },
    {
        q: 'Apakah karyawan harus membuka map?',
        a: 'Tidak. Karyawan cukup melakukan Clock In dan Clock Out melalui aplikasi AvanaHR.',
    },
    {
        q: 'Siapa yang dapat melihat lokasi karyawan?',
        a: 'Akses Live Tracking mengikuti role dan permission yang diberikan perusahaan.',
    },
    {
        q: 'Apakah riwayat perjalanan dapat dilihat kembali?',
        a: 'Ya, HR dapat melihat tracking history berdasarkan sesi dan tanggal yang tersedia.',
    },
];
