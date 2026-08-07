import type { CSSProperties } from 'react';
import { Fragment, useMemo, useState } from 'react';
import { AIcon, C, card, hexA } from '@/lib/avana';
import { ActionCheckbox, MOBILE_WEB_ICON, Switch } from './components';
import type {
    AccessAction,
    AccessModule,
    AccessRole,
    MatrixCell,
    MobileMenuTile,
} from './types';

interface RolePanelProps {
    role: AccessRole;
    roleIdx: number;
    actions: AccessAction[];
    modules: AccessModule[];
    /** cells[rowIdx] for the selected role only. */
    cells: MatrixCell[];
    /** The Menu Cepat tiles of the phone app, company-wide. */
    mobileMenu: MobileMenuTile[];
    /** The phone's bottom navigation bar, company-wide. */
    mobileTabs: MobileMenuTile[];
    onToggle: (rowIdx: number, action: string) => void;
    onToggleVisible: (rowIdx: number, visible: boolean) => void;
    onToggleMobile: (enabled: boolean) => void;
    onToggleMobileTile: (menuId: number, visible: boolean) => void;
}

/**
 * Everything about one role on one screen: who holds it, and which menus it
 * sees. One role at a time — the four-role-wide matrix made a simple wish
 * ("show Dashboard to Karyawan") into a hunt across columns.
 */
export function RolePanel({
    role,
    roleIdx,
    actions,
    modules,
    cells,
    mobileMenu,
    mobileTabs,
    onToggle,
    onToggleVisible,
    onToggleMobile,
    onToggleMobileTile,
}: RolePanelProps) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState<MenuStatus>('all');

    /**
     * "Not visible" splits in two, and the two have different fixes: a menu the
     * role was denied is switched back on right here, while one the company
     * turned off entirely can only be revived on the Menu Perusahaan tab.
     */
    const statusOf = (module: AccessModule, rowIdx: number): MenuStatus => {
        if (!module.menuActive || !module.featureEnabled) {
            return 'blocked';
        }

        return cells[rowIdx]?.visible === false ? 'hidden' : 'shown';
    };

    const counts = useMemo(() => {
        const tally: Record<MenuStatus, number> = {
            all: modules.length,
            shown: 0,
            hidden: 0,
            blocked: 0,
        };

        modules.forEach((module, rowIdx) => {
            tally[statusOf(module, rowIdx)] += 1;
        });

        return tally;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [modules, cells]);

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

                return status === 'all' || statusOf(module, rowIdx) === status;
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [modules, cells, search, status]);

    const shownCount = counts.shown;

    /** Only tiles the company still has switched on can be given to a role. */
    const liveTiles = mobileMenu.filter((tile) => tile.isActive);
    const liveTabs = mobileTabs.filter((tab) => tab.isActive);

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
                                <div style={{ fontSize: 12.5, color: C.muted }}>
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
                            <b style={{ color: C.navy }}>
                                {role.members.length}
                            </b>{' '}
                            pengguna memakai peran ini ·{' '}
                            <b style={{ color: C.navy }}>{shownCount}</b> dari{' '}
                            {modules.length} menu terlihat
                        </div>
                    </div>

                    {/* Assigning a role happens on the employee form, where the
                    person is being set up anyway. A second way in from here
                    only made two places to look when somebody's access is
                    wrong. */}
                    <a
                        href="/avana/employees"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            height: 38,
                            padding: '0 14px',
                            borderRadius: 10,
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            color: C.muted,
                            fontSize: 12.5,
                            fontWeight: 500,
                            textDecoration: 'none',
                        }}
                    >
                        <AIcon name="users" size={15} color={C.faint} />
                        Atur di Data Karyawan
                    </a>
                </div>

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
                                    padding: '5px 11px',
                                    borderRadius: 999,
                                    border: `1px solid ${C.line}`,
                                    background: '#fff',
                                    fontSize: 12.5,
                                    color: C.text,
                                }}
                            >
                                {member.name}
                            </span>
                        ))}
                    </div>
                )}
            </div>

            {/* ---- Mobile (Flutter) app access ---- */}
            <div
                style={{
                    ...card,
                    padding: '16px 20px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 14,
                    flexWrap: 'wrap',
                }}
            >
                <div
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
                                role.canAccessMobile ? C.green : C.faint,
                                0.12,
                            ),
                        }}
                    >
                        <AIcon
                            name="smartphone"
                            size={17}
                            color={role.canAccessMobile ? C.green : C.faint}
                        />
                    </span>
                    <span>
                        <div
                            style={{
                                fontSize: 14.5,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Aplikasi Mobile
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginTop: 3,
                                lineHeight: 1.5,
                            }}
                        >
                            {role.canAccessMobile
                                ? `Pemegang peran ${role.name} boleh masuk aplikasi HP (absensi, cuti, slip gaji).`
                                : `Pemegang peran ${role.name} ditolak saat login di aplikasi HP — akses web tidak terpengaruh.`}
                        </div>
                    </span>
                </div>
                <Switch
                    on={role.canAccessMobile}
                    disabled={role.locked}
                    title={
                        role.locked
                            ? 'Peran ini tidak dapat Anda ubah'
                            : 'Izinkan peran ini memakai aplikasi mobile'
                    }
                    label={role.canAccessMobile ? 'Diizinkan' : 'Ditutup'}
                    onToggle={() => onToggleMobile(!role.canAccessMobile)}
                />
            </div>

            {/* ---- Which phone shortcuts this role gets ---- */}
            {role.canAccessMobile && liveTiles.length > 0 && (
                <PhoneMenuCard
                    title="Menu Cepat di HP"
                    description={`Pintasan yang muncul di beranda aplikasi untuk pemegang peran ${role.name}. Urutan dan daftar lengkapnya diatur di tab Menu Perusahaan.`}
                    tiles={liveTiles}
                    roleIdx={roleIdx}
                    locked={role.locked}
                    onToggle={onToggleMobileTile}
                />
            )}

            {/* ---- Which bottom tabs this role gets ---- */}
            {role.canAccessMobile && liveTabs.length > 0 && (
                <PhoneMenuCard
                    title="Menu Bawah di HP"
                    description={`Tab di bagian bawah aplikasi untuk pemegang peran ${role.name}. Beranda dan Profil selalu ada — aplikasi tidak bisa dipakai tanpa keduanya.`}
                    tiles={liveTabs}
                    roleIdx={roleIdx}
                    locked={role.locked}
                    onToggle={onToggleMobileTile}
                />
            )}

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
                            Geser sakelar untuk memunculkan atau menyembunyikan
                            menu · centang aksi untuk izin tombolnya.
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
                        <div style={segmentGroupStyle}>
                            {STATUS_FILTERS.map((option) => {
                                const active = status === option.key;

                                return (
                                    <button
                                        key={option.key}
                                        type="button"
                                        title={option.title}
                                        onClick={() => setStatus(option.key)}
                                        style={{
                                            ...segmentStyle,
                                            background: active
                                                ? '#fff'
                                                : 'transparent',
                                            color: active ? C.navy : C.muted,
                                            boxShadow: active
                                                ? '0 1px 3px rgba(14,26,58,.12)'
                                                : 'none',
                                        }}
                                    >
                                        {option.label}
                                        <span
                                            style={{
                                                ...segmentCountStyle,
                                                background: active
                                                    ? hexA(option.tone, 0.14)
                                                    : '#EEF1F7',
                                                color: active
                                                    ? option.tone
                                                    : C.faint,
                                            }}
                                        >
                                            {counts[option.key]}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
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
                                    <div style={groupHeaderStyle}>
                                        {module.group}
                                    </div>
                                )}
                                <div
                                    style={{
                                        ...menuRowStyle,
                                        opacity: blocked ? 0.5 : 1,
                                    }}
                                >
                                    <div style={{ minWidth: 0 }}>
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
                                        reason={
                                            shown
                                                ? null
                                                : blocked
                                                  ? 'Menu nonaktif'
                                                  : cell.hidden
                                                    ? 'Disembunyikan'
                                                    : 'Belum ada izin'
                                        }
                                        disabled={role.locked || blocked}
                                        label={module.label}
                                        roleName={role.name}
                                        onToggle={() =>
                                            onToggleVisible(rowIdx, !shown)
                                        }
                                    />

                                    <div style={actionCellStyle}>
                                        {module.actionable &&
                                        !cell.hidden &&
                                        !blocked ? (
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
                                                    ? 'tampilkan dulu untuk mengatur izin'
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

/** Where a menu stands for this role, and what the list can be filtered to. */
type MenuStatus = 'all' | 'shown' | 'hidden' | 'blocked';

const STATUS_FILTERS: {
    key: MenuStatus;
    label: string;
    tone: string;
    title: string;
}[] = [
    {
        key: 'all',
        label: 'Semua',
        tone: C.primary,
        title: 'Seluruh menu, apa pun statusnya',
    },
    {
        key: 'shown',
        label: 'Tampil',
        tone: C.green,
        title: 'Menu yang dilihat peran ini',
    },
    {
        key: 'hidden',
        label: 'Disembunyikan',
        tone: C.muted,
        title: 'Menu yang disembunyikan dari peran ini — bisa dinyalakan di sini',
    },
    {
        key: 'blocked',
        label: 'Nonaktif',
        tone: '#B45309',
        title: 'Menu atau fitur yang dimatikan untuk seluruh perusahaan — atur di tab Menu Perusahaan',
    },
];

const segmentGroupStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 2,
    padding: 3,
    borderRadius: 10,
    background: '#EEF1F7',
};

const segmentStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    height: 28,
    padding: '0 10px',
    border: 'none',
    borderRadius: 8,
    fontSize: 12.5,
    fontWeight: 600,
    cursor: 'pointer',
    transition: 'background .14s ease',
};

const segmentCountStyle: CSSProperties = {
    fontSize: 11,
    fontWeight: 700,
    lineHeight: 1,
    padding: '3px 6px',
    borderRadius: 999,
};

/**
 * A fixed three-column row — name, visibility, actions — so the switches and
 * checkboxes stack into straight columns down a list a hundred menus long.
 * Flex-wrap let every row place them wherever its own label happened to end.
 */
const menuRowStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'minmax(0,1fr) 150px minmax(0,430px)',
    alignItems: 'center',
    gap: 14,
    padding: '11px 18px',
    borderTop: `1px solid ${C.line}`,
};

const groupHeaderStyle: CSSProperties = {
    position: 'sticky',
    top: 0,
    zIndex: 1,
    padding: '9px 18px',
    fontSize: 11,
    fontWeight: 700,
    letterSpacing: '.04em',
    color: C.muted,
    textTransform: 'uppercase',
    background: '#F2F5FB',
    borderTop: `1px solid ${C.line}`,
};

const actionCellStyle: CSSProperties = {
    display: 'flex',
    gap: '6px 14px',
    flexWrap: 'wrap',
    justifyContent: 'flex-end',
};

/**
 * The one control that answers "does this role see the menu". Off carries the
 * reason, because "not visible" has three different fixes.
 */
function VisibleToggle({
    on,
    reason,
    disabled,
    label,
    roleName,
    onToggle,
}: {
    on: boolean;
    reason: string | null;
    disabled: boolean;
    label: string;
    roleName: string;
    onToggle: () => void;
}) {
    return (
        <Switch
            on={on}
            disabled={disabled}
            label={on ? 'Tampil' : (reason ?? 'Tidak tampil')}
            title={
                disabled
                    ? reason === 'Menu nonaktif'
                        ? `${label} dimatikan untuk seluruh perusahaan — atur di tab Menu Perusahaan`
                        : `${roleName}: peran ini tidak dapat diubah`
                    : on
                      ? `Sembunyikan ${label} dari ${roleName}`
                      : `Tampilkan ${label} untuk ${roleName} (izin Lihat diberikan bila belum ada)`
            }
            onToggle={onToggle}
        />
    );
}

/**
 * One card of phone rows for the selected role — the Menu Cepat shortcuts or
 * the bottom tabs. Ticking a box shows that row to this role; the company-wide
 * list and its order are set on the Menu Perusahaan tab.
 */
function PhoneMenuCard({
    title,
    description,
    tiles,
    roleIdx,
    locked,
    onToggle,
}: {
    title: string;
    description: string;
    tiles: MobileMenuTile[];
    roleIdx: number;
    /** The role itself cannot be edited (a system role). */
    locked: boolean;
    onToggle: (menuId: number, visible: boolean) => void;
}) {
    return (
        <div style={{ ...card, padding: '18px 20px' }}>
            <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                {title}
            </div>
            <div
                style={{
                    fontSize: 12.5,
                    color: C.muted,
                    marginTop: 4,
                    lineHeight: 1.55,
                }}
            >
                {description}
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))',
                    gap: 8,
                    marginTop: 14,
                }}
            >
                {tiles.map((tile) => {
                    const visible = tile.visible[roleIdx] ?? true;
                    // A tab the app cannot run without stays ticked for every
                    // role, the same way its company switch refuses to move.
                    const frozen = locked || tile.locked === true;

                    return (
                        <label
                            key={tile.id}
                            title={
                                tile.locked
                                    ? `${tile.label} selalu ada — aplikasi butuh tab ini`
                                    : locked
                                      ? 'Peran ini tidak dapat Anda ubah'
                                      : `${tile.label} di aplikasi HP`
                            }
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                padding: '9px 11px',
                                borderRadius: 10,
                                border: `1px solid ${visible ? hexA(C.primary, 0.35) : C.line}`,
                                background: visible
                                    ? hexA(C.primary, 0.05)
                                    : '#fff',
                                cursor: frozen ? 'not-allowed' : 'pointer',
                                opacity: frozen ? 0.6 : 1,
                            }}
                        >
                            <input
                                type="checkbox"
                                checked={visible}
                                disabled={frozen}
                                onChange={() => onToggle(tile.id, !visible)}
                                style={{
                                    width: 16,
                                    height: 16,
                                    cursor: 'inherit',
                                }}
                            />
                            <span
                                style={{
                                    width: 26,
                                    height: 26,
                                    flex: 'none',
                                    borderRadius: 8,
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    background: `${tile.color}1f`,
                                }}
                            >
                                <AIcon
                                    name={
                                        MOBILE_WEB_ICON[tile.icon] ?? tile.icon
                                    }
                                    size={14}
                                    color={tile.color}
                                />
                            </span>
                            <span
                                style={{
                                    fontSize: 13,
                                    color: C.text,
                                    fontWeight: 500,
                                }}
                            >
                                {tile.label}
                            </span>
                        </label>
                    );
                })}
            </div>
        </div>
    );
}
