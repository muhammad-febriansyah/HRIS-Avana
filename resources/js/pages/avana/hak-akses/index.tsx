import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import AccessController from '@/actions/App/Http/Controllers/Avana/AccessController';
import FeatureCatalogController from '@/actions/App/Http/Controllers/Avana/FeatureCatalogController';
import { AIcon, btnCreate, btnNested, C, hexA } from '@/lib/avana';
import MenuBuilder from '../menu-builder';
import { CompanyMenuPanel } from './company-menu-panel';
import { blankFeatureForm, FeatureModal } from './feature-modal';
import type { FeatureForm } from './feature-modal';
import { MobileMenuPanel } from './mobile-menu-panel';
import { RolePanel } from './role-panel';
import type { HakAksesProps } from './types';

/**
 * One tab per role, plus a company-wide menu tab and (super admin) the menu
 * builder. Everything the admin sees at once belongs to a single role, so
 * "munculkan Dashboard untuk Karyawan" is: open Karyawan, flip Dashboard.
 */
export default function AvanaHakAkses({
    roles,
    actions,
    modules,
    matrix,
    hasTenant,
    canManageFeatures,
    moduleGroups,
    moduleOptions,
    canManageMenu,
    menu,
    mobileMenu,
    mobileTabs,
}: HakAksesProps) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;

    // The open tab lives in the URL (?tab=) so a redirect-back after any toggle
    // returns to the same role instead of jumping to the first one.
    const initialTab = (): string => {
        if (typeof window === 'undefined') {
            return roles[0]?.code ?? 'menu-perusahaan';
        }

        const wanted = new URLSearchParams(window.location.search).get('tab');
        const valid = [
            ...roles.map((role) => role.code),
            'menu-perusahaan',
            ...(canManageMenu ? ['struktur-menu'] : []),
        ];

        return wanted !== null && valid.includes(wanted)
            ? wanted
            : (roles[0]?.code ?? 'menu-perusahaan');
    };

    const [tab, setTab] = useState<string>(initialTab);
    /** Which platform the company-wide menu tab is showing. */
    const [companyPlatform, setCompanyPlatform] = useState<'web' | 'mobile'>(
        'web',
    );
    const selectTab = (next: string) => {
        setTab(next);

        const url = new URL(window.location.href);
        url.searchParams.set('tab', next);
        window.history.replaceState({}, '', url.toString());
    };

    // Feature create modal state (super admin registers a new module).
    const [featureOpen, setFeatureOpen] = useState<null | {
        mode: 'create' | 'edit';
        id: number | null;
    }>(null);
    const [featureForm, setFeatureForm] =
        useState<FeatureForm>(blankFeatureForm);
    const [featureErrors, setFeatureErrors] = useState<Record<string, string>>(
        {},
    );

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }

        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash?.success, flash?.error]);

    const activeRoleIdx = useMemo(
        () => roles.findIndex((role) => role.code === tab),
        [roles, tab],
    );
    const activeRole = activeRoleIdx >= 0 ? roles[activeRoleIdx] : null;

    const visitOpts = { preserveScroll: true, preserveState: false } as const;

    const toggleCell = (rowIdx: number, action: string) => {
        if (activeRole === null) {
            return;
        }

        router.post(
            AccessController.togglePermission().url,
            {
                module_key: modules[rowIdx].key,
                action,
                role_id: activeRole.id,
            },
            visitOpts,
        );
    };

    const toggleVisible = (rowIdx: number, visible: boolean) => {
        if (activeRole === null) {
            return;
        }

        router.post(
            AccessController.toggleMenuVisibility().url,
            {
                menu_key: modules[rowIdx].key,
                role_id: activeRole.id,
                visible,
            },
            visitOpts,
        );
    };

    const toggleMenu = (rowIdx: number, active: boolean) => {
        router.post(
            AccessController.toggleMenu().url,
            { menu_key: modules[rowIdx].key, active },
            visitOpts,
        );
    };

    const toggleFeature = (rowIdx: number, enabled: boolean) => {
        router.post(
            AccessController.toggleFeature().url,
            { module_key: modules[rowIdx].key, enabled },
            visitOpts,
        );
    };

    const toggleMobileTile = (
        menuId: number,
        roleId: number,
        visible: boolean,
    ) => {
        router.post(
            AccessController.toggleMobileMenuVisibility().url,
            { menu_id: menuId, role_id: roleId, visible },
            visitOpts,
        );
    };

    const toggleMobileTileActive = (menuId: number, active: boolean) => {
        router.put(
            AccessController.updateMobileMenu().url,
            { menu_id: menuId, active },
            visitOpts,
        );
    };

    const renameMobileTile = (menuId: number, label: string) => {
        router.put(
            AccessController.updateMobileMenu().url,
            { menu_id: menuId, label },
            visitOpts,
        );
    };

    const reorderMobileTiles = (order: number[]) => {
        router.put(
            AccessController.reorderMobileMenu().url,
            { order },
            visitOpts,
        );
    };

    const toggleRoleMobile = (enabled: boolean) => {
        if (activeRole === null) {
            return;
        }

        router.post(
            AccessController.toggleRoleMobile(activeRole.id).url,
            { enabled },
            visitOpts,
        );
    };

    const openCreateFeature = () => {
        setFeatureForm(blankFeatureForm);
        setFeatureErrors({});
        setFeatureOpen({ mode: 'create', id: null });
    };

    const submitFeature = () => {
        const payload = {
            code: featureForm.code.trim(),
            name: featureForm.name.trim(),
            module_group: featureForm.module_group.trim(),
            permission_modules: featureForm.permission_modules
                .split(',')
                .map((m) => m.trim())
                .filter(Boolean),
            is_active: featureForm.is_active,
        };
        const opts = {
            preserveScroll: true,
            onError: (e: Record<string, string>) => setFeatureErrors(e),
            onSuccess: () => setFeatureOpen(null),
        };

        if (featureOpen?.mode === 'edit' && featureOpen.id) {
            router.put(
                FeatureCatalogController.update(featureOpen.id).url,
                payload,
                opts,
            );
        } else {
            router.post(FeatureCatalogController.store().url, payload, opts);
        }
    };

    return (
        <>
            <Head title="Hak Akses" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ marginBottom: 18 }}>
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
                        <span style={{ color: C.muted }}>Hak Akses</span>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'flex-end',
                            justifyContent: 'space-between',
                            gap: 14,
                            flexWrap: 'wrap',
                        }}
                    >
                        <div>
                            <h1
                                style={{
                                    fontSize: 24,
                                    fontWeight: 600,
                                    color: C.navy,
                                    margin: 0,
                                    letterSpacing: '-.01em',
                                }}
                            >
                                Hak Akses &amp; Peran
                            </h1>
                            <div
                                style={{
                                    fontSize: 14,
                                    color: C.muted,
                                    marginTop: 4,
                                }}
                            >
                                Pilih peran, atur siapa penggunanya dan menu apa
                                yang mereka lihat.
                            </div>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                flexWrap: 'wrap',
                            }}
                        >
                            {canManageFeatures && (
                                <button
                                    onClick={openCreateFeature}
                                    style={btnNested}
                                    title="Daftarkan modul fitur baru ke katalog"
                                >
                                    <AIcon name="plus" size={16} color="#fff" />
                                    Tambah Fitur
                                </button>
                            )}
                            <Link
                                href={AccessController.createRole().url}
                                style={{
                                    ...btnCreate,
                                    textDecoration: 'none',
                                }}
                                title="Buat peran baru untuk perusahaan ini"
                            >
                                <AIcon name="plus" size={16} color="#fff" />
                                Buat Peran
                            </Link>
                        </div>
                    </div>
                </div>

                {/* ---- The order the setup is meant to run in ---- */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        flexWrap: 'wrap',
                        background: hexA(C.primary, 0.05),
                        border: `1px solid ${hexA(C.primary, 0.15)}`,
                        borderRadius: 10,
                        padding: '11px 14px',
                        marginBottom: 18,
                        fontSize: 12.5,
                        color: C.muted,
                    }}
                >
                    {[
                        'Buat peran (mis. Manager Cabang)',
                        'Atur menu & akses aplikasi mobile peran itu',
                        'Baru input karyawan, lalu pilih perannya',
                    ].map((step, idx) => (
                        <span
                            key={step}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                            }}
                        >
                            {idx > 0 && (
                                <AIcon
                                    name="chevron-right"
                                    size={14}
                                    color={C.faint}
                                />
                            )}
                            <span
                                style={{
                                    width: 19,
                                    height: 19,
                                    borderRadius: 999,
                                    flex: 'none',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    background: C.primary,
                                    color: '#fff',
                                    fontSize: 11,
                                    fontWeight: 700,
                                }}
                            >
                                {idx + 1}
                            </span>
                            {step}
                        </span>
                    ))}
                </div>

                {/* ---- Tabs: one per role, then the company-wide switches ---- */}
                <div
                    style={{
                        display: 'flex',
                        gap: 4,
                        borderBottom: `1px solid ${C.border}`,
                        marginBottom: 20,
                        flexWrap: 'wrap',
                    }}
                >
                    {roles.map((role) => {
                        const active = tab === role.code;

                        return (
                            <button
                                key={role.id}
                                onClick={() => selectTab(role.code)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    padding: '10px 15px',
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    border: 'none',
                                    background: active
                                        ? hexA(role.color, 0.08)
                                        : C.surface,
                                    borderRadius: '9px 9px 0 0',
                                    cursor: 'pointer',
                                    color: active ? role.color : C.muted,
                                    borderBottom: active
                                        ? `2px solid ${role.color}`
                                        : '2px solid transparent',
                                    marginBottom: -1,
                                }}
                            >
                                <AIcon
                                    name="shield"
                                    size={14}
                                    color={active ? role.color : C.faint}
                                />
                                {role.name}
                                <span
                                    style={{
                                        fontSize: 11,
                                        fontWeight: 700,
                                        color: active ? role.color : C.faint,
                                        background: active
                                            ? hexA(role.color, 0.14)
                                            : C.line,
                                        padding: '1px 7px',
                                        borderRadius: 999,
                                    }}
                                >
                                    {role.users}
                                </span>
                                {role.locked && (
                                    <AIcon
                                        name="lock"
                                        size={11}
                                        color={C.faint}
                                    />
                                )}
                            </button>
                        );
                    })}

                    {(
                        [
                            {
                                key: 'menu-perusahaan',
                                label: 'Menu Perusahaan',
                                icon: 'building-2',
                            },
                            ...(canManageMenu
                                ? [
                                      {
                                          key: 'struktur-menu',
                                          label: 'Struktur Menu',
                                          icon: 'list-tree',
                                      },
                                  ]
                                : []),
                        ] as { key: string; label: string; icon: string }[]
                    ).map((extra) => {
                        const active = tab === extra.key;

                        return (
                            <button
                                key={extra.key}
                                onClick={() => selectTab(extra.key)}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    padding: '10px 15px',
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    border: 'none',
                                    background: active
                                        ? 'rgba(47,84,201,.07)'
                                        : C.surface,
                                    borderRadius: '9px 9px 0 0',
                                    cursor: 'pointer',
                                    color: active ? C.primary : C.muted,
                                    borderBottom: active
                                        ? `2px solid ${C.primary}`
                                        : '2px solid transparent',
                                    marginBottom: -1,
                                }}
                            >
                                <AIcon
                                    name={extra.icon}
                                    size={14}
                                    color={active ? C.primary : C.faint}
                                />
                                {extra.label}
                            </button>
                        );
                    })}
                </div>

                {activeRole !== null && (
                    <RolePanel
                        role={activeRole}
                        roleIdx={activeRoleIdx}
                        actions={actions}
                        modules={modules}
                        cells={modules.map(
                            (_, rowIdx) =>
                                matrix[rowIdx]?.[activeRoleIdx] ?? {},
                        )}
                        mobileMenu={mobileMenu}
                        mobileTabs={mobileTabs}
                        onToggle={toggleCell}
                        onToggleVisible={toggleVisible}
                        onToggleMobile={toggleRoleMobile}
                        onToggleMobileTile={(menuId, visible) =>
                            toggleMobileTile(menuId, activeRole.id, visible)
                        }
                    />
                )}

                {/* Company level, both platforms: which menus this company uses
                at all. Per-role choices live on each role's own tab. */}
                {tab === 'menu-perusahaan' && (
                    <>
                        <div
                            style={{
                                display: 'inline-flex',
                                gap: 4,
                                padding: 4,
                                borderRadius: 11,
                                background: C.surface,
                                border: `1px solid ${C.border}`,
                                marginBottom: 14,
                            }}
                        >
                            {(
                                [
                                    {
                                        key: 'web',
                                        label: 'Web',
                                        icon: 'monitor',
                                    },
                                    {
                                        key: 'mobile',
                                        label: 'Aplikasi HP',
                                        icon: 'smartphone',
                                    },
                                ] as const
                            ).map((platform) => {
                                const on = companyPlatform === platform.key;

                                return (
                                    <button
                                        key={platform.key}
                                        type="button"
                                        onClick={() =>
                                            setCompanyPlatform(platform.key)
                                        }
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 7,
                                            padding: '7px 14px',
                                            borderRadius: 8,
                                            border: 'none',
                                            background: on ? '#fff' : 'none',
                                            boxShadow: on
                                                ? '0 1px 2px rgba(15,26,58,.08)'
                                                : 'none',
                                            fontSize: 13,
                                            fontWeight: on ? 600 : 500,
                                            color: on ? C.primary : C.muted,
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <AIcon
                                            name={platform.icon}
                                            size={14}
                                            color={on ? C.primary : C.faint}
                                        />
                                        {platform.label}
                                    </button>
                                );
                            })}
                        </div>

                        {companyPlatform === 'web' ? (
                            <CompanyMenuPanel
                                modules={modules}
                                hasTenant={hasTenant}
                                canManageFeatures={canManageFeatures}
                                onToggleMenu={toggleMenu}
                                onToggleFeature={toggleFeature}
                            />
                        ) : (
                            <>
                                <MobileMenuPanel
                                    tiles={mobileMenu}
                                    onToggleActive={toggleMobileTileActive}
                                    onRename={renameMobileTile}
                                    onReorder={reorderMobileTiles}
                                />

                                {/* The bar along the bottom of the app. Same
                                    rules as Menu Cepat, so it is set in the
                                    same place rather than needing a build. */}
                                <div style={{ marginTop: 26 }}>
                                    <div
                                        style={{
                                            fontSize: 14,
                                            fontWeight: 600,
                                            color: C.navy,
                                            marginBottom: 10,
                                        }}
                                    >
                                        Menu Bawah (Bottom Bar)
                                    </div>
                                    <MobileMenuPanel
                                        tiles={mobileTabs}
                                        onToggleActive={toggleMobileTileActive}
                                        onRename={renameMobileTile}
                                        onReorder={reorderMobileTiles}
                                        itemHeading="Tab"
                                        showRoute={false}
                                        description="Deretan tab di bagian bawah aplikasi HP. Matikan sakelar untuk menghilangkan tabnya dari seluruh perusahaan — Ruang Kita juga ikut hilang bila fiturnya dimatikan di Kelola Fitur. Beranda dan Profil selalu ada karena aplikasi memerlukannya. Untuk mengatur per peran, buka tab perannya."
                                    />
                                </div>
                            </>
                        )}
                    </>
                )}

                {tab === 'struktur-menu' && canManageMenu && (
                    <>
                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                alignItems: 'flex-start',
                                background: 'rgba(47,84,201,.05)',
                                border: '1px solid rgba(47,84,201,.15)',
                                borderRadius: 10,
                                padding: '11px 14px',
                                fontSize: 12.5,
                                color: C.muted,
                                lineHeight: 1.5,
                            }}
                        >
                            <AIcon name="info" size={15} color={C.primary} />
                            <span>
                                <b style={{ color: C.navy }}>Tambah Menu</b> =
                                item yang tampil di sidebar &amp; menunjuk ke
                                sebuah halaman. Atur urutan, nesting, ikon, dan
                                fitur/izin yang menggerbanginya. Beda dari{' '}
                                <b>Tambah Fitur</b> (yang bikin
                                modul&nbsp;+&nbsp;izin, bukan tampilan sidebar).
                            </span>
                        </div>
                        <div style={{ margin: '10px -32px 0' }}>
                            <MenuBuilder
                                {...(menu as React.ComponentProps<
                                    typeof MenuBuilder
                                >)}
                                embedded
                            />
                        </div>
                    </>
                )}
            </div>

            {featureOpen && (
                <FeatureModal
                    mode={featureOpen.mode}
                    form={featureForm}
                    setForm={setFeatureForm}
                    errors={featureErrors}
                    moduleGroups={moduleGroups}
                    moduleOptions={moduleOptions}
                    actions={actions}
                    onClose={() => setFeatureOpen(null)}
                    onSubmit={submitFeature}
                />
            )}
        </>
    );
}
