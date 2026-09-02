import {
    Database,
    EyeOff,
    Fingerprint,
    FolderLock,
    History,
    KeyRound,
    Lock,
    ServerCog,
    UserCog,
    Users,
} from 'lucide-react';
import type { Faq } from '../content';
import type { Icon } from '../content';

/**
 * Copy and illustrations for the public "Keamanan" (Security) page.
 *
 * Every list here restates a control that actually exists in the product —
 * see `[[security-hardening]]`, `[[two-factor-auth]]`,
 * `[[public-disk-document-exposure]]`, `[[dynamic-permission]]`,
 * `[[approval-control-rules]]` and `[[face-recognition-attendance]]` in the
 * engineering memory. Nothing here is aspirational copy.
 */

const IMG = '/avana/landing/images/keamanan';

export const HERO_IMAGE = `${IMG}/hero.png`;

/** Short value strip under the hero. */
export const HERO_HIGHLIGHTS: { label: string; icon: Icon }[] = [
    { label: 'Enkripsi Data Pribadi', icon: Lock },
    { label: 'Two-Factor Authentication', icon: KeyRound },
    { label: 'Role-Based Access Control', icon: UserCog },
    { label: 'Four-Eyes Approval', icon: Users },
    { label: 'Audit Trail', icon: History },
];

/** Section 1 — Keamanan Akun & Sesi. No dedicated illustration — checklist card instead. */
export const ACCOUNT_SECTION = {
    points: [
        'Proteksi login dan temporary lockout.',
        'Two-Factor Authentication (2FA/TOTP).',
        'Pemantauan perangkat dan sesi aktif.',
        'Notifikasi ketika perangkat baru terdeteksi.',
        'Riwayat login berhasil maupun gagal.',
        'Pencabutan akses perangkat secara manual.',
        'Sign out dari seluruh perangkat.',
        'Penggantian password secara aman.',
        'Single-device binding untuk aplikasi mobile.',
        'Pencabutan token perangkat lama setelah device reset.',
        'Proteksi terhadap perangkat root/jailbreak dan manipulasi lokasi.',
    ],
};

/** Section 2 — Proteksi Koneksi & Security Headers. */
export const HEADERS_SECTION = {
    image: `${IMG}/security-headers.png`,
    imageAlt: 'Security headers dan TLS pada setiap koneksi AvanaHR',
    headers: [
        'X-Content-Type-Options',
        'Referrer-Policy',
        'X-Frame-Options',
        'Permissions-Policy',
        'Cross-Origin-Opener-Policy',
        'Cross-Origin-Resource-Policy',
        'Strict-Transport-Security (HTTPS produksi)',
        'Content-Security-Policy',
    ],
};

/** Section 3 — Enkripsi & Perlindungan Data Pribadi. No dedicated illustration; icon-card layout instead. */
export const ENCRYPTION_POINTS: { icon: Icon; title: string; body: string }[] =
    [
        {
            icon: Lock,
            title: 'Enkripsi di Level Aplikasi',
            body: 'NIK karyawan, NIK/NPWP perpajakan, dan nomor rekening disimpan dalam bentuk terenkripsi.',
        },
        {
            icon: Fingerprint,
            title: 'Hash untuk Cek Duplikasi',
            body: 'Deteksi NIK ganda memakai nilai hash terpisah, tanpa perlu membaca data aslinya.',
        },
        {
            icon: EyeOff,
            title: 'Data Masking',
            body: 'Pengguna tanpa kewenangan hanya melihat sebagian data sensitif, mis. 327***********78.',
        },
        {
            icon: FolderLock,
            title: 'Hak Data Pribadi (UU PDP 27/2022)',
            body: 'Unduh salinan data pribadi dalam format terstruktur, atau ajukan penghapusan permanen lewat anonimisasi.',
        },
    ];

export const MASKING_EXAMPLES = ['327***********78', '1234 **** **** 5678'];

/** Section 4 — Backup & Deteksi Aktivitas Tidak Wajar. */
export const BACKUP_SECTION = {
    image: `${IMG}/backup-anomali.png`,
    imageAlt: 'Backup database terjadwal dan deteksi anomali aktivitas',
    points: [
        'Backup database berjalan terjadwal, dikompresi sebelum disimpan.',
        'Validasi memastikan file backup benar-benar terbentuk.',
        'Deteksi percobaan login berulang.',
        'Deteksi login di luar jam kerja.',
        'Deteksi satu akun dari banyak alamat IP.',
        'Deteksi lonjakan aktivitas ekspor data.',
        'Alert tidak berulang untuk temuan yang sama.',
    ],
};

/** Section 5 — File & Dokumen Privat. */
export const FILES_SECTION = {
    image: `${IMG}/file-privat.png`,
    imageAlt: 'Penyimpanan dokumen privat dengan akses signed URL',
    fileTypes: [
        'Dokumen karyawan',
        'Bukti/struk reimbursement',
        'Selfie absensi',
        'Foto karyawan',
        'Avatar',
        'Dokumen internal lainnya',
    ],
    points: [
        'File tidak dapat diakses hanya dengan mengetahui lokasinya.',
        'Akses melalui jalur aplikasi tervalidasi memakai signed access.',
        'Sistem tetap memeriksa hak pengguna sebelum dokumen dibuka.',
        'Tersedia proses migrasi file lama dari penyimpanan publik ke privat.',
    ],
};

/** Section 6 — Role-Based Access Control. */
export const RBAC_SECTION = {
    image: `${IMG}/rbac.png`,
    imageAlt: 'Matrix hak akses per role dan aksi di AvanaHR',
    actions: ['View', 'Create', 'Update', 'Archive', 'Export', 'Approve'],
    points: [
        'Matrix permission per role, bukan sekadar nama role.',
        'Per-user override — beri atau cabut izin individual.',
        'Permission khusus aplikasi mobile.',
        'Proteksi self-lockout administrator.',
        'Super Admin tidak dapat dimodifikasi lewat tenant.',
        'Kill-switch — nonaktifkan akses secara cepat.',
        'Permission otomatis untuk modul atau fitur baru.',
        'Tenant isolation pada query dan service aplikasi.',
    ],
};

/** Section 7 — Four-Eyes Approval. */
export const FOUR_EYES_SECTION = {
    image: `${IMG}/four-eyes.png`,
    imageAlt: 'Alur persetujuan four-eyes pada proses reimbursement',
    flows: ['Reimbursement', 'Settlement', 'Cash advance settlement', 'Cash advance disbursement'],
    points: [
        'Sistem memeriksa siapa yang bertindak di tiap tahap.',
        'Hanya approver tahap tersebut yang dapat menyetujui.',
        'Approver tidak bisa sekaligus mencairkan dana yang sama.',
        'HR/Super Admin tetap bisa override, tercatat di audit trail.',
    ],
};

/** Section 8 — Audit Trail & Accountability. */
export const AUDIT_SECTION = {
    image: `${IMG}/audit-trail.png`,
    imageAlt: 'Dashboard audit trail mencatat aktivitas pengguna',
    points: [
        'Pengguna yang melakukan tindakan.',
        'Jenis dan waktu aktivitas.',
        'Perubahan data.',
        'Proses approval.',
        'Login dan aktivitas akun.',
        'Payroll locking.',
        'KPI review.',
        'Aktivitas administratif lainnya.',
    ],
};

/** Section 9 — Proteksi Tambahan pada Mobile & Absensi. */
export const MOBILE_SECTION = {
    image: `${IMG}/mobile-absensi.png`,
    imageAlt: 'Proteksi perangkat mobile dan verifikasi wajah 1:1',
    points: [
        'Deteksi akun mobile tanpa relasi employee yang valid — akses dihentikan sebelum masuk ke fitur utama.',
        'Single-device binding.',
        'Root/jailbreak protection.',
        'Fake GPS detection.',
        'Revocation token setelah pergantian atau reset perangkat.',
        'Device validation.',
    ],
};

/** "Keamanan yang terus dievaluasi" — the two deliberately-staged rollout items. */
export const ROLLOUT_STATUS: {
    icon: Icon;
    title: string;
    body: string;
}[] = [
    {
        icon: ServerCog,
        title: 'Content Security Policy',
        body: 'CSP saat ini berjalan dalam mode report-only untuk memantau potensi konflik dengan resource yang digunakan aplikasi, sebelum enforcement penuh diaktifkan.',
    },
    {
        icon: Database,
        title: 'External Backup Storage',
        body: 'Backup database sudah berjalan terjadwal; pemindahan backup produksi ke storage terpisah dari server utama masih dalam tahap finalisasi.',
    },
];

export const CLOSING_KEYWORDS = [
    'Secure Access',
    'Data Encryption',
    'Device Protection',
    'Private Storage',
    'Granular Permissions',
    'Four-Eyes Approval',
    'Audit Trail',
    'Anomaly Detection',
];

export const SECURITY_FAQS: Faq[] = [
    {
        q: 'Apakah Two-Factor Authentication wajib diaktifkan?',
        a: 'Tidak. 2FA berbasis TOTP bersifat opt-in dan dapat diaktifkan setiap pengguna dari halaman Keamanan Akun, baik di web maupun aplikasi mobile.',
    },
    {
        q: 'Bagaimana AvanaHR melindungi NIK, NPWP, dan nomor rekening karyawan?',
        a: 'Data tersebut disimpan dalam bentuk terenkripsi di level aplikasi. Pengguna tanpa kewenangan hanya melihat versi masking-nya, bukan data aslinya.',
    },
    {
        q: 'Apakah Content Security Policy sudah aktif penuh?',
        a: 'Saat ini CSP berjalan dalam mode report-only untuk memantau kompatibilitas resource sebelum enforcement penuh diaktifkan di production.',
    },
    {
        q: 'Siapa yang bisa menyetujui pencairan reimbursement, settlement, atau cash advance?',
        a: 'Alur ini memakai kontrol four-eyes — approver dan pihak yang mencairkan dana harus berbeda orang, kecuali override oleh HR/Super Admin yang tercatat di audit trail.',
    },
    {
        q: 'Apakah karyawan dapat mengunduh atau menghapus data pribadinya sendiri?',
        a: 'Ya. Sesuai UU PDP No. 27 Tahun 2022, tersedia mekanisme unduh salinan data pribadi dan pengajuan penghapusan melalui anonimisasi.',
    },
];
