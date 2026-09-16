import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    BriefcaseBusiness,
    CalendarDays,
    Check,
    ChevronRight,
    Clock3,
    CircleCheck,
    ClipboardCheck,
    ContactRound,
    FileCheck2,
    FileText,
    Gauge,
    GitBranch,
    GraduationCap,
    Headphones,
    Landmark,
    ListChecks,
    LockKeyhole,
    MapPin,
    Megaphone,
    MessageSquareText,
    Mic2,
    Network,
    ReceiptText,
    ScanFace,
    ShieldAlert,
    Sparkles,
    Star,
    Target,
    TicketCheck,
    TrendingDown,
    TrendingUp,
    UserCheck,
    UserPlus,
    Users,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { DemoButton, TrialButton } from '@/components/marketing/cta-buttons';
import { FaqSection } from '@/components/marketing/faq-section';
import { FeatureSubnav } from '@/components/marketing/feature-subnav';
import { FinalCta } from '@/components/marketing/final-cta';
import {
    Container,
    Reveal,
    SectionHeading,
} from '@/components/marketing/reveal';
import { SiteFooter } from '@/components/marketing/site-footer';
import { SiteNavbar } from '@/components/marketing/site-navbar';
import { WhatsAppFab } from '@/components/marketing/whatsapp-fab';
import { PUBLIC_FEATURE_CATALOG } from '@/data/public-feature-catalog';
import type { PublicFeatureCatalogEntry } from '@/data/public-feature-catalog';
import { home, security } from '@/routes';
import { show as featuresShow } from '@/routes/features';
import { crm as solutionCrm } from '@/routes/solution';

type FeatureCard = {
    title: string;
    description: string;
    icon: LucideIcon;
    status?: 'live' | 'hold';
};

type FeaturePageData = {
    name: string;
    category: string;
    asset: string;
    title: string;
    description: string;
    heroNote: string;
    problems: string[];
    features: FeatureCard[];
    steps: string[];
    benefits: string[];
    audience: string[];
    faqs: { q: string; a: string }[];
    related: string[];
};

const F = (
    title: string,
    description: string,
    icon: LucideIcon,
    status?: 'live' | 'hold',
): FeatureCard => ({ title, description, icon, status });

const CATALOG_ICON_MAP: Record<string, LucideIcon> = {
    BarChart3,
    CalendarDays,
    CircleCheck,
    Clock3,
    ContactRound,
    FileCheck2,
    FileText,
    Gauge,
    GitBranch,
    GraduationCap,
    Landmark,
    ListChecks,
    MessageSquareText,
    Network,
    ReceiptText,
    ScanFace,
    ShieldAlert,
    Sparkles,
    Target,
    TicketCheck,
    TrendingDown,
    TrendingUp,
    UserCheck,
    UserPlus,
    Users,
    WalletCards,
};

const FEATURE_PAGES: Record<string, FeaturePageData> = {
    'core-hr': {
        name: 'Core HR',
        category: 'HR & Karyawan',
        asset: 'feature-assets/dashboard_hr_avanahr_terpadu.png',
        title: 'Kelola Data Karyawan dari Satu Sumber yang Rapi',
        description:
            'Satukan data personal, struktur organisasi, kontrak, dan dokumen karyawan dalam satu fondasi HR yang selalu siap dipakai.',
        heroNote: 'Data terpusat untuk setiap perjalanan karyawan.',
        problems: [
            'Data tersebar di banyak file.',
            'Histori perubahan sulit dilacak.',
            'Dokumen karyawan terselip di banyak tempat.',
            'Onboarding masih bergantung checklist manual.',
        ],
        features: [
            F(
                'Database karyawan',
                'Profil, status, dan informasi kerja tersusun dalam satu direktori.',
                Users,
            ),
            F(
                'Struktur organisasi',
                'Tampilkan relasi tim, jabatan, dan cabang dengan lebih jelas.',
                Network,
            ),
            F(
                'Kontrak & dokumen',
                'Simpan dokumen kerja dengan akses yang sesuai peran.',
                FileText,
            ),
            F(
                'Mutasi & karier',
                'Catat perpindahan, promosi, dan riwayat karier karyawan.',
                TrendingUp,
            ),
            F(
                'Onboarding',
                'Buat checklist awal kerja agar proses bergulir tanpa lupa langkah.',
                UserPlus,
            ),
            F(
                'Riwayat perubahan',
                'Telusuri siapa mengubah data dan kapan perubahan dilakukan.',
                FileCheck2,
            ),
        ],
        steps: [
            'Input data karyawan',
            'Lengkapi struktur & dokumen',
            'Kelola perubahan',
            'Data dipakai modul lain',
        ],
        benefits: [
            'Satu sumber data untuk HR dan manajemen.',
            'Pencarian data lebih cepat.',
            'Administrasi onboarding lebih terarah.',
            'Data siap mengalir ke Attendance dan Payroll.',
        ],
        audience: [
            'Perusahaan multi cabang',
            'Startup yang sedang bertumbuh',
            'Tim HR dengan banyak dokumen',
            'Perusahaan dengan struktur organisasi kompleks',
        ],
        faqs: [
            {
                q: 'Apakah Core HR mendukung multi cabang?',
                a: 'Ya. Data dapat dikelompokkan berdasarkan cabang, departemen, jabatan, dan struktur pelaporan.',
            },
            {
                q: 'Apakah histori perubahan data tersimpan?',
                a: 'Perubahan penting dapat ditelusuri agar HR mengetahui apa yang berubah, siapa yang melakukan, dan kapan waktunya.',
            },
            {
                q: 'Apakah dokumen karyawan dapat diunggah?',
                a: 'Bisa. Dokumen disimpan pada area privat dan mengikuti kontrol akses pengguna.',
            },
            {
                q: 'Apakah data terhubung dengan payroll?',
                a: 'Ya. Data karyawan menjadi fondasi untuk Attendance, Leave, Payroll, dan Analytics.',
            },
            {
                q: 'Apakah struktur organisasi bisa diatur?',
                a: 'Bisa. HR dapat mengelola unit, posisi, atasan, dan relasi kerja sesuai kebutuhan perusahaan.',
            },
        ],
        related: [
            'absensi-karyawan',
            'cuti-dan-izin',
            'payroll',
            'hr-analytics',
        ],
    },
    'data-karyawan': {
        name: 'Data Karyawan',
        category: 'HR & Karyawan',
        asset: 'feature-assets/dashboard_hr_avanahr_terpadu.png',
        title: 'Satu Sistem Terpadu untuk Data Karyawan yang Selalu Siap Dipakai',
        description:
            'Satukan data karyawan, dashboard aktivitas, pembaruan ESS, laporan, surat resmi, dan pencarian berbasis AI dalam satu sumber data HR yang terstruktur.',
        heroNote: 'Data karyawan lengkap, ringkas, dan mudah ditindaklanjuti.',
        problems: [
            'Data karyawan tersebar di banyak file dan aplikasi.',
            'HR membutuhkan waktu untuk menyusun ringkasan aktivitas setiap hari.',
            'Pembaruan data pribadi masih bergantung pada admin.',
            'Laporan dan surat resmi dibuat berulang dari awal.',
        ],
        features: [
            F(
                'Data lengkap dan terstruktur',
                'Simpan informasi karyawan dalam kategori yang rapi, mudah dicari, dan terhubung ke modul HR lainnya.',
                ContactRound,
            ),
            F(
                'Dashboard karyawan interaktif',
                'Lihat ringkasan headcount, kualitas data, notifikasi, kehadiran, dan permintaan karyawan dalam satu tampilan.',
                BarChart3,
            ),
            F(
                'Pembaruan mandiri melalui ESS',
                'Karyawan dapat memperbarui data pribadinya sendiri dengan alur persetujuan atasan yang lebih praktis.',
                UserCheck,
            ),
            F(
                'Laporan dan surat resmi otomatis',
                'Gunakan template dan filter fleksibel untuk membuat laporan, kontrak, surat peringatan, dan dokumen administratif.',
                FileText,
            ),
            F(
                'Akses data berbasis AI',
                'Ajukan pertanyaan dalam bahasa sehari-hari dan dapatkan jawaban dari data HR yang memiliki akses sesuai peran.',
                Sparkles,
            ),
        ],
        steps: [
            'Impor atau lengkapi data karyawan',
            'Kelompokkan informasi berdasarkan kategori',
            'Karyawan memperbarui data melalui ESS',
            'HR memantau dashboard dan aktivitas',
            'Buat laporan atau cari jawaban dengan AI',
        ],
        benefits: [
            'Satu sumber data karyawan untuk seluruh proses HR.',
            'Ringkasan aktivitas lebih cepat dipahami oleh HR dan manajemen.',
            'Akurasi data meningkat karena karyawan ikut memperbaruinya.',
            'Laporan dan surat resmi lebih konsisten dan hemat waktu.',
        ],
        audience: [
            'Tim HR dengan data karyawan yang terus bertambah',
            'Perusahaan multi cabang',
            'Organisasi yang ingin mendorong penggunaan ESS',
            'Manajemen yang membutuhkan ringkasan HR secara cepat',
        ],
        faqs: [
            {
                q: 'Informasi apa saja yang dapat dikelola?',
                a: 'Data personal, pekerjaan, struktur organisasi, dokumen, riwayat aktivitas, dan kategori informasi HR lainnya dapat dikelola sesuai konfigurasi perusahaan.',
            },
            {
                q: 'Apakah karyawan dapat mengubah datanya sendiri?',
                a: 'Bisa. Karyawan mengajukan perubahan melalui ESS, lalu perubahan dapat mengikuti persetujuan atasan sebelum diterapkan.',
            },
            {
                q: 'Aktivitas apa yang tampil di dashboard?',
                a: 'Dashboard dapat merangkum kehadiran, permintaan cuti, perubahan data, notifikasi, kualitas data, dan aktivitas penting lainnya.',
            },
            {
                q: 'Apakah bisa membuat surat resmi dari template?',
                a: 'Bisa. HR dapat menggunakan template dan filter data untuk menghasilkan surat serta laporan dengan format yang konsisten.',
            },
            {
                q: 'Bagaimana AI membantu pencarian data?',
                a: 'HR dapat mengajukan pertanyaan secara percakapan. AI memberikan jawaban berdasarkan data dan akses yang tersedia bagi pengguna.',
            },
        ],
        related: [
            'core-hr',
            'absensi-karyawan',
            'cuti-dan-izin',
            'hr-analytics',
        ],
    },
    'struktur-organisasi': {
        name: 'Struktur Organisasi',
        category: 'HR & Karyawan',
        asset: 'core-hr.png',
        title: 'Jelaskan Struktur Organisasi dengan Lebih Jelas dan Fleksibel',
        description:
            'Tampilkan hierarki, hubungan kerja, dan rencana perubahan organisasi dalam informasi visual yang mudah dipahami oleh seluruh perusahaan.',
        heroNote: 'Struktur hari ini dan rencana masa depan dalam satu cerita.',
        problems: [
            'Struktur organisasi sulit dipahami dari daftar jabatan biasa.',
            'Hubungan lintas fungsi tidak terlihat jelas.',
            'Rencana perubahan organisasi masih tersimpan di dokumen terpisah.',
            'Informasi kebijakan dan komunikasi internal tidak berada di satu tempat.',
        ],
        features: [
            F(
                'Tampilan hierarki otomatis',
                'Jelaskan posisi, unit, atasan, dan anggota tim melalui susunan hierarki yang mudah dibaca.',
                Network,
            ),
            F(
                'Diagram hubungan fleksibel',
                'Tampilkan hubungan pelaporan dan kolaborasi sesuai cara perusahaan ingin menjelaskan organisasinya.',
                GitBranch,
            ),
            F(
                'Perencanaan struktur masa depan',
                'Buat gambaran struktur yang direncanakan untuk membantu komunikasi perubahan dan pertumbuhan organisasi.',
                TrendingUp,
            ),
            F(
                'Kemudahan pengelolaan dan pembaruan struktur',
                'Buat atau perbarui posisi dan hubungan kerja dengan cepat melalui pengelolaan struktur yang intuitif.',
                ClipboardCheck,
            ),
            F(
                'Pembuatan konten berbasis AI',
                'Bantu menyusun penjelasan unit, peran, dan perubahan struktur dengan konten yang lebih cepat dan konsisten.',
                Sparkles,
            ),
            F(
                'Pengumuman internal terpusat',
                'Sampaikan perubahan struktur dan informasi penting kepada kelompok karyawan yang relevan.',
                Megaphone,
            ),
            F(
                'Transparansi kebijakan perusahaan',
                'Hubungkan struktur dengan penjelasan kebijakan agar karyawan memahami konteks peran dan tanggung jawabnya.',
                ShieldAlert,
            ),
        ],
        steps: [
            'Susun unit dan posisi organisasi',
            'Tampilkan hierarki dan hubungan kerja',
            'Siapkan rencana struktur masa depan',
            'Buat penjelasan dengan bantuan AI',
            'Bagikan perubahan dan kebijakan secara terpusat',
        ],
        benefits: [
            'Struktur organisasi lebih mudah dipahami semua pihak.',
            'Hubungan kerja lintas unit dapat dijelaskan dengan konteks.',
            'Perubahan organisasi lebih mudah dikomunikasikan.',
            'Karyawan mendapat informasi kebijakan dari sumber yang jelas.',
        ],
        audience: [
            'Perusahaan dengan banyak unit atau cabang',
            'Organisasi yang sedang bertumbuh dan berubah',
            'Tim HR dan internal communication',
            'Manajemen yang ingin menyampaikan struktur secara transparan',
        ],
        faqs: [
            {
                q: 'Apa yang dapat ditampilkan dalam struktur organisasi?',
                a: 'Anda dapat menjelaskan unit, posisi, atasan, anggota tim, dan hubungan kerja sesuai kebutuhan informasi perusahaan.',
            },
            {
                q: 'Apakah diagram dapat menjelaskan hubungan lintas fungsi?',
                a: 'Bisa. Diagram dapat digunakan untuk memperjelas relasi pelaporan maupun kolaborasi antar unit.',
            },
            {
                q: 'Apakah struktur masa depan dapat ikut dijelaskan?',
                a: 'Bisa. Rencana perubahan struktur dapat disiapkan sebagai bagian dari komunikasi dan perencanaan organisasi.',
            },
            {
                q: 'Bagaimana AI digunakan pada fitur ini?',
                a: 'AI membantu menyusun draf penjelasan tentang unit, peran, dan perubahan struktur agar konten lebih cepat dibuat.',
            },
            {
                q: 'Apakah kebijakan dan pengumuman dapat disampaikan bersama?',
                a: 'Bisa. Informasi perubahan struktur, pengumuman internal, dan kebijakan dapat diarahkan ke kelompok karyawan yang relevan.',
            },
        ],
        related: ['core-hr', 'data-karyawan', 'pengumuman-karyawan', 'ai-hr'],
    },
    'administrasi-karier': {
        name: 'Administrasi Karier',
        category: 'HR & Karyawan',
        asset: 'core-hr.png',
        title: 'Kelola Setiap Transisi Karier dengan Riwayat yang Lengkap',
        description:
            'Catat perjalanan karier karyawan dari rekrutmen, mutasi, promosi, penghargaan, hingga offboarding dalam satu informasi yang terstruktur.',
        heroNote: 'Perubahan peran dan perjalanan karier tetap terlacak.',
        problems: [
            'Riwayat mutasi dan promosi tersebar di banyak dokumen.',
            'Perubahan jabatan dan kompensasi sulit dibandingkan.',
            'Transisi karier massal rawan kesalahan saat dilakukan manual.',
            'Proses offboarding memiliki banyak langkah yang mudah terlewat.',
        ],
        features: [
            F(
                'Administrasi karier',
                'Catat rekrutmen, mutasi, promosi, perubahan peran, jabatan, dan kompensasi melalui alur langsung atau persetujuan, lalu bandingkan perubahan dalam tampilan before–after.',
                TrendingUp,
            ),
            F(
                'Transisi karier massal',
                'Kelola rotasi, pemutusan kerja, atau pergerakan karyawan dalam jumlah besar menggunakan template standar.',
                Users,
            ),
            F(
                'Penghargaan & disiplin',
                'Simpan pencapaian dan tindakan disipliner sebagai bagian dari riwayat karyawan yang terintegrasi.',
                ShieldAlert,
            ),
            F(
                'Offboarding karyawan',
                'Gunakan checklist terstruktur untuk memeriksa pinjaman, biaya, cuti, perjanjian kerja, aset, dan payroll terakhir.',
                ClipboardCheck,
            ),
            F(
                'Reporting & analitik karier',
                'Analisis tren perpindahan, promosi, dan perjalanan karier untuk mendukung perencanaan SDM.',
                BarChart3,
            ),
        ],
        steps: [
            'Catat peristiwa karier karyawan',
            'Tinjau perubahan before–after',
            'Ajukan persetujuan bila diperlukan',
            'Kelola transisi massal dengan template',
            'Gunakan riwayat untuk laporan dan analitik',
        ],
        benefits: [
            'Riwayat karier karyawan lebih lengkap dan mudah dirujuk.',
            'Perubahan jabatan dan kompensasi lebih transparan.',
            'Transisi massal lebih cepat diverifikasi.',
            'Offboarding berjalan terstruktur sampai payroll terakhir.',
        ],
        audience: [
            'Perusahaan dengan banyak mutasi dan promosi',
            'Tim HR yang mengelola perubahan karyawan secara massal',
            'Organisasi yang membutuhkan proses offboarding terkontrol',
            'Manajemen yang membutuhkan insight pergerakan karier',
        ],
        faqs: [
            {
                q: 'Peristiwa karier apa saja yang dapat dicatat?',
                a: 'Rekrutmen, mutasi, promosi, perubahan peran, jabatan, kompensasi, penghargaan, disiplin, dan offboarding dapat dijelaskan dalam riwayat karier.',
            },
            {
                q: 'Apakah perubahan dapat melalui persetujuan?',
                a: 'Bisa. Perubahan dapat mengikuti alur persetujuan sesuai kebijakan perusahaan.',
            },
            {
                q: 'Apakah transisi massal memakai template?',
                a: 'Bisa. Template standar membantu HR mengelola pergerakan karyawan sekaligus dan meninjau potensi kesalahan.',
            },
            {
                q: 'Apa saja yang diperiksa saat offboarding?',
                a: 'Checklist dapat mencakup pinjaman, biaya, saldo cuti, perjanjian kerja, pengembalian aset, dan persiapan payroll terakhir.',
            },
            {
                q: 'Apakah data karier dapat dianalisis?',
                a: 'Bisa. Riwayat perubahan dapat digunakan untuk melihat tren karier dan mendukung perencanaan SDM.',
            },
        ],
        related: [
            'core-hr',
            'data-karyawan',
            'manajemen-kinerja',
            'hr-analytics',
        ],
    },
    'cuti-dan-izin': {
        name: 'Leave & Cuti',
        category: 'HR & Karyawan',
        asset: 'leave-cuti.png',
        title: 'Kelola Cuti dan Izin Tanpa Rekap Manual',
        description:
            'Buat pengajuan lebih mudah, approval lebih jelas, dan saldo cuti selalu mengikuti aktivitas karyawan.',
        heroNote: 'Dari pengajuan sampai saldo, semua tercatat.',
        problems: [
            'Saldo cuti dihitung di spreadsheet.',
            'Approval tercecer di chat.',
            'HR sulit melihat cuti satu tim.',
            'Data cuti tidak sinkron dengan absensi.',
        ],
        features: [
            F(
                'Pengajuan cuti & izin',
                'Karyawan mengajukan dari satu alur yang mudah dipahami.',
                ClipboardCheck,
            ),
            F(
                'Approval atasan',
                'Atasan dapat meninjau dan memberi keputusan dengan konteks yang lengkap.',
                UserCheck,
            ),
            F(
                'Saldo otomatis',
                'Saldo diperbarui mengikuti kebijakan dan pengajuan yang disetujui.',
                WalletCards,
            ),
            F(
                'Kalender cuti',
                'Lihat ketersediaan tim dan rencana cuti dalam satu tampilan.',
                CalendarDays,
            ),
            F(
                'Kebijakan cuti',
                'Atur jenis cuti dan aturan yang berbeda sesuai kebutuhan perusahaan.',
                FileCheck2,
            ),
            F(
                'Riwayat pengajuan',
                'Temukan status dan histori cuti tanpa mencari-cari pesan lama.',
                FileText,
            ),
        ],
        steps: [
            'Karyawan mengajukan',
            'Atasan meninjau',
            'Approval diberikan',
            'Saldo diperbarui',
            'Attendance & Payroll tersinkron',
        ],
        benefits: [
            'Approval lebih cepat dan transparan.',
            'Saldo cuti mudah dipantau.',
            'Kalender tim membantu perencanaan kerja.',
            'Mengurangi koreksi dan rekap manual HR.',
        ],
        audience: [
            'Perusahaan dengan approval berjenjang',
            'Tim shift dan operasional',
            'Perusahaan multi cabang',
            'HR yang ingin mengurangi spreadsheet',
        ],
        faqs: [
            {
                q: 'Apakah saldo cuti dihitung otomatis?',
                a: 'Saldo mengikuti pengajuan yang disetujui dan kebijakan yang ditetapkan perusahaan.',
            },
            {
                q: 'Bisa approval berjenjang?',
                a: 'Bisa. Alur approval dapat disesuaikan dengan struktur atasan di perusahaan.',
            },
            {
                q: 'Bisa membuat jenis cuti berbeda?',
                a: 'Bisa, termasuk cuti tahunan, sakit, khusus, dan kebijakan internal lainnya.',
            },
            {
                q: 'Apakah terhubung dengan Attendance?',
                a: 'Ya. Pengajuan yang disetujui dapat menjadi konteks pada rekap kehadiran.',
            },
            {
                q: 'Bisa melihat cuti satu tim?',
                a: 'Bisa melalui kalender cuti dan filter tim atau departemen.',
            },
        ],
        related: [
            'core-hr',
            'absensi-karyawan',
            'payroll',
            'kalender-perusahaan',
        ],
    },
    rekrutmen: {
        name: 'Recruitment',
        category: 'HR & Karyawan',
        asset: 'feature-assets/dasbor_rekrutmen_avanahr_modern.png',
        title: 'Bangun Proses Recruitment dari Lowongan sampai Hiring',
        description:
            'Kelola kebutuhan posisi, pipeline kandidat, interview, dan keputusan hiring dalam satu alur yang mudah dipantau.',
        heroNote: 'Setiap kandidat punya tahap dan langkah berikutnya.',
        problems: [
            'Status kandidat tersimpan di banyak spreadsheet.',
            'Interview dan assessment sulit dijadwalkan.',
            'Hiring manager tidak punya visibilitas pipeline.',
            'Data kandidat yang diterima harus diinput ulang.',
        ],
        features: [
            F(
                'Plan with Confidence',
                'Analisis kemampuan workforce untuk memproyeksikan kebutuhan tenaga kerja dan keterampilan di masa depan.',
                BriefcaseBusiness,
            ),
            F(
                'Proses Rekrutmen dan Seleksi',
                'Lacak detail kandidat dan proses seleksi secara terstruktur untuk kualitas perekrutan yang lebih baik.',
                Megaphone,
            ),
            F(
                'Interaksi dengan Pelamar',
                'Automatiskan komunikasi dan kolaborasi untuk menciptakan pengalaman pelamar yang lebih baik.',
                GitBranch,
            ),
            F(
                'Proses Rekrutmen Online',
                'Arahkan kandidat ke portal pekerjaan dengan data pelamar yang lengkap dan tertata.',
                CalendarDays,
            ),
            F(
                'CV Reader Berbasis AI',
                'Ekstrak informasi CV, lalu cocokkan kandidat dengan kriteria pre-screening yang telah ditentukan.',
                Sparkles,
            ),
            F(
                'Video Interview',
                'Lakukan wawancara video dengan pertanyaan terstruktur yang mudah dibagikan kepada reviewer.',
                Mic2,
            ),
            F(
                'Pertanyaan Penilaian Otomatis',
                'Gunakan kriteria yang konsisten untuk membantu menyaring dan menilai kandidat lebih cepat.',
                ClipboardCheck,
            ),
        ],
        steps: [
            'Buat kebutuhan posisi',
            'Publikasikan lowongan',
            'Kandidat masuk',
            'Interview & assessment',
            'Hiring',
        ],
        benefits: [
            'Pipeline kandidat lebih terukur.',
            'Kolaborasi HR dan hiring manager lebih rapi.',
            'Riwayat kandidat tidak tercecer.',
            'Mengurangi input ulang saat kandidat diterima.',
        ],
        audience: [
            'Perusahaan yang rutin hiring',
            'Tim HR dan talent acquisition',
            'Perusahaan dengan banyak posisi terbuka',
            'Hiring manager lintas departemen',
        ],
        faqs: [
            {
                q: 'Apakah bisa mengelola banyak lowongan?',
                a: 'Bisa. Setiap lowongan memiliki detail, status, dan pipeline kandidatnya sendiri.',
            },
            {
                q: 'Apakah kandidat bisa dipindah antar tahap?',
                a: 'Bisa, sekaligus menyimpan status dan histori prosesnya.',
            },
            {
                q: 'Apakah mendukung assessment?',
                a: 'Ya. Catatan assessment dapat disimpan sebagai bagian dari proses seleksi.',
            },
            {
                q: 'Bisa tracking status kandidat?',
                a: 'Bisa melalui pipeline dan status kandidat yang terlihat oleh tim berwenang.',
            },
            {
                q: 'Apakah hasil hiring masuk ke Core HR?',
                a: 'Ya. Kandidat yang diterima dapat dilanjutkan ke proses onboarding dan data karyawan.',
            },
        ],
        related: ['core-hr', 'manajemen-kinerja', 'ai-hr', 'hr-analytics'],
    },
    'manajemen-kinerja': {
        name: 'Performance',
        category: 'HR & Karyawan',
        asset: 'feature-assets/dasbor_analitik_kinerja_avanahr.png',
        title: 'Jadikan Performance Review Lebih Terarah',
        description:
            'Hubungkan target, progres, self assessment, review atasan, dan histori performa dalam satu ruang kerja.',
        heroNote:
            'Target yang jelas membuat percakapan kinerja lebih bermakna.',
        problems: [
            'Target karyawan tidak terdokumentasi rapi.',
            'Review hanya dilakukan menjelang penilaian.',
            'Self assessment dan penilaian atasan terpisah.',
            'Histori performa sulit dibandingkan.',
        ],
        features: [
            F(
                'KPI & OKR',
                'Tetapkan indikator dan tujuan yang bisa dipantau per periode.',
                Target,
            ),
            F(
                'Target karyawan',
                'Buat ekspektasi kerja yang jelas untuk setiap peran.',
                Star,
            ),
            F(
                'Self assessment',
                'Beri ruang karyawan merefleksikan progres dan pencapaiannya.',
                UserCheck,
            ),
            F(
                'Review atasan',
                'Atasan memberi penilaian dengan konteks progres yang terlihat.',
                ClipboardCheck,
            ),
            F(
                'Performance dashboard',
                'Baca status target dan hasil review dalam ringkasan visual.',
                BarChart3,
            ),
            F(
                'Riwayat penilaian',
                'Simpan catatan performa untuk percakapan karier berikutnya.',
                TrendingUp,
            ),
        ],
        steps: [
            'Set target',
            'Karyawan mengisi progres',
            'Atasan meninjau',
            'Nilai final',
            'Analisis performa',
        ],
        benefits: [
            'Percakapan kinerja berbasis data.',
            'Target lebih mudah dipantau.',
            'Review periodik tidak lagi reaktif.',
            'Histori mendukung keputusan karier.',
        ],
        audience: [
            'Perusahaan berbasis target',
            'Tim dengan review periodik',
            'Organisasi yang memakai KPI atau OKR',
            'HR yang membangun budaya feedback',
        ],
        faqs: [
            {
                q: 'Apakah mendukung KPI dan OKR?',
                a: 'Ya. Perusahaan dapat memilih pendekatan target yang sesuai dengan cara kerja timnya.',
            },
            {
                q: 'Bisa penilaian per periode?',
                a: 'Bisa. Periode review dapat diatur sesuai siklus perusahaan.',
            },
            {
                q: 'Bisa self assessment?',
                a: 'Bisa. Karyawan dapat mengisi progres sebelum atasan melakukan review.',
            },
            {
                q: 'Bisa approval manager?',
                a: 'Bisa melalui alur review dan validasi sesuai struktur organisasi.',
            },
            {
                q: 'Apakah tersedia histori performa?',
                a: 'Ya. Riwayat penilaian tersimpan untuk mendukung evaluasi dan pengembangan karier.',
            },
        ],
        related: ['core-hr', 'rekrutmen', 'hr-analytics', 'ai-hr'],
    },
    payroll: {
        name: 'Payroll',
        category: 'Payroll & Bisnis',
        asset: 'feature-assets/ilustrasi_dashboard_payroll_avanahr.png',
        title: 'Hitung Payroll Lebih Terstruktur, Akurat, dan Siap Dibayar',
        description:
            'Hubungkan attendance, lembur, komponen gaji, pajak, BPJS, approval, dan slip gaji dalam satu proses payroll.',
        heroNote:
            'Dari data kehadiran sampai slip gaji, satu alur yang bisa ditelusuri.',
        problems: [
            'Rekap komponen gaji membutuhkan waktu lama.',
            'Perhitungan lembur dan potongan rawan terlewat.',
            'Koreksi payroll sulit dilacak.',
            'Slip gaji masih dibagikan satu per satu.',
        ],
        features: [
            F(
                'Manajemen Komponen',
                'Gunakan komponen tak terbatas untuk menghitung tunjangan, potongan, dan komponen netral. Atur pinjaman, asuransi, berbagai skema lembur dan biaya, pajak lintas yurisdiksi, serta pembayaran multi-mata uang untuk mendukung perhitungan payroll yang kompleks.',
                WalletCards,
            ),
            F(
                'Proses Penggajian',
                'Sederhanakan pengolahan penggajian melalui komponen gaji yang fleksibel dan perhitungan otomatis untuk mendukung perencanaan kompensasi yang kompleks.',
                ReceiptText,
            ),
            F(
                'Info Payroll & Slip Gaji',
                'Sediakan portal penggajian mandiri yang dapat diakses 24/7 agar karyawan dapat melihat informasi payroll, rincian komponen upah, dan slip gajinya.',
                FileText,
            ),
            F(
                'Analytics & Reporting',
                'Gunakan statistik singkat di halaman, visualisasi dashboard, laporan standar, feed informasi, dan pengingat berbasis AI untuk mendukung keputusan payroll yang tepat waktu dan informatif.',
                BarChart3,
            ),
            F(
                'Panduan Payroll',
                'Ikuti panduan dalam satu tampilan terpusat untuk menjalankan siklus payroll, mulai dari memilih periode pembayaran dan memperbarui data hingga memproses absensi, pinjaman, dan pengeluaran. Sistem mencantumkan setiap langkah wajib dan melacak penyelesaiannya.',
                ListChecks,
            ),
        ],
        steps: [
            'Data attendance masuk',
            'Validasi',
            'Perhitungan payroll',
            'Approval',
            'Slip gaji',
        ],
        benefits: [
            'Mengurangi rekap manual.',
            'Perhitungan lebih konsisten.',
            'Payroll memiliki jejak review.',
            'Karyawan menerima slip lebih cepat.',
        ],
        audience: [
            'Perusahaan multi cabang',
            'Tim payroll dengan banyak komponen',
            'Perusahaan dengan shift dan lembur',
            'HR yang membutuhkan approval terkontrol',
        ],
        faqs: [
            {
                q: 'Apakah mendukung PPh 21?',
                a: 'Ya. Payroll menyediakan ruang untuk komponen pajak sesuai konfigurasi perusahaan.',
            },
            {
                q: 'Apakah payroll terhubung Attendance?',
                a: 'Ya. Data kehadiran, lembur, dan potongan dapat menjadi input payroll.',
            },
            {
                q: 'Apakah mendukung BPJS?',
                a: 'Ya. Komponen BPJS dapat dimasukkan sesuai kebutuhan perhitungan.',
            },
            {
                q: 'Bisa membuat komponen gaji custom?',
                a: 'Bisa. Komponen dapat disesuaikan dengan kebijakan perusahaan.',
            },
            {
                q: 'Bisa export payroll ke Excel?',
                a: 'Bisa. Hasil payroll dapat diekspor untuk kebutuhan operasional yang tersedia.',
            },
        ],
        related: [
            'absensi-karyawan',
            'cuti-dan-izin',
            'core-hr',
            'hr-analytics',
        ],
    },
    reimbursement: {
        name: 'Settlement',
        category: 'Payroll & Bisnis',
        asset: 'feature-assets/dasbor_avanahr_untuk_pengelolaan_klaim.png',
        title: 'Kelola Reimbursement dan Perjalanan Dinas dengan Bukti yang Jelas',
        description:
            'Buat pengajuan klaim lebih teratur dari upload bukti, validasi, approval, sampai settlement.',
        heroNote: 'Setiap klaim punya bukti, status, dan pemilik proses.',
        problems: [
            'Bukti transaksi tercecer di chat.',
            'Status reimbursement sulit ditanyakan.',
            'Approval klaim berjalan tanpa konteks.',
            'Laporan klaim harus dirangkum ulang.',
        ],
        features: [
            F(
                'Perencanaan Perjalanan Berbasis Anggaran',
                'Kelola perjalanan dinas dari anggaran, pengajuan, approval, uang muka, hingga rekonsiliasi akhir.',
                BriefcaseBusiness,
            ),
            F(
                'Layanan Mandiri untuk Uang Muka Perjalanan',
                'Karyawan mengajukan uang muka dan melaporkan pengeluaran aktual untuk rekonsiliasi otomatis.',
                ReceiptText,
            ),
            F(
                'Upload bukti',
                'Lampirkan bukti transaksi di pengajuan yang sama.',
                FileCheck2,
            ),
            F(
                'Approval berjenjang',
                'Teruskan klaim kepada pihak yang sesuai dengan kebijakan.',
                GitBranch,
            ),
            F(
                'Status klaim',
                'Karyawan dan HR dapat mengikuti progres pengajuan.',
                ListChecks,
            ),
            F(
                'Reporting & histori',
                'Gunakan histori untuk audit dan analisis pengeluaran.',
                BarChart3,
            ),
        ],
        steps: [
            'Karyawan mengirim klaim',
            'Upload bukti',
            'Validasi',
            'Approval',
            'Settlement',
        ],
        benefits: [
            'Bukti transaksi lebih mudah dilacak.',
            'Status klaim transparan.',
            'Approval punya konteks yang cukup.',
            'Pelaporan pengeluaran lebih siap.',
        ],
        audience: [
            'Tim dengan perjalanan dinas',
            'Perusahaan multi cabang',
            'Sales dan field team',
            'Finance-HR dengan approval berlapis',
        ],
        faqs: [
            {
                q: 'Bisa upload bukti transaksi?',
                a: 'Bisa. Bukti dapat dilampirkan langsung pada pengajuan klaim.',
            },
            {
                q: 'Bisa approval berjenjang?',
                a: 'Bisa, sesuai struktur dan kebijakan approval perusahaan.',
            },
            {
                q: 'Bisa melihat histori klaim?',
                a: 'Bisa. Pengguna dapat melihat status dan riwayat klaim yang relevan.',
            },
            {
                q: 'Apakah ada batas nominal?',
                a: 'Batas nominal dapat dijadikan bagian dari kebijakan internal yang diterapkan.',
            },
            {
                q: 'Bisa export laporan?',
                a: 'Bisa melalui laporan settlement untuk kebutuhan administrasi dan rekonsiliasi.',
            },
        ],
        related: [
            'payroll',
            'absensi-karyawan',
            'kunjungan-karyawan',
            'core-hr',
        ],
    },
    crm: {
        name: 'CRM',
        category: 'Solusi Bisnis',
        asset: 'crm.png',
        title: 'Jaga Pipeline Sales dan Histori Client Tetap Terhubung',
        description:
            'Kelola lead, follow up, opportunity, dan aktivitas sales dalam satu ruang kerja yang mudah dipantau.',
        heroNote:
            'Dari lead masuk sampai histori client, tidak ada follow up yang hilang.',
        problems: [
            'Histori client berada di akun personal.',
            'Follow up tidak memiliki pengingat jelas.',
            'Pipeline sulit dibaca lintas tim.',
            'Progres deal tidak punya satu sumber data.',
        ],
        features: [
            F(
                'Data client',
                'Simpan informasi client dan kontak dalam satu direktori.',
                ContactRound,
            ),
            F(
                'Sales pipeline',
                'Lihat posisi setiap opportunity dan langkah berikutnya.',
                GitBranch,
            ),
            F(
                'Lead management',
                'Kelola lead baru agar tidak berhenti tanpa tindak lanjut.',
                UserPlus,
            ),
            F(
                'Follow up',
                'Catat aktivitas dan rencana follow up tim sales.',
                ListChecks,
            ),
            F(
                'Opportunity & deal',
                'Pantau peluang, nilai, dan status deal secara lebih rapi.',
                TrendingUp,
            ),
            F(
                'Reporting',
                'Baca performa pipeline untuk membantu prioritas tim.',
                BarChart3,
            ),
        ],
        steps: [
            'Lead masuk',
            'Follow up',
            'Opportunity',
            'Deal',
            'Riwayat client',
        ],
        benefits: [
            'Histori client tidak bergantung pada satu orang.',
            'Pipeline lebih mudah diprioritaskan.',
            'Follow up tim lebih konsisten.',
            'Manajemen mendapat ringkasan progres deal.',
        ],
        audience: [
            'Tim sales',
            'Perusahaan jasa dan B2B',
            'Tim account management',
            'Bisnis dengan proses follow up berulang',
        ],
        faqs: [
            {
                q: 'Bisa mengelola pipeline sales?',
                a: 'Bisa. Lead dan opportunity dapat dikelola berdasarkan tahap proses sales.',
            },
            {
                q: 'Bisa menyimpan histori client?',
                a: 'Bisa. Aktivitas dan interaksi penting dapat dicatat pada profil client.',
            },
            {
                q: 'Bisa assign client ke sales?',
                a: 'Bisa, sehingga pemilik tindak lanjut terlihat jelas.',
            },
            {
                q: 'Bisa melihat progres deal?',
                a: 'Bisa melalui pipeline dan status opportunity yang tersedia.',
            },
            {
                q: 'Apakah tersedia report?',
                a: 'Ya. CRM menyediakan ringkasan aktivitas dan pipeline untuk kebutuhan monitoring.',
            },
        ],
        related: ['reimbursement', 'hr-analytics', 'ai-hr', 'core-hr'],
    },
    'absensi-karyawan': {
        name: 'Attendance',
        category: 'Attendance & Lapangan',
        asset: 'attendance.png',
        title: 'Absensi Karyawan dengan GPS, Shift, dan Roster yang Lebih Siap',
        description:
            'Catat kehadiran dari lokasi kerja, kelola jadwal, dan siapkan data attendance untuk payroll.',
        heroNote: 'Kehadiran tercatat di tempat dan waktu yang tepat.',
        problems: [
            'Absensi sulit diverifikasi di lapangan.',
            'Jadwal shift masih dibuat manual.',
            'Koreksi kehadiran menumpuk di akhir bulan.',
            'Data attendance belum siap untuk payroll.',
        ],
        features: [
            F(
                'Check-in & check-out',
                'Catat jam masuk dan pulang dari alur yang mudah digunakan.',
                ScanFace,
            ),
            F(
                'GPS & selfie',
                'Tambahkan konteks lokasi dan verifikasi saat absensi.',
                MapPin,
            ),
            F(
                'Face recognition',
                'Gunakan verifikasi wajah untuk membantu memastikan identitas.',
                ScanFace,
            ),
            F(
                'Shift & roster',
                'Susun jadwal dan roster agar tim tahu waktu kerja masing-masing.',
                CalendarDays,
            ),
            F(
                'Lateness & overtime',
                'Tandai keterlambatan dan lembur sebagai bagian dari rekap.',
                Clock3,
            ),
            F(
                'Attendance report',
                'Baca rekap kehadiran untuk HR dan kebutuhan payroll.',
                BarChart3,
            ),
        ],
        steps: [
            'Karyawan check-in',
            'Lokasi tervalidasi',
            'Attendance tercatat',
            'HR memonitor',
            'Data masuk payroll',
        ],
        benefits: [
            'Kehadiran lebih mudah diverifikasi.',
            'Roster dan shift lebih rapi.',
            'Koreksi lebih terkontrol.',
            'Data siap diteruskan ke payroll.',
        ],
        audience: [
            'Tim lapangan',
            'Perusahaan dengan shift',
            'Retail dan operasional',
            'Perusahaan multi lokasi',
        ],
        faqs: [
            {
                q: 'Apakah absensi menggunakan GPS?',
                a: 'Ya. GPS dapat digunakan untuk memberi konteks lokasi pada proses absensi.',
            },
            {
                q: 'Bisa menggunakan selfie?',
                a: 'Bisa, sesuai konfigurasi dan kebutuhan verifikasi perusahaan.',
            },
            {
                q: 'Mendukung shift?',
                a: 'Ya. Shift dan roster dapat disiapkan untuk kebutuhan tim operasional.',
            },
            {
                q: 'Bisa mengatur roster?',
                a: 'Bisa. HR atau admin dapat menyusun jadwal berdasarkan tim dan periode.',
            },
            {
                q: 'Apakah terhubung Payroll?',
                a: 'Ya. Data kehadiran dan lembur dapat digunakan sebagai input payroll.',
            },
        ],
        related: [
            'live-tracking-karyawan',
            'kunjungan-karyawan',
            'cuti-dan-izin',
            'payroll',
        ],
    },
    'live-tracking-karyawan': {
        name: 'Live Tracking',
        category: 'Attendance & Lapangan',
        asset: 'live-tracking.png',
        title: 'Pantau Aktivitas Tim Lapangan dengan Live Tracking',
        description:
            'Lihat lokasi, perjalanan, dan aktivitas kerja lapangan dari satu peta yang bisa dipantau HR atau manager.',
        heroNote: 'Visibilitas lapangan tanpa menambah laporan manual.',
        problems: [
            'Posisi tim hanya diketahui lewat chat.',
            'Riwayat perjalanan tidak terdokumentasi.',
            'Manager sulit memastikan kunjungan berjalan.',
            'Laporan lapangan dibuat setelah pekerjaan selesai.',
        ],
        features: [
            F(
                'Lokasi real-time',
                'Pantau posisi karyawan yang sedang aktif di lapangan.',
                MapPin,
            ),
            F(
                'Riwayat perjalanan',
                'Telusuri perjalanan untuk memahami aktivitas dan rute kerja.',
                GitBranch,
            ),
            F(
                'Tracking per tim',
                'Filter karyawan berdasarkan tim, cabang, atau kebutuhan monitoring.',
                Users,
            ),
            F(
                'Last location',
                'Tetap dapatkan konteks lokasi terakhir saat karyawan tidak aktif.',
                Landmark,
            ),
            F(
                'Timeline aktivitas',
                'Baca urutan aktivitas lapangan dengan konteks waktunya.',
                ListChecks,
            ),
            F(
                'Monitoring kunjungan',
                'Hubungkan tracking dengan task dan kunjungan kerja.',
                BriefcaseBusiness,
            ),
        ],
        steps: [
            'Karyawan aktif di lapangan',
            'Lokasi dikirim',
            'HR memantau peta',
            'Riwayat tersimpan',
        ],
        benefits: [
            'Manager punya visibilitas kerja lapangan.',
            'Riwayat perjalanan lebih mudah ditelusuri.',
            'Monitoring tidak bergantung pada update chat.',
            'Konteks kunjungan lebih lengkap.',
        ],
        audience: [
            'Sales dan field force',
            'Tim teknisi',
            'Perusahaan multi cabang',
            'Operasional dengan banyak kunjungan',
        ],
        faqs: [
            {
                q: 'Apakah lokasi real-time?',
                a: 'Lokasi dapat dipantau saat karyawan aktif dan mengirimkan pembaruan lokasi.',
            },
            {
                q: 'Bisa melihat histori perjalanan?',
                a: 'Bisa, selama riwayat tersedia sesuai kebijakan penyimpanan perusahaan.',
            },
            {
                q: 'Bisa filter per tim?',
                a: 'Bisa. Monitoring dapat difokuskan berdasarkan karyawan atau tim.',
            },
            {
                q: 'Apakah hanya HR yang bisa melihat?',
                a: 'Akses mengikuti role dan permission yang diberikan perusahaan.',
            },
            {
                q: 'Apakah tracking berjalan di mobile?',
                a: 'Ya. Fitur ini dirancang untuk mendukung aktivitas karyawan di lapangan melalui perangkat mobile.',
            },
        ],
        related: [
            'absensi-karyawan',
            'kunjungan-karyawan',
            'reimbursement',
            'hr-analytics',
        ],
    },
    'kunjungan-karyawan': {
        name: 'Visiting Pekerjaan',
        category: 'Attendance & Lapangan',
        asset: 'visiting-pekerjaan.png',
        title: 'Atur Tasklist dan Kunjungan Kerja Lapangan',
        description:
            'Assign tugas, validasi kunjungan, kumpulkan bukti, dan pantau penyelesaiannya dalam satu alur kerja.',
        heroNote:
            'Task lapangan bergerak dari assignment sampai bukti selesai.',
        problems: [
            'Tugas kunjungan dibagikan lewat chat.',
            'Bukti pekerjaan tidak terkumpul rapi.',
            'Task belum selesai sulit dipantau.',
            'Manager tidak punya laporan kunjungan yang konsisten.',
        ],
        features: [
            F(
                'Daftar kunjungan',
                'Kelola seluruh pekerjaan lapangan dalam satu daftar.',
                ListChecks,
            ),
            F(
                'Assignment',
                'Tetapkan pemilik tugas dan detail kunjungan dengan jelas.',
                UserCheck,
            ),
            F(
                'Check-in lokasi',
                'Gunakan lokasi sebagai konteks saat tugas dimulai.',
                MapPin,
            ),
            F(
                'Bukti kunjungan',
                'Lampirkan foto, catatan, atau bukti pekerjaan di task.',
                FileCheck2,
            ),
            F(
                'Status task',
                'Pantau task baru, berjalan, tertunda, dan selesai.',
                ClipboardCheck,
            ),
            F(
                'Reporting',
                'Baca hasil kunjungan untuk evaluasi operasional.',
                BarChart3,
            ),
        ],
        steps: [
            'Manager membuat task',
            'Karyawan melakukan kunjungan',
            'Check-in',
            'Upload bukti',
            'Task selesai',
        ],
        benefits: [
            'Assignment lebih jelas.',
            'Bukti pekerjaan tersimpan bersama task.',
            'Task tertunda lebih mudah ditemukan.',
            'Laporan operasional lebih siap.',
        ],
        audience: [
            'Tim sales lapangan',
            'Teknisi dan maintenance',
            'Supervisor operasional',
            'Bisnis dengan kunjungan berulang',
        ],
        faqs: [
            {
                q: 'Bisa assign tugas ke karyawan?',
                a: 'Bisa. Setiap task memiliki pemilik dan detail pekerjaan yang jelas.',
            },
            {
                q: 'Bisa check-in berdasarkan lokasi?',
                a: 'Bisa, sehingga kunjungan memiliki konteks lokasi yang lebih kuat.',
            },
            {
                q: 'Bisa upload bukti kunjungan?',
                a: 'Bisa melalui lampiran dan catatan pada task.',
            },
            {
                q: 'Bisa melihat task belum selesai?',
                a: 'Bisa melalui filter status task dan monitoring pekerjaan.',
            },
            {
                q: 'Apakah terhubung Live Tracking?',
                a: 'Ya. Visiting Pekerjaan dapat melengkapi konteks aktivitas lapangan bersama Live Tracking.',
            },
        ],
        related: [
            'live-tracking-karyawan',
            'absensi-karyawan',
            'reimbursement',
            'core-hr',
        ],
    },
    'ai-hr': {
        name: 'AI Intelligence',
        category: 'AI & Analytics',
        asset: 'ai-intelligence.png',
        title: 'Bantu HR Menemukan Jawaban dan Insight Lebih Cepat dengan AI',
        description:
            'Ajukan pertanyaan tentang data HR, cari SOP, dan dapatkan ringkasan berdasarkan informasi yang memang boleh diakses.',
        heroNote: 'AI membantu HR membaca data tanpa kehilangan kontrol akses.',
        problems: [
            'HR menghabiskan waktu mencari data.',
            'SOP tersebar di banyak dokumen.',
            'Ringkasan harus dibuat manual.',
            'Insight terlambat karena data sulit dibaca.',
        ],
        features: [
            F(
                'Tanya data HR',
                'Ajukan pertanyaan dengan bahasa yang lebih natural.',
                MessageSquareText,
            ),
            F(
                'Ringkasan otomatis',
                'Ubah data dan dokumen panjang menjadi ringkasan yang lebih mudah dibaca.',
                FileText,
            ),
            F(
                'Insight otomatis',
                'Temukan pola dan hal yang perlu diperhatikan HR.',
                Sparkles,
            ),
            F(
                'Bantuan SOP',
                'Gunakan SOP internal sebagai konteks jawaban.',
                FileCheck2,
            ),
            F(
                'Role-aware',
                'Akses AI mengikuti data dan permission pengguna.',
                LockKeyhole,
            ),
            F(
                'Assistant internal',
                'Bantu pekerjaan HR berulang dalam satu ruang kerja.',
                Headphones,
            ),
        ],
        steps: [
            'User bertanya',
            'AI membaca data yang diizinkan',
            'Sistem menganalisis',
            'Jawaban & insight',
        ],
        benefits: [
            'Pencarian informasi lebih cepat.',
            'SOP lebih mudah ditemukan.',
            'HR mendapat titik awal analisis.',
            'Kontrol akses tetap menjadi fondasi.',
        ],
        audience: [
            'HR generalist',
            'People analytics team',
            'Manajemen yang membutuhkan ringkasan',
            'Perusahaan dengan banyak SOP',
        ],
        faqs: [
            {
                q: 'Data apa yang bisa dibaca AI?',
                a: 'AI hanya memproses data yang tersedia dan diizinkan untuk pengguna sesuai role.',
            },
            {
                q: 'Apakah akses AI mengikuti role?',
                a: 'Ya. Kontrol akses tetap berlaku ketika pengguna berinteraksi dengan AI.',
            },
            {
                q: 'Apakah AI bisa membaca SOP?',
                a: 'Bisa, jika SOP tersebut dimasukkan ke sumber informasi yang tersedia untuk assistant.',
            },
            {
                q: 'Apakah data perusahaan aman?',
                a: 'Data tetap mengikuti kontrol akses dan kebijakan keamanan aplikasi.',
            },
            {
                q: 'Apakah AI dapat memberikan analisis?',
                a: 'AI dapat membantu menyusun ringkasan dan insight; keputusan akhir tetap pada tim perusahaan.',
            },
        ],
        related: [
            'hr-analytics',
            'prediksi-risiko-resign',
            'core-hr',
            'manajemen-kinerja',
        ],
    },
    'hr-analytics': {
        name: 'Workforce Analytics',
        category: 'AI & Analytics',
        asset: 'workforce-analytics.png',
        title: 'Ubah Data Workforce Menjadi Dashboard untuk Keputusan HR',
        description:
            'Satukan headcount, turnover, attendance, payroll cost, dan distribusi workforce dalam satu dashboard eksekutif.',
        heroNote: 'Dari data operasional menjadi konteks untuk keputusan.',
        problems: [
            'Laporan HR dibuat ulang setiap bulan.',
            'Data antar modul sulit dibandingkan.',
            'Manajemen tidak mendapat gambaran cepat.',
            'Filter cabang dan departemen memakan waktu.',
        ],
        features: [
            F(
                'Headcount',
                'Pantau jumlah dan distribusi karyawan berdasarkan struktur organisasi.',
                Users,
            ),
            F(
                'Turnover',
                'Lihat tren keluar-masuk karyawan untuk menemukan perubahan.',
                TrendingUp,
            ),
            F(
                'Attendance trend',
                'Baca pola kehadiran sebagai konteks operasional.',
                BarChart3,
            ),
            F(
                'Payroll cost',
                'Bandingkan biaya payroll dan perubahan workforce.',
                WalletCards,
            ),
            F(
                'Department analytics',
                'Filter data berdasarkan departemen atau cabang.',
                Network,
            ),
            F(
                'Executive dashboard',
                'Berikan ringkasan data yang siap dibaca manajemen.',
                Landmark,
            ),
        ],
        steps: [
            'Data HR terkumpul',
            'Sistem mengolah',
            'Dashboard terupdate',
            'HR menganalisis',
        ],
        benefits: [
            'Laporan lebih cepat disiapkan.',
            'Manajemen mendapat gambaran workforce.',
            'Analisis lintas modul lebih mudah.',
            'Filter membantu menemukan konteks.',
        ],
        audience: [
            'HR manager',
            'People analytics',
            'Manajemen multi cabang',
            'Perusahaan berbasis data',
        ],
        faqs: [
            {
                q: 'Data apa saja yang dianalisis?',
                a: 'Dashboard dapat menggabungkan metrik workforce seperti headcount, turnover, attendance, payroll cost, dan distribusi tim.',
            },
            {
                q: 'Apakah dashboard real-time?',
                a: 'Dashboard mengikuti pembaruan data yang tersedia dari modul terkait.',
            },
            {
                q: 'Bisa filter cabang atau departemen?',
                a: 'Bisa, agar analisis dapat difokuskan pada bagian organisasi tertentu.',
            },
            {
                q: 'Bisa export laporan?',
                a: 'Bisa untuk kebutuhan pelaporan yang tersedia di aplikasi.',
            },
            {
                q: 'Apakah data berasal dari modul lain?',
                a: 'Ya. Analytics dirancang membaca data dari modul HR yang saling terhubung.',
            },
        ],
        related: [
            'core-hr',
            'absensi-karyawan',
            'payroll',
            'prediksi-risiko-resign',
        ],
    },
    'prediksi-risiko-resign': {
        name: 'Prediksi Risiko Resign',
        category: 'AI & Analytics',
        asset: 'prediksi-risiko-resign.png',
        title: 'Temukan Sinyal Risiko Resign Sebelum Menjadi Masalah',
        description:
            'Gunakan attrition scoring dan faktor pendukung untuk membantu HR menentukan prioritas tindak lanjut.',
        heroNote: 'AI memberi sinyal; HR tetap memegang keputusan.',
        problems: [
            'Risiko resign baru terlihat setelah karyawan pergi.',
            'Data turnover sulit dibaca per departemen.',
            'HR tidak tahu karyawan mana yang perlu diperhatikan.',
            'Tindak lanjut belum memiliki prioritas.',
        ],
        features: [
            F(
                'Attrition score',
                'Berikan konteks skor risiko pada daftar karyawan.',
                ShieldAlert,
            ),
            F(
                'Risk indicator',
                'Tandai area yang membutuhkan perhatian lebih dahulu.',
                CircleCheck,
            ),
            F(
                'Analisis faktor',
                'Lihat faktor yang berkontribusi pada sinyal risiko.',
                BarChart3,
            ),
            F(
                'Employee risk list',
                'Fokuskan review pada karyawan yang relevan.',
                Users,
            ),
            F(
                'Department risk',
                'Bandingkan risiko antar departemen atau cabang.',
                Network,
            ),
            F(
                'Insight & alert',
                'Bantu HR menyiapkan tindak lanjut yang lebih awal.',
                Sparkles,
            ),
        ],
        steps: [
            'Data historis',
            'AI menganalisis pola',
            'Skor risiko',
            'HR melakukan review',
            'Tindak lanjut',
        ],
        benefits: [
            'HR dapat bekerja lebih proaktif.',
            'Prioritas review lebih jelas.',
            'Pola turnover lebih mudah dibaca.',
            'Keputusan tetap melalui penilaian manusia.',
        ],
        audience: [
            'HR strategic partner',
            'Perusahaan dengan turnover tinggi',
            'Organisasi besar',
            'Tim yang membangun retention program',
        ],
        faqs: [
            {
                q: 'Bagaimana skor risiko dihitung?',
                a: 'Skor dibuat dari pola data yang tersedia dan konfigurasi analitik; detailnya perlu dibaca sebagai sinyal, bukan kepastian.',
            },
            {
                q: 'Apakah hasil AI merupakan keputusan final?',
                a: 'Tidak. Hasil AI adalah masukan untuk review HR dan bukan keputusan otomatis.',
            },
            {
                q: 'Data apa yang digunakan?',
                a: 'Data yang tersedia dan diizinkan dalam konteks analitik perusahaan.',
            },
            {
                q: 'Bisa filter departemen?',
                a: 'Bisa, sehingga HR dapat melihat pola berdasarkan unit organisasi.',
            },
            {
                q: 'Apakah hasil dapat berubah?',
                a: 'Ya. Hasil dapat berubah saat data dan pola yang dianalisis ikut berubah.',
            },
        ],
        related: ['hr-analytics', 'ai-hr', 'core-hr', 'manajemen-kinerja'],
    },
    'transkrip-rapat-ai': {
        name: 'Rapat & Transkrip',
        category: 'AI & Analytics',
        asset: 'rapat-transkrip.png',
        title: 'Ubah Rekaman Rapat Menjadi Transkrip dan Action Item',
        description:
            'Rekam atau unggah audio, dapatkan transkrip otomatis, lalu simpan ringkasan dan tindak lanjut rapat.',
        heroNote: 'Percakapan rapat tidak berhenti di rekaman.',
        problems: [
            'Catatan rapat tidak lengkap.',
            'Keputusan sulit ditemukan kembali.',
            'Action item hilang setelah meeting.',
            'Rekaman harus diputar ulang untuk membuat ringkasan.',
        ],
        features: [
            F(
                'Rekam rapat',
                'Mulai menangkap percakapan dari sesi rapat yang relevan.',
                Mic2,
            ),
            F(
                'Upload audio',
                'Masukkan file audio yang sudah tersedia untuk diproses.',
                FileText,
            ),
            F(
                'Transkrip otomatis',
                'Ubah percakapan menjadi teks yang bisa ditinjau.',
                FileCheck2,
            ),
            F(
                'Summary',
                'Dapatkan ringkasan agar tim tidak harus membaca semuanya.',
                ListChecks,
            ),
            F(
                'Action item',
                'Simpan tindak lanjut dan pemilik pekerjaan dari rapat.',
                ClipboardCheck,
            ),
            F(
                'Search transcript',
                'Cari isi meeting saat informasi dibutuhkan kembali.',
                MessageSquareText,
            ),
        ],
        steps: [
            'Rekam atau upload audio',
            'AI membuat transkrip',
            'Ringkasan dibuat',
            'Action item tersimpan',
        ],
        benefits: [
            'Waktu membuat notulen berkurang.',
            'Keputusan rapat lebih mudah ditemukan.',
            'Tindak lanjut punya pemilik yang jelas.',
            'Histori meeting lebih berguna.',
        ],
        audience: [
            'Tim hybrid',
            'HR dan people team',
            'Project manager',
            'Organisasi dengan meeting rutin',
        ],
        faqs: [
            {
                q: 'Bisa upload file audio?',
                a: 'Bisa, sesuai format dan batasan file yang didukung aplikasi.',
            },
            {
                q: 'Apakah transkrip otomatis?',
                a: 'Ya. Audio diproses untuk menghasilkan teks yang dapat ditinjau.',
            },
            {
                q: 'Bisa membuat summary?',
                a: 'Bisa. Sistem membantu menyusun ringkasan dari isi meeting.',
            },
            {
                q: 'Bisa mencari isi meeting?',
                a: 'Bisa melalui pencarian pada transkrip yang sudah tersimpan.',
            },
            {
                q: 'Apakah audio tersimpan?',
                a: 'Penyimpanan audio mengikuti konfigurasi, hak akses, dan kebijakan data perusahaan.',
            },
        ],
        related: ['ai-hr', 'hr-helpdesk', 'ruang-kita', 'hr-analytics'],
    },
    'hr-helpdesk': {
        name: 'Pusat Pengetahuan',
        category: 'Kolaborasi & Engagement',
        asset: 'hr-helpdesk.png',
        title: 'Bantu Karyawan Menemukan Jawaban dan Layanan dari Satu Pusat Pengetahuan',
        description:
            'Satukan FAQ, sumber daya, dan sistem tiket digital agar karyawan mendapat jawaban dan bantuan dengan alur yang lebih jelas.',
        heroNote:
            'Jawaban dan permintaan layanan punya tempat yang mudah ditemukan.',
        problems: [
            'Karyawan kesulitan menemukan jawaban dari FAQ dan kebijakan.',
            'Permintaan layanan masuk tanpa alur dan prioritas yang jelas.',
            'Tiket belum otomatis diarahkan ke tim yang tepat.',
            'Pertanyaan berulang belum berubah menjadi pengetahuan bersama.',
        ],
        features: [
            F(
                'Pusat FAQ & sumber daya',
                'Karyawan mencari informasi dari FAQ dan sumber daya yang terorganisir secara instan.',
                FileText,
            ),
            F(
                'Sistem tiket digital',
                'Karyawan mengajukan pertanyaan atau permintaan layanan secara online dan dapat mengikuti statusnya.',
                TicketCheck,
            ),
            F(
                'Distribusi permintaan otomatis',
                'Alokasikan tiket ke tim atau individu yang tepat agar respons lebih akurat dan beban kerja lebih seimbang.',
                ListChecks,
            ),
            F(
                'Komunikasi & Kolaborasi Terintegrasi',
                'Fitur chat internal untuk percakapan yang langsung, transparan, dan terdokumentasi.',
                MessageSquareText,
                'hold',
            ),
            F(
                'Konversi pertanyaan ke FAQ',
                'Ubah pertanyaan yang sering muncul menjadi FAQ yang mudah dicari untuk mendukung layanan mandiri.',
                Sparkles,
            ),
            F(
                'Pemantauan & Evaluasi Layanan',
                'Ukur kualitas dukungan melalui penilaian kepuasan setelah tiket ditutup dan gunakan datanya untuk perbaikan layanan.',
                BarChart3,
            ),
        ],
        steps: [
            'Karyawan mencari FAQ atau membuat tiket',
            'Permintaan diarahkan otomatis',
            'Tim terkait memberikan jawaban',
            'Jawaban berulang dikonversi menjadi FAQ',
        ],
        benefits: [
            'Karyawan menemukan jawaban lebih cepat.',
            'Permintaan layanan terdokumentasi dan transparan.',
            'Distribusi tiket membantu menyeimbangkan beban tim.',
            'Pengetahuan terus bertambah dari pertanyaan yang berulang.',
        ],
        audience: [
            'HR shared service dan pusat bantuan internal',
            'Perusahaan dengan banyak karyawan',
            'Tim HR multi cabang',
            'Organisasi dengan banyak pertanyaan berulang',
        ],
        faqs: [
            {
                q: 'Apakah karyawan dapat mencari FAQ sendiri?',
                a: 'Bisa. FAQ dan sumber daya yang terorganisir membantu karyawan menemukan informasi tanpa selalu menghubungi HR.',
            },
            {
                q: 'Apakah tersedia sistem tiket digital?',
                a: 'Bisa. Karyawan dapat mengajukan pertanyaan atau permintaan layanan secara online dan melihat statusnya.',
            },
            {
                q: 'Bagaimana permintaan diarahkan ke tim?',
                a: 'Distribusi dapat diatur agar tiket dialokasikan ke tim atau individu yang sesuai berdasarkan kebutuhan layanan.',
            },
            {
                q: 'Apakah pertanyaan sering muncul dapat dijadikan FAQ?',
                a: 'Bisa. Pertanyaan yang berulang dapat dikonversi menjadi FAQ yang mudah dicari.',
            },
            {
                q: 'Apakah status dan prioritas tiket terlihat?',
                a: 'Bisa. Status dan prioritas membantu karyawan serta tim layanan memahami progres dan urgensi permintaan.',
            },
        ],
        related: ['ai-hr', 'pengumuman-karyawan', 'survei-karyawan', 'core-hr'],
    },
    'mood-karyawan': {
        name: 'Mood Karyawan',
        category: 'Kolaborasi & Engagement',
        asset: 'mood-karyawan.png',
        title: 'Dengarkan Mood Tim Secara Rutin, Bukan Hanya Saat Ada Masalah',
        description:
            'Kumpulkan mood harian, baca tren tim, dan bantu HR menentukan percakapan lanjutan dengan konteks yang lebih baik.',
        heroNote: 'Sinyal kecil membantu HR memahami energi tim.',
        problems: [
            'HR baru tahu masalah setelah engagement turun.',
            'Karyawan tidak punya ruang check-in yang ringan.',
            'Mood tim sulit dibandingkan dari waktu ke waktu.',
            'Follow up tidak berdasarkan pola.',
        ],
        features: [
            F(
                'Daily mood',
                'Karyawan mengisi kondisi hariannya dengan cepat.',
                Star,
            ),
            F(
                'Mood trend',
                'Lihat perubahan mood dari waktu ke waktu.',
                TrendingUp,
            ),
            F(
                'Team mood',
                'Baca kondisi tim tanpa harus menunggu survey besar.',
                Users,
            ),
            F(
                'Anonymous option',
                'Berikan pilihan anonim sesuai kebijakan perusahaan.',
                LockKeyhole,
            ),
            F(
                'Reminder',
                'Bantu check-in menjadi kebiasaan yang konsisten.',
                CalendarDays,
            ),
            F(
                'Insight',
                'Gunakan tren sebagai awal percakapan HR yang lebih tepat.',
                Sparkles,
            ),
        ],
        steps: [
            'Karyawan isi mood',
            'Data direkap',
            'HR melihat tren',
            'Follow up',
        ],
        benefits: [
            'HR mendapat sinyal lebih awal.',
            'Check-in terasa lebih ringan.',
            'Tren tim membantu menentukan prioritas.',
            'Percakapan engagement punya konteks.',
        ],
        audience: [
            'Perusahaan hybrid',
            'Tim dengan ritme kerja cepat',
            'People experience team',
            'Organisasi yang membangun budaya feedback',
        ],
        faqs: [
            {
                q: 'Apakah mood bisa anonim?',
                a: 'Bisa, sesuai konfigurasi dan kebijakan pengumpulan data perusahaan.',
            },
            {
                q: 'Bisa melihat trend per tim?',
                a: 'Bisa selama data dan akses pengguna mengizinkannya.',
            },
            {
                q: 'Apakah HR melihat data individual?',
                a: 'Visibilitas mengikuti konfigurasi privasi dan hak akses yang diterapkan.',
            },
            {
                q: 'Seberapa sering mood diisi?',
                a: 'Perusahaan dapat menentukan ritme yang sesuai, misalnya harian atau periodik.',
            },
            {
                q: 'Bisa export hasil?',
                a: 'Bisa untuk hasil dan laporan yang tersedia di aplikasi.',
            },
        ],
        related: [
            'survei-karyawan',
            'hr-analytics',
            'ruang-kita',
            'hr-helpdesk',
        ],
    },
    'ruang-kita': {
        name: 'Ruang Kita',
        category: 'Kolaborasi & Engagement',
        asset: 'ruang-kita.png',
        title: 'Bangun Ruang Komunikasi Internal yang Lebih Hidup',
        description:
            'Bagikan informasi, cerita, dan aktivitas perusahaan lewat media sosial internal yang dekat dengan keseharian karyawan.',
        heroNote:
            'Komunikasi internal terasa seperti ruang bersama, bukan broadcast satu arah.',
        problems: [
            'Informasi internal tersebar di grup chat.',
            'Cerita antar tim tidak terdokumentasi.',
            'Karyawan sulit menemukan update lama.',
            'Engagement komunikasi rendah.',
        ],
        features: [
            F(
                'Post internal',
                'Bagikan informasi atau cerita untuk lingkungan perusahaan.',
                MessageSquareText,
            ),
            F(
                'Feed',
                'Baca pembaruan dari komunitas internal dalam satu aliran.',
                Users,
            ),
            F(
                'Like & comment',
                'Beri respons dan bangun percakapan antar tim.',
                CircleCheck,
            ),
            F(
                'Media upload',
                'Tambahkan foto atau media untuk membuat update lebih kontekstual.',
                FileText,
            ),
            F(
                'Community',
                'Buat ruang interaksi sesuai minat atau kebutuhan organisasi.',
                Network,
            ),
            F(
                'Moderation',
                'Jaga interaksi tetap sesuai aturan internal perusahaan.',
                ShieldAlert,
            ),
        ],
        steps: [
            'Karyawan membuat atau melihat post',
            'Interaksi',
            'Informasi tersebar',
            'Engagement meningkat',
        ],
        benefits: [
            'Komunikasi lintas tim lebih terbuka.',
            'Update internal lebih mudah ditemukan.',
            'Cerita perusahaan punya ruang.',
            'Engagement dapat dipantau dari interaksi.',
        ],
        audience: [
            'Perusahaan multi lokasi',
            'Tim hybrid',
            'Organisasi dengan banyak komunitas',
            'People experience team',
        ],
        faqs: [
            {
                q: 'Siapa yang bisa membuat post?',
                a: 'Hak membuat post dapat mengikuti role dan aturan komunitas perusahaan.',
            },
            {
                q: 'Bisa upload foto?',
                a: 'Bisa, sesuai dukungan media dan kebijakan aplikasi.',
            },
            {
                q: 'Bisa memberi komentar?',
                a: 'Bisa. Karyawan dapat berinteraksi sesuai permission yang berlaku.',
            },
            {
                q: 'Bisa digunakan untuk komunikasi internal?',
                a: 'Ya. Ruang Kita dibuat sebagai media sosial internal perusahaan.',
            },
            {
                q: 'Apakah ada moderation?',
                a: 'Perusahaan dapat menerapkan aturan dan pengelolaan konten sesuai kebutuhan.',
            },
        ],
        related: [
            'pengumuman-karyawan',
            'mood-karyawan',
            'survei-karyawan',
            'hr-helpdesk',
        ],
    },
    'pengumuman-karyawan': {
        name: 'Pengumuman',
        category: 'Kolaborasi & Engagement',
        asset: 'pengumuman.png',
        title: 'Sampaikan Pengumuman Perusahaan ke Orang yang Tepat',
        description:
            'Buat broadcast, pilih target penerima, jadwalkan publikasi, dan lihat siapa yang sudah membaca.',
        heroNote: 'Pesan penting punya target dan status baca yang jelas.',
        problems: [
            'Pengumuman penting tenggelam di chat.',
            'Target penerima sering terlalu luas.',
            'Tidak diketahui siapa yang sudah membaca.',
            'Lampiran dan versi pesan tidak terpusat.',
        ],
        features: [
            F(
                'Buat pengumuman',
                'Susun informasi perusahaan dalam format yang mudah dibaca.',
                Megaphone,
            ),
            F(
                'Target penerima',
                'Kirim ke cabang, departemen, atau kelompok tertentu.',
                Users,
            ),
            F(
                'Schedule',
                'Atur waktu publikasi untuk pesan yang sudah direncanakan.',
                CalendarDays,
            ),
            F(
                'Attachment',
                'Sertakan dokumen atau file pendukung di pengumuman.',
                FileText,
            ),
            F(
                'Priority',
                'Tandai pesan yang perlu mendapat perhatian lebih cepat.',
                ShieldAlert,
            ),
            F(
                'Read status',
                'Pantau penerima yang sudah membuka informasi.',
                CircleCheck,
            ),
        ],
        steps: [
            'HR membuat pengumuman',
            'Tentukan penerima',
            'Publish',
            'Karyawan membaca',
        ],
        benefits: [
            'Pesan lebih terarah.',
            'Riwayat pengumuman tersimpan.',
            'Status baca membantu follow up.',
            'Komunikasi lintas cabang lebih konsisten.',
        ],
        audience: [
            'HR corporate',
            'Perusahaan multi cabang',
            'Tim internal communication',
            'Organisasi dengan kebijakan terjadwal',
        ],
        faqs: [
            {
                q: 'Bisa memilih penerima tertentu?',
                a: 'Bisa. Penerima dapat ditargetkan berdasarkan kelompok yang tersedia.',
            },
            {
                q: 'Bisa menjadwalkan pengumuman?',
                a: 'Bisa untuk pesan yang perlu tayang pada waktu tertentu.',
            },
            {
                q: 'Bisa upload attachment?',
                a: 'Bisa, sesuai batasan file yang tersedia.',
            },
            {
                q: 'Bisa melihat siapa yang sudah membaca?',
                a: 'Bisa melalui status baca pengumuman.',
            },
            {
                q: 'Bisa kirim notifikasi?',
                a: 'Notifikasi mengikuti kanal dan konfigurasi yang tersedia pada aplikasi.',
            },
        ],
        related: [
            'ruang-kita',
            'kalender-perusahaan',
            'hr-helpdesk',
            'survei-karyawan',
        ],
    },
    'survei-karyawan': {
        name: 'Survei Karyawan',
        category: 'Kolaborasi & Engagement',
        asset: 'survei-karyawan.png',
        title: 'Dapatkan Suara Karyawan dalam Data yang Bisa Ditindaklanjuti',
        description:
            'Buat survei, atur pertanyaan, jaga anonimitas bila diperlukan, dan baca hasilnya per tim atau departemen.',
        heroNote:
            'Pertanyaan yang baik menghasilkan arah perbaikan yang lebih jelas.',
        problems: [
            'Survei dibuat dari banyak tools terpisah.',
            'Hasil sulit dibandingkan antar departemen.',
            'Karyawan ragu mengisi karena privasi.',
            'Insight survei berhenti di laporan.',
        ],
        features: [
            F(
                'Survey builder',
                'Buat survei dengan tipe pertanyaan yang sesuai tujuan.',
                ClipboardCheck,
            ),
            F(
                'Pertanyaan custom',
                'Sesuaikan pertanyaan dengan konteks organisasi.',
                ListChecks,
            ),
            F(
                'Anonymous survey',
                'Berikan pilihan anonim untuk topik yang sensitif.',
                LockKeyhole,
            ),
            F(
                'Schedule',
                'Tentukan periode survei dan waktu pengisian.',
                CalendarDays,
            ),
            F(
                'Result dashboard',
                'Ringkas respons dalam dashboard yang mudah dibaca.',
                BarChart3,
            ),
            F(
                'Department filter',
                'Bandingkan hasil berdasarkan unit yang relevan.',
                Network,
            ),
        ],
        steps: [
            'HR membuat survei',
            'Karyawan mengisi',
            'Sistem merekap',
            'HR melihat hasil',
        ],
        benefits: [
            'Pengumpulan feedback lebih terstruktur.',
            'Privasi responden lebih diperhatikan.',
            'Hasil bisa dibaca per segmen.',
            'HR lebih mudah menyusun tindak lanjut.',
        ],
        audience: [
            'People experience team',
            'Perusahaan dengan engagement program',
            'Organisasi multi departemen',
            'HR yang rutin mengumpulkan feedback',
        ],
        faqs: [
            {
                q: 'Apakah survei bisa anonim?',
                a: 'Bisa, sesuai pengaturan survei dan kebutuhan privasi perusahaan.',
            },
            {
                q: 'Bisa membuat pertanyaan custom?',
                a: 'Bisa dengan tipe pertanyaan yang tersedia.',
            },
            {
                q: 'Bisa filter hasil per departemen?',
                a: 'Bisa, jika segmentasi tersebut digunakan dalam survei.',
            },
            {
                q: 'Bisa export hasil?',
                a: 'Bisa untuk hasil yang tersedia pada dashboard dan laporan.',
            },
            {
                q: 'Bisa menentukan periode survei?',
                a: 'Bisa dengan jadwal mulai dan berakhir sesuai kebutuhan.',
            },
        ],
        related: [
            'mood-karyawan',
            'ruang-kita',
            'hr-analytics',
            'pengumuman-karyawan',
        ],
    },
    'kalender-perusahaan': {
        name: 'Kalender Acara',
        category: 'Kolaborasi & Engagement',
        asset: 'kalender-acara.png',
        title: 'Satukan Agenda Perusahaan, Hari Libur, dan Kegiatan HR',
        description:
            'Buat event, tentukan audience, bagikan agenda, dan bantu karyawan mengetahui kegiatan perusahaan tanpa mencari di chat.',
        heroNote: 'Satu kalender untuk ritme kerja dan kehidupan perusahaan.',
        problems: [
            'Agenda perusahaan tersebar di banyak kalender.',
            'Hari libur cabang tidak selalu terlihat.',
            'Karyawan melewatkan kegiatan internal.',
            'Reminder masih harus dikirim manual.',
        ],
        features: [
            F(
                'Event perusahaan',
                'Buat dan bagikan agenda kegiatan organisasi.',
                CalendarDays,
            ),
            F(
                'Hari libur',
                'Kelola hari libur perusahaan dan kebutuhan cabang.',
                Landmark,
            ),
            F(
                'Reminder',
                'Bantu peserta mengingat agenda yang akan datang.',
                Megaphone,
            ),
            F(
                'Audience',
                'Tentukan siapa yang perlu melihat atau mengikuti event.',
                Users,
            ),
            F(
                'Calendar view',
                'Baca agenda bulanan dalam tampilan yang ringkas.',
                ListChecks,
            ),
            F(
                'Integrasi HR',
                'Hubungkan agenda dengan kegiatan dan proses HR yang relevan.',
                Network,
            ),
        ],
        steps: [
            'HR membuat event',
            'Tentukan tanggal & audience',
            'Publish',
            'Karyawan melihat kalender',
        ],
        benefits: [
            'Agenda lebih mudah ditemukan.',
            'Kegiatan cabang lebih terkoordinasi.',
            'Karyawan mendapat konteks waktu.',
            'Reminder mengurangi agenda terlewat.',
        ],
        audience: [
            'Perusahaan multi cabang',
            'Tim internal communication',
            'Organisasi dengan banyak event',
            'HR dan people experience team',
        ],
        faqs: [
            {
                q: 'Bisa membuat hari libur perusahaan?',
                a: 'Bisa, sesuai kebutuhan kalender kerja perusahaan.',
            },
            {
                q: 'Bisa membuat event khusus cabang?',
                a: 'Bisa dengan menentukan audience atau cabang yang relevan.',
            },
            {
                q: 'Bisa menentukan peserta?',
                a: 'Bisa, event dapat diarahkan kepada audience yang ditentukan.',
            },
            {
                q: 'Ada reminder?',
                a: 'Reminder mengikuti fitur dan konfigurasi notifikasi yang tersedia.',
            },
            {
                q: 'Bisa melihat agenda bulanan?',
                a: 'Bisa melalui calendar view dan daftar agenda.',
            },
        ],
        related: [
            'pengumuman-karyawan',
            'cuti-dan-izin',
            'ruang-kita',
            'absensi-karyawan',
        ],
    },
    'manajemen-aset': {
        name: 'Manajemen Aset',
        category: 'Operasional Bisnis',
        asset: 'employees.png',
        title: 'Pastikan Setiap Aset Perusahaan Punya Pemilik dan Riwayat yang Jelas',
        description:
            'Kelola inventaris, penugasan, kondisi, dan pengembalian aset perusahaan dari satu tempat yang terhubung dengan data karyawan.',
        heroNote: 'Aset tercatat, penugasan jelas, histori mudah ditelusuri.',
        problems: [
            'Data aset masih tersebar di spreadsheet.',
            'Sulit mengetahui aset sedang dipakai siapa.',
            'Pengembalian aset tidak memiliki histori yang rapi.',
            'Kondisi dan nilai aset sulit dipantau dari waktu ke waktu.',
        ],
        features: [
            F(
                'Register aset',
                'Simpan kode, kategori, nilai, tanggal beli, kondisi, dan status setiap aset.',
                BriefcaseBusiness,
            ),
            F(
                'Penugasan karyawan',
                'Hubungkan aset dengan karyawan yang bertanggung jawab menggunakannya.',
                Users,
            ),
            F(
                'QR code aset',
                'Buka detail aset dengan cepat melalui kode QR yang tersedia.',
                ScanFace,
            ),
            F(
                'Status & kondisi',
                'Pantau aset yang tersedia, digunakan, dalam perawatan, atau perlu ditindaklanjuti.',
                ShieldAlert,
            ),
            F(
                'Pengembalian aset',
                'Catat waktu pengembalian dan lepaskan penugasan tanpa menghilangkan histori.',
                ClipboardCheck,
            ),
            F(
                'Terhubung dengan HR',
                'Gunakan data karyawan yang sama untuk proses onboarding, mutasi, dan offboarding.',
                Network,
            ),
        ],
        steps: [
            'Tambahkan data aset',
            'Tetapkan penanggung jawab',
            'Pantau status & kondisi',
            'Catat pengembalian',
        ],
        benefits: [
            'Inventaris lebih mudah ditemukan.',
            'Tanggung jawab penggunaan lebih jelas.',
            'Histori aset siap untuk audit.',
            'Risiko aset hilang atau terlupakan berkurang.',
        ],
        audience: [
            'Tim HR dan General Affairs',
            'Perusahaan dengan banyak perangkat kerja',
            'Organisasi multi cabang',
            'Perusahaan yang membutuhkan audit inventaris',
        ],
        faqs: [
            {
                q: 'Apakah aset bisa ditugaskan ke karyawan?',
                a: 'Bisa. Setiap aset dapat dihubungkan dengan karyawan yang sedang bertanggung jawab menggunakannya.',
            },
            {
                q: 'Apakah histori pengembalian tersimpan?',
                a: 'Ya. Histori penugasan dan pengembalian tetap tercatat untuk memudahkan pelacakan.',
            },
            {
                q: 'Apakah tersedia QR code?',
                a: 'Tersedia. Detail aset dapat dibuka melalui QR code yang dibuat dari sistem.',
            },
            {
                q: 'Apakah aset terhubung dengan data karyawan?',
                a: 'Ya. Penugasan aset menggunakan data karyawan yang sama di dalam platform AvanaHR.',
            },
        ],
        related: ['core-hr', 'data-karyawan', 'ess', 'administrasi-karier'],
    },
};

const FEATURE_SCREENSHOTS: Record<string, string[]> = {
    'core-hr': ['employees.png', 'dashboard.png'],
    'data-karyawan': ['employees.png', 'dashboard.png'],
    'struktur-organisasi': ['struktur-organisasi.png'],
    'administrasi-karier': ['administrasi-karier.png'],
    'cuti-dan-izin': ['dashboard.png'],
    rekrutmen: ['rekrutmen.png'],
    'manajemen-kinerja': ['kinerja.png'],
    payroll: ['payroll.png', 'payroll-ter.png'],
    reimbursement: ['settlement.png'],
    'absensi-karyawan': ['absensi.png'],
    'live-tracking-karyawan': ['live-tracking.png'],
    'kunjungan-karyawan': ['visiting.png'],
    'ai-hr': ['analytics.png'],
    'hr-analytics': ['analytics.png'],
    'prediksi-risiko-resign': ['attrition.png'],
    'transkrip-rapat-ai': ['rapat.png'],
    'hr-helpdesk': ['helpdesk.png'],
    'mood-karyawan': ['mood.png'],
    'ruang-kita': ['ruang-kita.png'],
    'pengumuman-karyawan': ['pengumuman.png'],
    'survei-karyawan': ['survei.png'],
    'kalender-perusahaan': ['kalender.png'],
    'manajemen-aset': ['employees.png'],
    crm: ['crm.png'],
    ess: [],
    'otomatisasi-alur-kerja': ['dashboard.png'],
    pelatihan: ['kinerja.png'],
    'time-management': ['absensi.png'],
    'manajemen-talenta': ['kinerja.png'],
    'compensation-benefits': ['settlement.png'],
    'loans-management': ['payroll.png'],
    kpi: ['kinerja.png'],
    'ai-analytics': ['analytics.png'],
};

function catalogPageToFeaturePage(
    catalogPage: PublicFeatureCatalogEntry,
    fallback?: FeaturePageData,
): FeaturePageData {
    return {
        ...fallback,
        name: catalogPage.name,
        category: catalogPage.category,
        asset: catalogPage.asset,
        title: catalogPage.title,
        description: catalogPage.description,
        heroNote: catalogPage.heroNote,
        problems: catalogPage.problems,
        features: catalogPage.items.map((item) => ({
            title: item.title,
            description: item.description,
            icon: CATALOG_ICON_MAP[item.icon] ?? ListChecks,
            status: item.status,
        })),
        steps: catalogPage.steps,
        benefits: catalogPage.benefits,
        audience: catalogPage.audience,
        faqs: catalogPage.faqs,
        related: catalogPage.related,
    };
}

function getFeaturePage(slug: string): FeaturePageData | undefined {
    const catalogPage = PUBLIC_FEATURE_CATALOG[slug];

    if (catalogPage) {
        return catalogPageToFeaturePage(catalogPage, FEATURE_PAGES[slug]);
    }

    return FEATURE_PAGES[slug];
}

function featureUrl(slug: string): string {
    return slug === 'crm' ? solutionCrm.url() : featuresShow.url(slug);
}

function FeatureVisual({
    page,
    eager = false,
}: {
    page: FeaturePageData;
    eager?: boolean;
}) {
    return (
        <div className="relative mx-auto flex min-h-[340px] w-full max-w-[440px] items-center justify-center overflow-hidden rounded-[28px] border border-[#DCE5F4] bg-[radial-gradient(circle_at_50%_35%,#FFFFFF_0%,#EDF4FF_55%,#DDE9FF_100%)] p-8 shadow-[0_24px_70px_-30px_rgba(16,42,92,0.32)] sm:min-h-[440px]">
            <div
                aria-hidden
                className="absolute top-8 left-8 h-16 w-16 rounded-full border border-white/80 bg-white/45"
            />
            <div
                aria-hidden
                className="absolute right-8 bottom-8 h-24 w-24 rounded-full border border-white/70 bg-white/35"
            />
            <img
                src={`/front/${page.asset}`}
                alt={`Ilustrasi fitur ${page.name} AvanaHR`}
                width={page.asset === 'kalender-acara.png' ? 861 : 621}
                height={759}
                loading={eager ? 'eager' : 'lazy'}
                className="relative z-10 max-h-[360px] w-auto max-w-full object-contain drop-shadow-[0_20px_18px_rgba(16,42,92,0.12)] sm:max-h-[410px]"
            />
            <span className="absolute right-5 bottom-5 left-5 rounded-2xl border border-white/70 bg-white/75 px-4 py-3 text-center text-xs font-semibold text-[#19366E] shadow-sm backdrop-blur-sm">
                {page.heroNote}
            </span>
        </div>
    );
}

function ProductVisual({
    featureSlug,
    page,
    eager = false,
    showGallery = true,
}: {
    featureSlug: string;
    page: FeaturePageData;
    eager?: boolean;
    showGallery?: boolean;
}) {
    const screenshots = FEATURE_SCREENSHOTS[featureSlug] ?? [];
    const visibleScreenshots = showGallery
        ? screenshots
        : screenshots.slice(0, 1);

    if (page.asset.startsWith('feature-assets/')) {
        return (
            <div className="relative mx-auto w-full max-w-[680px] overflow-hidden rounded-[28px] border border-[#DCE5F4] bg-[radial-gradient(circle_at_50%_35%,#FFFFFF_0%,#F1F6FF_58%,#E1ECFF_100%)] p-3 shadow-[0_24px_70px_-30px_rgba(16,42,92,0.32)] sm:p-5">
                <div
                    aria-hidden
                    className="absolute -top-14 -right-10 h-44 w-44 rounded-full bg-[#CFE0FF]/50 blur-2xl"
                />
                <div
                    aria-hidden
                    className="absolute -bottom-16 -left-12 h-48 w-48 rounded-full bg-[#DDF7F0]/60 blur-2xl"
                />
                <img
                    src={`/front/${page.asset}`}
                    alt={`Ilustrasi fitur ${page.name} AvanaHR`}
                    width={1672}
                    height={941}
                    loading={eager ? 'eager' : 'lazy'}
                    decoding="async"
                    className="relative z-10 h-auto w-full object-contain drop-shadow-[0_22px_22px_rgba(16,42,92,0.16)]"
                />
                <span className="absolute right-5 bottom-5 left-5 z-20 rounded-2xl border border-white/80 bg-white/85 px-4 py-3 text-center text-xs font-semibold text-[#19366E] shadow-sm backdrop-blur-sm">
                    Ilustrasi fitur {page.name}
                </span>
            </div>
        );
    }

    if (visibleScreenshots.length === 0) {
        return <FeatureVisual page={page} />;
    }

    return (
        <div className="relative mx-auto w-full max-w-[640px] overflow-hidden rounded-[22px] border border-[#DCE5F4] bg-[#F7FAFF] p-2 shadow-[0_24px_70px_-30px_rgba(16,42,92,0.32)] sm:p-3">
            <div className="flex h-8 items-center gap-1.5 border-b border-[#E6EDF8] px-2 sm:h-9">
                <span className="h-2 w-2 rounded-full bg-[#F2A7A0]" />
                <span className="h-2 w-2 rounded-full bg-[#F1D18A]" />
                <span className="h-2 w-2 rounded-full bg-[#8ED7C5]" />
                <span className="ml-2 h-5 flex-1 rounded-md bg-white" />
            </div>
            <div
                className={`mt-2 grid gap-2 ${visibleScreenshots.length > 1 ? 'sm:grid-cols-[1.35fr_0.65fr]' : ''}`}
            >
                <img
                    src={`/avana/landing/screenshots/${visibleScreenshots[0]}`}
                    alt={`Tampilan produk ${page.name} AvanaHR`}
                    width={1440}
                    height={900}
                    loading={eager ? 'eager' : 'lazy'}
                    decoding="async"
                    className="aspect-[16/10] w-full rounded-xl border border-[#E2EAF6] object-cover object-top"
                />
                {visibleScreenshots.length > 1 ? (
                    <div className="grid gap-2 sm:grid-rows-2">
                        {visibleScreenshots.slice(1, 3).map((screenshot) => (
                            <img
                                key={screenshot}
                                src={`/avana/landing/screenshots/${screenshot}`}
                                alt={`Detail tampilan ${page.name} AvanaHR`}
                                width={1440}
                                height={900}
                                loading="lazy"
                                decoding="async"
                                className="aspect-[16/10] w-full rounded-xl border border-[#E2EAF6] object-cover object-top sm:aspect-auto"
                            />
                        ))}
                    </div>
                ) : null}
            </div>
            <span className="absolute right-5 bottom-5 rounded-full border border-white/80 bg-white/90 px-3 py-1.5 text-[11px] font-bold text-[#19366E] shadow-sm backdrop-blur-sm">
                Tampilan produk {page.name}
            </span>
        </div>
    );
}

export default function Feature({
    featureSlug,
    canonicalUrl,
}: {
    featureSlug: string;
    canonicalUrl: string;
}) {
    const page = getFeaturePage(featureSlug);
    const { website } = usePage().props;

    if (!page) {
        return null;
    }

    const brand = website.site_name ?? 'AvanaHR';
    const logo = website.logo_url ?? '/avana/logo-full.png';
    const pageTitle = `${page.name} — ${brand}`;
    const siteUrl = new URL(canonicalUrl).origin;
    const imageUrl = new URL(`/front/${page.asset}`, siteUrl).toString();
    const parentLabel = featureSlug === 'crm' ? 'Solusi' : 'Fitur';
    const parentUrl = `${siteUrl}${featureSlug === 'crm' ? '/#solusi' : '/#platform'}`;
    const workflowGridColumns =
        page.steps.length >= 5
            ? 'md:grid-cols-3 lg:grid-cols-5'
            : page.steps.length === 4
              ? 'md:grid-cols-4'
              : 'md:grid-cols-3';
    const relatedPages = page.related.flatMap((slug) => {
        const relatedPage = getFeaturePage(slug);

        return relatedPage ? [{ slug, page: relatedPage }] : [];
    });
    const structuredData = {
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'WebPage',
                '@id': `${canonicalUrl}#webpage`,
                url: canonicalUrl,
                name: pageTitle,
                description: page.description,
                image: imageUrl,
                inLanguage: 'id-ID',
                isPartOf: {
                    '@type': 'WebSite',
                    '@id': `${siteUrl}#website`,
                    url: siteUrl,
                    name: brand,
                },
            },
            {
                '@type': 'BreadcrumbList',
                itemListElement: [
                    {
                        '@type': 'ListItem',
                        position: 1,
                        name: 'Home',
                        item: siteUrl,
                    },
                    {
                        '@type': 'ListItem',
                        position: 2,
                        name: parentLabel,
                        item: parentUrl,
                    },
                    {
                        '@type': 'ListItem',
                        position: 3,
                        name: page.name,
                        item: canonicalUrl,
                    },
                ],
            },
            {
                '@type': 'FAQPage',
                mainEntity: page.faqs.map((faq) => ({
                    '@type': 'Question',
                    name: faq.q,
                    acceptedAnswer: {
                        '@type': 'Answer',
                        text: faq.a,
                    },
                })),
            },
        ],
    };

    return (
        <>
            <Head title={pageTitle}>
                <link rel="canonical" href={canonicalUrl} />
                <meta name="description" content={page.description} />
                <meta property="og:type" content="website" />
                <meta property="og:url" content={canonicalUrl} />
                <meta property="og:title" content={pageTitle} />
                <meta property="og:description" content={page.description} />
                <meta property="og:image" content={imageUrl} />
                <meta
                    property="og:image:alt"
                    content={`Ilustrasi fitur ${page.name} AvanaHR`}
                />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={pageTitle} />
                <meta name="twitter:description" content={page.description} />
                <meta name="twitter:image" content={imageUrl} />
                <script type="application/ld+json">
                    {JSON.stringify(structuredData)}
                </script>
            </Head>

            <div
                id="top"
                className="min-h-dvh overflow-x-clip bg-white font-sans text-[#1A2333] antialiased"
            >
                <SiteNavbar brand={brand} logo={logo} anchorPrefix="/" />
                <FeatureSubnav currentSlug={featureSlug} />

                <main>
                    <section className="relative overflow-hidden border-b border-[#E8EEF8] bg-[#F7FAFF] pt-10 pb-16 sm:pt-14 sm:pb-24 lg:pt-16 lg:pb-28">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -top-48 right-[-8%] h-[480px] w-[480px] rounded-full bg-[#DCE8FF] blur-3xl"
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute bottom-[-35%] left-[-10%] h-[360px] w-[360px] rounded-full bg-[#E9F7F4] blur-3xl"
                        />
                        <Container className="relative">
                            <nav
                                aria-label="Breadcrumb"
                                className="flex flex-wrap items-center gap-1.5 text-xs font-medium text-[#667085]"
                            >
                                <Link
                                    href={home().url}
                                    className="rounded-sm hover:text-[#315FD4] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315FD4]"
                                >
                                    Home
                                </Link>
                                <ChevronRight
                                    className="h-3.5 w-3.5"
                                    aria-hidden
                                />
                                <Link
                                    href={parentUrl}
                                    className="rounded-sm hover:text-[#315FD4] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315FD4]"
                                >
                                    {parentLabel}
                                </Link>
                                <ChevronRight
                                    className="h-3.5 w-3.5"
                                    aria-hidden
                                />
                                <span className="text-[#19366E]">
                                    {page.name}
                                </span>
                            </nav>

                            <div className="mt-10 grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.84fr)] lg:gap-16">
                                <Reveal>
                                    <span className="inline-flex items-center gap-2 rounded-full border border-[#D6E2FA] bg-white px-3.5 py-1.5 text-[11px] font-bold tracking-[0.14em] text-[#315FD4] uppercase shadow-sm">
                                        <span className="h-1.5 w-1.5 rounded-full bg-[#27B89B]" />
                                        {page.category}
                                    </span>
                                    <h1 className="mt-5 max-w-2xl text-[36px] leading-[1.12] font-black tracking-[-0.04em] text-balance text-[#0E1A3A] sm:text-[44px] lg:text-[56px]">
                                        {page.title}
                                    </h1>
                                    <p className="mt-6 max-w-xl text-[15px] leading-7 text-[#5B6478] sm:text-base">
                                        {page.description}
                                    </p>
                                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                        <TrialButton
                                            variant="primary"
                                            className="w-full sm:w-auto"
                                        >
                                            Coba Gratis 3 Bulan
                                        </TrialButton>
                                        <DemoButton
                                            variant="secondary"
                                            className="w-full sm:w-auto"
                                        >
                                            Jadwalkan Demo
                                        </DemoButton>
                                    </div>
                                    <div className="mt-7 grid max-w-xl grid-cols-1 gap-3 text-sm text-[#19366E] sm:grid-cols-3 sm:gap-5">
                                        {[
                                            'Data terpusat',
                                            'Setup cepat',
                                            'Terhubung antar modul',
                                        ].map((point) => (
                                            <span
                                                key={point}
                                                className="flex items-center gap-2"
                                            >
                                                <CircleCheck
                                                    className="h-4 w-4 shrink-0 text-[#27B89B]"
                                                    aria-hidden
                                                />
                                                {point}
                                            </span>
                                        ))}
                                    </div>
                                </Reveal>
                                <Reveal delay={0.08}>
                                    <ProductVisual
                                        featureSlug={featureSlug}
                                        page={page}
                                        eager
                                        showGallery={false}
                                    />
                                </Reveal>
                            </div>

                            <div className="mt-12 grid overflow-hidden rounded-2xl border border-[#DCE5F4] bg-white/85 shadow-sm sm:grid-cols-3">
                                {[
                                    [
                                        '01',
                                        'Masalah nyata',
                                        'Pahami hambatan yang ingin diselesaikan.',
                                    ],
                                    [
                                        '02',
                                        'Alur yang jelas',
                                        'Kerjakan proses dengan langkah yang terlihat.',
                                    ],
                                    [
                                        '03',
                                        'Data siap dipakai',
                                        'Hubungkan hasilnya ke modul lain.',
                                    ],
                                ].map(([number, title, description]) => (
                                    <div
                                        key={number}
                                        className="flex gap-3 border-b border-[#E8EEF8] p-5 last:border-b-0 sm:border-r sm:border-b-0 sm:last:border-r-0"
                                    >
                                        <span className="text-xs font-black tracking-[0.16em] text-[#315FD4]">
                                            {number}
                                        </span>
                                        <div>
                                            <p className="text-sm font-bold text-[#0E1A3A]">
                                                {title}
                                            </p>
                                            <p className="mt-1 text-xs leading-relaxed text-[#667085]">
                                                {description}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Container>
                    </section>

                    <section
                        id="masalah"
                        className="scroll-mt-28 border-b border-[#EDF1F8] py-20 sm:py-24 lg:py-28"
                    >
                        <Container className="grid items-start gap-14 lg:grid-cols-[minmax(0,0.88fr)_minmax(0,1.12fr)] lg:gap-24">
                            <SectionHeading
                                size="compact"
                                eyebrow="Masalah yang diselesaikan"
                                title="Lebih sedikit pekerjaan administratif. Lebih banyak waktu untuk keputusan HR."
                                description={`Hal-hal kecil yang berulang sering menjadi beban terbesar. ${page.name} membantu merapikan titik-titik yang paling sering membuat tim berhenti dan mengecek ulang.`}
                                align="left"
                            />
                            <Reveal className="grid gap-5 sm:grid-cols-2">
                                {page.problems.map((problem, index) => (
                                    <div
                                        key={problem}
                                        className="flex min-h-[164px] flex-col justify-between rounded-2xl border border-[#E3EAF5] bg-[#F8FAFD] p-7 transition-[border-color,transform,box-shadow] duration-200 hover:-translate-y-1 hover:border-[#BFD0F3] hover:shadow-soft"
                                    >
                                        <span className="text-xs font-black tracking-[0.16em] text-[#9AA6BB]">
                                            0{index + 1}
                                        </span>
                                        <p className="mt-10 text-[15px] leading-[1.65] font-semibold text-[#19366E]">
                                            {problem}
                                        </p>
                                    </div>
                                ))}
                            </Reveal>
                        </Container>
                    </section>

                    <section
                        id="fitur-utama"
                        className="scroll-mt-28 bg-[#F8FAFD] py-20 sm:py-24 lg:py-28"
                    >
                        <Container>
                            <SectionHeading
                                size="compact"
                                eyebrow={`Yang bisa dilakukan dengan ${page.name}`}
                                title="Fitur yang bekerja sebagai satu alur, bukan kumpulan menu."
                                description="Semua bagian penting tersedia dalam konteks yang sama agar tim dapat bergerak lebih cepat dan lebih sedikit berpindah tempat."
                            />
                            <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {page.features.map(
                                    (
                                        {
                                            title,
                                            description,
                                            icon: Icon,
                                            status,
                                        },
                                        index,
                                    ) => (
                                        <Reveal
                                            key={title}
                                            delay={index * 0.035}
                                            className="group rounded-2xl border border-[#E3EAF5] bg-white p-7 shadow-[0_8px_30px_-24px_rgba(16,42,92,0.5)] transition-[border-color,transform,box-shadow] duration-200 hover:-translate-y-1 hover:border-[#BFD0F3] hover:shadow-avana-card"
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <span className="grid h-11 w-11 place-items-center rounded-xl bg-[#EAF0FF] text-[#315FD4] transition-colors group-hover:bg-[#315FD4] group-hover:text-white">
                                                    <Icon
                                                        className="h-5 w-5"
                                                        aria-hidden
                                                    />
                                                </span>
                                                {status === 'hold' ? (
                                                    <span className="rounded-full bg-[#FFF4D6] px-2.5 py-1 text-[10px] font-bold tracking-[0.08em] text-[#9A6A00] uppercase">
                                                        Segera hadir
                                                    </span>
                                                ) : (
                                                    <ArrowRight
                                                        className="h-4 w-4 text-[#B8C4D8] transition-transform duration-200 group-hover:translate-x-1 group-hover:text-[#315FD4]"
                                                        aria-hidden
                                                    />
                                                )}
                                            </div>
                                            <h3 className="mt-7 text-[15px] leading-7 font-bold text-[#0E1A3A]">
                                                {title}
                                            </h3>
                                            <p className="mt-3 text-[13px] leading-7 text-[#667085]">
                                                {description}
                                            </p>
                                        </Reveal>
                                    ),
                                )}
                            </div>
                        </Container>
                    </section>

                    <section
                        id="cara-kerja"
                        className="scroll-mt-28 border-y border-[#E8EEF8] bg-[#102A5C] py-20 text-white sm:py-24 lg:py-28"
                    >
                        <Container>
                            <SectionHeading
                                size="compact"
                                eyebrow="Cara kerja"
                                title={`Alur ${page.name} yang mudah diikuti tim.`}
                                description={`Proses ${page.name} dibuat terlihat dari input hingga hasil, sehingga setiap orang tahu apa yang perlu dilakukan berikutnya.`}
                                tone="dark"
                            />
                            <div
                                className={`relative mt-14 grid gap-5 ${workflowGridColumns}`}
                            >
                                <div
                                    aria-hidden
                                    className="pointer-events-none absolute top-7 right-[10%] left-[10%] hidden h-px bg-white/15 lg:block"
                                />
                                {page.steps.map((step, index) => (
                                    <Reveal
                                        key={step}
                                        delay={index * 0.05}
                                        className="relative flex min-h-[164px] flex-col rounded-2xl border border-white/15 bg-white/8 p-6 backdrop-blur-sm"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="grid h-8 w-8 place-items-center rounded-full bg-white text-sm font-black text-[#315FD4]">
                                                {index + 1}
                                            </span>
                                            {index < page.steps.length - 1 && (
                                                <ChevronRight
                                                    className="hidden h-5 w-5 text-blue-200/50 md:block"
                                                    aria-hidden
                                                />
                                            )}
                                        </div>
                                        <p className="mt-7 text-[10px] font-bold tracking-[0.16em] text-blue-200/65 uppercase">
                                            Langkah{' '}
                                            {String(index + 1).padStart(2, '0')}
                                        </p>
                                        <p className="mt-3 text-[13px] leading-7 font-semibold text-blue-50">
                                            {step}
                                        </p>
                                    </Reveal>
                                ))}
                            </div>
                        </Container>
                    </section>

                    <section
                        id="tampilan-produk"
                        className="scroll-mt-28 py-20 sm:py-24 lg:py-28"
                    >
                        <Container className="grid items-center gap-14 lg:grid-cols-[0.92fr_1.08fr] lg:gap-24">
                            <Reveal>
                                <ProductVisual
                                    featureSlug={featureSlug}
                                    page={page}
                                />
                            </Reveal>
                            <Reveal>
                                <span className="inline-flex items-center gap-2 rounded-full border border-[#E2E9F6] bg-[#F4F7FD] px-3.5 py-1 text-[12px] font-semibold tracking-[0.08em] text-[#2F54C9] uppercase">
                                    Tampilan produk
                                </span>
                                <h2 className="mt-6 text-[30px] leading-[1.25] font-bold tracking-[-0.03em] text-[#0E1A3A] sm:text-[34px] sm:leading-[1.22]">
                                    Satu tampilan untuk memahami pekerjaan yang
                                    sedang berjalan.
                                </h2>
                                <p className="mt-6 text-[15px] leading-[1.75] text-[#5B6478]">
                                    Asset visual {page.name} menempatkan konteks
                                    utama di depan: siapa yang terlibat, proses
                                    yang berjalan, dan hasil yang perlu
                                    ditindaklanjuti.
                                </p>
                                <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                                    {[
                                        'Informasi lebih mudah dipindai',
                                        'Status proses terlihat jelas',
                                        'Akses sesuai role',
                                        'Siap dihubungkan ke modul lain',
                                    ].map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-center gap-2 text-sm font-semibold text-[#19366E]"
                                        >
                                            <Check
                                                className="h-4 w-4 text-[#27B89B]"
                                                aria-hidden
                                            />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </Reveal>
                        </Container>
                    </section>

                    <section
                        id="manfaat"
                        className="scroll-mt-28 border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 sm:py-24 lg:py-28"
                    >
                        <Container className="grid gap-14 lg:grid-cols-[minmax(0,0.88fr)_minmax(0,1.12fr)] lg:gap-24">
                            <SectionHeading
                                size="compact"
                                eyebrow="Manfaat untuk tim"
                                title="Hasil yang terasa di pekerjaan sehari-hari."
                                description={`${page.name} membantu HR mengurangi pekerjaan berulang sambil menjaga data tetap siap digunakan oleh tim lain.`}
                                align="left"
                            />
                            <Reveal className="grid gap-5 sm:grid-cols-2">
                                {page.benefits.map((benefit) => (
                                    <div
                                        key={benefit}
                                        className="flex items-start gap-4 rounded-2xl border border-[#E3EAF5] bg-white p-7"
                                    >
                                        <CircleCheck
                                            className="mt-0.5 h-5 w-5 shrink-0 text-[#27B89B]"
                                            aria-hidden
                                        />
                                        <p className="text-sm leading-7 font-semibold text-[#19366E]">
                                            {benefit}
                                        </p>
                                    </div>
                                ))}
                            </Reveal>
                        </Container>
                    </section>

                    <section
                        id="integrasi"
                        aria-labelledby="integrasi-heading"
                        className="relative scroll-mt-28 overflow-hidden border-y border-[#DEE7F5] bg-[#F4F7FD] py-16 sm:py-20 lg:py-24"
                    >
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -top-28 -right-24 h-80 w-80 rounded-full bg-[#DCE8FF]/80 blur-3xl"
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -bottom-32 left-[18%] h-72 w-72 rounded-full bg-[#DDF5EF]/70 blur-3xl"
                        />
                        <Container className="relative grid items-center gap-10 lg:grid-cols-[minmax(0,0.72fr)_minmax(0,1.28fr)] lg:gap-16">
                            <Reveal className="max-w-xl">
                                <span className="inline-flex items-center rounded-full border border-[#D5E1F5] bg-white/80 px-3.5 py-1 text-[12px] font-semibold tracking-[0.08em] text-[#2F54C9] uppercase shadow-sm">
                                    Terhubung dengan modul lain
                                </span>
                                <h2
                                    id="integrasi-heading"
                                    className="mt-5 text-[30px] leading-[1.2] font-bold tracking-[-0.03em] text-balance text-[#0E1A3A] sm:text-[36px] lg:text-[42px]"
                                >
                                    Satu data, lanjut ke banyak proses.
                                </h2>
                                <p className="mt-5 max-w-lg text-[15px] leading-7 text-pretty text-[#5B6478] sm:text-base">
                                    Hasil dari {page.name} langsung siap dipakai
                                    oleh modul lain tanpa input ulang atau
                                    kehilangan konteks.
                                </p>

                                <div className="relative mt-8 flex max-w-sm items-center gap-4 rounded-2xl border border-[#254A9B] bg-[#102A5C] p-4 text-white shadow-[0_18px_45px_-24px_rgba(16,42,92,0.8)]">
                                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/10 ring-1 ring-white/15">
                                        <Network
                                            className="h-5 w-5 text-[#91E0CF]"
                                            aria-hidden
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[10px] font-bold tracking-[0.14em] text-blue-200/70 uppercase">
                                            Modul sumber
                                        </span>
                                        <span className="mt-1 block truncate text-sm font-bold">
                                            {page.name}
                                        </span>
                                    </span>
                                    <ArrowRight
                                        className="h-5 w-5 shrink-0 text-[#91E0CF]"
                                        aria-hidden
                                    />
                                    <span
                                        aria-hidden
                                        className="absolute top-1/2 left-full ml-3 hidden w-16 border-t border-dashed border-[#91A8D5] lg:block"
                                    />
                                </div>
                            </Reveal>

                            <Reveal className="rounded-[28px] border border-white/90 bg-white/85 p-3 shadow-[0_28px_80px_-40px_rgba(16,42,92,0.45)] ring-1 ring-[#DCE5F4]/80 backdrop-blur-sm sm:p-5">
                                <div className="flex items-center justify-between gap-4 px-2 py-2 sm:px-3">
                                    <span className="flex items-center gap-2 text-[11px] font-bold tracking-[0.12em] text-[#667085] uppercase">
                                        <span className="relative flex h-2.5 w-2.5">
                                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#27B89B]/40 motion-reduce:animate-none" />
                                            <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-[#27B89B]" />
                                        </span>
                                        Modul tujuan
                                    </span>
                                    <span className="rounded-full bg-[#EEF3FF] px-3 py-1 text-[11px] font-bold text-[#315FD4]">
                                        {relatedPages.length} terhubung
                                    </span>
                                </div>

                                <div className="mt-2 grid gap-3">
                                    {relatedPages.map(
                                        ({ slug, page: related }) => (
                                            <Link
                                                key={slug}
                                                href={featureUrl(slug)}
                                                prefetch
                                                aria-label={`Buka halaman fitur ${related.name}`}
                                                className="group relative flex min-h-24 items-center gap-4 overflow-hidden rounded-[20px] border border-[#E3EAF5] bg-[#F8FAFD] p-4 transition-[border-color,background-color,transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:border-[#B7C9EE] hover:bg-white hover:shadow-[0_16px_34px_-24px_rgba(16,42,92,0.55)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315FD4]"
                                            >
                                                <span className="grid h-16 w-16 shrink-0 place-items-center rounded-2xl border border-[#E5ECF8] bg-white shadow-[0_8px_24px_-18px_rgba(16,42,92,0.55)]">
                                                    <img
                                                        src={`/front/${related.asset}`}
                                                        alt=""
                                                        loading="lazy"
                                                        className="h-12 w-12 object-contain transition-transform duration-300 group-hover:scale-105"
                                                    />
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-[10px] font-bold tracking-[0.1em] text-[#72809A] uppercase">
                                                        {related.category}
                                                    </span>
                                                    <span className="mt-1 block text-[15px] font-bold text-[#19366E]">
                                                        {related.name}
                                                    </span>
                                                    <span className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-[#315FD4] sm:hidden">
                                                        Lihat modul
                                                        <ArrowRight
                                                            className="h-3.5 w-3.5"
                                                            aria-hidden
                                                        />
                                                    </span>
                                                </span>
                                                <span className="hidden shrink-0 items-center gap-2 rounded-full border border-[#DCE5F4] bg-white px-3.5 py-2 text-xs font-bold text-[#315FD4] transition-[border-color,background-color] group-hover:border-[#BFD0F3] group-hover:bg-[#F4F7FD] sm:inline-flex">
                                                    Lihat modul
                                                    <ArrowRight
                                                        className="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
                                                        aria-hidden
                                                    />
                                                </span>
                                            </Link>
                                        ),
                                    )}
                                </div>
                            </Reveal>
                        </Container>
                    </section>

                    <section
                        id="cocok-untuk"
                        className="scroll-mt-28 border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 sm:py-24 lg:py-28"
                    >
                        <Container className="grid gap-14 lg:grid-cols-[minmax(0,0.88fr)_minmax(0,1.12fr)] lg:gap-24">
                            <SectionHeading
                                size="compact"
                                eyebrow="Cocok untuk siapa"
                                title="Dibuat untuk cara kerja perusahaan yang nyata."
                                description="Mulai dari tim kecil sampai organisasi multi cabang, pilih alur yang paling dekat dengan kebutuhan Anda."
                                align="left"
                            />
                            <Reveal className="grid gap-5 sm:grid-cols-2">
                                {page.audience.map((item) => (
                                    <div
                                        key={item}
                                        className="rounded-2xl border border-[#E3EAF5] bg-white p-7"
                                    >
                                        <p className="text-sm font-bold text-[#19366E]">
                                            {item}
                                        </p>
                                        <p className="mt-3 text-xs leading-6 text-[#667085]">
                                            {page.name} dapat disesuaikan dengan
                                            struktur, role, dan ritme kerja tim
                                            Anda.
                                        </p>
                                    </div>
                                ))}
                            </Reveal>
                        </Container>
                    </section>

                    <section className="border-b border-[#EDF1F8] py-16 lg:py-20">
                        <Container>
                            <Reveal className="flex flex-col items-start justify-between gap-6 rounded-2xl border border-[#DCE5F4] bg-white p-6 shadow-soft sm:flex-row sm:items-center sm:p-8">
                                <div className="flex items-start gap-4">
                                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#EAF0FF] text-[#315FD4]">
                                        <LockKeyhole
                                            className="h-5 w-5"
                                            aria-hidden
                                        />
                                    </span>
                                    <div>
                                        <h2 className="text-base font-bold text-[#0E1A3A]">
                                            Keamanan tetap menjadi fondasi.
                                        </h2>
                                        <p className="mt-1 max-w-2xl text-sm leading-6 text-[#667085]">
                                            Gunakan role-based access, audit
                                            trail, dan proteksi dokumen untuk
                                            menjaga data {page.name} tetap
                                            berada di tangan yang tepat.
                                        </p>
                                    </div>
                                </div>
                                <Link
                                    href={security().url}
                                    className="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full border border-[#D9E0EE] px-5 text-sm font-bold text-[#19366E] transition-colors hover:border-[#315FD4] hover:text-[#315FD4] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#315FD4]"
                                >
                                    Lihat keamanan{' '}
                                    <ArrowRight
                                        className="h-4 w-4"
                                        aria-hidden
                                    />
                                </Link>
                            </Reveal>
                        </Container>
                    </section>

                    <FaqSection
                        items={page.faqs}
                        eyebrow={`FAQ ${page.name}`}
                        title={`Pertanyaan tentang ${page.name}`}
                    />
                    <FinalCta
                        title={
                            <>
                                Siap membuat pengelolaan{' '}
                                <span className="text-blue-400">
                                    {page.name}
                                </span>{' '}
                                lebih sederhana?
                            </>
                        }
                        body={`Jadwalkan demo dan lihat bagaimana AvanaHR membantu tim Anda mengelola ${page.name.toLowerCase()} dalam satu platform terpadu.`}
                        supporting="Mulai dari kebutuhan hari ini, lalu kembangkan bersama modul lain saat perusahaan bertumbuh."
                    />
                </main>

                <SiteFooter brand={brand} logo={logo} anchorPrefix="/" />
                <WhatsAppFab />
            </div>
        </>
    );
}
