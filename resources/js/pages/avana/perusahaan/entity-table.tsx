import { AIcon, ActionBtn, btnP, C, card, thCell } from '@/lib/avana';
import { renderCell } from './components';
import type { EntityRecord, TabDef } from './types';

interface EntityTableProps {
    tab: TabDef;
    rows: EntityRecord[];
    onCreate: () => void;
    onEdit: (row: EntityRecord) => void;
    onDelete: (row: EntityRecord) => void;
}

/** Entity card: count header, master table, and per-row action buttons. */
export function EntityTable({
    tab,
    rows,
    onCreate,
    onEdit,
    onDelete,
}: EntityTableProps) {
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
                        style={{
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        {tab.label}
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.muted,
                            marginTop: 2,
                        }}
                    >
                        {rows.length.toLocaleString('id-ID')} data terdaftar
                    </div>
                </div>
                <button onClick={onCreate} style={btnP}>
                    <AIcon name="plus" size={16} color="#fff" />
                    Tambah
                </button>
            </div>

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
                            {tab.columns.map((column) => (
                                <th key={column.key} style={thCell}>
                                    {column.header}
                                </th>
                            ))}
                            <th
                                style={{
                                    ...thCell,
                                    textAlign: 'right',
                                    padding: '12px 18px',
                                }}
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                <td
                                    colSpan={tab.columns.length + 1}
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
                                            name={tab.icon}
                                            size={28}
                                            color={C.faint}
                                        />
                                        <div>{tab.emptyText}</div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => (
                            <tr
                                key={row.id}
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                {tab.columns.map((column) => (
                                    <td
                                        key={column.key}
                                        style={{
                                            padding: '13px 16px',
                                            fontSize: 13,
                                            color: C.text,
                                        }}
                                    >
                                        {renderCell(row, column)}
                                    </td>
                                ))}
                                <td
                                    style={{
                                        padding: '13px 18px',
                                        textAlign: 'right',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'inline-flex',
                                            gap: 6,
                                            flexWrap: 'wrap',
                                            justifyContent: 'flex-end',
                                        }}
                                    >
                                        <ActionBtn
                                            icon="pencil"
                                            label="Ubah"
                                            variant="neutral"
                                            onClick={() => onEdit(row)}
                                        />
                                        <ActionBtn
                                            icon="trash-2"
                                            label="Hapus"
                                            variant="danger"
                                            onClick={() => onDelete(row)}
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
