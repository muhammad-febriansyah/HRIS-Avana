import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';

interface FactorRow {
    key: string;
    label: string;
    weight: number;
}

interface SettingsProps {
    factors: FactorRow[];
    bands: { low: number; medium: number };
    defaults: { weights: Record<string, number>; bands: { low: number; medium: number } };
}

interface FlashProps {
    flash?: { success?: string };
    [key: string]: unknown;
}

const lbl: CSSProperties = { fontSize: 12, color: C.muted, display: 'block', marginBottom: 5 };
const numInput: CSSProperties = {
    width: '100%',
    height: 40,
    padding: '0 12px',
    background: '#fff',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 14,
    color: C.text,
    outline: 'none',
    fontVariantNumeric: 'tabular-nums',
};

function BandRow({ color, label, from, to }: { color: string; label: string; from: number; to: number }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5 }}>
            <span style={{ width: 9, height: 9, borderRadius: '50%', background: color, flex: 'none' }} />
            <span style={{ color: C.text, fontWeight: 600 }}>{label}</span>
            <span style={{ color: C.faint }}>
                {from}–{to}
            </span>
        </div>
    );
}

export default function AttritionSettings({ factors, bands, defaults }: SettingsProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({
        weights: Object.fromEntries(factors.map((f) => [f.key, f.weight])) as Record<string, number>,
        band_low: bands.low,
        band_medium: bands.medium,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const total = Object.values(form.data.weights).reduce((a, b) => a + Number(b || 0), 0);

    const setWeight = (key: string, value: number) =>
        form.setData('weights', { ...form.data.weights, [key]: value });

    const resetDefaults = () =>
        form.setData({
            weights: { ...defaults.weights },
            band_low: defaults.bands.low,
            band_medium: defaults.bands.medium,
        });

    const submit = () => form.put('/avana/attrition/settings', { preserveScroll: true });

    const lowPct = Math.max(0, Math.min(100, form.data.band_low + 1));
    const mediumPct = Math.max(0, Math.min(100, form.data.band_medium - form.data.band_low));

    return (
        <>
            <Head title="Setup Prediksi Resign" />
            <div style={{ padding: '28px 32px', maxWidth: 1040 }}>
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
                    <Link href="/avana/attrition" style={{ color: C.faint, textDecoration: 'none' }}>
                        Prediksi Resign
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Pengaturan</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>
                    Konfigurasi Risiko Resign
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginTop: 4, marginBottom: 22 }}>
                    Atur bobot tiap faktor & ambang kategori risiko
                </div>

                <div
                    className="avn-2col"
                    style={{ display: 'grid', gridTemplateColumns: '1.5fr 1fr', gap: 18, alignItems: 'start' }}
                >
                    <div style={{ ...card, padding: '22px 24px' }}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 4,
                            }}
                        >
                            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>Bobot Faktor Risiko</div>
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
                        </div>
                        <div style={{ fontSize: 12.5, color: C.faint, marginBottom: 18 }}>
                            Makin tinggi bobot, makin besar pengaruh faktor. Bobot 0 = faktor dinonaktifkan.
                        </div>

                        {factors.map((f) => {
                            const w = Number(form.data.weights[f.key] ?? 0);

                            return (
                                <div key={f.key} style={{ marginBottom: 16 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                                        <span style={{ fontSize: 13, fontWeight: 500, color: C.text }}>{f.label}</span>
                                        <span
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 700,
                                                color: C.navy,
                                                fontVariantNumeric: 'tabular-nums',
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
                                        onChange={(e) => setWeight(f.key, Number(e.target.value))}
                                        style={{ width: '100%', accentColor: C.primary }}
                                    />
                                </div>
                            );
                        })}

                        {form.errors.weights && (
                            <div style={{ color: C.red, fontSize: 12.5, marginTop: 4 }}>{form.errors.weights}</div>
                        )}

                        <div style={{ display: 'flex', gap: 10, marginTop: 20, alignItems: 'center' }}>
                            <button onClick={resetDefaults} type="button" style={btnOut}>
                                <AIcon name="rotate-ccw" size={16} />
                                Reset Default
                            </button>
                            <div style={{ flex: 1 }} />
                            <button
                                onClick={submit}
                                type="button"
                                disabled={form.processing}
                                style={{ ...btnP, opacity: form.processing ? 0.6 : 1 }}
                            >
                                <AIcon name="save" size={16} color="#fff" />
                                Simpan Konfigurasi
                            </button>
                        </div>
                    </div>

                    <div style={{ ...card, padding: '22px 24px' }}>
                        <div style={{ fontSize: 15, fontWeight: 600, color: C.navy, marginBottom: 4 }}>
                            Ambang Kategori
                        </div>
                        <div style={{ fontSize: 12.5, color: C.faint, marginBottom: 18 }}>
                            Batas skor tiap tingkat risiko (skala 0–100).
                        </div>

                        <div style={{ display: 'flex', height: 12, borderRadius: 6, overflow: 'hidden', marginBottom: 18 }}>
                            <div style={{ width: `${lowPct}%`, background: C.green }} />
                            <div style={{ width: `${mediumPct}%`, background: C.amber }} />
                            <div style={{ flex: 1, background: C.red }} />
                        </div>

                        <BandRow color={C.green} label="Rendah" from={0} to={form.data.band_low} />
                        <div style={{ margin: '10px 0 16px' }}>
                            <label style={lbl}>Batas atas Rendah</label>
                            <input
                                type="number"
                                min={0}
                                max={98}
                                value={form.data.band_low}
                                onChange={(e) => form.setData('band_low', Number(e.target.value))}
                                style={numInput}
                            />
                        </div>

                        <BandRow color={C.amber} label="Sedang" from={form.data.band_low + 1} to={form.data.band_medium} />
                        <div style={{ margin: '10px 0 16px' }}>
                            <label style={lbl}>Batas atas Sedang</label>
                            <input
                                type="number"
                                min={1}
                                max={99}
                                value={form.data.band_medium}
                                onChange={(e) => form.setData('band_medium', Number(e.target.value))}
                                style={numInput}
                            />
                        </div>

                        <BandRow color={C.red} label="Tinggi" from={form.data.band_medium + 1} to={100} />

                        {(form.errors.band_low || form.errors.band_medium) && (
                            <div style={{ color: C.red, fontSize: 12.5, marginTop: 12 }}>
                                {form.errors.band_low || form.errors.band_medium}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
