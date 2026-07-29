import type { CSSProperties } from 'react';
import { Fragment, useMemo, useState } from 'react';
import { AIcon, C, card, hexA } from '@/lib/avana';
import { Switch } from './components';
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
    const [status, setStatus] = useState<CompanyMenuStatus>('all');

    /** A menu counts as live only when both its own switch and its feature are on. */
    const isLive = (module: AccessModule): boolean =>
        module.menuActive && module.featureEnabled;

    const counts = useMemo(() => {
        const live = modules.filter(isLive).length;

        return {
            all: modules.length,
            active: live,
            off: modules.length - live,
        };
    }, [modules]);

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
                    (status === 'all' ||
                        (status === 'active') === isLive(module)),
            );
    }, [modules, search, status]);

    const activeCount = counts.active;

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
                        Matikan di sini dan menu hilang untuk <b>semua peran</b>
                        . Untuk satu peran saja, pakai tab peran.{' '}
                        <b>{activeCount}</b> dari {modules.length} menu aktif.
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
                                        disabled={module.lockedActive}
                                        title={
                                            module.lockedActive
                                                ? `${module.label} tidak bisa dimatikan — ini layar yang sedang Anda pakai`
                                                : module.menuActive
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

/** Company-wide on/off, drawn by the same switch the role tabs use. */
function MasterSwitch({
    on,
    disabled = false,
    title,
    onToggle,
}: {
    on: boolean;
    disabled?: boolean;
    title: string;
    onToggle: () => void;
}) {
    return (
        <Switch
            on={on}
            disabled={disabled}
            title={title}
            tone={C.primary}
            onToggle={onToggle}
        />
    );
}

/** Filter states for the company menu list: everything, live, or switched off. */
type CompanyMenuStatus = 'all' | 'active' | 'off';

const STATUS_FILTERS: {
    key: CompanyMenuStatus;
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
        key: 'active',
        label: 'Aktif',
        tone: C.green,
        title: 'Menu yang hidup untuk perusahaan ini',
    },
    {
        key: 'off',
        label: 'Nonaktif',
        tone: '#B45309',
        title: 'Menu yang dimatikan, atau yang fiturnya tidak dibeli',
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
