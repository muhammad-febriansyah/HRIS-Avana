import { useState } from 'react';
import type { CSSProperties } from 'react';
import { AIcon, C } from '@/lib/avana';
import { MOBILE_WEB_ICON, Switch } from './components';
import type { MobileMenuTile } from './types';

interface MobileMenuPanelProps {
    tiles: MobileMenuTile[];
    onToggleActive: (menuId: number, active: boolean) => void;
    onRename: (menuId: number, label: string) => void;
    onReorder: (order: number[]) => void;
    /** Column heading for the first column — "Pintasan" or "Tab". */
    itemHeading?: string;
    /** Sentence above the table explaining what this list drives. */
    description?: string;
    /**
     * The bottom bar has no routes to show — a tab is switched to, not opened —
     * so that column is dropped rather than left blank on every row.
     */
    showRoute?: boolean;
}

const cellStyle: CSSProperties = {
    padding: '11px 10px',
    borderBottom: `1px solid ${C.line}`,
    fontSize: 13,
    color: C.text,
    verticalAlign: 'middle',
};

const headStyle: CSSProperties = {
    padding: '11px 10px',
    textAlign: 'center',
    fontSize: 11,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
    whiteSpace: 'nowrap',
    borderBottom: `1px solid ${C.line}`,
};

/**
 * The Menu Cepat of the phone app, at company level: which tiles exist at all,
 * what they are called, and in what order they appear.
 *
 * Who gets which tile is decided on the role's own tab, next to that role's web
 * menus — the same place, and the same question, for both platforms.
 */
export function MobileMenuPanel({
    tiles,
    onToggleActive,
    onRename,
    onReorder,
    itemHeading = 'Pintasan',
    description = 'Pintasan di beranda aplikasi HP. Matikan sakelar untuk menghapusnya dari seluruh perusahaan. Urutan di sini adalah urutan di HP — empat teratas yang paling sering dibuka. Untuk memilih peran mana yang dapat tiap pintasan, buka tab perannya.',
    showRoute = true,
}: MobileMenuPanelProps) {
    const [editing, setEditing] = useState<number | null>(null);
    const [draft, setDraft] = useState('');

    const move = (index: number, delta: number) => {
        const next = [...tiles];
        const target = index + delta;

        if (target < 0 || target >= next.length) {
            return;
        }

        [next[index], next[target]] = [next[target], next[index]];
        onReorder(next.map((tile) => tile.id));
    };

    const commitRename = (tile: MobileMenuTile) => {
        const label = draft.trim();

        setEditing(null);

        if (label !== '' && label !== tile.label) {
            onRename(tile.id, label);
        }
    };

    return (
        <div>
            <div
                style={{
                    fontSize: 12.5,
                    color: C.muted,
                    lineHeight: 1.55,
                    marginBottom: 12,
                }}
            >
                {description}
            </div>

            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr>
                            <th
                                style={{
                                    ...headStyle,
                                    textAlign: 'left',
                                    minWidth: 240,
                                }}
                            >
                                {itemHeading}
                            </th>
                            {showRoute ? (
                                <th style={{ ...headStyle, textAlign: 'left' }}>
                                    Membuka
                                </th>
                            ) : null}
                            <th style={headStyle}>Aktif</th>
                            <th style={{ ...headStyle, width: 80 }}>Urutan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {tiles.map((tile, index) => (
                            <tr
                                key={tile.id}
                                style={{ opacity: tile.isActive ? 1 : 0.45 }}
                            >
                                <td style={cellStyle}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 28,
                                                height: 28,
                                                flex: 'none',
                                                borderRadius: 8,
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                background: `${tile.color}1f`,
                                                border: `1px solid ${tile.color}3d`,
                                            }}
                                        >
                                            <AIcon
                                                name={
                                                    MOBILE_WEB_ICON[
                                                        tile.icon
                                                    ] ?? tile.icon
                                                }
                                                size={15}
                                                color={tile.color}
                                            />
                                        </span>
                                        {editing === tile.id ? (
                                            <input
                                                autoFocus
                                                value={draft}
                                                placeholder={tile.label}
                                                maxLength={30}
                                                onChange={(event) =>
                                                    setDraft(event.target.value)
                                                }
                                                onBlur={() =>
                                                    commitRename(tile)
                                                }
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') {
                                                        commitRename(tile);
                                                    }

                                                    if (
                                                        event.key === 'Escape'
                                                    ) {
                                                        setEditing(null);
                                                    }
                                                }}
                                                style={{
                                                    height: 30,
                                                    padding: '0 8px',
                                                    border: `1px solid ${C.primary}`,
                                                    borderRadius: 7,
                                                    fontSize: 13,
                                                    color: C.text,
                                                    outline: 'none',
                                                    width: 160,
                                                }}
                                            />
                                        ) : (
                                            <button
                                                type="button"
                                                title="Klik untuk ganti nama"
                                                onClick={() => {
                                                    setEditing(tile.id);
                                                    setDraft(tile.label);
                                                }}
                                                style={{
                                                    border: 'none',
                                                    background: 'none',
                                                    padding: 0,
                                                    fontSize: 13,
                                                    fontWeight: 500,
                                                    color: C.text,
                                                    cursor: 'text',
                                                }}
                                            >
                                                {tile.label}
                                            </button>
                                        )}
                                    </div>
                                </td>

                                {showRoute ? (
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontSize: 12,
                                            color: C.faint,
                                        }}
                                    >
                                        {tile.route}
                                    </td>
                                ) : null}

                                <td
                                    style={{
                                        ...cellStyle,
                                        textAlign: 'center',
                                    }}
                                >
                                    <Switch
                                        on={tile.isActive}
                                        tone={C.primary}
                                        disabled={tile.locked === true}
                                        title={
                                            tile.locked
                                                ? `${tile.label} selalu ada — aplikasi butuh tab ini`
                                                : tile.isActive
                                                  ? `Hapus ${tile.label} dari aplikasi seluruh perusahaan`
                                                  : `Tampilkan ${tile.label} lagi`
                                        }
                                        onToggle={() =>
                                            onToggleActive(
                                                tile.id,
                                                !tile.isActive,
                                            )
                                        }
                                    />
                                </td>

                                <td
                                    style={{
                                        ...cellStyle,
                                        textAlign: 'center',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'inline-flex',
                                            gap: 3,
                                        }}
                                    >
                                        <OrderButton
                                            icon="chevron-up"
                                            title="Naikkan"
                                            disabled={index === 0}
                                            onClick={() => move(index, -1)}
                                        />
                                        <OrderButton
                                            icon="chevron-down"
                                            title="Turunkan"
                                            disabled={
                                                index === tiles.length - 1
                                            }
                                            onClick={() => move(index, 1)}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** One nudge of a tile up or down the carousel. */
function OrderButton({
    icon,
    title,
    disabled,
    onClick,
}: {
    icon: string;
    title: string;
    disabled: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            style={{
                width: 24,
                height: 24,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                border: `1px solid ${C.border}`,
                borderRadius: 6,
                background: '#fff',
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.35 : 1,
                padding: 0,
            }}
        >
            <AIcon name={icon} size={13} color={C.muted} />
        </button>
    );
}
