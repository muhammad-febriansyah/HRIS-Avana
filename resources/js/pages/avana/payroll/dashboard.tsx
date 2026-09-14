import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import { AIcon, C, rp } from '@/lib/avana';
import type {
    PayrollDashboard as DashboardData,
    PayrollDashboardDepartment,
    PayrollDashboardKpi,
    PayrollDashboardPayment,
    PayrollDashboardPoint,
    PayrollDashboardStatus,
} from './types';

const chartColors = ['#2F54C9', '#F4C95D', '#16A34A', '#94A3B8'];

function shortMoney(value: number): string {
    if (Math.abs(value) >= 1_000_000_000) {
        return `Rp ${(value / 1_000_000_000).toFixed(1).replace('.', ',')} M`;
    }

    if (Math.abs(value) >= 1_000_000) {
        return `Rp ${(value / 1_000_000).toFixed(1).replace('.', ',')}M`;
    }

    return rp(value);
}

function percentage(value: number | null): string {
    return value === null
        ? '—'
        : `${value >= 0 ? '+' : ''}${value.toFixed(1).replace('.', ',')}%`;
}

function Panel({
    title,
    action,
    children,
    className = '',
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={`rounded-xl border border-[#E5E9F2] bg-white p-4 shadow-[0_1px_2px_rgba(14,26,58,.03)] ${className}`}
        >
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="m-0 text-[15px] font-semibold text-[#0E1A3A]">
                    {title}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

function KpiCard({
    kpi,
    previousPeriod,
}: {
    kpi: PayrollDashboardKpi;
    previousPeriod: string | null;
}) {
    const isCount = kpi.key === 'employee_count';
    const positive = (kpi.change_percent ?? 0) >= 0;

    return (
        <article
            className="min-w-0 rounded-xl border border-[#E5E9F2] p-3.5"
            style={{
                background: `linear-gradient(135deg, ${kpi.color}12, #fff 76%)`,
            }}
        >
            <div className="flex items-center gap-2.5">
                <div
                    className="flex size-10 shrink-0 items-center justify-center rounded-lg"
                    style={{ background: `${kpi.color}17` }}
                >
                    <AIcon name={kpi.icon} size={21} color={kpi.color} />
                </div>
                <div className="min-w-0">
                    <div className="truncate text-[11px] text-[#526A9C]">
                        {kpi.label}
                    </div>
                    <strong className="mt-0.5 block truncate text-[16px] text-[#0E1A3A] tabular-nums">
                        {isCount
                            ? kpi.value.toLocaleString('id-ID')
                            : rp(kpi.value)}
                    </strong>
                </div>
            </div>
            <div className="mt-2 flex items-center gap-1 text-[10px]">
                <span
                    className="flex items-center gap-0.5 font-semibold"
                    style={{ color: positive ? C.green : C.red }}
                >
                    <AIcon
                        name={positive ? 'trending-up' : 'trending-down'}
                        size={11}
                    />
                    {percentage(kpi.change_percent)}
                </span>
                <span className="truncate text-[#7A89A8]">
                    vs {previousPeriod ?? 'periode sebelumnya'}
                </span>
            </div>
            <div className="mt-1 pl-[50px] text-[10.5px] text-[#526A9C] tabular-nums">
                {isCount
                    ? kpi.previous_value.toLocaleString('id-ID')
                    : rp(kpi.previous_value)}
            </div>
        </article>
    );
}

function Comparison({ dashboard }: { dashboard: DashboardData }) {
    const current = dashboard.comparison.current;
    const previous = dashboard.comparison.previous;
    const maximum = Math.max(current, previous, 1);
    const positive = dashboard.comparison.difference >= 0;
    const bars = [
        {
            label: dashboard.previous_period ?? 'Periode sebelumnya',
            value: previous,
            color: '#A8C7F0',
        },
        {
            label: dashboard.period ?? 'Periode dipilih',
            value: current,
            color: C.primary,
        },
    ];

    return (
        <section className="rounded-xl border border-[#E5E9F2] bg-white p-4">
            <h2 className="m-0 text-[15px] font-semibold text-[#0E1A3A]">
                Perbandingan Total Pembayaran Payroll
            </h2>
            <p className="mt-0.5 text-[11px] text-[#526A9C]">
                Perbandingan total pembayaran payroll bulan{' '}
                {dashboard.previous_period ?? 'sebelumnya'} dengan{' '}
                {dashboard.period ?? 'periode dipilih'}.
            </p>

            <div className="mt-3 grid gap-4 lg:grid-cols-[1.35fr_.85fr_1fr]">
                <div className="relative flex h-[170px] items-end justify-center gap-12 border-b border-[#CCD8EA] px-8 pt-5">
                    <div className="absolute inset-x-0 top-4 grid h-[120px] grid-rows-4">
                        {[0, 1, 2, 3].map((line) => (
                            <span
                                key={line}
                                className="border-t border-[#EDF1F7]"
                            />
                        ))}
                    </div>
                    {bars.map((bar) => (
                        <div
                            key={bar.label}
                            className="relative z-10 flex h-full flex-1 flex-col items-center justify-end"
                        >
                            <strong className="mb-1 text-[10px] whitespace-nowrap text-[#0E1A3A] tabular-nums">
                                {rp(bar.value)}
                            </strong>
                            <div
                                className="w-full max-w-[100px] rounded-t-md"
                                style={{
                                    height: `${Math.max((bar.value / maximum) * 110, bar.value > 0 ? 7 : 2)}px`,
                                    background: bar.color,
                                }}
                            />
                            <span className="absolute top-full mt-1.5 text-[10px] whitespace-nowrap text-[#405684]">
                                {bar.label}
                            </span>
                        </div>
                    ))}
                    <div
                        className="absolute bottom-10 left-1/2 z-20 -translate-x-1/2 rounded-full bg-white/90 px-2 py-1 text-center"
                        style={{ color: positive ? C.green : C.red }}
                    >
                        <strong className="block text-[18px] leading-none">
                            {percentage(dashboard.comparison.change_percent)}
                        </strong>
                        <span className="text-[9px] font-semibold">
                            {rp(Math.abs(dashboard.comparison.difference))}
                        </span>
                    </div>
                </div>

                <div className="rounded-lg border border-[#E5E9F2] p-3.5">
                    <div className="flex gap-2.5">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#E8F8F1]">
                            <AIcon
                                name="chart-no-axes-combined"
                                size={22}
                                color={C.green}
                            />
                        </div>
                        <div>
                            <div className="text-[11px] font-semibold text-[#0E1A3A]">
                                Total Pembayaran Payroll
                            </div>
                            <strong
                                className="mt-1 block text-[25px] leading-none"
                                style={{ color: positive ? C.green : C.red }}
                            >
                                {percentage(
                                    dashboard.comparison.change_percent,
                                )}
                            </strong>
                            <strong className="mt-2 block text-[15px] text-[#0E1A3A] tabular-nums">
                                {rp(Math.abs(dashboard.comparison.difference))}
                            </strong>
                            <span className="text-[10px] text-[#526A9C]">
                                dibandingkan bulan lalu
                            </span>
                        </div>
                    </div>
                    <div className="mt-3 border-t border-[#E5E9F2] pt-2 text-[10px]">
                        <ComparisonRow
                            label={
                                dashboard.previous_period ??
                                'Periode sebelumnya'
                            }
                            value={previous}
                        />
                        <ComparisonRow
                            label={dashboard.period ?? 'Periode dipilih'}
                            value={current}
                        />
                    </div>
                </div>

                <div className="rounded-lg bg-[#F1F6FF] p-3.5 text-[#405684]">
                    <div className="mb-2 flex items-center gap-2 text-[12px] font-semibold text-[#2F54C9]">
                        <span className="flex size-6 items-center justify-center rounded-full bg-[#2F54C9]">
                            <AIcon name="info" size={14} color="#fff" />
                        </span>
                        Insight
                    </div>
                    <p className="m-0 text-[11px] leading-relaxed">
                        {dashboard.insight}
                    </p>
                </div>
            </div>
        </section>
    );
}

function ComparisonRow({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex justify-between gap-2 py-1">
            <span className="truncate text-[#526A9C]">{label}</span>
            <strong className="whitespace-nowrap text-[#0E1A3A] tabular-nums">
                {rp(value)}
            </strong>
        </div>
    );
}

function Trend({ data }: { data: PayrollDashboardPoint[] }) {
    const width = 430;
    const height = 140;
    const xStart = 22;
    const yStart = 18;
    const chartWidth = 380;
    const chartHeight = 92;
    const maximum = Math.max(...data.map((point) => point.value), 1);
    const points = data.map((point, index) => ({
        ...point,
        x: xStart + (index * chartWidth) / Math.max(data.length - 1, 1),
        y: yStart + chartHeight - (point.value / maximum) * chartHeight,
    }));
    const line = points.map((point) => `${point.x},${point.y}`).join(' ');
    const area = line
        ? `${xStart},${yStart + chartHeight} ${line} ${xStart + chartWidth},${yStart + chartHeight}`
        : '';

    return (
        <Panel
            title="Tren Total Pembayaran Payroll"
            action={
                <span className="rounded-lg border border-[#E5E9F2] px-2.5 py-1 text-[10px] text-[#405684]">
                    6 Bulan Terakhir⌄
                </span>
            }
        >
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="h-[155px] w-full"
                role="img"
                aria-label="Tren payroll enam bulan"
            >
                <defs>
                    <linearGradient id="trend-area" x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0%"
                            stopColor="#2F54C9"
                            stopOpacity=".2"
                        />
                        <stop
                            offset="100%"
                            stopColor="#2F54C9"
                            stopOpacity=".02"
                        />
                    </linearGradient>
                </defs>
                {[0, 1, 2, 3].map((row) => (
                    <line
                        key={row}
                        x1={xStart}
                        x2={xStart + chartWidth}
                        y1={yStart + (row * chartHeight) / 3}
                        y2={yStart + (row * chartHeight) / 3}
                        stroke="#E7EDF6"
                    />
                ))}
                {area && <polygon points={area} fill="url(#trend-area)" />}
                {line && (
                    <polyline
                        points={line}
                        fill="none"
                        stroke="#2F54C9"
                        strokeWidth="2"
                    />
                )}
                {points.map((point) => (
                    <g key={point.label}>
                        <circle
                            cx={point.x}
                            cy={point.y}
                            r="3.5"
                            fill="#2F54C9"
                        />
                        <text
                            x={point.x}
                            y={Math.max(point.y - 8, 9)}
                            textAnchor="middle"
                            fontSize="8"
                            fontWeight="700"
                            fill="#0E1A3A"
                        >
                            {shortMoney(point.value).replace('Rp ', '')}
                        </text>
                        <text
                            x={point.x}
                            y={height - 6}
                            textAnchor="middle"
                            fontSize="8"
                            fill="#405684"
                        >
                            {point.label}
                        </text>
                    </g>
                ))}
            </svg>
        </Panel>
    );
}

function Donut({ data }: { data: PayrollDashboardPoint[] }) {
    const total = data.reduce(
        (sum, point) => sum + Math.max(point.value, 0),
        0,
    );
    const stops = conicStops(
        data.map((point, index) => ({
            value: point.value,
            color: chartColors[index],
        })),
        total,
    );

    return (
        <Panel title="Distribusi Pembayaran Payroll">
            <div className="flex h-[155px] items-center gap-4">
                <DonutGraphic
                    stops={stops}
                    center={shortMoney(total)}
                    caption="Total Pembayaran"
                />
                <Legend
                    items={data.map((point, index) => ({
                        label: point.label,
                        value:
                            total > 0
                                ? `${((point.value / total) * 100).toFixed(1).replace('.', ',')}%`
                                : '0%',
                        color: chartColors[index],
                    }))}
                />
            </div>
        </Panel>
    );
}

function Status({ data }: { data: PayrollDashboardStatus[] }) {
    const total = data.reduce((sum, status) => sum + status.count, 0);
    const completed =
        data.find((status) => status.key === 'completed')?.count ?? 0;
    const stops = conicStops(
        data.map((status) => ({ value: status.count, color: status.color })),
        total,
    );
    const labels: Record<string, string> = {
        completed: 'Completed',
        pending: 'Pending Review',
        error: 'Error',
        not_processed: 'Not Processed',
    };

    return (
        <Panel title="Status Pembayaran Payroll">
            <div className="flex h-[155px] items-center gap-4">
                <DonutGraphic
                    stops={stops}
                    center={`${total > 0 ? Math.round((completed / total) * 100) : 0}%`}
                    caption="Completed"
                    large
                />
                <Legend
                    round
                    items={data.map((status) => ({
                        label: labels[status.key] ?? status.label,
                        value: status.count.toLocaleString('id-ID'),
                        color: status.color,
                    }))}
                />
            </div>
        </Panel>
    );
}

function conicStops(
    items: { value: number; color: string }[],
    total: number,
): string {
    if (total <= 0) {
        return '#E5E9F2 0 100%';
    }

    let sum = 0;

    return items
        .map((item) => {
            const start = (sum / total) * 100;
            sum += Math.max(item.value, 0);

            return `${item.color} ${start}% ${(sum / total) * 100}%`;
        })
        .join(', ');
}

function DonutGraphic({
    stops,
    center,
    caption,
    large = false,
}: {
    stops: string;
    center: string;
    caption: string;
    large?: boolean;
}) {
    return (
        <div
            className="relative size-[126px] shrink-0 rounded-full"
            style={{ background: `conic-gradient(${stops})` }}
        >
            <div className="absolute inset-[22px] flex flex-col items-center justify-center rounded-full bg-white text-center">
                <strong
                    className={`${large ? 'text-[23px]' : 'text-[13px]'} leading-none text-[#0E1A3A] tabular-nums`}
                >
                    {center}
                </strong>
                <span className="mt-1 text-[8.5px] text-[#526A9C]">
                    {caption}
                </span>
            </div>
        </div>
    );
}

function Legend({
    items,
    round = false,
}: {
    items: { label: string; value: string; color: string }[];
    round?: boolean;
}) {
    return (
        <div className="min-w-0 flex-1 space-y-2">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="flex items-center gap-2 text-[10px]"
                >
                    <span
                        className={`size-2 shrink-0 ${round ? 'rounded-full' : 'rounded-sm'}`}
                        style={{ background: item.color }}
                    />
                    <span className="min-w-0 flex-1 truncate text-[#24375E]">
                        {item.label}
                    </span>
                    <strong className="text-[#0E1A3A] tabular-nums">
                        {item.value}
                    </strong>
                </div>
            ))}
        </div>
    );
}

function CostCenter({ data }: { data: PayrollDashboardDepartment[] }) {
    const departments = data.slice(0, 5);
    const maximum = Math.max(
        ...departments.map((department) => department.value),
        1,
    );

    return (
        <Panel
            title="Pembayaran Payroll per Departemen"
            action={
                <span className="rounded-lg border border-[#E5E9F2] px-2.5 py-1 text-[10px] text-[#405684]">
                    Total Pembayaran⌄
                </span>
            }
        >
            {departments.length === 0 ? (
                <div className="py-14 text-center text-[11px] text-[#94A3B8]">
                    Belum ada rincian organisasi.
                </div>
            ) : (
                <div className="relative mb-5 flex h-[150px] items-end gap-5 border-b border-[#CCD8EA] px-4 pt-5">
                    <div className="absolute inset-x-0 top-5 grid h-[90px] grid-rows-3">
                        {[0, 1, 2].map((row) => (
                            <span
                                key={row}
                                className="border-t border-[#EDF1F7]"
                            />
                        ))}
                    </div>
                    {departments.map((department) => (
                        <div
                            key={`${department.code}-${department.label}`}
                            className="relative z-10 flex h-full min-w-0 flex-1 flex-col items-center justify-end"
                        >
                            <strong className="mb-1 text-[8.5px] whitespace-nowrap text-[#0E1A3A] tabular-nums">
                                {shortMoney(department.value)}
                            </strong>
                            <div
                                className="w-full max-w-[55px] rounded-t bg-gradient-to-b from-[#2F54C9] to-[#74A9EB]"
                                style={{
                                    height: `${Math.max((department.value / maximum) * 84, 5)}px`,
                                }}
                            />
                            <span className="absolute top-full mt-1 w-full text-center text-[8px] leading-tight text-[#24375E]">
                                <span className="block">{department.code}</span>
                                <span className="block min-h-5 whitespace-normal">
                                    {department.label}
                                </span>
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </Panel>
    );
}

function PaymentSummary({ data }: { data: PayrollDashboardPayment[] }) {
    return (
        <section className="grid gap-2.5">
            {data.map((payment) => (
                <article
                    key={payment.key}
                    className="flex min-h-[105px] items-center gap-2.5 rounded-xl border border-[#E5E9F2] bg-white p-3"
                >
                    <div
                        className="flex size-10 shrink-0 items-center justify-center rounded-lg"
                        style={{ background: `${payment.color}14` }}
                    >
                        <AIcon
                            name={payment.icon}
                            size={21}
                            color={payment.color}
                        />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="truncate text-[10px] font-semibold text-[#24375E]">
                            {payment.label}
                        </div>
                        <strong className="block truncate text-[14px] text-[#0E1A3A] tabular-nums">
                            {rp(payment.amount)}
                        </strong>
                        <span className="text-[9px] text-[#526A9C]">
                            {payment.count.toLocaleString('id-ID')} karyawan
                        </span>
                    </div>
                    <div
                        className="min-w-[78px] rounded-lg px-2 py-2 text-center text-[18px] leading-none font-bold"
                        style={{
                            color: payment.color,
                            background: `${payment.color}0D`,
                        }}
                    >
                        {Math.round(payment.percentage)}%
                        <span className="mt-1 block text-[8.5px] font-normal text-[#526A9C]">
                            dari total payroll
                        </span>
                    </div>
                </article>
            ))}
        </section>
    );
}

function PeriodControls({
    dashboard,
    onChange,
}: {
    dashboard: DashboardData;
    onChange: (periodId: string) => void;
}) {
    const index = dashboard.period_options.findIndex(
        (period) => period.id === dashboard.period_id,
    );
    const previous =
        index >= 0 ? dashboard.period_options[index + 1] : undefined;
    const next = index > 0 ? dashboard.period_options[index - 1] : undefined;

    return (
        <div className="flex flex-wrap items-center justify-end gap-2">
            <label className="relative">
                <AIcon
                    name="calendar-days"
                    size={15}
                    color={C.navy}
                    style={{ position: 'absolute', left: 11, top: 10 }}
                />
                <select
                    aria-label="Pilih periode payroll"
                    value={dashboard.period_id ?? ''}
                    onChange={(event) => onChange(event.target.value)}
                    className="h-9 min-w-[190px] appearance-none rounded-lg border border-[#E5E9F2] bg-white pr-8 pl-9 text-[11px] font-semibold text-[#0E1A3A] outline-none"
                >
                    {dashboard.period_options.length === 0 && (
                        <option value="">Belum ada periode</option>
                    )}
                    {dashboard.period_options.map((period) => (
                        <option key={period.id} value={period.id}>
                            {period.label}
                        </option>
                    ))}
                </select>
                <AIcon
                    name="chevron-down"
                    size={13}
                    color={C.muted}
                    style={{ position: 'absolute', right: 10, top: 11 }}
                />
            </label>
            <PeriodButton
                label="Periode sebelumnya"
                icon="chevron-left"
                disabled={!previous}
                onClick={() => previous && onChange(String(previous.id))}
            />
            <PeriodButton
                label="Periode berikutnya"
                icon="chevron-right"
                disabled={!next}
                onClick={() => next && onChange(String(next.id))}
            />
            <Link
                href={PayrollController.index().url}
                className="inline-flex h-9 items-center gap-2 rounded-lg bg-[#2F54C9] px-3.5 text-[11px] font-semibold text-white no-underline"
            >
                <AIcon name="play" size={14} color="#fff" /> Proses Payroll{' '}
                <AIcon name="arrow-right" size={14} color="#fff" />
            </Link>
            <a
                href={
                    PayrollController.transferFile({
                        query: { payroll_period_id: dashboard.period_id },
                    }).url
                }
                className="inline-flex h-9 items-center gap-2 rounded-lg border border-[#E5E9F2] bg-white px-3.5 text-[11px] font-semibold text-[#0E1A3A] no-underline"
            >
                <AIcon name="upload" size={14} /> Export
            </a>
        </div>
    );
}

function PeriodButton({
    label,
    icon,
    disabled,
    onClick,
}: {
    label: string;
    icon: string;
    disabled: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
            className="flex size-9 items-center justify-center rounded-lg border border-[#E5E9F2] bg-white disabled:opacity-40"
        >
            <AIcon name={icon} size={14} />
        </button>
    );
}

export default function PayrollDashboardPage({
    dashboard,
}: {
    dashboard: DashboardData;
}) {
    const changePeriod = (periodId: string) => {
        if (periodId !== '') {
            router.get(
                PayrollController.dashboard({ query: { period: periodId } })
                    .url,
                {},
                { preserveState: false, preserveScroll: false },
            );
        }
    };

    return (
        <>
            <Head title="Payroll Dashboard" />
            <main className="px-4 py-2.5 sm:px-6 xl:zoom-[1.06]">
                <header className="mb-3 flex flex-col justify-between gap-3 xl:flex-row xl:items-center">
                    <div>
                        <h1 className="m-0 text-[25px] font-semibold tracking-[-.02em] text-[#0E1A3A]">
                            Payroll Dashboard
                        </h1>
                        <p className="mt-1 text-[12px] text-[#6B7280]">
                            Ringkasan informasi payroll dan perbandingan dengan
                            periode sebelumnya.
                        </p>
                    </div>
                    <PeriodControls
                        dashboard={dashboard}
                        onChange={changePeriod}
                    />
                </header>

                <div className="space-y-2.5">
                    <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                        {dashboard.kpis.map((kpi) => (
                            <KpiCard
                                key={kpi.key}
                                kpi={kpi}
                                previousPeriod={dashboard.previous_period}
                            />
                        ))}
                    </div>
                    <Comparison dashboard={dashboard} />
                    <div className="grid gap-3 xl:grid-cols-[1.15fr_1fr_.9fr]">
                        <Trend data={dashboard.trend} />
                        <Donut data={dashboard.distribution} />
                        <Status data={dashboard.status} />
                    </div>
                    <div className="grid gap-3 xl:grid-cols-[1.35fr_1fr_.72fr]">
                        <CostCenter data={dashboard.departments} />
                        <PaymentSummary data={dashboard.payment_summary} />
                        <section className="flex min-h-[220px] items-center rounded-xl border border-[#E5E9F2] bg-white p-3">
                            <Link
                                href={PayrollController.index().url}
                                className="flex min-h-14 w-full items-center justify-between rounded-lg border border-[#CFE0FF] px-3 text-[11px] font-semibold text-[#2F54C9] no-underline"
                            >
                                <span className="flex items-center gap-2">
                                    <AIcon
                                        name="file-text"
                                        size={17}
                                        color={C.primary}
                                    />
                                    Lihat Detail Pembayaran
                                </span>
                                <AIcon
                                    name="arrow-right"
                                    size={16}
                                    color={C.primary}
                                />
                            </Link>
                        </section>
                    </div>
                </div>
            </main>
        </>
    );
}
