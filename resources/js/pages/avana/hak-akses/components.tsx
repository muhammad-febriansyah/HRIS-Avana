import { AIcon, C } from '@/lib/avana';

/* ---------- shared presentational helpers for the hak-akses page ---------- */

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
