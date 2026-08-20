import { CheckCircle2 } from 'lucide-react';
import { CONTROL_POINTS } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/** Grounded, verifiable checkmarks shown under the trust banner image. */
const BANNER_POINTS = [
    'Antarmuka berbahasa Indonesia',
    'Aplikasi mobile untuk karyawan',
    'Satu data karyawan lintas modul',
];

/**
 * Proof / why-Avana: the platform's control points reframed as trust
 * signals, closed with a banner of grounded, verifiable checkmarks.
 *
 * Deliberately avoids anything unverifiable from `content.ts` — no security
 * certifications, uptime SLAs or customer counts are claimed, only the
 * role-based access, approval workflow and connected-data practices already
 * backed by `CONTROL_POINTS`.
 */
export function ProofSection() {
    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Kontrol & Kepercayaan"
                    title="Dibangun untuk Kontrol, Bukan Sekadar Fitur"
                    description="Setiap pengajuan, akses dan data karyawan mengikuti aturan yang jelas — bukan sekadar terlihat rapi di permukaan."
                />

                <ul className="mt-12 grid gap-5 sm:grid-cols-2 lg:mt-14 lg:grid-cols-4">
                    {CONTROL_POINTS.map((point, i) => (
                        <Reveal
                            as="li"
                            key={point.title}
                            delay={(i % 4) * 0.06}
                            className="flex flex-col rounded-2xl border border-[#E7ECF5] bg-white p-6 shadow-soft transition-[border-color,box-shadow] duration-200 hover:border-[#C9D6F0] hover:shadow-lift"
                        >
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9]">
                                <point.icon className="h-5 w-5" aria-hidden />
                            </span>
                            <h3 className="mt-5 text-[16px] font-semibold text-[#0E1A3A]">
                                {point.title}
                            </h3>
                            <p className="mt-1.5 text-[14px] leading-relaxed text-[#5B6478]">
                                {point.body}
                            </p>
                        </Reveal>
                    ))}
                </ul>

                <Reveal delay={0.1}>
                    <div className="mt-12 overflow-hidden rounded-3xl border border-[#E7ECF5] bg-white p-7 shadow-soft lg:p-10">
                        <div className="grid items-center gap-8 lg:grid-cols-12">
                            <div className="lg:col-span-4">
                                <div className="mx-auto w-full max-w-[220px] overflow-hidden rounded-2xl lg:mx-0">
                                    <img
                                        src="/avana/landing/images/human-happy-user.png"
                                        alt="Tim HR menggunakan AvanaHR"
                                        className="h-auto w-full object-contain"
                                        loading="lazy"
                                    />
                                </div>
                            </div>

                            <div className="lg:col-span-8">
                                <h3 className="text-[20px] leading-snug font-bold text-[#0E1A3A] sm:text-[22px]">
                                    Satu platform yang dipakai HR, Finance,
                                    Manager dan karyawan setiap hari.
                                </h3>
                                <p className="mt-3 text-[14px] leading-relaxed text-[#5B6478]">
                                    AvanaHR dirancang supaya proses HR tetap
                                    berjalan sesuai peran masing-masing
                                    pengguna, dengan data yang sama dipakai
                                    dari absensi sampai payroll.
                                </p>

                                <ul className="mt-5 flex flex-wrap gap-x-6 gap-y-2.5">
                                    {BANNER_POINTS.map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-center gap-1.5 text-[13px] font-semibold text-[#0E1A3A]"
                                        >
                                            <CheckCircle2
                                                className="h-4 w-4 text-emerald-600"
                                                aria-hidden
                                            />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}
