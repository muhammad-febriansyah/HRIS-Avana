import {
    BarChart3,
    ChevronDown,
    Fingerprint,
    MapPin,
    Search,
    Users,
    Wallet,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { DemoEmployee } from './content';
import { DEMO_DETAIL, DEMO_EMPLOYEES } from './content';
import type { MapMarker } from './map-canvas';
import { MapCanvas } from './map-canvas';

/**
 * The Live Tracking screen, rebuilt as a marketing mockup.
 *
 * Structure mirrors the real application — sidebar, breadcrumb, filter bar,
 * map, employee list and the selected-employee panel — while every name,
 * distance and timestamp is demo material (see `content.ts`).
 */

const SIDEBAR = [
    { label: 'Dashboard', icon: BarChart3 },
    { label: 'Karyawan', icon: Users },
    { label: 'Kehadiran', icon: Fingerprint },
    { label: 'Live Tracking', icon: MapPin, active: true },
    { label: 'Payroll', icon: Wallet },
];

const LIVE_MARKERS: MapMarker[] = [
    { id: 'febri', x: 55, y: 40, label: 'Febri', tone: 'brand', pulse: true },
    { id: 'alex', x: 82, y: 72, label: 'Alex', tone: 'brand' },
    { id: 'raffa', x: 27, y: 82, label: 'Raffa', tone: 'muted' },
];

export function StatusDot({ status }: { status: DemoEmployee['status'] }) {
    return (
        <span
            aria-hidden
            className={cn(
                'inline-block h-2 w-2 shrink-0 rounded-full',
                status === 'active' && 'bg-[#16A34A]',
                status === 'idle' && 'bg-[#D97706]',
                status === 'ended' && 'bg-[#B6BFD2]',
            )}
        />
    );
}

const STATUS_LABEL: Record<DemoEmployee['status'], string> = {
    active: 'Active',
    idle: 'Idle',
    ended: 'Selesai',
};

/** A dropdown-looking filter control. Non-interactive: it is a picture. */
export function FilterChip({
    label,
    className,
}: {
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 rounded-lg border border-[#E3E9F5] bg-white px-3 py-2 text-[12px] font-medium text-[#3B455C]',
                className,
            )}
        >
            {label}
            <ChevronDown className="h-3.5 w-3.5 text-[#8A93A6]" aria-hidden />
        </span>
    );
}

export function SearchChip({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 rounded-lg border border-[#E3E9F5] bg-white px-3 py-2 text-[12px] text-[#8A93A6]',
                className,
            )}
        >
            <Search className="h-3.5 w-3.5" aria-hidden />
            Cari karyawan…
        </span>
    );
}

export function ActiveBadge({ count = 12 }: { count?: number }) {
    return (
        <span className="inline-flex items-center gap-2 rounded-full border border-[#16A34A]/25 bg-[#16A34A]/10 px-3 py-1.5 text-[12px] font-semibold text-[#15803D]">
            <span className="relative grid place-items-center">
                <span
                    aria-hidden
                    className="avn-ping absolute h-3 w-3 rounded-full bg-[#16A34A]/40"
                />
                <span
                    aria-hidden
                    className="relative h-2 w-2 rounded-full bg-[#16A34A]"
                />
            </span>
            {count} Tracking Active
        </span>
    );
}

export function EmployeeList({
    employees = DEMO_EMPLOYEES,
    selected = 'febri',
    className,
}: {
    employees?: DemoEmployee[];
    selected?: string;
    className?: string;
}) {
    return (
        <ul className={cn('space-y-2', className)}>
            {employees.map((employee) => (
                <li
                    key={employee.id}
                    className={cn(
                        'flex items-center gap-3 rounded-xl border px-3 py-2.5',
                        employee.id === selected
                            ? 'border-[#C9D6F0] bg-[#F5F8FE]'
                            : 'border-[#EAEFF7] bg-white',
                    )}
                >
                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#EEF2FB] text-[11px] font-semibold text-[#2F54C9]">
                        {employee.initials}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-[12.5px] font-semibold text-[#0E1A3A]">
                            {employee.name}
                        </span>
                        <span className="block truncate text-[11px] text-[#8A93A6]">
                            {employee.department} · {employee.updated}
                        </span>
                    </span>
                    <span className="inline-flex items-center gap-1.5 text-[11px] font-medium text-[#5B6478]">
                        <StatusDot status={employee.status} />
                        {STATUS_LABEL[employee.status]}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export function EmployeeDetailPanel({
    className,
    withAction = true,
}: {
    className?: string;
    withAction?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-2xl border border-[#EAEFF7] bg-white p-4',
                className,
            )}
        >
            <div className="flex items-center gap-3">
                <span className="grid h-10 w-10 place-items-center rounded-full bg-[#EEF2FB] text-[12px] font-semibold text-[#2F54C9]">
                    MF
                </span>
                <span>
                    <span className="block text-[13.5px] font-semibold text-[#0E1A3A]">
                        {DEMO_DETAIL.name}
                    </span>
                    <span className="block text-[11.5px] text-[#8A93A6]">
                        {DEMO_DETAIL.department}
                    </span>
                </span>
            </div>

            <span className="mt-3 inline-flex items-center gap-2 rounded-full bg-[#16A34A]/10 px-2.5 py-1 text-[11px] font-semibold text-[#15803D]">
                <StatusDot status="active" />
                {DEMO_DETAIL.status}
            </span>

            <dl className="mt-4 space-y-2.5">
                {DEMO_DETAIL.rows.map((row) => (
                    <div
                        key={row.label}
                        className="flex items-baseline justify-between gap-3 border-b border-[#F1F4FA] pb-2.5 last:border-0 last:pb-0"
                    >
                        <dt className="text-[11.5px] text-[#8A93A6]">
                            {row.label}
                        </dt>
                        <dd className="text-[13px] font-semibold text-[#0E1A3A] tabular-nums">
                            {row.value}
                        </dd>
                    </div>
                ))}
            </dl>

            {withAction && (
                <span className="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-[#DFE6F4] px-3 py-2 text-[12px] font-semibold text-[#2F54C9]">
                    Lihat Riwayat
                </span>
            )}
        </div>
    );
}

/** Full application frame: sidebar + header + filters + map + detail panel. */
export function TrackingDashboard({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl border border-[#E1E7F2] bg-white shadow-frame',
                className,
            )}
        >
            {/* Browser chrome */}
            <div className="flex items-center gap-2 border-b border-[#F0F3F9] bg-[#FAFBFE] px-4 py-3">
                {[0, 1, 2].map((dot) => (
                    <span
                        key={dot}
                        aria-hidden
                        className="h-2.5 w-2.5 rounded-full bg-[#E4E9F2]"
                    />
                ))}
                <span className="mx-auto hidden h-6 w-full max-w-[260px] items-center justify-center rounded-md border border-[#EDF1F7] bg-white text-[10px] text-[#9CA3AF] sm:flex">
                    Live Tracking
                </span>
            </div>

            <div className="flex">
                <aside className="hidden w-44 shrink-0 border-r border-[#F0F3F9] p-3 lg:block">
                    {SIDEBAR.map((item) => (
                        <div
                            key={item.label}
                            className={cn(
                                'mb-1 flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[12px]',
                                item.active
                                    ? 'bg-[#EEF2FB] font-medium text-[#2F54C9]'
                                    : 'text-[#6B7280]',
                            )}
                        >
                            <item.icon className="h-3.5 w-3.5" aria-hidden />
                            {item.label}
                        </div>
                    ))}
                </aside>

                <div className="min-w-0 flex-1 bg-[#F7F9FC] p-4">
                    <p className="text-[11px] text-[#9AA7C7]">
                        Kehadiran / Live Tracking
                    </p>
                    <div className="mt-1 flex flex-wrap items-center justify-between gap-3">
                        <h3 className="text-[15px] font-semibold text-[#0E1A3A]">
                            Live Tracking
                        </h3>
                        <ActiveBadge />
                    </div>

                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <SearchChip className="min-w-[150px] flex-1" />
                        <FilterChip label="Semua Department" />
                        <FilterChip label="Semua Shift" />
                    </div>

                    <div className="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1fr)_210px]">
                        <MapCanvas
                            markers={LIVE_MARKERS}
                            className="h-[220px] sm:h-[280px] lg:h-[320px]"
                        />
                        <EmployeeDetailPanel className="hidden lg:block" />
                    </div>

                    <EmployeeList
                        employees={DEMO_EMPLOYEES.slice(0, 3)}
                        className="mt-3 lg:hidden"
                    />
                </div>
            </div>
        </div>
    );
}
