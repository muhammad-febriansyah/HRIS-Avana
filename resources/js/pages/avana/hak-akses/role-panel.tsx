import { Fragment, useMemo, useState } from 'react';
import { AIcon, C, card, hexA } from '@/lib/avana';
import { ActionCheckbox } from './components';
import type {
    AccessAction,
    AccessModule,
    AccessRole,
    AssignableUser,
    MatrixCell,
} from './types';

interface RolePanelProps {
    role: AccessRole;
    roleIdx: number;
    actions: AccessAction[];
    modules: AccessModule[];
    /** cells[rowIdx] for the selected role only. */
    cells: MatrixCell[];
    assignableUsers: AssignableUser[];
    onToggle: (rowIdx: number, action: string) => void;
    onToggleVisible: (rowIdx: number, visible: boolean) => void;
    onAttachUser: (userId: number) => void;
    onDetachUser: (userId: number) => void;
}

/**
 * Everything about one role on one screen: who holds it, and which menus it
 * sees. One role at a time — the four-role-wide matrix made a simple wish
 * ("show Dashboard to Karyawan") into a hunt across columns.
 */
export function RolePanel({
    role,
    actions,
    modules,
    cells,
    assignableUsers,
    onToggle,
    onToggleVisible,
    onAttachUser,
    onDetachUser,
}: RolePanelProps) {
    const [search, setSearch] = useState('');
    const [onlyHidden, setOnlyHidden] = useState(false);
    const [memberPickerOpen, setMemberPickerOpen] = useState(false);
    const [memberSearch, setMemberSearch] = useState('');

    const rows = useMemo(() => {
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

                return onlyHidden ? cells[rowIdx]?.visible === false : true;
            });
    }, [modules, cells, search, onlyHidden]);

    const shownCount = modules.filter(
        (_, rowIdx) => cells[rowIdx]?.visible !== false,
    ).length;

    const candidates = useMemo(() => {
        const term = memberSearch.trim().toLowerCase();

        return assignableUsers
            .filter((candidate) => !candidate.role_ids.includes(role.id))
            .filter(
                (candidate) =>
                    term === '' ||
                    `${candidate.name} ${candidate.email}`
                        .toLowerCase()
                        .includes(term),
            )
            .slice(0, 30);
    }, [assignableUsers, memberSearch, role.id]);

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            {/* ---- Who holds this role ---- */}
            <div style={{ ...card, padding: '18px 20px' }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 14,
                        flexWrap: 'wrap',
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 9,
                            }}
                        >
                            <span
                                style={{
                                    width: 34,
                                    height: 34,
                                    borderRadius: 10,
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    background: hexA(role.color, 0.12),
                                }}
                            >
                                <AIcon
                                    name="shield"
                                    size={17}
                                    color={role.color}
                                />
                            </span>
                            <span>
                                <div
                                    style={{
                                        fontSize: 16,
                                        fontWeight: 700,
                                        color: C.navy,
                                    }}
                                >
                                    {role.name}
                                </div>
                                <div
                                    style={{ fontSize: 12.5, color: C.muted }}
                                >
                                    {role.desc ||
                                        'Peran kustom perusahaan Anda'}
                                </div>
                            </span>
                        </div>

                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginTop: 12,
                            }}
                        >
                            <b style={{ color: C.navy }}>{role.members.length}</b>{' '}
                            pengguna memakai peran ini ·{' '}
                            <b style={{ color: C.navy }}>{shownCount}</b> dari{' '}
                            {modules.length} menu terlihat
                        </div>
                    </div>

                    {!role.locked && (
                        <button
                            type="button"
                            onClick={() => setMemberPickerOpen((v) => !v)}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                height: 38,
                                padding: '0 14px',
                                borderRadius: 10,
                                border: `1px solid ${C.primary}`,
                                background: memberPickerOpen
                                    ? hexA(C.primary, 0.08)
                                    : '#fff',
                                color: C.primary,
                                fontSize: 13,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
                            <AIcon name="user-plus" size={15} />
                            Tambah Pengguna
                        </button>
                    )}
                </div>

                {memberPickerOpen && (
                    <div
                        style={{
                            marginTop: 14,
                            border: `1px solid ${C.border}`,
                            borderRadius: 11,
                            padding: 12,
                            background: C.surface,
                        }}
                    >
                        <input
                            value={memberSearch}
                            onChange={(e) => setMemberSearch(e.target.value)}
                            placeholder="Cari nama atau email…"
                            style={{
                                width: '100%',
                                height: 36,
                                padding: '0 11px',
                                borderRadius: 9,
                                border: `1px solid ${C.border}`,
                                fontSize: 13,
                                outline: 'none',
                                marginBottom: 10,
                            }}
                        />
                        <div
                            style={{
                                display: 'grid',
                                gap: 6,
                                maxHeight: 210,
                                overflowY: 'auto',
                            }}
                        >
                            {candidates.length === 0 && (
                                <div
                                    style={{ fontSize: 12.5, color: C.faint }}
                                >
                                    Semua pengguna sudah memakai peran ini.
                                </div>
                            )}
                            {candidates.map((candidate) => (
                                <button
                                    key={candidate.id}
                                    type="button"
                                    onClick={() => onAttachUser(candidate.id)}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 10,
                                        padding: '9px 11px',
                                        borderRadius: 9,
                                        border: `1px solid ${C.line}`,
                                        background: '#fff',
                                        cursor: 'pointer',
                                        textAlign: 'left',
                                    }}
                                >
                                    <span>
                                        <div
                                            style={{
                                                fontSize: 13,
                                                fontWeight: 500,
                                                color: C.text,
                                            }}
                                        >
                                            {candidate.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                            }}
                                        >
                                            {candidate.email}
                                        </div>
                                    </span>
                                    <AIcon
                                        name="plus"
                                        size={15}
                                        color={C.primary}
                                    />
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {role.members.length > 0 && (
                    <div
                        style={{
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: 8,
                            marginTop: 14,
                        }}
                    >
                        {role.members.map((member) => (
                            <span
                                key={member.id}
                                title={`${member.email}${member.position ? ' · '.concat(member.position) : ''}`}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    padding: '5px 9px 5px 11px',
                                    borderRadius: 999,
                                    border: `1px solid ${C.line}`,
                                    background: '#fff',
                                    fontSize: 12.5,
                                    color: C.text,
                                }}
                            >
                                {member.name}
                                {!role.locked && (
                                    <button
                                        type="button"
                                        title={`Keluarkan ${member.name} dari peran ini`}
                                        onClick={() =>
                                            onDetachUser(member.id)
                                        }
                                        style={{
                                            border: 'none',
                                            background: 'none',
                                            padding: 0,
                                            cursor: 'pointer',
                                            display: 'inline-flex',
                                            color: C.faint,
                                        }}
                                    >
                                        <AIcon
                                            name="x"
                                            size={13}
                                            color={C.faint}
                                        />
                                    </button>
                                )}
                            </span>
                        ))}
                    </div>
                )}
            </div>

            {/* ---- Menus this role sees ---- */}
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
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Menu untuk {role.name}
                        </div>
                        <div
                            style={{
                                fontSize: 12,
                                color: C.faint,
                                marginTop: 3,
                            }}
                        >
                            Klik <b>Tampil</b> untuk memunculkan atau
                            menyembunyikan menu · centang aksi untuk izin
                            tombolnya.
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
                                <AIcon
                                    name="search"
                                    size={14}
                                    color={C.faint}
                                />
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
                                    ? hexA(C.primary, 0.08)
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

                {role.locked && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 9,
                            alignItems: 'flex-start',
                            padding: '11px 18px',
                            background: 'rgba(217,119,6,.07)',
                            borderBottom: `1px solid ${C.line}`,
                            fontSize: 12.5,
                            color: '#B45309',
                        }}
                    >
                        <AIcon name="lock" size={15} color="#B45309" />
                        <span>
                            Peran ini tidak dapat diubah dari akun Anda — sistem
                            mencegah Anda mengunci diri sendiri. Gunakan akun
                            super admin bila perlu mengubahnya.
                        </span>
                    </div>
                )}

                <div style={{ display: 'grid' }}>
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
                        const cell = cells[rowIdx] ?? {};
                        const shown = cell.visible !== false;
                        const blocked =
                            !module.menuActive || !module.featureEnabled;
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
                                        flexWrap: 'wrap',
                                        padding: '12px 18px',
                                        borderTop: `1px solid ${C.line}`,
                                        opacity: blocked ? 0.5 : 1,
                                    }}
                                >
                                    <div style={{ minWidth: 240, flex: 1 }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 8,
                                                fontSize: 13.5,
                                                fontWeight: 500,
                                                color: C.text,
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
                                                    title="Menu self-service karyawan: tidak punya izin aksi"
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
                                        {blocked && (
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: '#B45309',
                                                    marginTop: 3,
                                                }}
                                            >
                                                {!module.featureEnabled
                                                    ? 'Fitur tidak aktif untuk perusahaan ini'
                                                    : 'Menu dimatikan untuk seluruh perusahaan'}{' '}
                                                — atur di tab Menu Perusahaan.
                                            </div>
                                        )}
                                    </div>

                                    <VisibleToggle
                                        on={shown}
                                        disabled={role.locked}
                                        label={module.label}
                                        roleName={role.name}
                                        onToggle={() =>
                                            onToggleVisible(rowIdx, !shown)
                                        }
                                    />

                                    <div
                                        style={{
                                            display: 'flex',
                                            gap: '6px 14px',
                                            flexWrap: 'wrap',
                                            minWidth: 320,
                                            justifyContent: 'flex-end',
                                        }}
                                    >
                                        {module.actionable && shown ? (
                                            actions.map((action) => (
                                                <ActionCheckbox
                                                    key={action.key}
                                                    label={action.label}
                                                    on={!!cell[action.key]}
                                                    disabled={role.locked}
                                                    title={
                                                        role.locked
                                                            ? `${role.name}: peran ini tidak dapat diubah`
                                                            : `${action.label} · ${module.label}`
                                                    }
                                                    onToggle={() =>
                                                        onToggle(
                                                            rowIdx,
                                                            action.key,
                                                        )
                                                    }
                                                />
                                            ))
                                        ) : (
                                            <span
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                }}
                                            >
                                                {module.actionable
                                                    ? 'sembunyikan/tampilkan untuk mengatur izin'
                                                    : 'tanpa izin aksi'}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </Fragment>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

/** The one control that answers "does this role see the menu". */
function VisibleToggle({
    on,
    disabled,
    label,
    roleName,
    onToggle,
}: {
    on: boolean;
    disabled: boolean;
    label: string;
    roleName: string;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            disabled={disabled}
            title={
                disabled
                    ? `${roleName}: peran ini tidak dapat diubah`
                    : on
                      ? `Sembunyikan ${label} dari ${roleName}`
                      : `Tampilkan ${label} untuk ${roleName}`
            }
            onClick={disabled ? undefined : onToggle}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 7,
                padding: '5px 11px',
                borderRadius: 999,
                border: `1px solid ${on ? 'rgba(22,163,74,.35)' : C.border}`,
                background: on ? 'rgba(22,163,74,.1)' : '#fff',
                color: on ? C.green : C.faint,
                fontSize: 12,
                fontWeight: 600,
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.5 : 1,
                whiteSpace: 'nowrap',
                flex: 'none',
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
