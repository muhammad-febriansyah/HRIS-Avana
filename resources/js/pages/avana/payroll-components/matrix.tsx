/**
 * The position × component nominal matrix table (card + sticky columns).
 */

import { AIcon, C, card } from '@/lib/avana';
import { BasisSelect, RupiahInput } from './components';
import type { CalcBasis, ComponentRef, PositionRef } from './types';
import { cellKey } from './types';

interface ComponentMatrixProps {
    positions: PositionRef[];
    components: ComponentRef[];
    amounts: Record<string, number>;
    onAmountChange: (
        positionId: number,
        componentId: number,
        amount: number,
    ) => void;
    onBasisChange: (componentId: number, basis: CalcBasis) => void;
}

export function ComponentMatrix({
    positions,
    components,
    amounts,
    onAmountChange,
    onBasisChange,
}: ComponentMatrixProps) {
    const hasMatrix = positions.length > 0 && components.length > 0;

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 640,
                    }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th
                                style={{
                                    padding: '12px 18px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                    position: 'sticky',
                                    left: 0,
                                    background: '#FAFBFD',
                                    minWidth: 180,
                                }}
                            >
                                Jabatan
                            </th>
                            {components.map((component) => (
                                <th
                                    key={component.id}
                                    style={{
                                        padding: '11px 16px',
                                        textAlign: 'right',
                                        fontSize: 11.5,
                                        fontWeight: 600,
                                        color: C.faint,
                                        textTransform: 'uppercase',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            alignItems: 'flex-end',
                                            gap: 4,
                                        }}
                                    >
                                        <span style={{ color: C.text }}>
                                            {component.name}
                                        </span>
                                        <BasisSelect
                                            component={component}
                                            onChange={(basis) =>
                                                onBasisChange(
                                                    component.id,
                                                    basis,
                                                )
                                            }
                                        />
                                    </div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {!hasMatrix && (
                            <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                <td
                                    colSpan={
                                        Math.max(components.length, 1) + 1
                                    }
                                    style={{
                                        padding: '48px 18px',
                                        textAlign: 'center',
                                        fontSize: 13.5,
                                        color: C.muted,
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <AIcon
                                            name="sliders-horizontal"
                                            size={28}
                                            color={C.faint}
                                        />
                                        <div>
                                            Belum ada jabatan atau komponen
                                            untuk dikonfigurasi.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {hasMatrix &&
                            positions.map((position) => (
                                <tr
                                    key={position.id}
                                    style={{
                                        borderTop: `1px solid ${C.line}`,
                                    }}
                                >
                                    <td
                                        style={{
                                            padding: '12px 18px',
                                            fontSize: 13.5,
                                            fontWeight: 600,
                                            color: C.navy,
                                            position: 'sticky',
                                            left: 0,
                                            background: '#fff',
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {position.name}
                                    </td>
                                    {components.map((component) => (
                                        <td
                                            key={component.id}
                                            style={{
                                                padding: '10px 16px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <RupiahInput
                                                value={
                                                    amounts[
                                                        cellKey(
                                                            position.id,
                                                            component.id,
                                                        )
                                                    ] ?? 0
                                                }
                                                onChange={(amount) =>
                                                    onAmountChange(
                                                        position.id,
                                                        component.id,
                                                        amount,
                                                    )
                                                }
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
