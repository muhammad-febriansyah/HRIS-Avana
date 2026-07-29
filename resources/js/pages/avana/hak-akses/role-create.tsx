import { Head, router } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import AccessController from '@/actions/App/Http/Controllers/Avana/AccessController';
import { AIcon, btnCreate, C, card, hexA } from '@/lib/avana';
import { ActionCheckbox, MOBILE_WEB_ICON, Switch } from './components';
import type { AccessAction, AccessModule } from './types';

/** An existing role offered as a starting point, with what it can reach today. */
interface RoleTemplate {
    id: number;
    name: string;
    canAccessMobile: boolean;
    /** menu key → the actions the role holds there. */
    selection: Record<string, string[]>;
    /** Menu Cepat keys the role shows on the phone. */
    mobileSelection: string[];
}

/** One Menu Cepat shortcut of the phone app, as offered on this page. */
interface MobileTileOption {
    key: string;
    label: string;
    icon: string;
    color: string;
}

interface RoleCreateProps {
    modules: AccessModule[];
    actions: AccessAction[];
    templates: RoleTemplate[];
    mobileMenu: MobileTileOption[];
}

/**
 * Creating a role is one page, not a dialog: the name and the menu picks belong
 * to the same decision, and the menu list is as long as the company's sidebar.
 * Everything here is read from the tenant's own menus — no role or menu is
 * baked into the code.
 */
export default function AvanaRoleCreate({
    modules,
    actions,
    templates,
    mobileMenu,
}: RoleCreateProps) {
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [canAccessMobile, setCanAccessMobile] = useState(true);
    // A new role starts with every shortcut, then the admin trims — the phone
    // is meant to be usable the moment somebody is given the role.
    const [mobileKeys, setMobileKeys] = useState<string[]>(() =>
        mobileMenu.map((tile) => tile.key),
    );
    const [template, setTemplate] = useState('');
    const [search, setSearch] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    /** menu key → chosen actions. A key present at all means "role sees it". */
    const [selection, setSelection] = useState<Record<string, string[]>>({});

    /** A menu the company switched off, or whose package feature is not bought. */
    const isBlocked = (module: AccessModule): boolean =>
        !module.menuActive || !module.featureEnabled;

    const selectable = useMemo(
        () => modules.filter((module) => !isBlocked(module)),
        [modules],
    );

    const rows = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (term === '') {
            return modules;
        }

        return modules.filter((module) =>
            `${module.label} ${module.parent ?? ''} ${module.group}`
                .toLowerCase()
                .includes(term),
        );
    }, [modules, search]);

    /** Rows grouped under their sidebar section, in the order they arrive. */
    const sections = useMemo(() => {
        const grouped: { group: string; items: AccessModule[] }[] = [];

        rows.forEach((module) => {
            const last = grouped[grouped.length - 1];

            if (last !== undefined && last.group === module.group) {
                last.items.push(module);

                return;
            }

            grouped.push({ group: module.group, items: [module] });
        });

        return grouped;
    }, [rows]);

    const chosenCount = Object.keys(selection).length;

    const toggleMenu = (module: AccessModule) => {
        setSelection((current) => {
            const next = { ...current };

            if (next[module.key] !== undefined) {
                delete next[module.key];

                return next;
            }

            // Picking a menu grants Lihat straight away — a menu with no action
            // never reaches the sidebar, so an empty pick would do nothing.
            next[module.key] = module.actionable ? ['view'] : [];

            return next;
        });
    };

    const toggleAction = (module: AccessModule, action: string) => {
        setSelection((current) => {
            const held = current[module.key];

            // Ticking an action on an unpicked menu picks the menu too.
            if (held === undefined) {
                return { ...current, [module.key]: [action] };
            }

            const next = held.includes(action)
                ? held.filter((held) => held !== action)
                : [...held, action];

            return { ...current, [module.key]: next };
        });
    };

    const applyTemplate = (value: string) => {
        setTemplate(value);

        if (value === '') {
            setSelection({});

            return;
        }

        const source = templates.find((role) => String(role.id) === value);

        if (source === undefined) {
            return;
        }

        // Only what this tenant can actually reach — a template may hold menus
        // the company has since switched off.
        const allowed = new Set(selectable.map((module) => module.key));

        setSelection(
            Object.fromEntries(
                Object.entries(source.selection).filter(([key]) =>
                    allowed.has(key),
                ),
            ),
        );
        setCanAccessMobile(source.canAccessMobile);
        setMobileKeys(source.mobileSelection);
    };

    const toggleMobileTile = (key: string) => {
        setMobileKeys((current) =>
            current.includes(key)
                ? current.filter((chosen) => chosen !== key)
                : [...current, key],
        );
        setTemplate('');
    };

    const selectAll = () => {
        setSelection(
            Object.fromEntries(
                selectable.map((module) => [
                    module.key,
                    module.actionable ? ['view'] : [],
                ]),
            ),
        );
        setTemplate('');
    };

    const clearAll = () => {
        setSelection({});
        setTemplate('');
    };

    const submit = () => {
        setSaving(true);

        router.post(
            AccessController.storeRole().url,
            {
                name,
                description,
                can_access_mobile: canAccessMobile,
                mobile_menus: canAccessMobile ? mobileKeys : [],
                menus: Object.entries(selection).map(([key, chosen]) => ({
                    key,
                    actions: chosen,
                })),
            },
            {
                preserveScroll: true,
                onError: (received: Record<string, string>) => {
                    setErrors(received);
                    setSaving(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const labelStyle = {
        display: 'block',
        fontSize: 13,
        fontWeight: 500,
        marginBottom: 7,
        color: C.text,
    } as const;

    const inputStyle = {
        width: '100%',
        height: 44,
        padding: '0 13px',
        border: `1px solid ${C.border}`,
        borderRadius: 9,
        fontSize: 13.5,
        color: C.text,
        outline: 'none',
        background: '#fff',
    } as const;

    return (
        <>
            <Head title="Buat Peran" />
            <div style={{ padding: '28px 32px 96px' }}>
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
                    <span>Pengaturan</span>
                    <AIcon name="chevron-right" size={13} />
                    <a href="/avana/hak-akses" style={{ color: C.muted }}>
                        Hak Akses
                    </a>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Peran</span>
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
                    Buat Peran Baru
                </h1>
                <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                    Isi identitas peran, lalu centang menu yang boleh dibuka.
                    Setelah tersimpan, tinggal masukkan karyawannya.
                </div>

                {/* ---- 1. The role itself ---- */}
                <div style={{ ...card, padding: '20px 22px', marginTop: 18 }}>
                    <div
                        style={{
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 14,
                        }}
                    >
                        1 · Identitas Peran
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(260px, 1fr))',
                            gap: 16,
                        }}
                    >
                        <div>
                            <label style={labelStyle} htmlFor="role-name">
                                Nama Peran{' '}
                                <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                id="role-name"
                                value={name}
                                autoFocus
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                placeholder="mis. Supervisor Cabang"
                                style={{
                                    ...inputStyle,
                                    borderColor: errors.name ? C.red : C.border,
                                }}
                            />
                            {errors.name !== undefined && (
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: C.red,
                                        marginTop: 6,
                                    }}
                                >
                                    {errors.name}
                                </div>
                            )}
                        </div>
                        <div>
                            <label style={labelStyle} htmlFor="role-desc">
                                Keterangan{' '}
                                <span style={{ color: C.faint }}>
                                    (opsional)
                                </span>
                            </label>
                            <input
                                id="role-desc"
                                value={description}
                                onChange={(event) =>
                                    setDescription(event.target.value)
                                }
                                placeholder="mis. Approval tim cabang & lihat laporan unit"
                                style={inputStyle}
                            />
                        </div>
                        <div>
                            <label style={labelStyle} htmlFor="role-template">
                                Tiru peran yang sudah ada{' '}
                                <span style={{ color: C.faint }}>
                                    (opsional)
                                </span>
                            </label>
                            <select
                                id="role-template"
                                value={template}
                                onChange={(event) =>
                                    applyTemplate(event.target.value)
                                }
                                style={{ ...inputStyle, cursor: 'pointer' }}
                            >
                                <option value="">
                                    Mulai kosong — pilih menu sendiri
                                </option>
                                {templates.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        Tiru {role.name}
                                    </option>
                                ))}
                            </select>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.faint,
                                    marginTop: 6,
                                    lineHeight: 1.5,
                                }}
                            >
                                Centangan di bawah langsung terisi dan masih
                                bisa Anda ubah sebelum disimpan.
                            </div>
                        </div>
                    </div>

                    <div
                        style={{
                            marginTop: 16,
                            border: `1px solid ${C.border}`,
                            borderRadius: 11,
                            padding: '14px 16px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: 14,
                            flexWrap: 'wrap',
                        }}
                    >
                        <span
                            style={{
                                display: 'flex',
                                alignItems: 'flex-start',
                                gap: 11,
                            }}
                        >
                            <span
                                style={{
                                    width: 34,
                                    height: 34,
                                    borderRadius: 10,
                                    flex: 'none',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    background: hexA(
                                        canAccessMobile ? C.green : C.faint,
                                        0.12,
                                    ),
                                }}
                            >
                                <AIcon
                                    name="smartphone"
                                    size={17}
                                    color={canAccessMobile ? C.green : C.faint}
                                />
                            </span>
                            <span>
                                <div
                                    style={{
                                        fontSize: 14,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    Boleh pakai aplikasi mobile
                                </div>
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        marginTop: 3,
                                        lineHeight: 1.5,
                                    }}
                                >
                                    Matikan untuk peran yang hanya bekerja di
                                    web — login di HP akan ditolak.
                                </div>
                            </span>
                        </span>
                        <Switch
                            on={canAccessMobile}
                            title="Izinkan peran ini memakai aplikasi mobile"
                            label={canAccessMobile ? 'Diizinkan' : 'Ditutup'}
                            onToggle={() =>
                                setCanAccessMobile(!canAccessMobile)
                            }
                        />
                    </div>

                    {canAccessMobile && mobileMenu.length > 0 && (
                        <div
                            style={{
                                marginTop: 14,
                                paddingTop: 14,
                                borderTop: `1px solid ${C.line}`,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'baseline',
                                    justifyContent: 'space-between',
                                    gap: 10,
                                    flexWrap: 'wrap',
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    Menu Cepat di beranda HP
                                    <span
                                        style={{
                                            fontWeight: 400,
                                            color: C.faint,
                                            marginLeft: 6,
                                        }}
                                    >
                                        {mobileKeys.length}/{mobileMenu.length}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setMobileKeys(
                                            mobileKeys.length ===
                                                mobileMenu.length
                                                ? []
                                                : mobileMenu.map((t) => t.key),
                                        )
                                    }
                                    style={{
                                        border: 'none',
                                        background: 'none',
                                        padding: 0,
                                        fontSize: 12.5,
                                        color: C.primary,
                                        cursor: 'pointer',
                                    }}
                                >
                                    {mobileKeys.length === mobileMenu.length
                                        ? 'Kosongkan'
                                        : 'Pilih semua'}
                                </button>
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: 7,
                                    marginTop: 11,
                                }}
                            >
                                {mobileMenu.map((tile) => {
                                    const on = mobileKeys.includes(tile.key);

                                    return (
                                        <button
                                            key={tile.key}
                                            type="button"
                                            onClick={() =>
                                                toggleMobileTile(tile.key)
                                            }
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 7,
                                                padding: '6px 11px 6px 8px',
                                                borderRadius: 999,
                                                border: `1px solid ${on ? hexA(C.primary, 0.4) : C.line}`,
                                                background: on
                                                    ? hexA(C.primary, 0.06)
                                                    : '#fff',
                                                fontSize: 12.5,
                                                fontWeight: 500,
                                                color: on ? C.text : C.faint,
                                                cursor: 'pointer',
                                            }}
                                        >
                                            <AIcon
                                                name={
                                                    MOBILE_WEB_ICON[
                                                        tile.icon
                                                    ] ?? tile.icon
                                                }
                                                size={14}
                                                color={
                                                    on ? tile.color : C.faint
                                                }
                                            />
                                            {tile.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                {/* ---- 2. Which menus the role gets ---- */}
                <div style={{ ...card, marginTop: 16, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: 12,
                            flexWrap: 'wrap',
                        }}
                    >
                        <div>
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                }}
                            >
                                2 · Menu untuk peran ini
                            </div>
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.faint,
                                    marginTop: 3,
                                }}
                            >
                                <b style={{ color: C.navy }}>{chosenCount}</b>{' '}
                                dari {selectable.length} menu dipilih · centang
                                aksi untuk izin tombolnya.
                            </div>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            <input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari menu…"
                                style={{
                                    height: 34,
                                    padding: '0 11px',
                                    borderRadius: 9,
                                    border: `1px solid ${C.border}`,
                                    fontSize: 12.5,
                                    outline: 'none',
                                }}
                            />
                            <button
                                type="button"
                                onClick={selectAll}
                                style={ghostBtn}
                            >
                                Pilih semua
                            </button>
                            <button
                                type="button"
                                onClick={clearAll}
                                style={ghostBtn}
                            >
                                Kosongkan
                            </button>
                        </div>
                    </div>

                    {sections.map((section) => (
                        <Fragment key={section.group}>
                            <div
                                style={{
                                    padding: '9px 18px',
                                    background: C.surface,
                                    borderBottom: `1px solid ${C.line}`,
                                    fontSize: 11,
                                    fontWeight: 700,
                                    letterSpacing: '.06em',
                                    color: C.faint,
                                }}
                            >
                                {section.group}
                            </div>
                            {section.items.map((module) => {
                                const blocked = isBlocked(module);
                                const chosen = selection[module.key];
                                const on = chosen !== undefined;

                                return (
                                    <div
                                        key={module.key}
                                        style={{
                                            padding: '11px 18px',
                                            borderBottom: `1px solid ${C.line}`,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 14,
                                            flexWrap: 'wrap',
                                            opacity: blocked ? 0.45 : 1,
                                        }}
                                    >
                                        <label
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                                cursor: blocked
                                                    ? 'not-allowed'
                                                    : 'pointer',
                                                minWidth: 240,
                                            }}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={on}
                                                disabled={blocked}
                                                onChange={() =>
                                                    toggleMenu(module)
                                                }
                                                style={{
                                                    width: 16,
                                                    height: 16,
                                                    margin: 0,
                                                    accentColor: C.primary,
                                                    cursor: 'inherit',
                                                }}
                                            />
                                            <span>
                                                <span
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 500,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {module.parent !== null
                                                        ? `${module.parent} · `
                                                        : ''}
                                                    {module.label}
                                                </span>
                                                {blocked && (
                                                    <span
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                            marginLeft: 8,
                                                        }}
                                                    >
                                                        tidak aktif untuk
                                                        perusahaan
                                                    </span>
                                                )}
                                                {!blocked &&
                                                    module.selfService && (
                                                        <span
                                                            style={{
                                                                fontSize: 11.5,
                                                                color: C.faint,
                                                                marginLeft: 8,
                                                            }}
                                                        >
                                                            layanan mandiri
                                                        </span>
                                                    )}
                                            </span>
                                        </label>

                                        {module.actionable && (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 12,
                                                    flexWrap: 'wrap',
                                                }}
                                            >
                                                {actions.map((action) => (
                                                    <ActionCheckbox
                                                        key={action.key}
                                                        label={action.label}
                                                        on={
                                                            chosen?.includes(
                                                                action.key,
                                                            ) ?? false
                                                        }
                                                        disabled={blocked}
                                                        onToggle={() =>
                                                            toggleAction(
                                                                module,
                                                                action.key,
                                                            )
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </Fragment>
                    ))}

                    {rows.length === 0 && (
                        <div
                            style={{
                                padding: 20,
                                fontSize: 13,
                                color: C.faint,
                            }}
                        >
                            Tidak ada menu yang cocok dengan pencarian.
                        </div>
                    )}
                </div>
            </div>

            {/* ---- Save bar: the menu list is long, so it never scrolls away ---- */}
            <div
                style={{
                    position: 'sticky',
                    bottom: 0,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'flex-end',
                    gap: 10,
                    padding: '12px 32px',
                    background: '#fff',
                    borderTop: `1px solid ${C.border}`,
                }}
            >
                <span
                    style={{
                        marginRight: 'auto',
                        fontSize: 12.5,
                        color: C.muted,
                    }}
                >
                    {chosenCount === 0
                        ? 'Belum ada menu dipilih — peran hanya melihat Dashboard.'
                        : `${chosenCount} menu akan terbuka untuk peran ini.`}
                </span>
                <a href="/avana/hak-akses" style={ghostBtn}>
                    Batal
                </a>
                <button
                    type="button"
                    onClick={submit}
                    disabled={name.trim() === '' || saving}
                    style={{
                        ...btnCreate,
                        opacity: name.trim() === '' || saving ? 0.6 : 1,
                        cursor:
                            name.trim() === '' || saving
                                ? 'not-allowed'
                                : 'pointer',
                    }}
                >
                    <AIcon name="check" size={16} color="#fff" />
                    {saving ? 'Menyimpan…' : 'Simpan Peran'}
                </button>
            </div>
        </>
    );
}

const ghostBtn = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 7,
    height: 38,
    padding: '0 14px',
    borderRadius: 9,
    border: `1px solid ${C.border}`,
    background: '#fff',
    color: C.text,
    fontSize: 13,
    fontWeight: 500,
    cursor: 'pointer',
    textDecoration: 'none',
} as const;
