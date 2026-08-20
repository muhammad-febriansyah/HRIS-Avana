import {
    FolderSync,
    GraduationCap,
    Headphones,
    ShieldCheck,
} from 'lucide-react';
import { Sparkles } from 'lucide-react';
import { DemoButton } from './cta-buttons';
import { Container, Reveal } from './reveal';

const GUARANTEES: { title: string; desc: string; icon: Icon }[] = [
    {
        title: 'Pemetaan Workflow & SOP',
        desc: 'Penyesuaian kebijakan absensi shift, lembur, dan alur persetujuan bertingkat.',
        icon: Headphones,
    },
    {
        title: 'Migrasi Database Karyawan Aman',
        desc: 'Bantuan import data profil, saldo cuti, dan master gaji tanpa risiko selip.',
        icon: FolderSync,
    },
    {
        title: 'Training & Pendampingan Penuh',
        desc: 'Sesi pengenalan aplikasi untuk tim HR serta sosialisasi mobile app ke seluruh staf.',
        icon: GraduationCap,
    },
] as const;

type Icon = typeof Headphones;

/** Onboarding-flow section — how a company gets from sign-up to running Avana live. */
export function ImplementationSection() {
    return (
        <section
            id="implementasi"
            className="border-t border-[#EDF1F8] bg-white py-20 lg:py-28"
        >
            <Container>
                <div className="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-[#0E1A3A] via-[#111F45] to-[#0A1330] p-6 text-white shadow-avana-hover sm:p-10 lg:p-12">
                    <div
                        className="pointer-events-none absolute top-0 right-0 h-[420px] w-[420px] rounded-full bg-[#2F54C9]/20 blur-3xl"
                        aria-hidden
                    />

                    <div className="relative z-10 grid items-center gap-10 lg:grid-cols-12 lg:gap-12">
                        <Reveal className="lg:col-span-5">
                            <div className="relative mx-auto flex w-full max-w-[340px] overflow-hidden rounded-2xl border border-white/15 bg-gradient-to-b from-white/10 to-transparent shadow-xl backdrop-blur-sm">
                                <img
                                    src="/avana/landing/images/human-consultant-blue.png"
                                    alt="Tim implementasi AvanaHR"
                                    className="h-auto w-full object-cover"
                                    loading="lazy"
                                />
                                <div className="absolute right-4 bottom-4 left-4 rounded-xl border border-white/10 bg-white/95 p-3 text-left text-[#0E1A3A] shadow-lg backdrop-blur-md">
                                    <div className="text-[13px] font-bold">
                                        Tim Support & Onboarding
                                    </div>
                                    <div className="text-[11px] text-[#5B6478]">
                                        Siap mendampingi hingga sistem siap
                                        pakai
                                    </div>
                                </div>
                            </div>
                        </Reveal>

                        <div className="lg:col-span-7">
                            <Reveal>
                                <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1.5 text-[12px] font-semibold tracking-[0.08em] text-blue-200 uppercase backdrop-blur-sm">
                                    <Sparkles
                                        className="h-3.5 w-3.5 text-blue-300"
                                        aria-hidden
                                    />
                                    Pendampingan Ahli AvanaHR
                                </span>
                                <h2 className="mt-4 text-[26px] leading-[1.2] font-bold tracking-[-0.01em] text-white sm:text-[32px] lg:text-[36px]">
                                    Bukan Sekadar Software. <br />
                                    <span className="text-[#7C9BFF]">
                                        Kami Membantu Anda Menjalankannya.
                                    </span>
                                </h2>
                                <p className="mt-4 text-[15px] leading-relaxed text-blue-100/80 sm:text-[16px]">
                                    Anda tidak perlu khawatir soal kerumitan
                                    transisi sistem HRIS baru. Tim spesialis
                                    implementasi AvanaHR hadir mendampingi
                                    seluruh proses setup hingga tim Anda
                                    mandiri.
                                </p>
                            </Reveal>

                            <Reveal delay={0.08}>
                                <div className="mt-8 space-y-3">
                                    {GUARANTEES.map((item) => {
                                        const Icon = item.icon;

                                        return (
                                            <div
                                                key={item.title}
                                                className="flex items-start gap-3.5 rounded-xl border border-white/10 bg-white/5 p-3.5"
                                            >
                                                <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#2F54C9]/20 text-[#93AEFF]">
                                                    <Icon
                                                        className="h-4 w-4"
                                                        aria-hidden
                                                    />
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block text-[14px] font-bold text-white">
                                                        {item.title}
                                                    </span>
                                                    <span className="mt-0.5 block text-[13px] text-[#B9C4E0]">
                                                        {item.desc}
                                                    </span>
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </Reveal>

                            <Reveal delay={0.14}>
                                <div className="mt-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                                    <DemoButton
                                        variant="primary"
                                        className="w-full sm:w-auto"
                                    >
                                        Konsultasi Kebutuhan Perusahaan
                                    </DemoButton>
                                    <div className="flex items-center gap-1.5 text-[12px] text-[#B9C4E0]">
                                        <ShieldCheck
                                            className="h-4 w-4 shrink-0 text-emerald-400"
                                            aria-hidden
                                        />
                                        <span>
                                            Gratis konsultasi & estimasi
                                            implementasi
                                        </span>
                                    </div>
                                </div>
                            </Reveal>
                        </div>
                    </div>
                </div>
            </Container>
        </section>
    );
}
