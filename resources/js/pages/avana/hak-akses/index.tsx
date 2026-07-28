import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import AccessController from '@/actions/App/Http/Controllers/Avana/AccessController';
import FeatureCatalogController from '@/actions/App/Http/Controllers/Avana/FeatureCatalogController';
import { AIcon, btnCreate, btnNested, C, hexA } from '@/lib/avana';
import MenuBuilder from '../menu-builder';
import { CompanyMenuPanel } from './company-menu-panel';
import { blankFeatureForm, FeatureModal } from './feature-modal';
import type { FeatureForm } from './feature-modal';
import { RoleModal } from './role-modal';
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
    assignableUsers,
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
    const selectTab = (next: string) => {
        setTab(next);

        const url = new URL(window.location.href);
        url.searchParams.set('tab', next);
        window.history.replaceState({}, '', url.toString());
    };

    const [roleModalOpen, setRoleModalOpen] = useState(false);
    const [roleName, setRoleName] = useState('');
    const [copyFromRole, setCopyFromRole] = useState<string>('');

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

    const attachUser = (userId: number) => {
        if (activeRole === null) {
            return;
        }

        router.post(
            AccessController.attachRoleUser(activeRole.id).url,
            { user_id: userId },
            visitOpts,
        );
    };

    const detachUser = (userId: number) => {
        if (activeRole === null) {
            return;
        }

        router.delete(
            AccessController.detachRoleUser({
                role: activeRole.id,
                member: userId,
            }).url,
            visitOpts,
        );
    };

    const closeRoleModal = () => {
        setRoleModalOpen(false);
        setRoleName('');
        setCopyFromRole('');
    };

    const submitRole = () => {
        router.post(
            AccessController.storeRole().url,
            {
                name: roleName,
                copy_from_role_id:
                    copyFromRole === '' ? null : Number(copyFromRole),
            },
            { preserveScroll: true, onSuccess: closeRoleModal },
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
                            <button
                                onClick={() => setRoleModalOpen(true)}
                                style={btnCreate}
                                title="Buat peran baru untuk perusahaan ini"
                            >
                                <AIcon name="plus" size={16} color="#fff" />
                                Buat Peran
                            </button>
                        </div>
                    </div>
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
                        assignableUsers={assignableUsers}
                        onToggle={toggleCell}
                        onToggleVisible={toggleVisible}
                        onAttachUser={attachUser}
                        onDetachUser={detachUser}
                    />
                )}

                {tab === 'menu-perusahaan' && (
                    <CompanyMenuPanel
                        modules={modules}
                        hasTenant={hasTenant}
                        canManageFeatures={canManageFeatures}
                        onToggleMenu={toggleMenu}
                        onToggleFeature={toggleFeature}
                    />
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

            {roleModalOpen && (
                <RoleModal
                    roleName={roleName}
                    onChangeName={setRoleName}
                    copyFromRole={copyFromRole}
                    onChangeCopyFrom={setCopyFromRole}
                    roles={roles}
                    onSubmit={submitRole}
                    onClose={closeRoleModal}
                />
            )}

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
