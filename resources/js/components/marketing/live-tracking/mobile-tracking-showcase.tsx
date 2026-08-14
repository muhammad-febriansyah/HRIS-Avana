import {
    BatteryMedium,
    EyeOff,
    LogIn,
    LogOut,
    MapPinOff,
    Radio,
    Signal,
    CircleStop,
    Wifi,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Container, Reveal, SectionHeading } from '../reveal';

type Step = {
    label: string;
    detail: string;
    icon: LucideIcon;
    time: string;
    /** Steps inside the tracking window are highlighted as "aktif". */
    active?: boolean;
};

const FLOW: Step[] = [
    {
        label: 'Clock In',
        detail: 'Karyawan absen seperti biasa dari aplikasi.',
        icon: LogIn,
        time: '08:02',
    },
    {
        label: 'Tracking berjalan otomatis',
        detail: 'Lokasi terkirim di latar belakang, tanpa aksi tambahan.',
        icon: Radio,
        time: '08:02 – 17:04',
        active: true,
    },
    {
        label: 'Clock Out',
        detail: 'Satu tap menutup jam kerja hari itu.',
        icon: LogOut,
        time: '17:04',
    },
    {
        label: 'Tracking berhenti',
        detail: 'Perekaman lokasi mati total di luar jam kerja.',
        icon: CircleStop,
        time: 'Setelah 17:04',
    },
];

const NOT_VISIBLE = [
    { label: 'Map karyawan lain', icon: MapPinOff },
    { label: 'Live dashboard HR', icon: EyeOff },
    { label: 'Tracking karyawan lain', icon: EyeOff },
];

/** What the employee side looks like: one screen, two taps, nothing to manage. */
export function MobileTrackingShowcase() {
    return (
        <section className="border-y border-[#EDF1F8] bg-[#F8FAFD] py-20 lg:py-28">
            <Container>
                <div className="grid items-center gap-12 lg:grid-cols-[1fr_auto] lg:gap-20">
                    <div>
                        <SectionHeading
                            align="left"
                            eyebrow="Sisi karyawan"
                            title="Sederhana untuk Karyawan."
                            description="Karyawan tidak perlu membuka map atau mengoperasikan sistem tracking secara manual sepanjang hari. Dua tap sehari, sisanya berjalan sendiri."
                        />

                        <Reveal delay={0.08}>
                            <ol className="relative mt-9 space-y-5">
                                <span
                                    aria-hidden
                                    className="absolute top-3 bottom-3 left-[19px] w-px bg-gradient-to-b from-[#DDE5F4] via-[#2F54C9]/35 to-[#DDE5F4]"
                                />
                                {FLOW.map((step) => (
                                    <li
                                        key={step.label}
                                        className="relative flex gap-4"
                                    >
                                        <span
                                            className={[
                                                'relative z-10 grid h-10 w-10 shrink-0 place-items-center rounded-full border transition-colors',
                                                step.active
                                                    ? 'border-[#2F54C9]/30 bg-[#2F54C9] text-white shadow-[0_6px_16px_-6px_rgba(47,84,201,0.65)]'
                                                    : 'border-[#E1E8F5] bg-white text-[#2F54C9]',
                                            ].join(' ')}
                                        >
                                            {step.active && (
                                                <span
                                                    aria-hidden
                                                    className="avn-ping absolute inset-0 rounded-full bg-[#2F54C9]/25"
                                                />
                                            )}
                                            <step.icon
                                                className="relative h-[18px] w-[18px]"
                                                aria-hidden
                                            />
                                        </span>

                                        <div className="min-w-0 flex-1 pt-0.5">
                                            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                                <span className="text-[15px] font-semibold text-[#0E1A3A]">
                                                    {step.label}
                                                </span>
                                                <span
                                                    className={[
                                                        'rounded-md px-2 py-0.5 text-[11.5px] font-medium tabular-nums',
                                                        step.active
                                                            ? 'bg-[#EEF2FB] text-[#2F54C9]'
                                                            : 'bg-[#F1F4FA] text-[#7C8699]',
                                                    ].join(' ')}
                                                >
                                                    {step.time}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-[13.5px] leading-relaxed text-[#5B6478]">
                                                {step.detail}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </Reveal>

                        <Reveal delay={0.12}>
                            <div className="mt-8 rounded-2xl border border-[#E7ECF5] bg-white p-5">
                                <div className="flex items-center gap-2">
                                    <EyeOff
                                        className="h-3.5 w-3.5 text-[#8A93A6]"
                                        aria-hidden
                                    />
                                    <p className="text-[12px] font-semibold tracking-[0.08em] text-[#8A93A6] uppercase">
                                        Tidak ditampilkan ke karyawan
                                    </p>
                                </div>
                                <ul className="mt-3 flex flex-wrap gap-2">
                                    {NOT_VISIBLE.map((item) => (
                                        <li
                                            key={item.label}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-[#F4F6FB] px-3 py-1.5 text-[13px] text-[#5B6478]"
                                        >
                                            <item.icon
                                                className="h-3.5 w-3.5 text-[#A3ACC0]"
                                                aria-hidden
                                            />
                                            {item.label}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </Reveal>
                    </div>

                    <Reveal delay={0.1} className="flex justify-center">
                        <div className="relative">
                            <span
                                aria-hidden
                                className="absolute -inset-8 -z-10 rounded-full bg-[#2F54C9]/6 blur-3xl"
                            />
                            <div className="w-[292px] rounded-[2.5rem] border border-[#E1E7F2] bg-white p-2.5 shadow-frame">
                                <div className="overflow-hidden rounded-[2rem] bg-[#F7F9FC]">
                                    <div className="relative bg-white px-5 pt-3 pb-2">
                                        <div className="flex items-center justify-between text-[11px] font-semibold text-[#0E1A3A] tabular-nums">
                                            <span>09:41</span>
                                            <span className="flex items-center gap-1 text-[#5B6478]">
                                                <Signal
                                                    className="h-3 w-3"
                                                    aria-hidden
                                                />
                                                <Wifi
                                                    className="h-3 w-3"
                                                    aria-hidden
                                                />
                                                <BatteryMedium
                                                    className="h-3.5 w-3.5"
                                                    aria-hidden
                                                />
                                            </span>
                                        </div>
                                        <span
                                            aria-hidden
                                            className="absolute top-2.5 left-1/2 h-5 w-20 -translate-x-1/2 rounded-full bg-[#0E1A3A]"
                                        />
                                    </div>

                                    <div className="px-5 pt-3 pb-6">
                                        <p className="text-[11px] text-[#8A93A6]">
                                            AvanaHR
                                        </p>
                                        <p className="mt-0.5 text-[15px] font-semibold text-[#0E1A3A]">
                                            Attendance Hari Ini
                                        </p>

                                        <div className="mt-3 grid grid-cols-2 gap-2">
                                            <div className="rounded-xl border border-[#EAEFF7] bg-white px-3 py-2.5">
                                                <span className="text-[11px] text-[#8A93A6]">
                                                    Clock In
                                                </span>
                                                <span className="mt-0.5 block text-[16px] font-semibold text-[#0E1A3A] tabular-nums">
                                                    08:02
                                                </span>
                                            </div>
                                            <div className="rounded-xl border border-dashed border-[#E4E9F2] bg-[#FAFBFE] px-3 py-2.5">
                                                <span className="text-[11px] text-[#8A93A6]">
                                                    Clock Out
                                                </span>
                                                <span className="mt-0.5 block text-[16px] font-semibold text-[#B6C0D2] tabular-nums">
                                                    --:--
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mt-3 flex items-center gap-2.5 rounded-xl border border-[#16A34A]/25 bg-[#16A34A]/8 px-4 py-3">
                                            <span className="relative grid place-items-center">
                                                <span
                                                    aria-hidden
                                                    className="avn-ping absolute h-4 w-4 rounded-full bg-[#16A34A]/40"
                                                />
                                                <span
                                                    aria-hidden
                                                    className="relative h-2.5 w-2.5 rounded-full bg-[#16A34A]"
                                                />
                                            </span>
                                            <span className="flex-1 text-[13px] font-semibold text-[#15803D]">
                                                Tracking Aktif
                                            </span>
                                            <span className="text-[11px] font-medium text-[#15803D]/70 tabular-nums">
                                                7j 02m
                                            </span>
                                        </div>

                                        <p className="mt-3 text-[12px] leading-relaxed text-[#5B6478]">
                                            Lokasi dicatat selama jam kerja
                                            berlangsung.
                                        </p>

                                        <div className="mt-3 flex items-center gap-2 rounded-lg bg-[#F4F6FB] px-3 py-2">
                                            <BatteryMedium
                                                className="h-3.5 w-3.5 shrink-0 text-[#8A93A6]"
                                                aria-hidden
                                            />
                                            <span className="text-[11.5px] text-[#5B6478]">
                                                Hemat baterai — kirim lokasi
                                                berkala, bukan terus-menerus.
                                            </span>
                                        </div>

                                        <span className="mt-4 block rounded-xl bg-[#2F54C9] px-4 py-3 text-center text-[13px] font-semibold text-white shadow-[0_8px_20px_-10px_rgba(47,84,201,0.9)]">
                                            Clock Out
                                        </span>
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
