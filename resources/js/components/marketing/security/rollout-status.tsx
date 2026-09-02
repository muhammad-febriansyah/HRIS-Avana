import { Container, Reveal, SectionHeading } from '../reveal';
import { ROLLOUT_STATUS } from './content';

/**
 * "Keamanan yang terus dievaluasi" — the honest-status section. Some
 * configuration is deliberately staged (report-only CSP, local backup disk)
 * rather than flipped blind; this section says so instead of overclaiming.
 */
export function RolloutStatus() {
    return (
        <section className="py-16 lg:py-20">
            <Container>
                <SectionHeading
                    eyebrow="Terus Dievaluasi"
                    title="Keamanan Bukan Fitur yang Selesai Sekali Jadi."
                    description="Konfigurasi keamanan terus dievaluasi di production. Sebagian sengaja diterapkan bertahap sebelum enforcement penuh, agar tidak mengganggu operasional."
                />

                <div className="mx-auto mt-10 grid max-w-3xl gap-5 sm:grid-cols-2">
                    {ROLLOUT_STATUS.map((item, i) => (
                        <Reveal
                            key={item.title}
                            delay={i * 0.07}
                            className="rounded-2xl border border-amber-200 bg-amber-50 p-6"
                        >
                            <div className="flex items-center gap-3">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-amber-600">
                                    <item.icon className="h-5 w-5" aria-hidden />
                                </span>
                                <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold tracking-wide text-amber-700 uppercase">
                                    Dalam tahap verifikasi
                                </span>
                            </div>
                            <h3 className="mt-4 text-[16px] font-semibold text-[#0E1A3A]">
                                {item.title}
                            </h3>
                            <p className="mt-1.5 text-[14px] leading-relaxed text-amber-900/80">
                                {item.body}
                            </p>
                        </Reveal>
                    ))}
                </div>

                <Reveal delay={0.14}>
                    <p className="mx-auto mt-8 max-w-2xl text-center text-[13.5px] text-[#5B6478]">
                        Pendekatan bertahap ini memungkinkan peningkatan
                        keamanan dilakukan tanpa mengorbankan stabilitas
                        layanan.
                    </p>
                </Reveal>
            </Container>
        </section>
    );
}
