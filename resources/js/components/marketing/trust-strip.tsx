import {
    BarChart3,
    Building2,
    Cpu,
    FileCheck,
    ShieldCheck,
} from 'lucide-react';
import { Reveal } from './reveal';

/**
 * Trust strip — capability badges in an auto-scrolling marquee, right under
 * the hero. Badges are real, shipped capabilities (see `content.ts`
 * SOLUTIONS/PAIN_POINTS), never invented customer names or numeric claims —
 * the reference site's "Bank-Grade Security" badge is swapped for the actual
 * access-control feature we ship (role-based access + audit trail).
 */
const CAPABILITY_BADGES = [
    {
        icon: Cpu,
        title: 'AI-Native HR Platform',
        desc: 'Workforce insight cerdas',
    },
    {
        icon: BarChart3,
        title: 'Workforce Analytics',
        desc: 'Real-time data reporting',
    },
    {
        icon: ShieldCheck,
        title: 'Role-Based Access & Audit',
        desc: 'Kontrol akses & jejak aktivitas ketat',
    },
    {
        icon: Building2,
        title: 'Multi-Branch & Shift',
        desc: 'Siap untuk multi-lokasi',
    },
    {
        icon: FileCheck,
        title: '100% Regulasi RI',
        desc: 'TER PPh 21 & BPJS terkini',
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
                    animation: avana-trust-marquee 35s linear infinite;
                  }
                  .avana-trust-marquee:hover {
                    animation-play-state: paused;
                  }
                `}
            </style>

            <Reveal>
                <p className="mb-8 text-center text-xs font-semibold tracking-wider text-avana-muted uppercase sm:text-sm">
                    Dipercaya oleh organisasi yang berkembang
                </p>
            </Reveal>

            <div className="relative flex max-w-[100vw] overflow-hidden">
                <div className="avana-trust-marquee flex w-max gap-4 pr-4 pl-4 sm:gap-6 sm:pl-6">
                    {[
                        ...CAPABILITY_BADGES,
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
