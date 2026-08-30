import type { CSSProperties } from 'react';
import { AIcon, C } from '@/lib/avana';

/* ---------- shared presentational helpers for the hak-akses page ---------- */

interface SwitchProps {
    on: boolean;
    disabled?: boolean;
    title?: string;
    /** Text rendered beside the track; carries the reason when the switch is off. */
    label?: string;
    /** Colour of the "on" state — green for grants, primary for view filters. */
    tone?: string;
    onToggle: () => void;
}

/**
 * A sliding switch. Used wherever the page turns something on or off, so a
 * setting never reads like a button that performs an action: the track shows
 * the current state, not what a click would do.
 */
/**
 * Lucide stand-ins for the Iconsax names the phone app uses.
 *
 * The stored icon is the app's own name, because that is what the app has to
 * render; the web only needs something recognisable beside the label, so the
 * mapping lives here rather than becoming a second column to keep in sync.
 */
export const MOBILE_WEB_ICON: Record<string, string> = {
    category: 'layout-grid',
    sun_1: 'sun',
    calendar_remove: 'calendar-x',
    timer_1: 'timer',
    house: 'house',
    calendar_1: 'calendar',
    clock: 'clock',
    arrow_swap_horizontal: 'arrow-left-right',
    receipt_2: 'receipt',
    wallet_money: 'wallet',
    wallet_add: 'hand-coins',
    receipt_2_1: 'receipt-text',
    location: 'map-pin',
    document_text: 'file-text',
    emoji_happy: 'smile',
    microphone_2: 'mic',
    flash_1: 'zap',
    task_square: 'clipboard-list',
    // Bottom-bar tabs.
    home_2: 'house',
    people: 'users',
    finger_scan: 'fingerprint',
    volume_high: 'megaphone',
    user: 'user',
};

export function Switch({
    on,
    disabled = false,
    title,
    label,
    tone = C.green,
    onToggle,
}: SwitchProps) {
    const trackStyle: CSSProperties = {
        position: 'relative',
        display: 'inline-block',
        width: 34,
        height: 19,
        flex: 'none',
        borderRadius: 999,
        background: on ? tone : '#D3D9E4',
        transition: 'background .16s ease',
    };

    const knobStyle: CSSProperties = {
        position: 'absolute',
        top: 2,
        left: on ? 17 : 2,
        width: 15,
        height: 15,
        borderRadius: '50%',
        background: '#fff',
        boxShadow: '0 1px 3px rgba(14,26,58,.28)',
        transition: 'left .16s ease',
    };

    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            aria-label={title}
            disabled={disabled}
            title={title}
            onClick={disabled ? undefined : onToggle}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 9,
                padding: 0,
                border: 'none',
                background: 'none',
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.45 : 1,
                whiteSpace: 'nowrap',
            }}
        >
            <span style={trackStyle}>
                <span style={knobStyle} />
            </span>
            {label ? (
                <span
                    style={{
                        fontSize: 12.5,
                        fontWeight: 600,
                        color: on ? tone : C.faint,
                    }}
                >
                    {label}
                </span>
            ) : null}
        </button>
    );
}

interface ToggleCellProps {
    on: boolean;
    onToggle: () => void;
}

/**
 * A single permission-matrix cell: a button showing a green check when the
 * role covers the module, or a grey minus when it does not. Clicking toggles.
 */
export function ToggleCell({ on, onToggle }: ToggleCellProps) {
    return (
        <td
            style={{
                padding: '11px 16px',
                textAlign: 'center',
            }}
        >
            <button
                onClick={onToggle}
                style={{
                    width: 30,
                    height: 30,
                    borderRadius: 8,
                    border: 'none',
                    cursor: 'pointer',
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    transition: '.15s',
                    background: on ? 'rgba(22,163,74,.12)' : C.line,
                    color: on ? C.green : C.faint,
                }}
            >
                <AIcon
                    name={on ? 'check' : 'minus'}
                    size={15}
                    color={on ? C.green : C.faint}
                />
            </button>
        </td>
    );
}

interface ActionCheckboxProps {
    label: string;
    on: boolean;
    disabled?: boolean;
    title?: string;
    onToggle: () => void;
}

/**
 * A per-action checkbox used inside a role cell (Lihat / Tambah / Ubah / Hapus /
 * Ekspor / Setujui). Checked = granted; a locked role renders it disabled.
 */
export function ActionCheckbox({
    label,
    on,
    disabled = false,
    title,
    onToggle,
}: ActionCheckboxProps) {
    return (
        <label
            title={title ?? label}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 6,
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.5 : 1,
                fontSize: 11.5,
                fontWeight: 500,
                color: on ? C.green : C.muted,
                userSelect: 'none',
                whiteSpace: 'nowrap',
            }}
        >
            <input
                type="checkbox"
                checked={on}
                disabled={disabled}
                onChange={disabled ? undefined : onToggle}
                style={{
                    width: 15,
                    height: 15,
                    margin: 0,
                    accentColor: C.green,
                    cursor: 'inherit',
                }}
            />
            {label}
        </label>
    );
}
