import { Fragment, useMemo, useState } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import { ActionCheckbox } from './components';
import type {
    AccessAction,
    AccessModule,
    AccessRole,
    MatrixCell,
} from './types';

interface PermissionMatrixProps {
    roles: AccessRole[];
    actions: AccessAction[];
    modules: AccessModule[];
    permHeaders: string[];
    hasTenant: boolean;
    canManageFeatures: boolean;
    onToggle: (rowIdx: number, colIdx: number, action: string) => void;
    onToggleVisible: (rowIdx: number, colIdx: number, visible: boolean) => void;
    onToggleMenu: (rowIdx: number, active: boolean) => void;
    onToggleFeature: (rowIdx: number, enabled: boolean) => void;
    matrix: MatrixCell[][];
}

/** A compact pill switch used by the tenant-wide "Aktif" column. */
function MasterSwitch({
    on,
    title,
    disabled = false,
    onToggle,
}: {
    on: boolean;
    title: string;
    disabled?: boolean;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            title={title}
            disabled={disabled}
            onClick={disabled ? undefined : onToggle}
            style={{
                width: 40,
                height: 23,
                borderRadius: 100,
                border: 'none',
                cursor: disabled ? 'not-allowed' : 'pointer',
                position: 'relative',
                transition: 'background .15s',
                background: on ? C.primary : '#D5DCEA',
                opacity: disabled ? 0.5 : 1,
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

/** The per-role "Tampil" switch: does this role see the menu at all. */
function VisibleToggle({
    on,
    disabled,
    title,
    onToggle,
}: {
    on: boolean;
    disabled: boolean;
    title: string;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            title={title}
            disabled={disabled}
            onClick={disabled ? undefined : onToggle}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                padding: '4px 9px',
                borderRadius: 999,
                border: `1px solid ${on ? 'rgba(22,163,74,.35)' : C.border}`,
                background: on ? 'rgba(22,163,74,.1)' : '#fff',
                color: on ? C.green : C.faint,
                fontSize: 11.5,
                fontWeight: 600,
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.5 : 1,
                whiteSpace: 'nowrap',
            }}
        >
            <AIcon
                name={on ? 'eye' : 'eye-off'}
                size={13}
                color={on ? C.green : C.faint}
            />
            {on ? 'Tampil' : 'Disembunyikan'}
        </button>
    );
}

/**
 * Menu × role matrix. One row per real menu of the sidebar: the tenant-wide
 * "Aktif" switch, then per role a "Tampil" switch plus the action checkboxes the
 * menu's permission modules allow.
 */
export function PermissionMatrix({
    roles,
    actions,
    modules,
    permHeaders,
    hasTenant,
    canManageFeatures,
    onToggle,
    onToggleVisible,
    onToggleMenu,
    onToggleFeature,
    matrix,
}: PermissionMatrixProps) {
    const [search, setSearch] = useState('');
    const [onlyHidden, setOnlyHidden] = useState(false);

    // Rows are filtered for display only; every callback still carries the row's
    // original index so the server keeps receiving the right menu.
    const visibleRows = useMemo(() => {
        const term = search.trim().toLowerCase();

        return modules
            .map((module, rowIdx) => ({ module, rowIdx }))
            .filter(({ module, rowIdx }) => {
                if (
                    term !== '' &&
                    !`${module.label} ${module.parent ?? ''} ${module.group}`
                        .toLowerCase()
                        .includes(term)
                ) {
                    return false;
                }

                if (onlyHidden) {
                    const anyHidden = roles.some(
                        (_, colIdx) => matrix[rowIdx]?.[colIdx]?.visible === false,
                    );

                    return anyHidden || !module.menuActive;
                }

                return true;
            });
    }, [modules, roles, matrix, search, onlyHidden]);

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
                        Matriks Menu × Peran
                    </div>
                    <div
                        style={{ fontSize: 12, color: C.faint, marginTop: 3 }}
                    >
                        Satu baris = satu menu sidebar. <b>Aktif</b> = on/off
                        untuk seluruh perusahaan · <b>Tampil</b> = menu terlihat
                        oleh peran itu · centang aksi = izin tombolnya.
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
                                color: C.text,
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
                        onClick={() => setOnlyHidden((v) => !v)}
                        style={{
                            height: 34,
                            padding: '0 12px',
                            borderRadius: 9,
                            border: `1px solid ${onlyHidden ? C.primary : C.border}`,
                            background: onlyHidden
                                ? 'rgba(47,84,201,.08)'
                                : '#fff',
                            color: onlyHidden ? C.primary : C.muted,
                            fontSize: 12.5,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        Hanya yang disembunyikan
                    </button>
                </div>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 860,
                    }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th
                                style={{
                                    padding: '13px 18px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Menu
                            </th>
                            <th
                                style={{
                                    padding: '13px 12px',
                                    textAlign: 'center',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                Aktif
                            </th>
                            {permHeaders.map((h, i) => (
                                <th
                                    key={i}
                                    style={{
                                        padding: '13px 16px',
                                        textAlign: 'center',
                                        fontSize: 11.5,
                                        fontWeight: 600,
                                        color: roles[i]?.locked
                                            ? C.faint
                                            : C.muted,
                                        textTransform: 'uppercase',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {h}
                                    {roles[i]?.locked && (
                                        <AIcon
                                            name="lock"
                                            size={11}
                                            color={C.faint}
                                        />
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={2 + roles.length}
                                    style={{
                                        padding: '34px 18px',
                                        textAlign: 'center',
                                        fontSize: 13,
                                        color: C.faint,
                                    }}
                                >
                                    Tidak ada menu yang cocok.
                                </td>
                            </tr>
                        )}
                        {visibleRows.map(({ module, rowIdx }, listIdx) => {
                            // Off tenant-wide, or its package feature is absent:
                            // per-role edits are moot until that is fixed.
                            const dimmed =
                                !module.menuActive || !module.featureEnabled;
                            const showGroup =
                                visibleRows[listIdx - 1]?.module.group !==
                                module.group;

                            return (
                                <Fragment key={module.key}>
                                    {showGroup && (
                                        <tr>
                                            <td
                                                colSpan={2 + roles.length}
                                                style={{
                                                    padding: '12px 18px 6px',
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
                                            </td>
                                        </tr>
                                    )}
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                fontSize: 13.5,
                                                fontWeight: 500,
                                                color: dimmed
                                                    ? C.faint
                                                    : C.text,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 8,
                                                }}
                                            >
                                                {module.parent && (
                                                    <span
                                                        style={{
                                                            color: C.faint,
                                                            fontWeight: 400,
                                                        }}
                                                    >
                                                        {module.parent} ›
                                                    </span>
                                                )}
                                                {module.label}
                                                {module.selfService && (
                                                    <span
                                                        title="Menu self-service karyawan: tidak punya izin aksi, atur lewat kolom Tampil"
                                                        style={{
                                                            fontSize: 10.5,
                                                            fontWeight: 700,
                                                            color: C.sky,
                                                            background:
                                                                'rgba(14,165,233,.12)',
                                                            padding: '2px 7px',
                                                            borderRadius: 999,
                                                        }}
                                                    >
                                                        ESS
                                                    </span>
                                                )}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11,
                                                    color: C.faint,
                                                    marginTop: 3,
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                    flexWrap: 'wrap',
                                                }}
                                            >
                                                {module.permissionModules
                                                    .length > 0 && (
                                                    <span
                                                        title="Modul izin yang menggerbangi menu ini — menu lain dengan modul sama ikut berubah"
                                                        style={{
                                                            fontFamily:
                                                                'ui-monospace, monospace',
                                                        }}
                                                    >
                                                        {module.permissionModules.join(
                                                            ', ',
                                                        )}
                                                    </span>
                                                )}
                                                {!module.featureEnabled &&
                                                    module.featureLabel && (
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
                                                                    ? `Fitur "${module.featureLabel}" tidak aktif — klik untuk mengaktifkan`
                                                                    : `Fitur "${module.featureLabel}" tidak termasuk paket langganan`
                                                            }
                                                            style={{
                                                                border: 'none',
                                                                background:
                                                                    'rgba(217,119,6,.12)',
                                                                color: '#B45309',
                                                                fontSize: 10.5,
                                                                fontWeight: 700,
                                                                padding:
                                                                    '2px 7px',
                                                                borderRadius: 999,
                                                                cursor:
                                                                    canManageFeatures &&
                                                                    hasTenant
                                                                        ? 'pointer'
                                                                        : 'default',
                                                            }}
                                                        >
                                                            Fitur nonaktif
                                                        </button>
                                                    )}
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                padding: '10px 12px',
                                                textAlign: 'center',
                                                verticalAlign: 'middle',
                                            }}
                                        >
                                            {hasTenant &&
                                            module.menuItemId !== null ? (
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        justifyContent:
                                                            'center',
                                                    }}
                                                >
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
                                                </div>
                                            ) : (
                                                <div
                                                    style={{
                                                        color: C.faint,
                                                        fontSize: 12,
                                                    }}
                                                >
                                                    selalu aktif
                                                </div>
                                            )}
                                        </td>
                                        {roles.map((role, colIdx) => {
                                            const cell =
                                                matrix[rowIdx]?.[colIdx] ?? {};
                                            const shown = cell.visible !== false;

                                            return (
                                                <td
                                                    key={role.id}
                                                    style={{
                                                        padding: '10px 12px',
                                                        verticalAlign: 'middle',
                                                        opacity: dimmed
                                                            ? 0.45
                                                            : 1,
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            display: 'grid',
                                                            gap: 7,
                                                            justifyItems:
                                                                'center',
                                                        }}
                                                    >
                                                        <VisibleToggle
                                                            on={shown}
                                                            disabled={
                                                                role.locked
                                                            }
                                                            title={
                                                                role.locked
                                                                    ? `${role.name}: peran ini tidak dapat diubah`
                                                                    : shown
                                                                      ? `Sembunyikan ${module.label} dari ${role.name}`
                                                                      : `Tampilkan ${module.label} untuk ${role.name}`
                                                            }
                                                            onToggle={() =>
                                                                onToggleVisible(
                                                                    rowIdx,
                                                                    colIdx,
                                                                    !shown,
                                                                )
                                                            }
                                                        />
                                                        {module.actionable &&
                                                            shown && (
                                                                <div
                                                                    style={{
                                                                        display:
                                                                            'grid',
                                                                        gridTemplateColumns:
                                                                            'repeat(2, auto)',
                                                                        gap: '5px 12px',
                                                                        justifyContent:
                                                                            'center',
                                                                    }}
                                                                >
                                                                    {actions.map(
                                                                        (
                                                                            action,
                                                                        ) => (
                                                                            <ActionCheckbox
                                                                                key={
                                                                                    action.key
                                                                                }
                                                                                label={
                                                                                    action.label
                                                                                }
                                                                                on={
                                                                                    !!cell[
                                                                                        action
                                                                                            .key
                                                                                    ]
                                                                                }
                                                                                disabled={
                                                                                    role.locked
                                                                                }
                                                                                title={
                                                                                    role.locked
                                                                                        ? `${role.name}: peran ini tidak dapat diubah`
                                                                                        : `${action.label} · ${module.label}`
                                                                                }
                                                                                onToggle={() =>
                                                                                    onToggle(
                                                                                        rowIdx,
                                                                                        colIdx,
                                                                                        action.key,
                                                                                    )
                                                                                }
                                                                            />
                                                                        ),
                                                                    )}
                                                                </div>
                                                            )}
                                                    </div>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                </Fragment>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
