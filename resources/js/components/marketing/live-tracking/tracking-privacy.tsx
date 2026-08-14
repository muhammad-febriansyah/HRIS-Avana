import { Container, Reveal, SectionHeading } from '../reveal';
import { PRIVACY_POINTS } from './content';

const STATES: { phase: string; state: 'OFF' | 'ON' }[] = [
    { phase: 'Sebelum Clock In', state: 'OFF' },
    { phase: 'Setelah Clock In', state: 'ON' },
    { phase: 'Setelah Clock Out', state: 'OFF' },
];

/**
 * The boundary section: tracking follows the attendance session and nothing
 * else. Deliberately plain — claims here have to be exactly what the product
 * does, with no compliance badges attached.
 */
export function TrackingPrivacy() {
    return (
        <section className="py-20 lg:py-28">
            <Container>
                <SectionHeading
                    eyebrow="Batas tracking"
                    title="Tracking Hanya Selama Jam Kerja."
                    description="Live Tracking mengikuti sesi attendance karyawan. Tracking dimulai saat Clock In dan berhenti saat Clock Out."
                />

                <Reveal delay={0.08}>
                    <ol className="mt-12 grid gap-3 sm:grid-cols-3">
                        {STATES.map((item) => (
                            <li
                                key={item.phase}
                                className={
                                    'rounded-2xl border p-6 text-center ' +
                                    (item.state === 'ON'
                                        ? 'border-[#16A34A]/30 bg-[#16A34A]/8'
                                        : 'border-[#E7ECF5] bg-white')
                                }
                            >
                                <span className="text-[13px] font-medium text-[#5B6478]">
                                    {item.phase}
                                </span>
                                <span
                                    className={
                                        'mt-2 block text-[26px] font-bold tracking-tight ' +
                                        (item.state === 'ON'
                                            ? 'text-[#15803D]'
                                            : 'text-[#9AA7C7]')
                                    }
                                >
                                    Tracking {item.state}
                                </span>
                            </li>
                        ))}
                    </ol>
                </Reveal>

                <ul className="mt-10 grid gap-4 sm:grid-cols-2">
                    {PRIVACY_POINTS.map((point, i) => (
                        <Reveal
                            as="li"
                            key={point.title}
                            delay={(i % 2) * 0.06}
                            className="flex gap-4 rounded-2xl border border-[#E7ECF5] bg-white p-6"
                        >
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#EEF2FB] text-[#2F54C9]">
                                <point.icon className="h-5 w-5" aria-hidden />
                            </span>
                            <div>
                                <h3 className="text-[16px] font-semibold text-[#0E1A3A]">
                                    {point.title}
                                </h3>
                                <p className="mt-1.5 text-[14px] leading-relaxed text-[#5B6478]">
                                    {point.body}
                                </p>
                            </div>
                        </Reveal>
                    ))}
                </ul>
            </Container>
        </section>
    );
}
