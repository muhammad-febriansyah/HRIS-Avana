import { Rocket, ShieldCheck, Sliders, UserPlus, Users2 } from 'lucide-react';
import { DemoButton } from './cta-buttons';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Onboarding flow — what actually happens between signing up and running
 * payroll live on Avana. Kept generic and process-shaped rather than an SLA
 * claim, since no fixed turnaround time is guaranteed to every tenant.
 */
const STEPS: { title: string; desc: string; icon: Icon }[] = [
    {
        title: 'Setup Data Karyawan',
        desc: 'Import data profil, unit kerja, dan saldo cuti karyawan ke dalam sistem.',
        icon: UserPlus,
    },
    {
        title: 'Konfigurasi Payroll & Approval',
        desc: 'Atur komponen gaji, jadwal absensi, serta alur persetujuan sesuai kebijakan perusahaan.',
        icon: Sliders,
    },
    {
        title: 'Training Tim',
        desc: 'Pendampingan untuk tim HR dan sosialisasi aplikasi mobile ke seluruh karyawan.',
        icon: Users2,
    },
    {
        title: 'Go Live',
        desc: 'Sistem berjalan penuh — absensi, payroll, dan approval berjalan di Avana.',
        icon: Rocket,
    },
] as const;

type Icon = typeof UserPlus;

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
                                        Mendampingi sampai sistem siap
                                        dipakai
                                    </div>
                                </div>
                            </div>
                        </Reveal>

                        <div className="lg:col-span-7">
                            <SectionHeading
                                align="left"
                                eyebrow="Proses Onboarding"
                                title={
                                    <>
                                        Bukan Sekadar Software. <br />
                                        <span className="text-[#7C9BFF]">
                                            Kami Dampingi Sampai Berjalan.
                                        </span>
                                    </>
                                }
                                description="Tim implementasi Avana membantu setiap tahap transisi ke sistem baru, dari setup data sampai tim Anda mandiri menjalankannya."
                            />

                            <Reveal delay={0.08}>
                                <ol className="mt-8 space-y-3">
                                    {STEPS.map((step, i) => {
                                        const Icon = step.icon;
                                        const isLast =
                                            i === STEPS.length - 1;

                                        return (
                                            <li
                                                key={step.title}
                                                className="flex items-start gap-3.5 rounded-xl border border-white/10 bg-white/5 p-3.5"
                                            >
                                                <span
                                                    className={
                                                        'grid h-9 w-9 shrink-0 place-items-center rounded-lg text-[13px] font-bold ' +
                                                        (isLast
                                                            ? 'bg-emerald-400/20 text-emerald-300'
                                                            : 'bg-[#2F54C9]/25 text-[#93AEFF]')
                                                    }
                                                >
                                                    <Icon
                                                        className="h-4 w-4"
                                                        aria-hidden
                                                    />
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block text-[14px] font-bold text-white">
                                                        {i + 1}.{' '}
                                                        {step.title}
                                                    </span>
                                                    <span className="mt-0.5 block text-[13px] text-[#B9C4E0]">
                                                        {step.desc}
                                                    </span>
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ol>
                            </Reveal>

                            <Reveal delay={0.14}>
                                <div className="mt-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                                    <DemoButton
                                        variant="primary"
                                        withArrow={false}
                                        className="w-full sm:w-auto"
                                    />
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
