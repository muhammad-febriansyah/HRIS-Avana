import { MOBILE_CAPABILITIES } from './content';
import { Container, Reveal, SectionHeading } from './reveal';

/**
 * Mobile employee experience. The phone is drawn as a device frame with the
 * ESS actions the app actually offers — no invented app screenshot.
 */
export function MobileSection() {
    return (
        <section className="py-20 lg:py-28">
            <Container>
                <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <SectionHeading
                            align="left"
                            eyebrow="Mobile"
                            title="HR Ada di Genggaman Karyawan."
                            description="Pengalaman employee service terhubung dengan aplikasi mobile. Absensi dilakukan melalui mobile dan riwayatnya dapat dilihat di ESS."
                        />
                        <Reveal delay={0.08}>
                            <ul className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {MOBILE_CAPABILITIES.map((item) => (
                                    <li
                                        key={item.label}
                                        className="flex items-center gap-2.5 rounded-xl border border-[#E7ECF5] bg-white px-3.5 py-3 text-[14px] font-medium text-[#3B455C] shadow-soft transition-[border-color,box-shadow] duration-200 hover:border-[#C9D6F0] hover:shadow-lift"
                                    >
                                        <item.icon
                                            className="h-4 w-4 shrink-0 text-[#2F54C9]"
                                            aria-hidden
                                        />
                                        {item.label}
                                    </li>
                                ))}
                            </ul>
                        </Reveal>
                    </div>

                    <Reveal delay={0.12} className="flex justify-center">
                        <div className="relative">
                            <div
                                aria-hidden
                                className="pointer-events-none absolute top-1/2 left-1/2 h-[420px] w-[320px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(47,84,201,0.12),transparent)]"
                            />
                            <div className="relative w-[272px] rounded-[2.5rem] border border-[#E3E9F4] bg-white p-2 shadow-frame">
                                <div className="overflow-hidden rounded-[2rem] bg-[#F8FAFD]">
                                    {/* Status bar */}
                                    <div className="flex items-center justify-between bg-white px-5 pt-3 pb-2">
                                        <span className="text-[10px] font-semibold text-[#0E1A3A]">
                                            09.41
                                        </span>
                                        <span
                                            aria-hidden
                                            className="h-4 w-16 rounded-full bg-[#0E1A3A]"
                                        />
                                        <span className="flex items-end gap-0.5">
                                            {[3, 5, 7, 9].map((h) => (
                                                <span
                                                    key={h}
                                                    aria-hidden
                                                    className="w-[3px] rounded-sm bg-[#0E1A3A]"
                                                    style={{ height: h }}
                                                />
                                            ))}
                                        </span>
                                    </div>
                                    <div className="px-4 pt-3 pb-5">
                                        <p className="text-[11px] text-[#8A93A6]">
                                            Employee Self Service
                                        </p>
                                        <p className="mt-0.5 text-[15px] font-semibold text-[#0E1A3A]">
                                            Absensi Hari Ini
                                        </p>
                                        <div className="mt-3 rounded-xl bg-[#2F54C9] px-4 py-3 text-center text-[13px] font-semibold text-white shadow-[0_10px_20px_-8px_rgba(47,84,201,0.55)]">
                                            Clock In
                                        </div>
                                        <div className="mt-3 space-y-2">
                                            {MOBILE_CAPABILITIES.slice(
                                                1,
                                                5,
                                            ).map((item) => (
                                                <div
                                                    key={item.label}
                                                    className="flex items-center gap-2.5 rounded-xl border border-[#EAEFF7] bg-white px-3 py-2.5"
                                                >
                                                    <item.icon
                                                        className="h-3.5 w-3.5 text-[#2F54C9]"
                                                        aria-hidden
                                                    />
                                                    <span className="text-[12.5px] text-[#3B455C]">
                                                        {item.label}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                        {/* Home indicator */}
                                        <div className="mt-4 flex justify-center">
                                            <span
                                                aria-hidden
                                                className="h-1 w-20 rounded-full bg-[#D8E0EE]"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
