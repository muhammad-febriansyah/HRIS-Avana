import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import AccessController from '@/actions/App/Http/Controllers/Avana/AccessController';
import FeatureCatalogController from '@/actions/App/Http/Controllers/Avana/FeatureCatalogController';
import { AIcon, btnP, C } from '@/lib/avana';
import MenuBuilder from '../menu-builder';
import {
    blankFeatureForm,
    FeatureModal,
    type FeatureForm,
} from './feature-modal';
import { PermissionMatrix } from './permission-matrix';
import { RoleCards } from './role-cards';
import { RoleModal } from './role-modal';
import type { AccessModule, FlashProps, HakAksesProps } from './types';

type Tab = 'akses' | 'menu';

export default function AvanaHakAkses({
    roles,
    actions,
    modules,
    permHeaders,
    matrix,
    hasTenant,
    canManageFeatures,
    moduleGroups,
    moduleOptions,
    canManageMenu,
    menu,
}: HakAksesProps) {
    const { flash } = usePage<FlashProps>().props;
    // Tab lives in the URL (?tab=menu) so a tenant switch / redirect-back keeps it.
    const [tab, setTab] = useState<Tab>(() =>
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('tab') === 'menu'
            ? 'menu'
            : 'akses',
    );
    const selectTab = (next: Tab) => {
        setTab(next);
        const url = new URL(window.location.href);
        if (next === 'akses') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', next);
        }
        window.history.replaceState({}, '', url.toString());
    };
    const [roleModalOpen, setRoleModalOpen] = useState(false);
    const [roleName, setRoleName] = useState('');

    // Feature create/edit modal state.
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
    }, [flash?.success]);

    const closeRoleModal = () => {
        setRoleModalOpen(false);
        setRoleName('');
    };

    const toggleCell = (rowIdx: number, colIdx: number, action: string) => {
        router.post(
            AccessController.togglePermission().url,
            {
                module_key: modules[rowIdx].key,
                action,
                role_id: roles[colIdx].id,
            },
            { preserveScroll: true },
        );
    };

    const toggleFeature = (rowIdx: number, enabled: boolean) => {
        router.post(
            AccessController.toggleFeature().url,
            { module_key: modules[rowIdx].key, enabled },
            { preserveScroll: true },
        );
    };

    const submitRole = () => {
        router.post(
            AccessController.storeRole().url,
            { name: roleName },
            { preserveScroll: true, onSuccess: closeRoleModal },
        );
    };

    // --- Feature catalog CRUD (super-admin) --------------------------------
    const openCreateFeature = () => {
        setFeatureForm(blankFeatureForm);
        setFeatureErrors({});
        setFeatureOpen({ mode: 'create', id: null });
    };

    const openEditFeature = (module: AccessModule) => {
        setFeatureForm({
            code: module.key,
            name: module.label,
            module_group: module.moduleGroup ?? '',
            permission_modules: module.permissionModules.join(', '),
            is_active: true,
        });
        setFeatureErrors({});
        setFeatureOpen({ mode: 'edit', id: module.featureId });
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

    const deleteFeature = (module: AccessModule) => {
        if (
            module.featureId === null ||
            !window.confirm(
                `Hapus fitur "${module.label}"? Baris & menu terkait akan hilang.`,
            )
        ) {
            return;
        }
        router.delete(FeatureCatalogController.destroy(module.featureId).url, {
            preserveScroll: true,
        });
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
                        style={{ fontSize: 14, color: C.muted, marginTop: 4 }}
                    >
                        Fitur, izin per-peran &amp; struktur menu dalam satu
                        layar.
                    </div>
                </div>

                {/* Tabs */}
                {canManageMenu && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 4,
                            borderBottom: `1px solid ${C.border}`,
                            marginBottom: 22,
                        }}
                    >
                        {(
                            [
                                { key: 'akses', label: 'Izin & Fitur' },
                                { key: 'menu', label: 'Struktur Menu' },
                            ] as { key: Tab; label: string }[]
                        ).map((t) => (
                            <button
                                key={t.key}
                                onClick={() => selectTab(t.key)}
                                style={{
                                    padding: '10px 16px',
                                    fontSize: 13.5,
                                    fontWeight: 600,
                                    border: 'none',
                                    background: 'transparent',
                                    cursor: 'pointer',
                                    color: tab === t.key ? C.primary : C.muted,
                                    borderBottom:
                                        tab === t.key
                                            ? `2px solid ${C.primary}`
                                            : '2px solid transparent',
                                    marginBottom: -1,
                                }}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                )}

                {tab === 'akses' && (
                    <>
                        {canManageFeatures && (
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 10,
                                    alignItems: 'flex-start',
                                    background: 'rgba(47,84,201,.05)',
                                    border: `1px solid rgba(47,84,201,.15)`,
                                    borderRadius: 10,
                                    padding: '11px 14px',
                                    marginBottom: 16,
                                    fontSize: 12.5,
                                    color: C.muted,
                                    lineHeight: 1.5,
                                }}
                            >
                                <AIcon
                                    name="info"
                                    size={15}
                                    color={C.primary}
                                />
                                <span>
                                    <b style={{ color: C.navy }}>
                                        Tambah Fitur
                                    </b>{' '}
                                    = daftarkan modul baru (otomatis dapat izin
                                    per-peran + baris di matriks). Ini{' '}
                                    <b>belum</b> jadi menu di sidebar — untuk itu
                                    pakai tab <b>Struktur Menu</b> →{' '}
                                    <b>Tambah Menu</b>.
                                </span>
                            </div>
                        )}
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                gap: 12,
                                flexWrap: 'wrap',
                                marginBottom: 16,
                            }}
                        >
                            <div />
                            <div style={{ display: 'flex', gap: 10 }}>
                                {canManageFeatures && (
                                    <button
                                        onClick={openCreateFeature}
                                        style={{
                                            ...btnP,
                                            background: '#fff',
                                            color: C.primary,
                                            border: `1px solid ${C.primary}`,
                                        }}
                                    >
                                        <AIcon name="plus" size={16} />
                                        Tambah Fitur
                                    </button>
                                )}
                                <button
                                    onClick={() => setRoleModalOpen(true)}
                                    style={btnP}
                                >
                                    <AIcon name="plus" size={16} />
                                    Buat Role Kustom
                                </button>
                            </div>
                        </div>

                        <RoleCards roles={roles} />

                        <PermissionMatrix
                            roles={roles}
                            actions={actions}
                            modules={modules}
                            permHeaders={permHeaders}
                            matrix={matrix}
                            hasTenant={hasTenant}
                            canManageFeatures={canManageFeatures}
                            onToggle={toggleCell}
                            onToggleFeature={toggleFeature}
                            onEditFeature={openEditFeature}
                            onDeleteFeature={deleteFeature}
                        />
                    </>
                )}

                {tab === 'menu' && canManageMenu && (
                    <>
                        <div
                            style={{
                                display: 'flex',
                                gap: 10,
                                alignItems: 'flex-start',
                                background: 'rgba(47,84,201,.05)',
                                border: `1px solid rgba(47,84,201,.15)`,
                                borderRadius: 10,
                                padding: '11px 14px',
                                marginBottom: 4,
                                fontSize: 12.5,
                                color: C.muted,
                                lineHeight: 1.5,
                            }}
                        >
                            <AIcon name="info" size={15} color={C.primary} />
                            <span>
                                <b style={{ color: C.navy }}>Tambah Menu</b> =
                                item yang tampil di sidebar &amp; menunjuk ke
                                sebuah halaman/route. Atur urutan, nesting, ikon,
                                sembunyikan, dan fitur/izin yang menggerbanginya.
                                Beda dari <b>Tambah Fitur</b> (yang bikin
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
