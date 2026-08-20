import {
    BarChart3,
    Building2,
    FileCheck,
    MapPin,
    ShieldCheck,
    Workflow,
} from 'lucide-react';
import { Reveal } from './reveal';

/**
 * Trust strip — capability badges in an auto-scrolling marquee, right under
 * the hero. Badges are real, shipped capabilities (see `content.ts`
 * SOLUTIONS/PAIN_POINTS), never invented customer names or numeric claims.
 */
const CAPABILITY_BADGES = [
    {
        icon: FileCheck,
        title: 'PPh 21 TER 2024',
        desc: 'Tarif efektif rata-rata terkini',
    },
    {
        icon: ShieldCheck,
        title: 'BPJS Otomatis',
        desc: 'Kesehatan & Ketenagakerjaan',
    },
    {
        icon: MapPin,
        title: 'Live GPS Attendance',
        desc: 'Absensi & tracking lokasi real-time',
    },
    {
        icon: Building2,
        title: 'Multi-Cabang & Shift',
        desc: 'Siap untuk banyak lokasi kerja',
    },
    {
        icon: Workflow,
        title: 'Alur Persetujuan Fleksibel',
        desc: 'Approval berjenjang sesuai struktur',
    },
    {
        icon: BarChart3,
        title: 'Workforce Analytics',
        desc: 'Laporan & dashboard real-time',
    },
];

export function TrustStrip() {
    return (
        <section className="overflow-hidden border-y border-avana-border/60 bg-white py-10">
            <style>
                {`
                  @keyframes avana-trust-marquee {
                    0% { transform: translateX(0%); }
                    100% { transform: translateX(-50%); }
                  }
                  .avana-trust-marquee {
                    animation: avana-trust-marquee 32s linear infinite;
                  }
                  .avana-trust-marquee:hover {
                    animation-play-state: paused;
                  }
                `}
            </style>

            <Reveal>
                <p className="mb-8 text-center text-xs font-semibold tracking-wider text-avana-muted uppercase sm:text-sm">
                    Kapabilitas yang siap dipakai
                </p>
            </Reveal>

            <div className="relative flex max-w-[100vw] overflow-hidden">
                <div className="avana-trust-marquee flex w-max gap-4 pr-4 pl-4 sm:gap-6 sm:pl-6">
                    {[
                        ...CAPABILITY_BADGES,
                        ...CAPABILITY_BADGES,
                        ...CAPABILITY_BADGES,
                    ].map((badge, idx) => {
                        const Icon = badge.icon;

                        return (
                            <div
                                key={idx}
                                className="flex w-[240px] shrink-0 items-center gap-3 rounded-2xl border border-avana-border/50 bg-avana-soft/60 p-3.5 transition-all duration-200 hover:bg-avana-light/60 sm:w-[260px]"
                            >
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-avana-blue shadow-sm">
                                    <Icon className="h-5 w-5" aria-hidden />
                                </div>
                                <div className="min-w-0 text-left">
                                    <div className="text-xs leading-snug font-bold text-avana-navy sm:text-[13px]">
                                        {badge.title}
                                    </div>
                                    <div className="mt-0.5 text-[11px] leading-tight text-avana-muted">
                                        {badge.desc}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
