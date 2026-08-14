import { Gauge, MapPin, Timer } from 'lucide-react';
import { Container, Reveal, SectionHeading } from '../reveal';

const SUPPORTING = [
    { icon: MapPin, label: 'Start Location', value: '08:02 WIB' },
    { icon: MapPin, label: 'Current Location', value: '8 detik lalu' },
    { icon: Gauge, label: 'Distance', value: '8.42 km' },
    { icon: Timer, label: 'Duration', value: '4j 21m' },
];

/**
 * Distance — visual on the left this time, keeping the page alternating rather
 * than stacking one more card grid.
 */
export function DistanceShowcase() {
    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28">
            <Container>
                <div className="grid items-center gap-12 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:gap-16">
                    <Reveal className="order-2 lg:order-1">
                        <div className="rounded-[24px] border border-[#E7ECF5] bg-white p-6 shadow-frame sm:p-8">
                            {/* Journey line: start, recorded points, end. */}
                            <div className="flex items-center gap-2">
                                <span
                                    aria-hidden
                                    className="h-3.5 w-3.5 shrink-0 rounded-full border-[3px] border-white bg-[#16A34A] shadow-[0_0_0_1px_#16A34A55]"
                                />
                                <span
                                    aria-hidden
                                    className="h-0.5 flex-1 rounded-full bg-gradient-to-r from-[#16A34A] via-[#2F54C9] to-[#2F54C9]"
                                />
                                <span
                                    aria-hidden
                                    className="h-2.5 w-2.5 shrink-0 rounded-full bg-[#2F54C9]/45"
                                />
                                <span
                                    aria-hidden
                                    className="h-0.5 flex-1 rounded-full bg-[#2F54C9]"
                                />
                                <span
                                    aria-hidden
                                    className="h-2.5 w-2.5 shrink-0 rounded-full bg-[#2F54C9]/45"
                                />
                                <span
                                    aria-hidden
                                    className="h-0.5 flex-1 rounded-full bg-[#2F54C9]"
                                />
                                <span
                                    aria-hidden
                                    className="h-3.5 w-3.5 shrink-0 rounded-full border-[3px] border-white bg-[#2F54C9] shadow-[0_0_0_1px_#2F54C955]"
                                />
                            </div>
                            <div className="mt-2 flex justify-between text-[11.5px] font-medium text-[#8A93A6]">
                                <span>Start</span>
                                <span>Titik tercatat</span>
                                <span>Posisi terakhir</span>
                            </div>

                            <div className="mt-8 rounded-2xl bg-[#0E1A3A] px-6 py-6 text-center">
                                <span className="text-[12px] font-medium tracking-[0.08em] text-white/55 uppercase">
                                    Total Distance
                                </span>
                                <span className="mt-1 block text-[40px] leading-none font-bold text-white tabular-nums">
                                    8.42{' '}
                                    <span className="text-[20px] font-semibold text-white/70">
                                        km
                                    </span>
                                </span>
                            </div>

                            <ul className="mt-6 grid grid-cols-2 gap-3">
                                {SUPPORTING.map((item) => (
                                    <li
                                        key={item.label}
                                        className="rounded-xl border border-[#EAEFF7] bg-[#FAFBFE] px-3.5 py-3"
                                    >
                                        <span className="flex items-center gap-2 text-[11.5px] text-[#8A93A6]">
                                            <item.icon
                                                className="h-3.5 w-3.5 text-[#2F54C9]"
                                                aria-hidden
                                            />
                                            {item.label}
                                        </span>
                                        <span className="mt-1 block text-[14px] font-semibold text-[#0E1A3A] tabular-nums">
                                            {item.value}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </Reveal>

                    <div className="order-1 lg:order-2">
                        <SectionHeading
                            align="left"
                            eyebrow="Jarak perjalanan"
                            title="Jarak Perjalanan Tercatat Otomatis."
                            description="AvanaHR menghitung total perjalanan berdasarkan lokasi yang tercatat selama sesi tracking sehingga HR mendapatkan ringkasan perjalanan kerja secara lebih praktis."
                        />
                        <Reveal delay={0.08}>
                            <p className="mt-6 rounded-2xl border border-[#E7ECF5] bg-white p-5 text-[14.5px] leading-relaxed text-[#3B455C]">
                                Perhitungan mengikuti titik lokasi yang diterima
                                selama sesi tracking berjalan — tidak ada
                                pencatatan di luar rentang Clock In sampai Clock
                                Out.
                            </p>
                        </Reveal>
                    </div>
                </div>
            </Container>
        </section>
    );
}
