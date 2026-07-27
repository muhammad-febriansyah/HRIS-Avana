import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';

interface FactorRow {
    key: string;
    label: string;
    weight: number;
    source: string;
    active: boolean;
    has_data: boolean;
}

interface RoleOption {
    code: string;
    label: string;
}

interface SettingsProps {
    factors: FactorRow[];
    bands: { low: number; medium: number };
    alerts: { enabled: boolean; threshold: number; weekly_summary: boolean };
    routing: { high: string | null; medium: string | null; low: string | null };
    frequency: string;
    roleOptions: RoleOption[];
    defaults: {
        weights: Record<string, number>;
        bands: { low: number; medium: number };
    };
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

const control: CSSProperties = {
    height: 38,
    padding: '0 10px',
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13,
    color: C.text,
    outline: 'none',
    fontVariantNumeric: 'tabular-nums',
};
const lbl: CSSProperties = {
    fontSize: 12,
    color: C.muted,
    display: 'block',
    marginBottom: 5,
};

function Switch({ on, onClick }: { on: boolean; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            style={{
                width: 40,
                height: 22,
                borderRadius: 100,
                border: 'none',
                cursor: 'pointer',
                background: on ? C.primary : C.border,
                position: 'relative',
                transition: 'background .2s',
                flex: 'none',
            }}
        >
            <span
                style={{
                    position: 'absolute',
                    top: 2,
                    left: on ? 20 : 2,
                    width: 18,
                    height: 18,
                    borderRadius: '50%',
                    background: '#fff',
                    transition: 'left .2s',
                }}
            />
        </button>
    );
}

function Panel({
    icon,
    title,
    desc,
    right,
    children,
}: {
    icon: string;
    title: string;
    desc?: string;
    right?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div style={{ ...card, padding: '20px 22px' }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    marginBottom: desc ? 4 : 16,
                }}
            >
                <AIcon name={icon} size={16} color={C.primary} />
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        flex: 1,
                    }}
                >
                    {title}
                </div>
                {right}
            </div>
            {desc ? (
                <div
                    style={{ fontSize: 12.5, color: C.faint, marginBottom: 16 }}
                >
                    {desc}
                </div>
            ) : null}
            {children}
        </div>
    );
}

function impactBadge(weight: number): { label: string; color: string } {
    if (weight >= 15) {
        return { label: 'Tinggi', color: C.red };
    }

    if (weight >= 8) {
        return { label: 'Sedang', color: C.amber };
    }

    if (weight > 0) {
        return { label: 'Rendah', color: C.green };
    }

    return { label: '—', color: C.faint };
}

const FREQUENCIES = [
    { value: 'daily', label: 'Harian' },
    { value: 'weekly', label: 'Mingguan' },
    { value: 'off', label: 'Nonaktif' },
];

export default function AttritionSettings({
    factors,
    bands,
    alerts,
    routing,
    frequency,
    roleOptions,
    defaults,
}: SettingsProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({
        weights: Object.fromEntries(
            factors.map((f) => [f.key, f.weight]),
        ) as Record<string, number>,
        band_low: bands.low,
        band_medium: bands.medium,
        alerts_enabled: alerts.enabled,
        alert_threshold: alerts.threshold,
        weekly_summary: alerts.weekly_summary,
        scan_frequency: frequency,
        notify_roles: {
            high: routing.high,
            medium: routing.medium,
            low: routing.low,
        },
        disabled_factors: factors.filter((f) => !f.active).map((f) => f.key),
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const total = Object.values(form.data.weights).reduce(
        (a, b) => a + Number(b || 0),
        0,
    );
    const isActive = (key: string) => !form.data.disabled_factors.includes(key);

    const setWeight = (key: string, value: number) =>
        form.setData('weights', { ...form.data.weights, [key]: value });

    const toggleFactor = (key: string) => {
        const set = new Set(form.data.disabled_factors);

        if (set.has(key)) {
            set.delete(key);
        } else {
            set.add(key);
        }

        form.setData('disabled_factors', [...set]);
    };

    const setRouting = (level: 'high' | 'medium' | 'low', code: string) =>
        form.setData('notify_roles', {
            ...form.data.notify_roles,
            [level]: code || null,
        });

    const resetDefaults = () =>
        form.setData({
            ...form.data,
            weights: { ...defaults.weights },
            band_low: defaults.bands.low,
            band_medium: defaults.bands.medium,
        });

    const submit = () =>
        form.put('/avana/attrition/settings', { preserveScroll: true });

    const lowPct = Math.max(0, Math.min(100, form.data.band_low + 1));
    const mediumPct = Math.max(
        0,
        Math.min(100, form.data.band_medium - form.data.band_low),
    );

    return (
        <>
            <Head title="Setup Prediksi Resign" />
            <div style={{ padding: '28px 32px', maxWidth: 1160 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        justifyContent: 'space-between',
                        gap: 12,
                        marginBottom: 22,
                        flexWrap: 'wrap',
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 7,
                            }}
                        >
                            <Link
                                href="/avana/attrition"
                                style={{
                                    color: C.faint,
                                    textDecoration: 'none',
                                }}
                            >
                                Prediksi Resign
                            </Link>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Setup Master</span>
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            Konfigurasi Risiko Resign
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Bobot faktor, ambang, indikator, alert & jadwal —
                            semua memengaruhi skor & notifikasi
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button
                            onClick={resetDefaults}
                            type="button"
                            style={btnOut}
                        >
                            <AIcon name="rotate-ccw" size={16} />
                            Reset Bobot
                        </button>
                        <button
                            onClick={submit}
                            type="button"
                            disabled={form.processing}
                            style={{
                                ...btnP,
                                background: C.green,
                                opacity: form.processing ? 0.6 : 1,
                            }}
                        >
                            <AIcon name="save" size={16} color="#fff" />
                            Simpan Konfigurasi
                        </button>
                    </div>
                </div>

                {(form.errors.weights || form.errors.band_medium) && (
                    <div
                        style={{
                            ...card,
                            padding: '12px 16px',
                            marginBottom: 16,
                            color: C.red,
                            fontSize: 13,
                            background: 'rgba(220,38,38,.06)',
                        }}
                    >
                        {form.errors.weights || form.errors.band_medium}
                    </div>
                )}

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.4fr 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Weightage */}
                    <Panel
                        icon="sliders-horizontal"
                        title="Bobot Faktor Risiko"
                        desc="Makin tinggi bobot, makin besar pengaruh faktor pada skor."
                        right={
                            <span
                                style={{
                                    fontSize: 12,
                                    fontWeight: 600,
                                    color: total > 0 ? C.primary : C.red,
                                    background: C.surface,
                                    padding: '4px 10px',
                                    borderRadius: 100,
                                }}
                            >
                                Total: {total}
                            </span>
                        }
                    >
                        {factors.map((f) => {
                            const w = Number(form.data.weights[f.key] ?? 0);
                            const active = isActive(f.key);

                            return (
                                <div
                                    key={f.key}
                                    style={{
                                        marginBottom: 16,
                                        opacity: active ? 1 : 0.45,
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            marginBottom: 6,
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 500,
                                                color: C.text,
                                            }}
                                        >
                                            {f.label}
                                        </span>
                                        <span
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 700,
                                                color: C.navy,
                                                fontVariantNumeric:
                                                    'tabular-nums',
                                            }}
                                        >
                                            {w}
                                        </span>
                                    </div>
                                    <input
                                        type="range"
                                        min={0}
                                        max={100}
                                        value={w}
                                        disabled={!active}
                                        onChange={(e) =>
                                            setWeight(
                                                f.key,
                                                Number(e.target.value),
                                            )
                                        }
                                        style={{
                                            width: '100%',
                                            accentColor: C.primary,
                                        }}
                                    />
                                </div>
                            );
                        })}
                    </Panel>

                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                        }}
                    >
                        {/* Threshold Tuning */}
                        <Panel
                            icon="gauge"
                            title="Ambang Kategori"
                            desc="Batas skor tiap tingkat risiko (0–100)."
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    height: 12,
                                    borderRadius: 6,
                                    overflow: 'hidden',
                                    marginBottom: 18,
                                }}
                            >
                                <div
                                    style={{
                                        width: `${lowPct}%`,
                                        background: C.green,
                                    }}
                                />
                                <div
                                    style={{
                                        width: `${mediumPct}%`,
                                        background: C.amber,
                                    }}
                                />
                                <div style={{ flex: 1, background: C.red }} />
                            </div>
                            <div style={{ display: 'flex', gap: 12 }}>
                                <div style={{ flex: 1 }}>
                                    <label style={lbl}>Batas Rendah</label>
                                    <input
                                        type="number"
                                        min={0}
                                        max={98}
                                        value={form.data.band_low}
                                        onChange={(e) =>
                                            form.setData(
                                                'band_low',
                                                Number(e.target.value),
                                            )
                                        }
                                        style={{ ...control, width: '100%' }}
                                    />
                                </div>
                                <div style={{ flex: 1 }}>
                                    <label style={lbl}>Batas Sedang</label>
                                    <input
                                        type="number"
                                        min={1}
                                        max={99}
                                        value={form.data.band_medium}
                                        onChange={(e) =>
                                            form.setData(
                                                'band_medium',
                                                Number(e.target.value),
                                            )
                                        }
                                        style={{ ...control, width: '100%' }}
                                    />
                                </div>
                            </div>
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.faint,
                                    marginTop: 10,
                                }}
                            >
                                Rendah 0–{form.data.band_low} · Sedang{' '}
                                {form.data.band_low + 1}–{form.data.band_medium}{' '}
                                · Tinggi {form.data.band_medium + 1}–100
                            </div>
                        </Panel>

                        {/* Smart Alerts */}
                        <Panel
                            icon="bell"
                            title="Smart Alerts"
                            desc="Notifikasi otomatis ke HR untuk karyawan berisiko."
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    marginBottom: 14,
                                }}
                            >
                                <div>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 500,
                                            color: C.text,
                                        }}
                                    >
                                        Peringatan Risiko Tinggi
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                        }}
                                    >
                                        Kirim notifikasi bila skor ≥ ambang
                                    </div>
                                </div>
                                <Switch
                                    on={form.data.alerts_enabled}
                                    onClick={() =>
                                        form.setData(
                                            'alerts_enabled',
                                            !form.data.alerts_enabled,
                                        )
                                    }
                                />
                            </div>
                            <div
                                style={{
                                    marginBottom: 14,
                                    opacity: form.data.alerts_enabled
                                        ? 1
                                        : 0.45,
                                }}
                            >
                                <label style={lbl}>
                                    Ambang skor peringatan
                                </label>
                                <input
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={form.data.alert_threshold}
                                    disabled={!form.data.alerts_enabled}
                                    onChange={(e) =>
                                        form.setData(
                                            'alert_threshold',
                                            Number(e.target.value),
                                        )
                                    }
                                    style={{ ...control, width: '100%' }}
                                />
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <div>
                                    <div
                                        style={{
                                            fontSize: 13,
                                            fontWeight: 500,
                                            color: C.text,
                                        }}
                                    >
                                        Ringkasan Mingguan
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: C.faint,
                                        }}
                                    >
                                        Rekap risiko tim tiap Senin
                                    </div>
                                </div>
                                <Switch
                                    on={form.data.weekly_summary}
                                    onClick={() =>
                                        form.setData(
                                            'weekly_summary',
                                            !form.data.weekly_summary,
                                        )
                                    }
                                />
                            </div>
                        </Panel>
                    </div>
                </div>

                {/* Indicators */}
                <div style={{ marginTop: 18 }}>
                    <Panel
                        icon="list-checks"
                        title="Indikator Risiko"
                        desc="Aktifkan/nonaktifkan faktor & lihat sumber datanya. Faktor nonaktif tidak dihitung."
                    >
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                }}
                            >
                                <thead>
                                    <tr style={{ background: C.surface }}>
                                        {[
                                            'Indikator',
                                            'Sumber Data',
                                            'Dampak',
                                            'Data',
                                            'Status',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                style={{
                                                    textAlign: 'left',
                                                    padding: '10px 14px',
                                                    fontSize: 11,
                                                    fontWeight: 600,
                                                    color: C.muted,
                                                    textTransform: 'uppercase',
                                                    letterSpacing: '.03em',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {factors.map((f) => {
                                        const impact = impactBadge(
                                            Number(
                                                form.data.weights[f.key] ?? 0,
                                            ),
                                        );
                                        const active = isActive(f.key);

                                        return (
                                            <tr
                                                key={f.key}
                                                style={{
                                                    borderTop: `1px solid ${C.line}`,
                                                }}
                                            >
                                                <td
                                                    style={{
                                                        padding: '11px 14px',
                                                        fontSize: 13,
                                                        fontWeight: 500,
                                                        color: active
                                                            ? C.text
                                                            : C.faint,
                                                    }}
                                                >
                                                    {f.label}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '11px 14px',
                                                        fontSize: 12,
                                                        color: C.faint,
                                                        fontFamily:
                                                            'ui-monospace, monospace',
                                                    }}
                                                >
                                                    {f.source}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '11px 14px',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            fontSize: 11.5,
                                                            fontWeight: 600,
                                                            color: impact.color,
                                                            background:
                                                                impact.color +
                                                                '1a',
                                                            padding: '2px 9px',
                                                            borderRadius: 100,
                                                        }}
                                                    >
                                                        {impact.label}
                                                    </span>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '11px 14px',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            fontSize: 11.5,
                                                            fontWeight: 600,
                                                            color: f.has_data
                                                                ? C.green
                                                                : C.faint,
                                                        }}
                                                    >
                                                        {f.has_data
                                                            ? 'Tersedia'
                                                            : 'Belum ada'}
                                                    </span>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '11px 14px',
                                                    }}
                                                >
                                                    <Switch
                                                        on={active}
                                                        onClick={() =>
                                                            toggleFactor(f.key)
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                </div>

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 18,
                        alignItems: 'start',
                        marginTop: 18,
                    }}
                >
                    {/* Notification Routing */}
                    <Panel
                        icon="git-branch"
                        title="Notification Routing"
                        desc="Peran penerima notifikasi untuk tiap tingkat risiko."
                    >
                        {(['high', 'medium', 'low'] as const).map((level) => {
                            const meta = {
                                high: ['Risiko Tinggi', C.red],
                                medium: ['Risiko Sedang', C.amber],
                                low: ['Risiko Rendah', C.green],
                            }[level] as [string, string];

                            return (
                                <div
                                    key={level}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        marginBottom: 12,
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 9,
                                            height: 9,
                                            borderRadius: '50%',
                                            background: meta[1],
                                            flex: 'none',
                                        }}
                                    />
                                    <span
                                        style={{
                                            fontSize: 13,
                                            color: C.text,
                                            flex: 1,
                                        }}
                                    >
                                        {meta[0]}
                                    </span>
                                    <select
                                        value={
                                            form.data.notify_roles[level] ?? ''
                                        }
                                        onChange={(e) =>
                                            setRouting(level, e.target.value)
                                        }
                                        style={{ ...control, minWidth: 150 }}
                                    >
                                        <option value="">Tidak ada</option>
                                        {roleOptions.map((r) => (
                                            <option key={r.code} value={r.code}>
                                                {r.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            );
                        })}
                    </Panel>

                    {/* Scan Frequency */}
                    <Panel
                        icon="calendar-clock"
                        title="Frekuensi Pemindaian"
                        desc="Seberapa sering skor dipindai untuk mengirim alert."
                    >
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            {FREQUENCIES.map((opt) => {
                                const active =
                                    form.data.scan_frequency === opt.value;

                                return (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() =>
                                            form.setData(
                                                'scan_frequency',
                                                opt.value,
                                            )
                                        }
                                        style={{
                                            height: 40,
                                            padding: '0 18px',
                                            borderRadius: 8,
                                            cursor: 'pointer',
                                            fontSize: 13,
                                            fontWeight: 600,
                                            border: `1px solid ${active ? C.primary : C.border}`,
                                            color: active ? '#fff' : C.muted,
                                            background: active
                                                ? C.primary
                                                : '#fff',
                                        }}
                                    >
                                        {opt.label}
                                    </button>
                                );
                            })}
                        </div>
                        <div
                            style={{
                                fontSize: 11.5,
                                color: C.faint,
                                marginTop: 12,
                            }}
                        >
                            Skor dashboard selalu dihitung real-time; frekuensi
                            ini hanya untuk jadwal alert otomatis.
                        </div>
                    </Panel>
                </div>
            </div>
        </>
    );
}
