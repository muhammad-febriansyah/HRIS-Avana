import { AIcon, C, card } from '@/lib/avana';
import { ToggleCell } from './components';
import type { AccessModule, AccessRole } from './types';

interface PermissionMatrixProps {
    roles: AccessRole[];
    modules: AccessModule[];
    permHeaders: string[];
    matrix: boolean[][];
    onToggle: (rowIdx: number, colIdx: number) => void;
}

/** Module × role permission matrix; each cell is a clickable toggle. */
export function PermissionMatrix({
    roles,
    modules,
    permHeaders,
    matrix,
    onToggle,
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
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                    }}
                >
                    Matriks Izin · Modul × Peran
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
                    Klik sel untuk mengubah izin
                </div>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 620,
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
                                Modul / Menu
                            </th>
                            {permHeaders.map((h, i) => (
                                <th
                                    key={i}
                                    style={{
                                        padding: '13px 16px',
                                        textAlign: 'center',
                                        fontSize: 11.5,
                                        fontWeight: 600,
                                        color: C.muted,
                                        textTransform: 'uppercase',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {modules.map((module, rowIdx) => (
                            <tr
                                key={module.key}
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                <td
                                    style={{
                                        padding: '13px 18px',
                                        fontSize: 13.5,
                                        fontWeight: 500,
                                        color: C.text,
                                    }}
                                >
                                    {module.label}
                                </td>
                                {roles.map((role, colIdx) => (
                                    <ToggleCell
                                        key={role.id}
                                        on={matrix[rowIdx][colIdx]}
                                        onToggle={() => onToggle(rowIdx, colIdx)}
                                    />
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
