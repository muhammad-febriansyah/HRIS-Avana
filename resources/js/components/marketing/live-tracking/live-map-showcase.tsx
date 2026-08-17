import { Container, Reveal, SectionHeading } from '../reveal';
import type { MapMarker } from './map-canvas';
import { MapCanvas } from './map-canvas';
import {
    ActiveBadge,
    EmployeeDetailPanel,
    EmployeeList,
    FilterChip,
    SearchChip,
} from './tracking-dashboard';

const MARKERS: MapMarker[] = [
    { id: 'febri', x: 52, y: 38, label: 'Febri', tone: 'brand', pulse: true },
    { id: 'alex', x: 84, y: 66, label: 'Alex', tone: 'brand' },
    { id: 'raffa', x: 24, y: 84, label: 'Raffa', tone: 'muted' },
];

/**
 * The live map, given the width it deserves: map on the left, employee list and
 * selected-employee panel on the right, filter bar above. On mobile the panel
 * drops below the map so nothing is squeezed.
 */
export function LiveMapShowcase() {
    return (
        <section
            id="live-map"
            className="scroll-mt-28 border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28"
        >
            <Container>
                <SectionHeading
                    eyebrow="Live map"
                    title="Ketahui Posisi Tim Saat Mereka Bekerja."
                    description="HR dan manager dapat melihat posisi terakhir karyawan yang sedang menjalankan sesi kerja melalui Live Tracking Dashboard."
                />

                <Reveal delay={0.08}>
                    <div className="mt-12 overflow-hidden rounded-[24px] border border-[#E1E7F2] bg-white p-4 shadow-frame sm:p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-1 flex-wrap items-center gap-2">
                                <SearchChip className="min-w-[170px] flex-1 sm:flex-none" />
                                <FilterChip label="Semua Department" />
                                <FilterChip label="Semua Shift" />
                            </div>
                            <ActiveBadge />
                        </div>

                        <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
                            <MapCanvas
                                markers={MARKERS}
                                className="h-[260px] sm:h-[360px] lg:h-[440px]"
                            />

                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                <div>
                                    <h3 className="text-[12px] font-semibold tracking-[0.08em] text-[#8A93A6] uppercase">
                                        Karyawan
                                    </h3>
                                    <EmployeeList className="mt-3" />
                                </div>
                                <div>
                                    <h3 className="text-[12px] font-semibold tracking-[0.08em] text-[#8A93A6] uppercase">
                                        Detail
                                    </h3>
                                    <EmployeeDetailPanel className="mt-3" />
                                </div>
                            </div>
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={0.12}>
                    <p className="mt-6 text-center text-[13px] text-[#8A93A6]">
                        Contoh tampilan dengan data demo — bukan data karyawan
                        sebenarnya.
                    </p>
                </Reveal>
            </Container>
        </section>
    );
}
