export type PublicFeatureStatus = 'live' | 'hold';

export type PublicFeatureItem = {
    title: string;
    description: string;
    icon: string;
    status?: PublicFeatureStatus;
};

export type PublicFeatureCatalogEntry = {
    slug: string;
    name: string;
    category: string;
    asset: string;
    title: string;
    description: string;
    heroNote: string;
    problems: string[];
    items: PublicFeatureItem[];
    steps: string[];
    benefits: string[];
    audience: string[];
    faqs: { q: string; a: string }[];
    related: string[];
};

const page = (
    entry: Omit<PublicFeatureCatalogEntry, 'slug'> & { slug: string },
): PublicFeatureCatalogEntry => entry;

/**
 * Public product copy is deliberately kept in one typed catalog. The same
 * entries power feature detail pages and the product navigation, so a new
 * public capability cannot silently become an orphaned page.
 */
export const PUBLIC_FEATURE_CATALOG: Record<string, PublicFeatureCatalogEntry> =
    {
        ess: page({
            slug: 'ess',
            name: 'Employee Self Service',
            category: 'HR Core',
            asset: 'ess-mobile.png',
            title: 'Beri karyawan akses mandiri yang tetap terkontrol',
            description:
                'Satu ruang bagi karyawan untuk mengelola data, absensi, payroll, pengembangan, dan permintaan HR tanpa menambah pekerjaan administratif tim HR.',
            heroNote: 'Mandiri untuk karyawan, terarah untuk HR.',
            problems: [
                'Permintaan sederhana masih masuk lewat chat pribadi.',
                'Karyawan sulit melihat status pengajuan dan saldo mereka.',
                'HR harus menyalin data yang sebenarnya sudah dimiliki karyawan.',
            ],
            items: [
                {
                    title: 'Data Pribadi dan Pekerjaan',
                    description:
                        'Karyawan dapat melihat dan memperbarui informasi pribadi serta data pekerjaannya dari satu profil.',
                    icon: 'ContactRound',
                },
                {
                    title: 'Catatan Kehadiran',
                    description:
                        'Lihat timesheet, saldo cuti, pengajuan lembur, perjalanan dinas, dan pertukaran shift dalam satu alur.',
                    icon: 'Clock3',
                },
                {
                    title: 'Sistem Penggajian (Payroll)',
                    description:
                        'Akses riwayat gaji, komponen upah, dan kalkulasi pajak dengan proteksi yang sesuai.',
                    icon: 'WalletCards',
                },
                {
                    title: 'Pelatihan dan Pembelajaran',
                    description:
                        'Temukan pelatihan, ajukan partisipasi, dan simpan feedback untuk mendukung pengembangan berkelanjutan.',
                    icon: 'GraduationCap',
                },
                {
                    title: 'Kompensasi dan Tunjangan',
                    description:
                        'Ajukan klaim dengan lampiran pendukung dan pantau saldo penggantian biaya secara transparan.',
                    icon: 'ReceiptText',
                },
                {
                    title: 'Manajemen Pinjaman',
                    description:
                        'Ajukan pinjaman, pantau cicilan, dan lihat rincian pelunasan yang terhubung ke payroll.',
                    icon: 'Landmark',
                },
                {
                    title: 'Penilaian Kinerja',
                    description:
                        'Pantau target OKR, feedback, dan formulir evaluasi dari ruang kerja pribadi.',
                    icon: 'Target',
                },
                {
                    title: 'Onboarding Karyawan',
                    description:
                        'Selesaikan checklist, orientasi, dan pembaruan profil sejak hari pertama.',
                    icon: 'UserCheck',
                },
                {
                    title: 'Manajemen Proyek',
                    description:
                        'Akses proyek, tugas harian, dan to-do list untuk kolaborasi yang lebih teratur.',
                    icon: 'ListChecks',
                },
                {
                    title: 'Internal Helpdesk dan Sistem Tiket',
                    description:
                        'Cari jawaban lewat FAQ atau kirim tiket dengan status penyelesaian yang mudah dipantau.',
                    icon: 'TicketCheck',
                },
                {
                    title: 'Employee Engagement',
                    description:
                        'Ikuti pengumuman, kalender, kebijakan, dan aktivitas di Ruang Kita; lihat leaderboard kontribusi karyawan untuk menjaga engagement tetap hidup.',
                    icon: 'Users',
                },
                {
                    title: 'AI-Assistant',
                    description:
                        'Tanyakan kebijakan atau minta panduan tugas sederhana melalui asisten AI yang memahami konteks perusahaan.',
                    icon: 'Sparkles',
                },
            ],
            steps: [
                'Karyawan masuk ke ruang ESS sesuai aksesnya.',
                'Pilih data, layanan, atau permintaan yang dibutuhkan.',
                'Ajukan perubahan bila memerlukan persetujuan atasan.',
                'Pantau status dan riwayat tanpa follow-up manual.',
            ],
            benefits: [
                'Beban pertanyaan berulang di tim HR berkurang.',
                'Karyawan memiliki visibilitas atas data dan pengajuannya.',
                'Perubahan data meninggalkan jejak dan persetujuan yang jelas.',
                'Pengalaman karyawan konsisten di desktop maupun mobile.',
            ],
            audience: [
                'Perusahaan dengan banyak permintaan HR harian',
                'Tim hybrid dan multi-cabang',
            ],
            faqs: [
                {
                    q: 'Apakah semua data bisa diubah sendiri oleh karyawan?',
                    a: 'Tidak. Akses perubahan mengikuti role dan jenis data. Data tertentu dapat memerlukan persetujuan atasan atau HR.',
                },
                {
                    q: 'Apakah pengajuan karyawan bisa dilacak?',
                    a: 'Bisa. Status, riwayat, dan langkah persetujuan ditampilkan pada ruang ESS.',
                },
            ],
            related: [
                'data-karyawan',
                'cuti-dan-izin',
                'payroll',
                'hr-helpdesk',
            ],
        }),
        'otomatisasi-alur-kerja': page({
            slug: 'otomatisasi-alur-kerja',
            name: 'Otomatisasi Alur Kerja',
            category: 'HR Core',
            asset: 'core-hr.png',
            title: 'Jadikan proses HR lebih konsisten dengan workflow yang jelas',
            description:
                'Atur pengajuan, penugasan, dan persetujuan lintas divisi dalam alur yang mudah dipantau dari awal sampai selesai.',
            heroNote: 'Aturan jelas. Persetujuan tercatat. Proses lebih cepat.',
            problems: [
                'Pengajuan berjalan lewat jalur yang berbeda-beda.',
                'Status persetujuan sulit diketahui tanpa bertanya.',
                'Tugas mudah tertahan saat berpindah antar tim.',
            ],
            items: [
                {
                    title: 'Request HR dan karyawan',
                    description:
                        'Kelola cuti, klaim penggantian biaya, dan pembaruan data melalui formulir yang terstruktur.',
                    icon: 'FileCheck2',
                },
                {
                    title: 'Performa kerja dan kepatuhan',
                    description:
                        'Susun evaluasi, penyesuaian nilai, dan siklus feedback dengan tahap yang konsisten.',
                    icon: 'Target',
                },
                {
                    title: 'Keuangan dan operasional',
                    description:
                        'Sederhanakan persetujuan anggaran, transfer dana, dan administrasi pembayaran.',
                    icon: 'Landmark',
                },
                {
                    title: 'Penugasan dan persetujuan tugas',
                    description:
                        'Tetapkan pemilik tugas, tenggat, dan langkah berikutnya agar kolaborasi lintas divisi tetap jelas.',
                    icon: 'GitBranch',
                },
            ],
            steps: [
                'Tentukan jenis request dan aturan persetujuannya.',
                'Request masuk ke pemilik proses yang tepat.',
                'Pihak terkait meninjau dan memberi keputusan.',
                'Sistem mencatat hasil dan meneruskan langkah berikutnya.',
            ],
            benefits: [
                'Tidak ada lagi proses penting yang bergantung pada chat pribadi.',
                'Pemilik tugas dan approver terlihat sejak awal.',
                'Audit trail siap digunakan untuk evaluasi dan kepatuhan.',
                'Proses baru dapat ditambahkan tanpa mengubah pola kerja tim lain.',
            ],
            audience: [
                'Tim HR dan Finance yang berbagi proses persetujuan',
                'Organisasi dengan struktur approval berjenjang',
            ],
            faqs: [
                {
                    q: 'Apakah alur dapat mengikuti struktur organisasi?',
                    a: 'Bisa. Penanggung jawab dapat ditentukan berdasarkan role, atasan, departemen, atau aturan yang dikonfigurasi.',
                },
                {
                    q: 'Bagaimana jika request ditolak?',
                    a: 'Keputusan dan catatannya tersimpan pada riwayat request sehingga pemohon tahu tindakan berikutnya.',
                },
            ],
            related: ['struktur-organisasi', 'data-karyawan', 'hr-helpdesk'],
        }),
        pelatihan: page({
            slug: 'pelatihan',
            name: 'Pelatihan',
            category: 'Learning & Development',
            asset: 'feature-assets/ilustrasi_dashboard_pelatihan_avanahr.png',
            title: 'Bangun learning path yang selaras dengan kebutuhan bisnis',
            description:
                'Rencanakan pembelajaran berdasarkan role, minat, dan hasil kinerja agar upskilling tidak berhenti sebagai daftar kelas.',
            heroNote: 'Belajar lebih relevan karena terhubung ke tujuan kerja.',
            problems: [
                'Katalog training tidak selalu menjawab gap kompetensi.',
                'Riwayat pembelajaran tersebar dan sulit dievaluasi.',
                'HR kesulitan mengukur dampak program terhadap bisnis.',
            ],
            items: [
                {
                    title: 'Flexible & scalable',
                    description:
                        'Dukung pembelajaran role-based maupun interest-based untuk upskilling, reskilling, dan jalur pengembangan personal yang selaras dengan target bisnis serta pertumbuhan karyawan. Susun learning path yang dapat dikustomisasi dan skalakan program lintas divisi maupun level jabatan.',
                    icon: 'GitBranch',
                },
                {
                    title: 'Pelatihan berbasis kinerja',
                    description:
                        'Rekomendasikan program training secara otomatis berdasarkan performance review, succession planning, dan IDP. Petakan kebutuhan berdasarkan kompetensi, gunakan hasil evaluasi kinerja serta perencanaan karier, dan hemat waktu analisis kebutuhan pelatihan.',
                    icon: 'Target',
                },
                {
                    title: 'Pengembangan selaras bisnis',
                    description:
                        'Bangun tenaga kerja dinamis yang mampu mengikuti perubahan bisnis melalui pengembangan keterampilan yang lebih cepat, kesempatan belajar di luar bidang keahlian, perpaduan pelatihan langsung dan daring, serta perbandingan biaya program dengan efektivitas dan dampaknya.',
                    icon: 'TrendingUp',
                },
                {
                    title: 'Engaging virtual learning',
                    description:
                        'Integrasikan konten pembelajaran yang relevan ke ritme kerja harian agar dapat diakses kapan pun, disajikan dalam beragam format, serta dapat dilacak dan didukung feedback. Gunakan kuis, tes, dan survei untuk memperkuat hasil belajar.',
                    icon: 'Sparkles',
                },
            ],
            steps: [
                'Petakan role, kompetensi, dan tujuan pembelajaran.',
                'Susun learning path atau rekomendasi program.',
                'Karyawan mengikuti materi dan aktivitas yang tersedia.',
                'Pantau progres, feedback, dan dampaknya terhadap kinerja.',
            ],
            benefits: [
                'Kebutuhan training lebih mudah diprioritaskan.',
                'Karyawan memiliki arah pengembangan yang terlihat.',
                'Hasil pembelajaran terdokumentasi dalam satu profil.',
                'Investasi learning dapat dievaluasi dengan konteks kinerja.',
            ],
            audience: [
                'Organisasi yang sedang membangun learning culture',
                'Tim L&D dengan program lintas role',
            ],
            faqs: [
                {
                    q: 'Apakah learning path dapat dibuat per jabatan?',
                    a: 'Bisa. Jalur belajar dapat disusun berdasarkan role, kompetensi, minat, atau kebutuhan pengembangan tertentu.',
                },
                {
                    q: 'Apakah progres training dapat dipantau?',
                    a: 'Bisa, termasuk status materi, hasil aktivitas, dan feedback peserta sesuai data yang tersedia.',
                },
            ],
            related: ['manajemen-kinerja', 'manajemen-talenta', 'ess'],
        }),
        'time-management': page({
            slug: 'time-management',
            name: 'Time Management',
            category: 'Attendance & Lapangan',
            asset: 'feature-assets/dasbor_manajemen_waktu_avanahr.png',
            title: 'Kelola waktu kerja dengan aturan yang bisa diikuti semua orang',
            description:
                'Satukan penjadwalan, kehadiran, timesheet, dan koreksi waktu untuk mendukung operasional yang fleksibel sekaligus patuh.',
            heroNote: 'Jadwal yang jelas, data waktu yang dapat dipercaya.',
            problems: [
                'Jadwal dan perubahan shift tidak tersampaikan dengan cepat.',
                'Timesheet kosong baru diketahui ketika payroll sudah dekat.',
                'Aturan waktu kerja berbeda antar tim dan lokasi.',
            ],
            items: [
                {
                    title: 'Sederhanakan & Otomatisasi Proses untuk Kepatuhan Regulasi',
                    description:
                        'Sesuaikan aturan waktu kerja dengan kebutuhan bisnis, kebijakan perusahaan, operasional global, dan regulasi lokal. Otomatiskan proses serta persetujuan lintas perangkat, penjadwalan shift, pengingat timesheet, dan pemantauan jam kerja mingguan serta biaya proyek agar aturan payroll lebih jelas dan risiko ketidakpatuhan berkurang.',
                    icon: 'ShieldAlert',
                },
                {
                    title: 'Hadirkan Pengalaman Karyawan yang Lebih Baik',
                    description:
                        'Rencanakan jadwal melalui fungsi penjadwalan yang intuitif dan gunakan notifikasi untuk mencegah penugasan berlebih. Karyawan dapat mengubah shift dengan quick apply, menerima jadwal langsung di ponsel, melihat agenda harian di kalender, serta mengajukan cuti, lembur, atau tugas.',
                    icon: 'CalendarDays',
                },
                {
                    title: 'Pusat Data dari Berbagai Sumber',
                    description:
                        'Satukan data kehadiran dari Avana HR, kios, dan perangkat eksternal dengan GPS tagging serta pengenalan wajah. Karyawan dapat clock-in/out lewat ponsel, menggunakan geo-fencing, mengajukan koreksi mandiri, dan mencatat kehadiran saat offline. Deteksi keterlambatan dan ketidakhadiran secara otomatis, serta gunakan variabel sederhana atau kompleks dari berbagai dimensi sebagai dasar perhitungan.',
                    icon: 'ScanFace',
                },
            ],
            steps: [
                'Tentukan aturan, lokasi, pola shift, dan kalender kerja.',
                'Publikasikan jadwal ke karyawan dan perangkat yang dipakai.',
                'Kumpulkan kehadiran serta timesheet dari berbagai sumber.',
                'Tinjau koreksi dan teruskan data yang sudah valid ke payroll.',
            ],
            benefits: [
                'Perencanaan shift lebih mudah dibaca oleh karyawan.',
                'Pengingat membantu mengurangi timesheet yang terlambat.',
                'Data kehadiran lebih konsisten untuk payroll dan analitik.',
                'Aturan lokal dan kebutuhan operasional dapat hidup berdampingan.',
            ],
            audience: [
                'Operasional shift dan multi-lokasi',
                'Perusahaan dengan karyawan lapangan atau hybrid',
            ],
            faqs: [
                {
                    q: 'Apakah karyawan dapat mengajukan koreksi waktu?',
                    a: 'Bisa, dengan alur review sesuai kebijakan dan otorisasi perusahaan.',
                },
                {
                    q: 'Apakah data dapat bekerja saat koneksi tidak stabil?',
                    a: 'Sumber kehadiran yang mendukung mode tersebut dapat menyimpan catatan dan menyinkronkannya saat koneksi kembali.',
                },
            ],
            related: ['absensi-karyawan', 'cuti-dan-izin', 'payroll'],
        }),
        'manajemen-talenta': page({
            slug: 'manajemen-talenta',
            name: 'Manajemen Talenta',
            category: 'Talent Management',
            asset: 'feature-assets/dasbor_manajemen_talenta_avanahr.png',
            title: 'Hubungkan tujuan, kinerja, dan pertumbuhan talenta',
            description:
                'Bantu manajer dan karyawan melihat hubungan antara OKR, feedback, pengembangan, karier, dan kesiapan suksesi.',
            heroNote:
                'Talenta tumbuh ketika tujuan dan dukungan berjalan bersama.',
            problems: [
                'Target tahunan tidak selalu terhubung ke pekerjaan harian.',
                'Feedback dan pengembangan berjalan terpisah dari evaluasi.',
                'Perencanaan suksesi masih mengandalkan ingatan dan spreadsheet.',
            ],
            items: [
                {
                    title: 'Selaraskan tujuan dengan OKR',
                    description:
                        'Turunkan prioritas perusahaan menjadi tujuan departemen dan individu yang dapat dipantau bersama.',
                    icon: 'Target',
                },
                {
                    title: 'Pantau kinerja lebih mudah',
                    description:
                        'Integrasikan target jangka panjang, tugas harian, feedback, dan penilaian 360 derajat.',
                    icon: 'TrendingUp',
                },
                {
                    title: 'Evaluasi dan feedback bermakna',
                    description:
                        'Kumpulkan perspektif atasan, rekan kerja, pihak terkait, dan self-assessment dalam evaluasi yang utuh.',
                    icon: 'MessageSquareText',
                },
                {
                    title: 'Manajemen kinerja berkelanjutan',
                    description:
                        'Buat tugas dan feedback berkala sebagai dasar keputusan kenaikan gaji, karier, dan pengembangan.',
                    icon: 'CircleCheck',
                },
                {
                    title: 'Perencanaan karier yang jelas',
                    description:
                        'Gunakan career mapping, analisis gap keterampilan, dan rencana pengembangan yang personal.',
                    icon: 'Network',
                },
                {
                    title: 'Suksesi karier masa depan',
                    description:
                        'Identifikasi penerus untuk posisi kunci berdasarkan kesiapan dan kebutuhan organisasi.',
                    icon: 'Users',
                },
                {
                    title: 'Rekrutmen yang efektif',
                    description:
                        'Hubungkan workforce planning, seleksi, stakeholder feedback, hingga onboarding.',
                    icon: 'UserPlus',
                },
                {
                    title: 'Pengembangan dan pelatihan terarah',
                    description:
                        'Pantau tujuan, rencana pengembangan, dan pencapaian dalam budaya belajar yang berkelanjutan.',
                    icon: 'GraduationCap',
                },
            ],
            steps: [
                'Tetapkan prioritas dan target organisasi.',
                'Turunkan target ke role, tim, dan individu.',
                'Lakukan check-in, feedback, dan evaluasi berkala.',
                'Gunakan hasilnya untuk pengembangan dan keputusan talenta.',
            ],
            benefits: [
                'Manajer memiliki konteks yang lebih utuh saat memberi feedback.',
                'Karyawan memahami kontribusinya terhadap tujuan perusahaan.',
                'Gap kompetensi lebih mudah diterjemahkan menjadi rencana pengembangan.',
                'Posisi penting memiliki gambaran kesiapan penerus.',
            ],
            audience: [
                'Perusahaan yang menerapkan OKR atau performance cycle',
                'Organisasi yang sedang menyiapkan succession planning',
            ],
            faqs: [
                {
                    q: 'Apakah OKR dan performance review dapat berjalan bersama?',
                    a: 'Bisa. Keduanya dapat dihubungkan agar evaluasi mempertimbangkan target dan perkembangan sepanjang periode.',
                },
                {
                    q: 'Apakah data talenta dapat dibatasi berdasarkan role?',
                    a: 'Bisa. Visibilitas dan akses mengikuti role serta kebijakan data perusahaan.',
                },
            ],
            related: [
                'manajemen-kinerja',
                'pelatihan',
                'administrasi-karier',
                'kpi',
            ],
        }),
        'compensation-benefits': page({
            slug: 'compensation-benefits',
            name: 'Compensation & Benefits',
            category: 'Compensation & Benefit',
            asset: 'feature-assets/dasbor_kompensasi_avanahr_modern.png',
            title: 'Rancang kompensasi dan tunjangan yang fleksibel',
            description:
                'Atur aturan kelayakan, anggaran, tanggungan, dan penerima manfaat sambil menjaga data tetap sinkron dengan payroll.',
            heroNote: 'Skema fleksibel dengan aturan yang tetap terukur.',
            problems: [
                'Skema tunjangan sulit dikelola ketika kriteria karyawan beragam.',
                'Perubahan benefit harus dicocokkan ulang dengan payroll.',
                'Tim kesulitan melihat penggunaan anggaran secara menyeluruh.',
            ],
            items: [
                {
                    title: 'Konfigurasi tunjangan fleksibel',
                    description:
                        'Tentukan aturan kelayakan, anggaran, dependensi, tanggungan, dan penerima manfaat sesuai kebijakan perusahaan.',
                    icon: 'WalletCards',
                },
            ],
            steps: [
                'Tentukan jenis benefit dan kriteria kelayakannya.',
                'Atur anggaran, tanggungan, dan periode berlaku.',
                'Karyawan mengakses atau mengajukan benefit sesuai haknya.',
                'Data benefit disinkronkan ke payroll dan laporan.',
            ],
            benefits: [
                'Skema benefit dapat mengikuti level dan kebijakan perusahaan.',
                'Perubahan hak karyawan lebih mudah ditelusuri.',
                'Administrasi benefit dan payroll bekerja dari konteks data yang sama.',
            ],
            audience: [
                'Perusahaan dengan paket benefit beragam',
                'Tim HR dan Finance yang membutuhkan kontrol anggaran',
            ],
            faqs: [
                {
                    q: 'Apakah benefit dapat memiliki aturan kelayakan berbeda?',
                    a: 'Bisa. Setiap skema dapat memiliki kriteria, anggaran, dan dependensi tersendiri.',
                },
            ],
            related: ['payroll', 'reimbursement', 'ess'],
        }),
        'loans-management': page({
            slug: 'loans-management',
            name: 'Loans Management',
            category: 'Compensation & Benefit',
            asset: 'feature-assets/dasbor_manajemen_pinjaman_avanahr.png',
            title: 'Kelola pinjaman karyawan sampai pelunasan dengan rapi',
            description:
                'Dukung berbagai skema pinjaman dengan kriteria, bunga, pembayaran, dan integrasi payroll yang dapat disesuaikan.',
            heroNote: 'Saldo, cicilan, dan pelunasan terlihat dalam satu alur.',
            problems: [
                'Perhitungan pinjaman masih dikelola di file terpisah.',
                'Perubahan saldo tidak langsung terlihat oleh karyawan dan HR.',
                'Sisa pinjaman berisiko terlewat saat offboarding.',
            ],
            items: [
                {
                    title: 'Jenis pinjaman yang dapat disesuaikan',
                    description:
                        'Buat skema kendaraan, perumahan, atau kebutuhan lain dengan bunga dan metode perhitungan yang fleksibel.',
                    icon: 'Landmark',
                },
                {
                    title: 'Kriteria dan batas pinjaman',
                    description:
                        'Tetapkan batas berdasarkan level, jabatan, jenis pinjaman, atau kebijakan perusahaan.',
                    icon: 'ShieldAlert',
                },
                {
                    title: 'Opsi pembayaran fleksibel',
                    description:
                        'Dukung potongan gaji, pelunasan awal, dan penyesuaian otomatis ketika saldo berubah.',
                    icon: 'WalletCards',
                },
                {
                    title: 'Integrasi otomatis saat offboarding',
                    description:
                        'Hitung sisa pinjaman saat karyawan resign sesuai aturan pemotongan pesangon atau pembayaran akhir.',
                    icon: 'FileCheck2',
                },
                {
                    title: 'Dukungan pinjaman koperasi',
                    description:
                        'Berikan akses aman bagi koperasi dengan potongan otomatis dan pencatatan pada akun karyawan.',
                    icon: 'Users',
                },
                {
                    title: 'Pemantauan pinjaman real-time',
                    description:
                        'Pantau pengeluaran, status cicilan, pengajuan, dan notifikasi melalui ringkasan analitik.',
                    icon: 'BarChart3',
                },
            ],
            steps: [
                'Tentukan skema, kriteria, dan batas pinjaman.',
                'Karyawan mengajukan pinjaman melalui alur persetujuan.',
                'Sistem membuat jadwal cicilan dan mengirim potongan ke payroll.',
                'Pantau saldo hingga lunas atau lakukan pelunasan saat offboarding.',
            ],
            benefits: [
                'Aturan pinjaman lebih mudah diterapkan secara konsisten.',
                'Karyawan memahami saldo dan jadwal pembayaran mereka.',
                'Payroll menerima data potongan yang lebih siap diproses.',
                'Sisa kewajiban tidak terlewat dalam proses offboarding.',
            ],
            audience: [
                'Perusahaan yang memiliki fasilitas pinjaman karyawan',
                'Koperasi atau Finance yang terhubung ke payroll',
            ],
            faqs: [
                {
                    q: 'Apakah jenis pinjaman dapat dibuat lebih dari satu?',
                    a: 'Bisa. Setiap jenis dapat memiliki aturan bunga, batas, dan metode pembayaran masing-masing.',
                },
                {
                    q: 'Apakah cicilan otomatis masuk ke payroll?',
                    a: 'Bisa, selama skema dan integrasi payroll dikonfigurasi sesuai kebijakan perusahaan.',
                },
            ],
            related: ['payroll', 'compensation-benefits', 'ess'],
        }),
        kpi: page({
            slug: 'kpi',
            name: 'KPI & OKR',
            category: 'Performance & Talent',
            asset: 'feature-assets/dasbor_analitik_kinerja_avanahr.png',
            title: 'Turunkan tujuan perusahaan menjadi hasil yang terukur',
            description:
                'Susun objectives, key results, milestone, dan indikator yang membantu tim bergerak dari prioritas besar ke tindakan nyata.',
            heroNote:
                'Tujuan besar menjadi langkah yang dapat dilihat progresnya.',
            problems: [
                'Tujuan individu tidak selalu memiliki konteks bisnis.',
                'Progres sulit dibaca karena indikator berada di banyak tempat.',
                'Kolaborasi antar tim berhenti pada penetapan target.',
            ],
            items: [
                {
                    title: 'Target berdasarkan Tujuan Perusahaan',
                    description:
                        'Kerucutkan tujuan bisnis eksekutif menjadi prioritas yang dapat dipahami oleh tim dan individu.',
                    icon: 'Target',
                },
                {
                    title: 'Penetapan Objektif dan Turunannya',
                    description:
                        'Manajer dan karyawan menetapkan tujuan berdasarkan prioritas, durasi, dan ruang lingkup yang jelas.',
                    icon: 'GitBranch',
                },
                {
                    title: 'Perencanaan Key Results Perusahaan',
                    description:
                        'Pecah objective menjadi milestone dan indikator terukur yang dapat dipantau kemajuannya.',
                    icon: 'BarChart3',
                },
                {
                    title: 'Wujudkan Kolaborasi Antarkaryawan',
                    description:
                        'Hubungkan objective perusahaan, departemen, dan individu dalam rentang waktu yang sesuai.',
                    icon: 'Users',
                },
                {
                    title: 'Desain OKR dengan panduan terstruktur',
                    description:
                        'Dokumentasikan tujuan dan rencana key results agar karyawan dapat menyusun target dengan lebih mandiri.',
                    icon: 'ListChecks',
                },
            ],
            steps: [
                'Tentukan prioritas bisnis dan objective utama.',
                'Turunkan objective menjadi key results dan milestone.',
                'Perbarui progres dan kolaborasikan hambatan secara berkala.',
                'Gunakan hasilnya untuk review dan perencanaan berikutnya.',
            ],
            benefits: [
                'Setiap target memiliki hubungan yang jelas dengan prioritas perusahaan.',
                'Progres dan hambatan dapat dibahas sebelum akhir periode.',
                'Kolaborasi lintas tim lebih mudah terlihat.',
                'Hasil OKR menjadi input yang lebih kuat untuk performance review.',
            ],
            audience: [
                'Organisasi berbasis target dan OKR',
                'Tim yang membutuhkan transparansi progres lintas fungsi',
            ],
            faqs: [
                {
                    q: 'Apa perbedaan KPI dan OKR di halaman ini?',
                    a: 'Keduanya dapat dipakai sesuai kebutuhan: KPI membantu memantau indikator kinerja, sedangkan OKR menghubungkan objective dengan hasil kunci dan milestone.',
                },
            ],
            related: ['manajemen-kinerja', 'manajemen-talenta', 'pelatihan'],
        }),
        'ai-analytics': page({
            slug: 'ai-analytics',
            name: 'AI & Analytics',
            category: 'AI & Analytics',
            asset: 'workforce-analytics.png',
            title: 'Ubah data HR menjadi insight yang siap ditindaklanjuti',
            description:
                'Gabungkan dashboard, report builder, smart alert, analitik prediktif, dan insight AI untuk membantu pengambil keputusan bergerak lebih cepat.',
            heroNote:
                'Insight lebih dekat dengan data dan keputusan yang harus dibuat.',
            problems: [
                'Laporan membutuhkan banyak pekerjaan manual sebelum bisa dibaca.',
                'Perubahan penting sering diketahui setelah terlambat.',
                'Data HR sulit dihubungkan untuk melihat pola yang lebih besar.',
            ],
            items: [
                {
                    title: 'Laporan ad hoc',
                    description:
                        'Buat dan simpan laporan dengan memilih data serta filter tanpa harus menunggu pekerjaan teknis.',
                    icon: 'FileText',
                },
                {
                    title: 'Notifikasi pintar',
                    description:
                        'Terima alert berdasarkan batas atau perubahan signifikan pada data yang dikonfigurasi.',
                    icon: 'Sparkles',
                },
                {
                    title: 'Report builder',
                    description:
                        'Susun laporan tabular dan grafis yang tetap mengikuti standar keamanan dan privasi.',
                    icon: 'ListChecks',
                },
                {
                    title: 'Analisis prediktif',
                    description:
                        'Gunakan data historis seperti demografi, masa kerja, gaji, absensi, dan kinerja untuk menemukan pola risiko.',
                    icon: 'TrendingDown',
                },
                {
                    title: 'Dashboards',
                    description:
                        'Tampilkan ringkasan tenaga kerja dan kinerja dalam dashboard grafis yang dapat ditelusuri lebih lanjut.',
                    icon: 'BarChart3',
                },
                {
                    title: 'Berbagai metode analisis',
                    description:
                        'Gunakan dashboard fleksibel, laporan standar, feed informasi, dan pengingat berbasis AI.',
                    icon: 'Network',
                },
                {
                    title: 'Laporan komprehensif',
                    description:
                        'Gunakan laporan yang dapat difilter dan diekspor untuk kebutuhan operasional maupun eksekutif.',
                    icon: 'ReceiptText',
                },
                {
                    title: 'Analitik in-app',
                    description:
                        'Baca metrik penting langsung pada halaman kerja tanpa berpindah ke alat BI yang terpisah.',
                    icon: 'Gauge',
                },
                {
                    title: 'Laporan eksekutif berbasis AI',
                    description:
                        'Dapatkan rangkuman tren, hubungan antar kategori, dan rekomendasi action plan dari data HR.',
                    icon: 'Sparkles',
                },
            ],
            steps: [
                'Pilih sumber data dan konteks analisis.',
                'Gunakan filter, dashboard, atau report builder yang sesuai.',
                'Terima alert ketika metrik berubah melewati batas.',
                'Bagikan insight dan gunakan rekomendasinya untuk action plan.',
            ],
            benefits: [
                'Tim non-teknis dapat menjawab pertanyaan data dengan lebih mandiri.',
                'Perubahan penting lebih cepat terlihat melalui smart alert.',
                'Data historis membantu keputusan workforce planning.',
                'Insight tetap dekat dengan halaman kerja dan konteks prosesnya.',
            ],
            audience: [
                'HR dan pimpinan yang membutuhkan insight workforce',
                'Organisasi dengan kebutuhan laporan lintas modul',
            ],
            faqs: [
                {
                    q: 'Apakah laporan dapat dibuat tanpa kemampuan teknis?',
                    a: 'Bisa. Laporan ad hoc dan dashboard menggunakan pilihan data serta filter yang dapat disimpan untuk dipakai kembali.',
                },
                {
                    q: 'Apakah AI otomatis mengambil keputusan HR?',
                    a: 'Tidak. AI membantu merangkum pola dan memberi rekomendasi; keputusan tetap berada pada pengguna yang berwenang.',
                },
            ],
            related: [
                'hr-analytics',
                'prediksi-risiko-resign',
                'ai-hr',
                'payroll',
            ],
        }),
    };

export const PUBLIC_CATALOG_MODULES = [
    'ess',
    'otomatisasi-alur-kerja',
    'pelatihan',
    'time-management',
    'manajemen-talenta',
    'compensation-benefits',
    'loans-management',
    'kpi',
    'ai-analytics',
] as const;
