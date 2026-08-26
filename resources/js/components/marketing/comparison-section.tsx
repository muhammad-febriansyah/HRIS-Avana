import { ArrowRight, CheckCircle2 } from 'lucide-react';
import { Container, Reveal, SectionHeading } from './reveal';

const COMPARISON_ROWS = [
    [
        'Data karyawan',
        'Tersebar dalam file dan versi berbeda',
        'Tersimpan per aplikasi',
        'Terpusat dalam satu platform, sesuai modul yang diaktifkan',
    ],
    [
        'Absensi dan shift',
        'Rekap manual atau impor berulang',
        'Bergantung integrasi',
        'Absensi GPS, face recognition, dan shift roster sesuai fitur yang tersedia',
    ],
    [
        'Payroll dan pajak',
        'Kalkulasi serta rekonsiliasi manual',
        'Mungkin perlu ekspor-impor',
        'Payroll, PPh 21 TER, BPJS, dan slip gaji dalam satu alur',
    ],
    [
        'Cuti dan approval',
        'Chat, email, atau formulir',
        'Approval berada di aplikasi berbeda',
        'Workflow pengajuan dan persetujuan yang dapat dikonfigurasi',
    ],
    [
        'Insight workforce',
        'Laporan dibuat manual',
        'Laporan terpecah',
        'Dashboard workforce analytics dan reporting',
    ],
    [
        'Bantuan kebijakan HR',
        'Mencari dokumen secara manual',
        'Tidak selalu berbasis SOP internal',
        'AI Assistant berbasis SOP perusahaan yang dipublikasikan',
    ],
    [
        'Chat dengan AI',
        'Tidak tersedia; pertanyaan dijawab manual',
        'Tergantung fitur dan konfigurasi vendor',
        'Pengguna dapat bertanya dengan bahasa natural melalui AI Assistant',
    ],
    [
        'Skalabilitas',
        'Sulit menjaga konsistensi saat perusahaan tumbuh',
        'Tergantung jumlah integrasi',
        'Mendukung kebutuhan multi-branch dan modul bertahap sesuai paket',
    ],
] as const;

export function ComparisonSection() {
    return (
        <section className="border-y border-[#EDF1F8] bg-white py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Bandingkan Cara Kerja"
                    title="Satu data, lebih sedikit pekerjaan manual."
                    description="Lihat perbedaan ketika proses HR berpindah dari spreadsheet dan aplikasi yang terpisah ke satu platform yang saling terhubung."
                />

                <Reveal className="mt-12" delay={0.05}>
                    <div className="overflow-hidden rounded-3xl border border-[#DCE5F5] bg-white shadow-soft">
                        <div className="flex items-center justify-between gap-4 border-b border-[#E7ECF5] bg-[#F8FAFD] px-5 py-4 sm:px-7">
                            <div>
                                <p className="text-[15px] font-bold text-[#0E1A3A]">
                                    Tabel perbandingan
                                </p>
                                <p className="mt-1 text-xs text-[#6B7690]">
                                    Ringkasan kemampuan berdasarkan proses yang
                                    umum digunakan tim HR.
                                </p>
                            </div>
                            <span className="hidden items-center gap-1.5 rounded-full bg-[#EAF0FF] px-3 py-1.5 text-[11px] font-bold text-[#2F54C9] sm:inline-flex">
                                <CheckCircle2
                                    className="h-3.5 w-3.5"
                                    aria-hidden
                                />
                                Terhubung
                            </span>
                        </div>

                        <div
                            className="overflow-x-auto"
                            tabIndex={0}
                            aria-label="Tabel perbandingan AvanaHR"
                        >
                            <table className="w-full min-w-[850px] border-collapse text-left">
                                <caption className="sr-only">
                                    Perbandingan proses manual, aplikasi HR
                                    terpisah, dan AvanaHR
                                </caption>
                                <thead>
                                    <tr className="border-b border-[#DCE5F5] bg-[#F3F6FC] text-[12px] font-bold tracking-[0.08em] text-[#52617D] uppercase">
                                        <th
                                            scope="col"
                                            className="w-[16%] px-5 py-4 sm:px-7"
                                        >
                                            Kebutuhan
                                        </th>
                                        <th
                                            scope="col"
                                            className="w-[25%] border-l border-[#E1E8F4] px-5 py-4"
                                        >
                                            Spreadsheet atau proses manual
                                        </th>
                                        <th
                                            scope="col"
                                            className="w-[24%] border-l border-[#E1E8F4] px-5 py-4"
                                        >
                                            Aplikasi HR terpisah
                                        </th>
                                        <th
                                            scope="col"
                                            className="w-[35%] border-l border-[#C8D7F5] bg-[#EAF0FF] px-5 py-4 text-[#2348B0]"
                                        >
                                            AvanaHR
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="text-[13.5px] leading-relaxed text-[#5B6478]">
                                    {COMPARISON_ROWS.map(
                                        ([need, manual, separate, avana]) => (
                                            <tr
                                                key={need}
                                                className="border-b border-[#EDF1F8] last:border-0 hover:bg-[#FAFBFE]"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-5 py-5 align-top font-bold text-[#0E1A3A] sm:px-7"
                                                >
                                                    {need}
                                                </th>
                                                <td className="border-l border-[#EDF1F8] px-5 py-5 align-top">
                                                    {manual}
                                                </td>
                                                <td className="border-l border-[#EDF1F8] px-5 py-5 align-top">
                                                    {separate}
                                                </td>
                                                <td className="border-l border-[#DCE6FA] bg-[#F8FAFF] px-5 py-5 align-top font-semibold text-[#2348B0]">
                                                    {avana}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-col gap-3 border-t border-[#E7ECF5] bg-[#F8FAFD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                            <p className="text-xs leading-relaxed text-[#6B7690]">
                                Geser tabel ke samping pada layar kecil untuk
                                melihat semua kolom.
                            </p>
                            <a
                                href="#harga"
                                className="inline-flex items-center gap-1.5 text-[13px] font-bold text-[#2F54C9] hover:text-[#2348B0]"
                            >
                                Lihat paket AvanaHR{' '}
                                <ArrowRight
                                    className="h-3.5 w-3.5"
                                    aria-hidden
                                />
                            </a>
                        </div>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
