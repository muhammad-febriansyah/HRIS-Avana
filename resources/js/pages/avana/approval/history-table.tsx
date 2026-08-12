import { useState } from 'react';
import { card, statusBadge } from '@/lib/avana';
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

interface HistoryTableProps {
    items: ApprovalItem[];
    meta: PageMeta;
    days: number;
    onPage: (page: number) => void;
    onPerPage: (perPage: number) => void;
}

/** The read-only approval history table with status pills. */
export function HistoryTable({
    items,
    meta,
    days,
    onPage,
    onPerPage,
}: HistoryTableProps) {
    const [hovered, setHovered] = useState<string | null>(null);

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <TableHeading
                title="Riwayat Persetujuan"
                subtitle={`Keputusan ${days} hari terakhir`}
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
                            <th style={{ ...headThStyle, width: 120 }}>
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <EmptyRow
                                icon="history"
                                message="Belum ada riwayat persetujuan."
                                colSpan={6}
                            />
                        )}
                        {items.map((item, index) => {
                            const key = `${item.type}-${item.id}`;
                            const badge = statusBadge(item.status_label);

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
                                    <td style={{ padding: '14px 16px' }}>
                                        <span
                                            style={{
                                                display: 'inline-block',
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                whiteSpace: 'nowrap',
                                                color: badge.color,
                                                background: badge.bg,
                                            }}
                                        >
                                            {badge.label}
                                        </span>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <Pagination
                meta={meta}
                unit="keputusan"
                onPage={onPage}
                onPerPage={onPerPage}
            />
        </div>
    );
}
