import { useState } from 'react';
import { ActionBtn, card } from '@/lib/avana';
import {
    DetailCell,
    EmployeeCell,
    EmptyRow,
    headThStyle,
    ReasonCell,
    RequestedCell,
    rowStyle,
    TableHeading,
    TypeBadge,
} from './components';
import { Pagination } from './pagination';
import type { ApprovalItem, PageMeta } from './types';

interface PendingTableProps {
    items: ApprovalItem[];
    meta: PageMeta;
    onApprove: (item: ApprovalItem) => void;
    onReject: (item: ApprovalItem) => void;
    onPage: (page: number) => void;
    onPerPage: (perPage: number) => void;
}

/** The pending-approval table with per-row approve / reject actions. */
export function PendingTable({
    items,
    meta,
    onApprove,
    onReject,
    onPage,
    onPerPage,
}: PendingTableProps) {
    const [hovered, setHovered] = useState<string | null>(null);

    return (
        <div style={{ ...card, overflow: 'hidden', marginBottom: 24 }}>
            <TableHeading
                title="Menunggu Persetujuan"
                subtitle={
                    meta.total > 0
                        ? `${meta.total} pengajuan menunggu keputusan Anda`
                        : undefined
                }
            />

            <div style={{ overflowX: 'auto', maxHeight: 620 }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 900,
                    }}
                >
                    <thead>
                        <tr>
                            <th style={{ ...headThStyle, width: '20%' }}>
                                Karyawan
                            </th>
                            <th style={{ ...headThStyle, width: 110 }}>
                                Jenis
                            </th>
                            <th style={{ ...headThStyle, width: '26%' }}>
                                Detail
                            </th>
                            <th style={{ ...headThStyle, width: '22%' }}>
                                Alasan
                            </th>
                            <th style={{ ...headThStyle, width: 150 }}>
                                Diajukan
                            </th>
                            <th
                                style={{
                                    ...headThStyle,
                                    textAlign: 'right',
                                    width: 170,
                                }}
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <EmptyRow
                                icon="check-check"
                                message="Tidak ada pengajuan yang menunggu persetujuan."
                                colSpan={6}
                            />
                        )}
                        {items.map((item, index) => {
                            const key = `${item.type}-${item.id}`;

                            return (
                                <tr
                                    key={key}
                                    onMouseEnter={() => setHovered(key)}
                                    onMouseLeave={() => setHovered(null)}
                                    style={rowStyle(index, hovered === key)}
                                >
                                    <td style={{ padding: '14px 18px' }}>
                                        <EmployeeCell
                                            employee={item.employee}
                                        />
                                    </td>
                                    <td style={{ padding: '14px 16px' }}>
                                        <TypeBadge type={item.type} />
                                    </td>
                                    <td style={{ padding: '14px 16px' }}>
                                        <DetailCell item={item} />
                                    </td>
                                    <td style={{ padding: '14px 16px' }}>
                                        <ReasonCell reason={item.reason} />
                                    </td>
                                    <td style={{ padding: '14px 16px' }}>
                                        <RequestedCell item={item} />
                                    </td>
                                    <td
                                        style={{
                                            padding: '14px 18px',
                                            textAlign: 'right',
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: 'inline-flex',
                                                gap: 6,
                                            }}
                                        >
                                            <ActionBtn
                                                icon="check"
                                                label="Setujui"
                                                variant="success"
                                                onClick={() => onApprove(item)}
                                            />
                                            <ActionBtn
                                                icon="x"
                                                label="Tolak"
                                                variant="danger"
                                                onClick={() => onReject(item)}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <Pagination
                meta={meta}
                unit="pengajuan"
                onPage={onPage}
                onPerPage={onPerPage}
            />
        </div>
    );
}
