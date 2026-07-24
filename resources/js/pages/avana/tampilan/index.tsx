import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import TenantAppearanceController from '@/actions/App/Http/Controllers/Avana/TenantAppearanceController';
import { AIcon, btnOut, btnP, C, card, hexA } from '@/lib/avana';

/* ============================================================
 * Tampilan & Tema (tenant admin): recolour sidebar + topbar.
 * ============================================================ */

type Colors = Record<string, string>;

interface Token {
    key: string;
    label: string;
    hint: string;
}

interface Preset {
    key: string;
    name: string;
    colors: Colors;
}

interface PageProps {
    theme: Colors;
    defaults: Colors;
    tokens: Token[];
    presets: Preset[];
    logo_url: string | null;
    is_platform: boolean;
}

type FlashProps = { flash?: { success?: string; error?: string } };

const HEX = /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/;

function normalizeHex(value: string): string {
    let v = value.trim();
    if (v && !v.startsWith('#')) {
        v = '#' + v;
    }

    return v.slice(0, 7);
}

/** Small mock of the real chrome so changes are visible before saving. */
function Preview({ c, brand }: { c: Colors; brand: string }) {
    const items = [
        { icon: 'layout-dashboard', label: 'Dashboard', active: false },
        { icon: 'users', label: 'Karyawan', active: true },
        { icon: 'wallet', label: 'Payroll', active: false },
        { icon: 'palette', label: 'Tampilan', active: false },
    ];

    return (
        <div
            style={{
                border: `1px solid ${C.border}`,
                borderRadius: 14,
                overflow: 'hidden',
                boxShadow: '0 10px 30px rgba(14,26,58,.10)',
                background: C.surface,
            }}
        >
            <div style={{ display: 'flex', height: 320 }}>
                {/* sidebar */}
                <div
                    style={{
                        width: 148,
                        background: c.sidebar_bg,
                        borderRight: `1px solid ${hexA(c.sidebar_text, 0.16)}`,
                        display: 'flex',
                        flexDirection: 'column',
                        flex: 'none',
                    }}
                >
                    <div
                        style={{
                            height: 44,
                            borderBottom: `1px solid ${hexA(c.sidebar_text, 0.16)}`,
                            display: 'flex',
                            alignItems: 'center',
                            padding: '0 12px',
                            fontWeight: 700,
                            fontSize: 13,
                            color: c.sidebar_accent,
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        }}
                    >
                        {brand}
                    </div>
                    <div style={{ padding: 8, display: 'flex', flexDirection: 'column', gap: 3 }}>
                        <div
                            style={{
                                fontSize: 9,
                                fontWeight: 600,
                                letterSpacing: '.06em',
                                color: hexA(c.sidebar_text, 0.7),
                                padding: '6px 8px 2px',
                            }}
                        >
                            MENU
                        </div>
                        {items.map((it) => (
                            <div
                                key={it.label}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                    height: 32,
                                    padding: '0 9px',
                                    borderRadius: 8,
                                    fontSize: 12,
                                    fontWeight: it.active ? 600 : 500,
                                    color: it.active
                                        ? c.sidebar_accent
                                        : c.sidebar_text,
                                    background: it.active
                                        ? hexA(c.sidebar_accent, 0.14)
                                        : 'transparent',
                                }}
                            >
                                <AIcon name={it.icon} size={15} />
                                {it.label}
                            </div>
                        ))}
                    </div>
                </div>
                {/* main */}
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                    <div
                        style={{
                            height: 44,
                            background: c.topbar_bg,
                            borderBottom: `1px solid ${hexA(c.topbar_text, 0.14)}`,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '0 12px',
                        }}
                    >
                        <div
                            style={{
                                width: 26,
                                height: 26,
                                borderRadius: 7,
                                border: `1px solid ${hexA(c.topbar_text, 0.14)}`,
                                background: hexA(c.topbar_text, 0.06),
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                color: c.topbar_text,
                            }}
                        >
                            <AIcon name="panel-left" size={14} />
                        </div>
                        <div style={{ flex: 1 }} />
                        <div style={{ fontSize: 12, fontWeight: 600, color: c.topbar_text }}>
                            Rina A.
                        </div>
                        <div
                            style={{
                                width: 26,
                                height: 26,
                                borderRadius: 7,
                                background: 'linear-gradient(135deg,#2F54C9,#6E9BE6)',
                            }}
                        />
                    </div>
                    <div style={{ flex: 1, padding: 14 }}>
                        <div
                            style={{
                                ...card,
                                height: '100%',
                                padding: 14,
                                fontSize: 12,
                                color: C.muted,
                            }}
                        >
                            Area konten
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function TampilanTema({
    theme,
    defaults,
    tokens,
    presets,
    logo_url,
    is_platform,
}: PageProps) {
    const page = usePage<
        FlashProps & {
            auth?: { tenant?: { company_name?: string | null } };
        }
    >();
    const { flash } = page.props;
    const brand = page.props.auth?.tenant?.company_name || 'AvanaHR';
    const [colors, setColors] = useState<Colors>({ ...theme });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash?.success, flash?.error]);

    const set = (key: string, value: string) =>
        setColors((prev) => ({ ...prev, [key]: value }));

    const applyPreset = (preset: Preset) => setColors({ ...preset.colors });

    // The preset whose colours match the current selection (highlighted active).
    const activePreset =
        presets.find((p) =>
            tokens.every(
                (t) =>
                    (colors[t.key] ?? '').toUpperCase() ===
                    (p.colors[t.key] ?? '').toUpperCase(),
            ),
        )?.key ?? null;

    const isValid = tokens.every((t) => HEX.test(colors[t.key] ?? ''));

    const save = () => {
        if (!isValid) {
            toast.error('Ada warna yang tidak valid.');

            return;
        }
        setSaving(true);
        router.post(TenantAppearanceController.update().url, colors, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const reset = () => {
        setColors({ ...defaults });
        router.post(
            TenantAppearanceController.reset().url,
            {},
            { preserveScroll: true },
        );
    };

    const logoInput = useRef<HTMLInputElement>(null);
    const [uploadingLogo, setUploadingLogo] = useState(false);

    const onPickLogo = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }
        setUploadingLogo(true);
        router.post(
            TenantAppearanceController.updateLogo().url,
            { logo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setUploadingLogo(false);
                    if (logoInput.current) {
                        logoInput.current.value = '';
                    }
                },
            },
        );
    };

    const removeLogo = () => {
        router.delete(TenantAppearanceController.removeLogo().url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Tampilan & Tema" />
            <div style={{ padding: '28px 32px', maxWidth: 1180 }}>
                <div style={{ marginBottom: 24 }}>
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
                        <span>Sistem</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Tampilan & Tema</span>
                    </div>
                    <h1
                        style={{
                            fontSize: 25,
                            fontWeight: 600,
                            color: C.navy,
                            margin: 0,
                            letterSpacing: '-.01em',
                        }}
                    >
                        Tampilan & Tema
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 5 }}>
                        Sesuaikan warna sidebar dan topbar panel admin sesuai brand
                        perusahaan. Perubahan berlaku untuk semua pengguna tenant.
                    </div>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)',
                        gap: 20,
                        alignItems: 'start',
                    }}
                >
                    {/* editor */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <div style={{ ...card, padding: 20 }}>
                            <div style={sectionTitle}>Logo</div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                {is_platform
                                    ? 'Logo platform (AvanaHR).'
                                    : 'Logo perusahaan yang tampil di sidebar panel Anda. Tanpa logo, sidebar memakai lambang AvanaHR.'}
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 16,
                                    marginTop: 14,
                                }}
                            >
                                <div
                                    style={{
                                        width: 96,
                                        height: 96,
                                        borderRadius: 12,
                                        border: `1px solid ${C.border}`,
                                        background: '#fff',
                                        display: 'grid',
                                        placeItems: 'center',
                                        overflow: 'hidden',
                                        flex: 'none',
                                    }}
                                >
                                    {logo_url ? (
                                        <img
                                            src={logo_url}
                                            alt="Logo"
                                            style={{
                                                maxWidth: '80%',
                                                maxHeight: '80%',
                                                objectFit: 'contain',
                                            }}
                                        />
                                    ) : (
                                        <span
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                                textAlign: 'center',
                                                padding: 8,
                                            }}
                                        >
                                            Belum ada logo
                                        </span>
                                    )}
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 8,
                                    }}
                                >
                                    <input
                                        ref={logoInput}
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={onPickLogo}
                                        style={{ display: 'none' }}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => logoInput.current?.click()}
                                        disabled={uploadingLogo}
                                        style={{
                                            ...btnP,
                                            opacity: uploadingLogo ? 0.6 : 1,
                                        }}
                                    >
                                        <AIcon
                                            name="upload"
                                            size={16}
                                            color="#fff"
                                        />
                                        {uploadingLogo
                                            ? 'Mengunggah…'
                                            : 'Unggah Logo'}
                                    </button>
                                    {logo_url ? (
                                        <button
                                            type="button"
                                            onClick={removeLogo}
                                            style={btnOut}
                                        >
                                            <AIcon name="trash-2" size={15} />
                                            Hapus Logo
                                        </button>
                                    ) : null}
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: C.faint,
                                        }}
                                    >
                                        PNG, JPG, atau WEBP · maks 1 MB
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style={{ ...card, padding: 20 }}>
                            <div style={sectionTitle}>Preset</div>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'repeat(auto-fill, minmax(150px, 1fr))',
                                    gap: 10,
                                    marginTop: 12,
                                }}
                            >
                                {presets.map((p) => {
                                    const active = p.key === activePreset;

                                    return (
                                        <button
                                            key={p.key}
                                            onClick={() => applyPreset(p)}
                                            style={{
                                                position: 'relative',
                                                border: `1.5px solid ${active ? C.primary : C.border}`,
                                                borderRadius: 10,
                                                padding: 10,
                                                background: active
                                                    ? 'rgba(47,84,201,.05)'
                                                    : '#fff',
                                                cursor: 'pointer',
                                                textAlign: 'left',
                                                boxShadow: active
                                                    ? '0 0 0 3px rgba(47,84,201,.08)'
                                                    : 'none',
                                            }}
                                        >
                                            <div style={{ display: 'flex', gap: 4, marginBottom: 8 }}>
                                                {['sidebar_bg', 'sidebar_accent', 'topbar_bg', 'topbar_text'].map(
                                                    (k) => (
                                                        <span
                                                            key={k}
                                                            style={{
                                                                width: 18,
                                                                height: 18,
                                                                borderRadius: 5,
                                                                background: p.colors[k],
                                                                border: `1px solid ${C.border}`,
                                                            }}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                    fontSize: 12.5,
                                                    fontWeight: 600,
                                                    color: active ? C.primary : C.text,
                                                }}
                                            >
                                                {p.name}
                                                {active ? (
                                                    <span
                                                        style={{
                                                            fontSize: 10.5,
                                                            fontWeight: 700,
                                                            color: C.primary,
                                                            background: 'rgba(47,84,201,.12)',
                                                            borderRadius: 5,
                                                            padding: '1px 6px',
                                                        }}
                                                    >
                                                        Aktif
                                                    </span>
                                                ) : null}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div style={{ ...card, padding: 20 }}>
                            <div style={sectionTitle}>Warna</div>
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                    marginTop: 12,
                                }}
                            >
                                {tokens.map((t) => {
                                    const value = colors[t.key] ?? '';
                                    const valid = HEX.test(value);

                                    return (
                                        <div
                                            key={t.key}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 14,
                                            }}
                                        >
                                            <div style={{ flex: 1, minWidth: 0 }}>
                                                <div
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {t.label}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {t.hint}
                                                </div>
                                            </div>
                                            <input
                                                type="color"
                                                value={valid ? value : '#ffffff'}
                                                onChange={(e) =>
                                                    set(t.key, e.target.value.toUpperCase())
                                                }
                                                style={{
                                                    width: 44,
                                                    height: 38,
                                                    border: `1px solid ${C.border}`,
                                                    borderRadius: 8,
                                                    background: '#fff',
                                                    padding: 3,
                                                    cursor: 'pointer',
                                                    flex: 'none',
                                                }}
                                            />
                                            <input
                                                type="text"
                                                value={value}
                                                onChange={(e) =>
                                                    set(t.key, normalizeHex(e.target.value))
                                                }
                                                spellCheck={false}
                                                style={{
                                                    width: 108,
                                                    height: 38,
                                                    padding: '0 12px',
                                                    borderRadius: 8,
                                                    border: `1px solid ${valid ? C.border : C.red}`,
                                                    fontSize: 13,
                                                    fontFamily: 'monospace',
                                                    textTransform: 'uppercase',
                                                    color: C.text,
                                                    outline: 'none',
                                                    flex: 'none',
                                                }}
                                            />
                                        </div>
                                    );
                                })}
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    gap: 10,
                                    marginTop: 20,
                                    paddingTop: 16,
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                <button onClick={reset} style={btnOut}>
                                    <AIcon name="rotate-ccw" size={15} color={C.text} />
                                    Kembalikan Bawaan
                                </button>
                                <button
                                    onClick={save}
                                    disabled={saving || !isValid}
                                    style={{
                                        ...btnP,
                                        opacity: saving || !isValid ? 0.6 : 1,
                                    }}
                                >
                                    <AIcon name="check" size={15} color="#fff" />
                                    {saving ? 'Menyimpan…' : 'Simpan Tema'}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* preview */}
                    <div style={{ position: 'sticky', top: 20 }}>
                        <div style={{ ...sectionTitle, marginBottom: 12 }}>
                            Pratinjau
                        </div>
                        <Preview c={colors} brand={brand} />
                    </div>
                </div>
            </div>
        </>
    );
}

const sectionTitle: CSSProperties = {
    fontSize: 15,
    fontWeight: 600,
    color: C.navy,
};
