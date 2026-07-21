import { Fragment } from 'react';
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
    onToggle: (rowIdx: number, colIdx: number, action: string) => void;
    onToggleFeature: (rowIdx: number, enabled: boolean) => void;
    matrix: MatrixCell[][];
}

/** A compact pill switch mirroring the Menu & Fitur toggle. */
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

/** Menu × role matrix; each role cell holds one toggle per action. */
export function PermissionMatrix({
    roles,
    actions,
    modules,
    permHeaders,
    hasTenant,
    onToggle,
    onToggleFeature,
    matrix,
}: PermissionMatrixProps) {
    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div
                style={{
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.border}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                    Matriks Izin · Menu × Peran × Aksi
                </div>
                <div
                    style={{
                        fontSize: 12,
                        color: C.faint,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 6,
                    }}
                >
                    <AIcon name="info" size={14} />
                    Aktif = tampilkan menu · aksi = beri / cabut izin
                </div>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 780,
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
                        {modules.map((module, rowIdx) => {
                            // A menu with features turned off is hidden tenant-wide;
                            // dim its per-role cells to signal the role edits are moot
                            // until the menu is re-enabled.
                            const dimmed =
                                module.hasFeature && !module.featureEnabled;
                            // Section header when the group changes between rows.
                            const showGroup =
                                modules[rowIdx - 1]?.group !== module.group;

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
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td
                                        style={{
                                            padding: '13px 18px',
                                            fontSize: 13.5,
                                            fontWeight: 500,
                                            color: dimmed ? C.faint : C.text,
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {module.label}
                                    </td>
                                    <td
                                        style={{
                                            padding: '10px 12px',
                                            textAlign: 'center',
                                            verticalAlign: 'middle',
                                        }}
                                    >
                                        {module.hasFeature && hasTenant ? (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    justifyContent: 'center',
                                                }}
                                            >
                                                <MasterSwitch
                                                    on={module.featureEnabled}
                                                    title={
                                                        module.featureEnabled
                                                            ? `Nonaktifkan menu ${module.label} (sembunyikan dari sidebar)`
                                                            : `Aktifkan menu ${module.label}`
                                                    }
                                                    onToggle={() =>
                                                        onToggleFeature(
                                                            rowIdx,
                                                            !module.featureEnabled,
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
                                    {roles.map((role, colIdx) => (
                                        <td
                                            key={role.id}
                                            style={{
                                                padding: '10px 12px',
                                                verticalAlign: 'middle',
                                                opacity: dimmed ? 0.4 : 1,
                                            }}
                                        >
                                            {module.actionable ? (
                                                <div
                                                    style={{
                                                        display: 'grid',
                                                        gridTemplateColumns:
                                                            'repeat(2, auto)',
                                                        gap: '6px 14px',
                                                        justifyContent: 'center',
                                                    }}
                                                >
                                                    {actions.map((action) => (
                                                        <ActionCheckbox
                                                            key={action.key}
                                                            label={action.label}
                                                            on={
                                                                !!matrix[rowIdx][
                                                                    colIdx
                                                                ][action.key]
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
                                                    ))}
                                                </div>
                                            ) : (
                                                <div
                                                    style={{
                                                        textAlign: 'center',
                                                        color: C.faint,
                                                        fontSize: 12,
                                                    }}
                                                    title={
                                                        module.hasFeature
                                                            ? 'Izin per-peran diatur di modul terkait'
                                                            : undefined
                                                    }
                                                >
                                                    {module.hasFeature
                                                        ? '—'
                                                        : 'selalu aktif'}
                                                </div>
                                            )}
                                        </td>
                                    ))}
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
