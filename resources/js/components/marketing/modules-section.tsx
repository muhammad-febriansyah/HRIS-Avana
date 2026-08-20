import {
    ArrowRight,
    BarChart3,
    Check,
    ClipboardCheck,
    Fingerprint,
    Handshake,
    MapPin,
    Mic,
    Palmtree,
    ShieldCheck,
    Sparkles,
    Target,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { DemoButton } from './cta-buttons';
import { Container, Reveal, SectionHeading } from './reveal';

type Module = {
    title: string;
    icon: typeof Users;
    tagline: string;
    desc: string;
    screenshot: string;
    highlights: string[];
};

/**
 * The concrete product modules, each with its own real screenshot — one
 * level more granular than the 6 platform areas in `SolutionSection`.
 */
const MODULES: Module[] = [
    {
        title: 'Core HR',
        icon: Users,
        tagline: 'Manajemen Data Karyawan',
        desc: 'Database profil karyawan lengkap, riwayat karir, struktur organisasi interaktif, SOP perusahaan, dan manajemen dokumen digital terpusat.',
        screenshot: '/avana/landing/screenshots/employees.png',
        highlights: [
            'Struktur organisasi real-time',
            'Manajemen kontrak & mutasi',
            'Digital employee file',
        ],
    },
    {
        title: 'Payroll',
        icon: Wallet,
        tagline: 'Otomasi Penggajian & Pajak',
        desc: 'Kelola proses payroll bersama data HR dalam satu platform. Otomatisasi perhitungan PPh 21 tarif TER 2024, BPJS Ketenagakerjaan & Kesehatan, serta slip gaji instan.',
        screenshot: '/avana/landing/screenshots/payroll.png',
        highlights: [
            'Kalkulasi tarif TER PPh 21 2024',
            'BPJS Ketenagakerjaan & Kesehatan',
            'Multi komponen gaji & insentif',
        ],
    },
    {
        title: 'Attendance',
        icon: Fingerprint,
        tagline: 'Absensi GPS & Shift Roster',
        desc: 'Pantau kehadiran kerja secara akurat dengan geotagging GPS, verifikasi wajah, manajemen pola shift roster fleksibel, dan tukar shift mandiri.',
        screenshot: '/avana/landing/screenshots/absensi.png',
        highlights: [
            'Live GPS tracking & face verification',
            'Manajemen jadwal shift kompleks',
            'Timesheet otomatis & koreksi',
        ],
    },
    {
        title: 'Leave & Cuti',
        icon: Palmtree,
        tagline: 'Manajemen Cuti & Izin',
        desc: 'Otomatisasi pengajuan dan persetujuan cuti berjenjang, perhitungan sisa kuota cuti tahunan, rekap lembur, serta permohonan dinas kerja.',
        screenshot: '/avana/landing/screenshots/dashboard-hero.png',
        highlights: [
            'Alur persetujuan multi-level',
            'Kalkulasi sisa cuti otomatis',
            'Integrasi langsung ke payroll',
        ],
    },
    {
        title: 'Recruitment',
        icon: UserPlus,
        tagline: 'Talent Acquisition & Hiring',
        desc: 'Kelola requisition lowongan kerja, hiring request dari user department, pipeline pelamar kerja, jadwal interview, hingga proses onboarding digital.',
        screenshot: '/avana/landing/screenshots/rekrutmen.png',
        highlights: [
            'Applicant Tracking System (ATS)',
            'Progres kandidat per tahap',
            'Onboarding digital terhubung',
        ],
    },
    {
        title: 'Performance',
        icon: Target,
        tagline: 'Manajemen Kinerja & OKR',
        desc: 'Evaluasi kinerja berkala (KPI / OKR), self-assessment dan penilaian atasan, serta talent matrix nine-box untuk perencanaan suksesi kepemimpinan.',
        screenshot: '/avana/landing/screenshots/kinerja.png',
        highlights: [
            'OKR & Goal tracking',
            'Self-assessment & penilaian atasan',
            'Pemetaan talent nine-box',
        ],
    },
    {
        title: 'AI Intelligence',
        icon: Sparkles,
        tagline: 'Kecerdasan Buatan HR',
        desc: 'AI Assistant yang membaca SOP perusahaan yang dipublikasikan, membantu HR dan karyawan menjawab pertanyaan kebijakan sesuai konteks internal.',
        screenshot: '/avana/landing/screenshots/dashboard.png',
        highlights: [
            'Jawaban bersumber dari SOP internal',
            'Provider & model AI dapat dikonfigurasi',
            'Tersedia untuk HR dan karyawan',
        ],
    },
    {
        title: 'Workforce Analytics',
        icon: BarChart3,
        tagline: 'Executive HR Dashboard',
        desc: 'Bantu HR melihat data tenaga kerja dengan perspektif yang lebih terhubung melalui grafik headcount, absensi, payroll, dan demografi karyawan.',
        screenshot: '/avana/landing/screenshots/analytics.png',
        highlights: [
            'Prediksi risiko resign (attrition)',
            'Sebaran demografi & masa kerja',
            'Filter & export report',
        ],
    },
    {
        title: 'CRM',
        icon: Handshake,
        tagline: 'Manajemen Klien & Sales Pipeline',
        desc: 'Kelola kontak, deal, dan pipeline penjualan dalam satu tempat, lengkap dengan aktivitas, tugas tindak lanjut, dan insight performa tim sales.',
        screenshot: '/avana/landing/screenshots/crm.png',
        highlights: [
            'Pipeline deal drag & drop per tahap',
            'Kontak & aktivitas tim sales terpusat',
            'Insight nilai pipeline & deal won',
        ],
    },
    {
        title: 'Live Tracking',
        icon: MapPin,
        tagline: 'Pantau Karyawan Lapangan Real-Time',
        desc: 'Pantau posisi karyawan lapangan secara real-time selama sesi kerja aktif, lengkap dengan rute perjalanan, jarak tempuh, dan riwayat tracking.',
        screenshot: '/avana/landing/screenshots/live-tracking.png',
        highlights: [
            'Posisi live selama Clock In-Clock Out',
            'Rute & jarak tempuh otomatis',
            'Riwayat tracking per karyawan',
        ],
    },
    {
        title: 'Rapat & Transkrip',
        icon: Mic,
        tagline: 'Voice Note Rapat dengan AI',
        desc: 'Rekam rapat dari aplikasi HP, lalu biarkan AI mengubahnya jadi transkrip, ringkasan, dan daftar tindak lanjut otomatis, tanpa perlu notulen manual.',
        screenshot: '/avana/landing/screenshots/rapat.png',
        highlights: [
            'Rekaman suara diubah jadi transkrip otomatis',
            'Ringkasan & tindak lanjut oleh AI',
            'Riwayat rapat tersimpan per karyawan',
        ],
    },
    {
        title: 'Visiting Pekerjaan',
        icon: ClipboardCheck,
        tagline: 'Tasklist Kunjungan Lapangan',
        desc: 'Catat kunjungan lapangan dan klien karyawan lengkap dengan tasklist per kunjungan, sehingga progres tugas di lokasi klien terpantau jelas.',
        screenshot: '/avana/landing/screenshots/visiting.png',
        highlights: [
            'Tasklist per kunjungan & progres selesai',
            'Terhubung ke lokasi, klien, dan cabang',
            'Riwayat kunjungan per karyawan',
        ],
    },
    {
        title: 'Settlement',
        icon: ShieldCheck,
        tagline: 'Klaim Dinas dengan Deteksi Fraud AI',
        desc: 'Kelola klaim biaya perjalanan dinas dari pengajuan, persetujuan manager, hingga verifikasi Finance — dengan AI yang memeriksa keaslian bukti pengeluaran dan menandai risiko fraud.',
        screenshot: '/avana/landing/screenshots/settlement.png',
        highlights: [
            'Alur persetujuan manager & verifikasi Finance',
            'Skor risiko fraud otomatis pada bukti pengeluaran',
            'Terhubung langsung ke pembayaran',
        ],
    },
];

/** Grid columns at the `lg` breakpoint — kept in sync with the centering math below. */
const GRID_COLS = 4;

/** Static so Tailwind's scanner can see every class it needs to generate. */
const COL_START_CLASSES = [
    '',
    'lg:col-start-1',
    'lg:col-start-2',
    'lg:col-start-3',
    'lg:col-start-4',
];

/**
 * The concrete product modules as a clickable grid with a live screenshot
 * showcase below — one level more granular than the abstract value-flow in
 * `SolutionSection` above it.
 */
export function ModulesSection() {
    const [active, setActive] = useState(0);
    const current = MODULES[active];

    // When the module count doesn't fill the last row evenly, center that
    // row's cards instead of leaving them stranded against the left edge.
    const remainder = MODULES.length % GRID_COLS;
    const lastRowStart = MODULES.length - remainder;
    const leadingGap = Math.floor((GRID_COLS - remainder) / 2);

    return (
        <section id="platform" className="scroll-mt-28 py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Fitur & modul komprehensif"
                    title={
                        <>
                            Satu Platform untuk{' '}
                            <br className="hidden sm:inline" />
                            <span className="text-[#2F54C9]">
                                Proses HR yang Terhubung
                            </span>
                        </>
                    }
                    description="Kelola seluruh siklus karyawan dari rekrutmen hingga payroll dan analitik tanpa perlu berpindah-pindah aplikasi yang terpisah."
                />

                <div className="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {MODULES.map((module, index) => {
                        const isActive = index === active;
                        const isLastRow =
                            remainder > 0 && index >= lastRowStart;
                        const colStart = isLastRow
                            ? leadingGap + (index - lastRowStart) + 1
                            : 0;

                        return (
                            <Reveal
                                key={module.title}
                                delay={(index % 4) * 0.05}
                                className={cn(
                                    COL_START_CLASSES[colStart],
                                    isLastRow &&
                                        remainder === 1 &&
                                        'lg:col-span-2',
                                )}
                            >
                                <button
                                    type="button"
                                    onClick={() => setActive(index)}
                                    aria-pressed={isActive}
                                    className={cn(
                                        'flex h-full w-full cursor-pointer flex-col justify-between rounded-2xl border p-6 text-left transition-[background-color,border-color,box-shadow,transform] duration-200',
                                        isActive
                                            ? 'scale-[1.02] border-[#0E1A3A] bg-gradient-to-b from-[#16234A] to-[#0E1A3A] text-white shadow-avana-hover'
                                            : 'border-[#E7ECF5] bg-[#F8FAFD] text-[#0E1A3A] hover:bg-[#EEF2FB]',
                                    )}
                                >
                                    <div>
                                        <div className="mb-4 flex items-center justify-between">
                                            <span
                                                className={cn(
                                                    'grid h-12 w-12 place-items-center rounded-xl transition-colors',
                                                    isActive
                                                        ? 'bg-[#2F54C9] text-white'
                                                        : 'bg-white text-[#2F54C9] shadow-sm',
                                                )}
                                            >
                                                <module.icon
                                                    className="h-6 w-6"
                                                    aria-hidden
                                                />
                                            </span>
                                            {isActive && (
                                                <span className="rounded-full bg-[#2F54C9] px-2 py-0.5 text-[10px] font-bold tracking-wider text-white uppercase">
                                                    Aktif
                                                </span>
                                            )}
                                        </div>

                                        <h3
                                            className={cn(
                                                'mb-1 text-lg font-bold',
                                                isActive
                                                    ? 'text-white'
                                                    : 'text-[#0E1A3A]',
                                            )}
                                        >
                                            {module.title}
                                        </h3>
                                        <p
                                            className={cn(
                                                'mb-2 text-xs font-semibold',
                                                isActive
                                                    ? 'text-blue-200'
                                                    : 'text-[#2F54C9]',
                                            )}
                                        >
                                            {module.tagline}
                                        </p>
                                        <p
                                            className={cn(
                                                'line-clamp-2 text-xs leading-relaxed',
                                                isActive
                                                    ? 'text-white/80'
                                                    : 'text-[#3B455C]/80',
                                            )}
                                        >
                                            {module.desc}
                                        </p>
                                    </div>

                                    <div
                                        className={cn(
                                            'mt-4 flex items-center justify-between border-t pt-3 text-xs font-bold',
                                            isActive
                                                ? 'border-white/10 text-white'
                                                : 'border-[#E7ECF5] text-[#2F54C9]',
                                        )}
                                    >
                                        <span>Lihat Tampilan UI</span>
                                        <ArrowRight
                                            className="h-3.5 w-3.5"
                                            aria-hidden
                                        />
                                    </div>
                                </button>
                            </Reveal>
                        );
                    })}
                </div>

                <Reveal delay={0.1} className="mt-6">
                    <div className="rounded-[28px] border border-[#E7ECF5] bg-[#F8FAFD] p-6 sm:p-10">
                        <div className="grid gap-8 lg:grid-cols-12 lg:items-center">
                            <div className="lg:col-span-5">
                                <span className="inline-flex items-center rounded-full border border-[#E2E9F6] bg-white px-3.5 py-1 text-[12px] font-semibold tracking-[0.08em] text-[#2F54C9] uppercase">
                                    Modul Terpilih
                                </span>
                                <h3 className="mt-4 text-2xl font-extrabold tracking-[-0.01em] text-[#0E1A3A] sm:text-[28px]">
                                    {current.title}
                                </h3>
                                <p className="mt-1 text-[13.5px] font-semibold text-[#2F54C9]">
                                    {current.tagline}
                                </p>
                                <p className="mt-3 text-[15px] leading-relaxed text-[#5B6478] sm:text-[16px]">
                                    {current.desc}
                                </p>

                                <div className="mt-5 space-y-2.5">
                                    <div className="text-[12px] font-bold tracking-wider text-[#0E1A3A] uppercase">
                                        Fitur Unggulan:
                                    </div>
                                    {current.highlights.map((item) => (
                                        <div
                                            key={item}
                                            className="flex items-center gap-2.5 text-[14px] text-[#3B455C]"
                                        >
                                            <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#E7F6EE] text-[#1F9D55]">
                                                <Check
                                                    className="h-3 w-3"
                                                    aria-hidden
                                                />
                                            </span>
                                            {item}
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-6">
                                    <DemoButton variant="primary">
                                        Coba Modul {current.title}
                                    </DemoButton>
                                </div>
                            </div>

                            <div className="lg:col-span-7">
                                <div className="rounded-2xl border border-avana-border bg-white p-2 shadow-avana-hover sm:p-3">
                                    <div className="mb-2 flex items-center justify-between border-b border-[#F0F3F9] px-3 py-1.5 text-xs text-avana-muted">
                                        <div className="flex items-center gap-1.5">
                                            <span className="h-2 w-2 rounded-full bg-gray-300" />
                                            <span className="h-2 w-2 rounded-full bg-gray-300" />
                                            <span className="h-2 w-2 rounded-full bg-gray-300" />
                                        </div>
                                        <span className="font-mono text-[11px] text-avana-muted">
                                            app.avanahr.id/modul/
                                            {current.title
                                                .toLowerCase()
                                                .replace(/\s+/g, '-')}
                                        </span>
                                        <div className="w-6" />
                                    </div>

                                    <div className="flex max-h-[380px] items-start justify-center overflow-hidden rounded-xl border border-[#F0F3F9] bg-[#F8FAFD] sm:max-h-[460px]">
                                        <img
                                            src={current.screenshot}
                                            alt={`Tampilan ${current.title} di AvanaHR`}
                                            loading="lazy"
                                            className="h-auto w-full rounded-lg object-contain"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={0.15} className="mt-6 flex justify-center">
                    <a
                        href="#solusi"
                        className="inline-flex items-center gap-1.5 text-[13.5px] font-semibold text-[#2F54C9] hover:text-[#2546AD]"
                    >
                        Lihat cakupan platform
                        <ArrowRight className="h-3.5 w-3.5" aria-hidden />
                    </a>
                </Reveal>
            </Container>
        </section>
    );
}
