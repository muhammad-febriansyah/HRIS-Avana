import { Container, Reveal, SectionHeading } from '../reveal';
import { HISTORY_SUMMARY } from './content';
import type { MapMarker } from './map-canvas';
import { MapCanvas } from './map-canvas';

const MARKERS: MapMarker[] = [
    { id: 'start', x: 12, y: 26, label: 'Start', tone: 'start' },
    { id: 'end', x: 89, y: 86, label: 'End', tone: 'end' },
];

/**
 * Route history — the recorded session replayed: map, session summary and the
 * activity timeline. Text sits left of the visual, alternating with the live
 * map section above it.
 */
export function RouteHistoryShowcase() {
    return (
        <section className="py-20 lg:py-28">
            <Container>
                <div className="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] lg:gap-14">
                    <div>
                        <SectionHeading
                            align="left"
                            eyebrow="Route history"
                            title="Lihat Kembali Perjalanan Karyawan."
                            description="Tinjau rute perjalanan selama sesi kerja untuk mendapatkan gambaran aktivitas lapangan yang lebih jelas."
                        />

                        <Reveal delay={0.08}>
                            <div className="mt-8 rounded-2xl border border-[#E7ECF5] bg-white p-5 shadow-soft">
                                <p className="text-[12px] font-semibold tracking-[0.08em] text-[#8A93A6] uppercase">
                                    {HISTORY_SUMMARY.date}
                                </p>
                                <dl className="mt-4 grid grid-cols-2 gap-4">
                                    {HISTORY_SUMMARY.rows.map((row) => (
                                        <div key={row.label}>
                                            <dt className="text-[12px] text-[#8A93A6]">
                                                {row.label}
                                            </dt>
                                            <dd className="mt-0.5 text-[17px] font-semibold text-[#0E1A3A] tabular-nums">
                                                {row.value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        </Reveal>

                        <Reveal delay={0.12}>
                            <ol className="mt-6 space-y-0">
                                {HISTORY_SUMMARY.timeline.map((item, i) => (
                                    <li
                                        key={item.time}
                                        className="flex gap-4 pb-4 last:pb-0"
                                    >
                                        <span className="w-12 shrink-0 pt-0.5 text-right text-[12.5px] font-semibold text-[#5B6478] tabular-nums">
                                            {item.time}
                                        </span>
                                        <span className="relative flex flex-col items-center">
                                            <span
                                                aria-hidden
                                                className="mt-1.5 h-2.5 w-2.5 rounded-full bg-[#2F54C9]"
                                            />
                                            {i <
                                                HISTORY_SUMMARY.timeline
                                                    .length -
                                                    1 && (
                                                <span
                                                    aria-hidden
                                                    className="mt-1 w-px flex-1 bg-[#E3E9F5]"
                                                />
                                            )}
                                        </span>
                                        <span className="text-[14px] text-[#3B455C]">
                                            {item.label}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </Reveal>
                    </div>

                    <Reveal delay={0.1}>
                        <div className="overflow-hidden rounded-[24px] border border-[#E1E7F2] bg-white p-4 shadow-frame">
                            <MapCanvas
                                markers={MARKERS}
                                variant="history"
                                className="h-[280px] sm:h-[380px] lg:h-[460px]"
                            />
                            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {[
                                    { label: 'Start Location', value: '08:02' },
                                    { label: 'End Location', value: '17:04' },
                                    { label: 'Distance', value: '23.84 km' },
                                    { label: 'Duration', value: '9j 02m' },
                                ].map((item) => (
                                    <div
                                        key={item.label}
                                        className="rounded-xl border border-[#EAEFF7] bg-[#FAFBFE] px-3 py-2.5"
                                    >
                                        <span className="block text-[11px] text-[#8A93A6]">
                                            {item.label}
                                        </span>
                                        <span className="mt-0.5 block text-[14px] font-semibold text-[#0E1A3A] tabular-nums">
                                            {item.value}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Reveal>
                </div>
            </Container>
        </section>
    );
}
