import { Fragment, useMemo, useState } from 'react';
import { AIcon, C, card, hexA } from '@/lib/avana';
import type { AccessModule } from './types';

interface CompanyMenuPanelProps {
    modules: AccessModule[];
    hasTenant: boolean;
    canManageFeatures: boolean;
    onToggleMenu: (rowIdx: number, active: boolean) => void;
    onToggleFeature: (rowIdx: number, enabled: boolean) => void;
}

/**
 * The switches that are NOT per role: a menu on or off for the whole company,
 * and the package feature behind it. Kept on its own tab so the per-role tabs
 * only ever show per-role decisions.
 */
export function CompanyMenuPanel({
    modules,
    hasTenant,
    canManageFeatures,
    onToggleMenu,
    onToggleFeature,
}: CompanyMenuPanelProps) {
    const [search, setSearch] = useState('');
    const [onlyOff, setOnlyOff] = useState(false);

    const rows = useMemo(() => {
        const term = search.trim().toLowerCase();

        return modules
            .map((module, rowIdx) => ({ module, rowIdx }))
            .filter(
                ({ module }) =>
                    (term === '' ||
                        `${module.label} ${module.parent ?? ''} ${module.group}`
                            .toLowerCase()
                            .includes(term)) &&
                    (!onlyOff || !module.menuActive || !module.featureEnabled),
            );
    }, [modules, search, onlyOff]);

    const activeCount = modules.filter((m) => m.menuActive).length;

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
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
                        style={{ fontSize: 15, fontWeight: 600, color: C.navy }}
                    >
                        Menu Perusahaan
                    </div>
                    <div style={{ fontSize: 12, color: C.faint, marginTop: 3 }}>
                        Matikan di sini dan menu hilang untuk{' '}
                        <b>semua peran</b>. Untuk satu peran saja, pakai tab
                        peran. <b>{activeCount}</b> dari {modules.length} menu
                        aktif.
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
                    <span style={{ position: 'relative' }}>
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari menu…"
                            style={{
                                height: 34,
                                padding: '0 11px 0 30px',
                                borderRadius: 9,
                                border: `1px solid ${C.border}`,
                                fontSize: 13,
                                outline: 'none',
                                width: 190,
                            }}
                        />
                        <span
                            style={{
                                position: 'absolute',
                                left: 9,
                                top: 9,
                                pointerEvents: 'none',
                            }}
                        >
                            <AIcon name="search" size={14} color={C.faint} />
                        </span>
                    </span>
                    <button
                        type="button"
                        onClick={() => setOnlyOff((v) => !v)}
                        style={{
                            height: 34,
                            padding: '0 12px',
                            borderRadius: 9,
                            border: `1px solid ${onlyOff ? C.primary : C.border}`,
                            background: onlyOff
                                ? hexA(C.primary, 0.08)
                                : '#fff',
                            color: onlyOff ? C.primary : C.muted,
                            fontSize: 12.5,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        Hanya yang nonaktif
                    </button>
                </div>
            </div>

            <div>
                {rows.length === 0 && (
                    <div
                        style={{
                            padding: '34px 18px',
                            textAlign: 'center',
                            fontSize: 13,
                            color: C.faint,
                        }}
                    >
                        Tidak ada menu yang cocok.
                    </div>
                )}
                {rows.map(({ module, rowIdx }, listIdx) => {
                    const showGroup =
                        rows[listIdx - 1]?.module.group !== module.group;

                    return (
                        <Fragment key={module.key}>
                            {showGroup && (
                                <div
                                    style={{
                                        padding: '11px 18px 5px',
                                        fontSize: 11,
                                        fontWeight: 700,
                                        letterSpacing: '.04em',
                                        color: C.faint,
                                        textTransform: 'uppercase',
                                        background: '#FAFBFD',
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    {module.group}
                                </div>
                            )}
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 14,
                                    padding: '12px 18px',
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                <div style={{ flex: 1, minWidth: 220 }}>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 500,
                                            color: module.menuActive
                                                ? C.text
                                                : C.faint,
                                        }}
                                    >
                                        {module.parent && (
                                            <span
                                                style={{
                                                    color: C.faint,
                                                    fontWeight: 400,
                                                }}
                                            >
                                                {module.parent} ›{' '}
                                            </span>
                                        )}
                                        {module.label}
                                    </div>
                                    {module.featureLabel && (
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                                marginTop: 3,
                                            }}
                                        >
                                            Fitur: {module.featureLabel}
                                            {!module.featureEnabled && (
                                                <>
                                                    {' · '}
                                                    <button
                                                        type="button"
                                                        onClick={
                                                            canManageFeatures &&
                                                            hasTenant
                                                                ? () =>
                                                                      onToggleFeature(
                                                                          rowIdx,
                                                                          true,
                                                                      )
                                                                : undefined
                                                        }
                                                        title={
                                                            canManageFeatures
                                                                ? 'Aktifkan fitur ini'
                                                                : 'Fitur tidak termasuk paket langganan'
                                                        }
                                                        style={{
                                                            border: 'none',
                                                            background:
                                                                'rgba(217,119,6,.12)',
                                                            color: '#B45309',
                                                            fontSize: 10.5,
                                                            fontWeight: 700,
                                                            padding: '2px 7px',
                                                            borderRadius: 999,
                                                            cursor:
                                                                canManageFeatures &&
                                                                hasTenant
                                                                    ? 'pointer'
                                                                    : 'default',
                                                        }}
                                                    >
                                                        nonaktif
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {hasTenant && module.menuItemId !== null ? (
                                    <MasterSwitch
                                        on={module.menuActive}
                                        title={
                                            module.menuActive
                                                ? `Nonaktifkan ${module.label} untuk seluruh perusahaan`
                                                : `Aktifkan ${module.label}`
                                        }
                                        onToggle={() =>
                                            onToggleMenu(
                                                rowIdx,
                                                !module.menuActive,
                                            )
                                        }
                                    />
                                ) : (
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: C.faint,
                                        }}
                                    >
                                        selalu aktif
                                    </span>
                                )}
                            </div>
                        </Fragment>
                    );
                })}
            </div>
        </div>
    );
}

function MasterSwitch({
    on,
    title,
    onToggle,
}: {
    on: boolean;
    title: string;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            title={title}
            onClick={onToggle}
            style={{
                width: 40,
                height: 23,
                borderRadius: 100,
                border: 'none',
                cursor: 'pointer',
                position: 'relative',
                transition: 'background .15s',
                background: on ? C.primary : '#D5DCEA',
                flex: 'none',
            }}
        >
            <span
                style={{
                    position: 'absolute',
                    top: 3,
                    left: on ? 20 : 3,
                    width: 17,
                    height: 17,
                    borderRadius: '50%',
                    background: '#fff',
                    transition: 'left .15s',
                    boxShadow: '0 1px 3px rgba(15,23,42,.2)',
                }}
            />
        </button>
    );
}
