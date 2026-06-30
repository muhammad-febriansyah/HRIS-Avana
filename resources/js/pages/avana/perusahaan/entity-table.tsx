import { AIcon, btnP, C, card, thCell } from '@/lib/avana';
import { renderCell, rowMenuItemStyle } from './components';
import type { EntityRecord, TabDef } from './types';

interface EntityTableProps {
    tab: TabDef;
    rows: EntityRecord[];
    openMenu: number | null;
    onToggleMenu: (id: number) => void;
    onCreate: () => void;
    onEdit: (row: EntityRecord) => void;
    onDelete: (row: EntityRecord) => void;
}

/** Entity card: count header, master table, and per-row action menu. */
export function EntityTable({
    tab,
    rows,
    openMenu,
    onToggleMenu,
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
                                            position: 'relative',
                                            display: 'inline-block',
                                        }}
                                    >
                                        <button
                                            onClick={() => onToggleMenu(row.id)}
                                            aria-label="Aksi"
                                            style={{
                                                width: 32,
                                                height: 32,
                                                border: `1px solid ${C.border}`,
                                                background: '#fff',
                                                borderRadius: 8,
                                                cursor: 'pointer',
                                                color: C.muted,
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                transition: '.15s',
                                            }}
                                        >
                                            <AIcon
                                                name="ellipsis-vertical"
                                                size={16}
                                            />
                                        </button>
                                        <div
                                            style={{
                                                display:
                                                    openMenu === row.id
                                                        ? 'block'
                                                        : 'none',
                                                position: 'absolute',
                                                right: 0,
                                                top: 38,
                                                width: 148,
                                                background: '#fff',
                                                border: `1px solid ${C.border}`,
                                                borderRadius: 10,
                                                boxShadow:
                                                    '0 8px 24px rgba(15,23,42,.12)',
                                                zIndex: 20,
                                                padding: 5,
                                                textAlign: 'left',
                                            }}
                                        >
                                            <button
                                                onClick={() => onEdit(row)}
                                                style={rowMenuItemStyle}
                                            >
                                                <AIcon
                                                    name="pencil"
                                                    size={15}
                                                    color={C.muted}
                                                />
                                                Ubah
                                            </button>
                                            <button
                                                onClick={() => onDelete(row)}
                                                style={{
                                                    ...rowMenuItemStyle,
                                                    color: C.red,
                                                }}
                                            >
                                                <AIcon
                                                    name="trash-2"
                                                    size={15}
                                                    color={C.red}
                                                />
                                                Hapus
                                            </button>
                                        </div>
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
