import { AIcon, C, statusBadge } from '@/lib/avana';
import { locationBadge } from './components';
import type { Attendance, PaginationMeta } from './types';

interface RekapTableProps {
    rows: Attendance[];
    meta: PaginationMeta;
    search: string;
    onSearchChange: (value: string) => void;
    onGoToPage: (page: number) => void;
    onRowClick: (row: Attendance) => void;
    /** Opens HR's correction form; the column is hidden when absent. */
    onCorrect?: (row: Attendance) => void;
    /** Row selection for bulk correction; the checkbox column is hidden when absent. */
    selection?: RowSelection;
    /** Change the page size; the "Baris" picker is hidden when absent. */
    onPerPage?: (perPage: number) => void;
}

/** Page sizes offered; mirrors AttendanceController::PER_PAGE_OPTIONS. */
export const PER_PAGE_OPTIONS = [10, 25, 50, 100];

/** Ticked rows, keyed by {@see rowKey}. */
export interface RowSelection {
    isSelected: (row: Attendance) => boolean;
    toggle: (row: Attendance) => void;
    togglePage: (rows: Attendance[], select: boolean) => void;
}

/** One rekap row's identity across pages: the employee and the day. */
export function rowKey(row: Attendance): string {
    return `${row.employee?.id ?? 0}|${row.date_raw ?? ''}`;
}

const checkboxStyle = {
    width: 16,
    height: 16,
    cursor: 'pointer',
    accentColor: C.primary,
} as const;

/** Daily attendance rekap table with a search header and pagination footer. */
export function RekapTable({
    rows,
    meta,
    search,
    onSearchChange,
    onGoToPage,
    onRowClick,
    onCorrect,
    selection,
    onPerPage,
}: RekapTableProps) {
    const selectableRows = rows.filter((row) => row.employee !== null);
    const pageSelected =
        selection !== undefined &&
        selectableRows.length > 0 &&
        selectableRows.every((row) => selection.isSelected(row));

    return (
        <div
            style={{
                background: '#fff',
                border: `1px solid ${C.border}`,
                borderRadius: 12,
                boxShadow: '0 1px 2px rgba(15,23,42,.04)',
                overflow: 'hidden',
            }}
        >
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
                    Rekap Kehadiran Harian
                </div>
                <div style={{ position: 'relative' }}>
                    <span
                        style={{
                            position: 'absolute',
                            left: 11,
                            top: '50%',
                            transform: 'translateY(-50%)',
                            display: 'flex',
                        }}
                    >
                        <AIcon name="search" size={15} color={C.faint} />
                    </span>
                    <input
                        placeholder="Cari karyawan…"
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        style={{
                            height: 36,
                            width: 180,
                            padding: '0 12px 0 34px',
                            background: C.surface,
                            border: '1px solid transparent',
                            borderRadius: 8,
                            fontSize: 13,
                            outline: 'none',
                        }}
                    />
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
                            {selection && (
                                <th
                                    style={{
                                        padding: '11px 0 11px 18px',
                                        width: 34,
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        aria-label="Pilih semua baris di halaman ini"
                                        checked={pageSelected}
                                        disabled={selectableRows.length === 0}
                                        onChange={() =>
                                            selection.togglePage(
                                                selectableRows,
                                                !pageSelected,
                                            )
                                        }
                                        style={checkboxStyle}
                                    />
                                </th>
                            )}
                            <th
                                style={{
                                    padding: '11px 18px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Karyawan
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Shift
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Masuk
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Keluar
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Tanggal Absen
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Telat
                            </th>
                            <th
                                style={{
                                    padding: '11px 16px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Lokasi
                            </th>
                            <th
                                style={{
                                    padding: '11px 18px',
                                    textAlign: 'left',
                                    fontSize: 11.5,
                                    fontWeight: 600,
                                    color: C.faint,
                                    textTransform: 'uppercase',
                                }}
                            >
                                Status
                            </th>
                            {onCorrect && (
                                <th
                                    style={{
                                        padding: '11px 18px',
                                        textAlign: 'right',
                                        fontSize: 11.5,
                                        fontWeight: 600,
                                        color: C.faint,
                                        textTransform: 'uppercase',
                                    }}
                                >
                                    Aksi
                                </th>
                            )}
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
                                    colSpan={
                                        8 +
                                        (onCorrect ? 1 : 0) +
                                        (selection ? 1 : 0)
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
                                            name="fingerprint"
                                            size={28}
                                            color={C.faint}
                                        />
                                        <div>
                                            Belum ada data kehadiran untuk
                                            tanggal ini.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => {
                            const badge = statusBadge(row.status_label);
                            const hasPoint = row.clock_in_coords !== null;
                            const location = locationBadge(row.location_status);

                            return (
                                <tr
                                    key={row.id}
                                    onClick={() => hasPoint && onRowClick(row)}
                                    style={{
                                        borderTop: `1px solid ${C.line}`,
                                        cursor: hasPoint
                                            ? 'pointer'
                                            : 'default',
                                        background:
                                            selection?.isSelected(row) === true
                                                ? '#F3F6FE'
                                                : undefined,
                                    }}
                                >
                                    {selection && (
                                        <td
                                            style={{
                                                padding: '12px 0 12px 18px',
                                            }}
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                        >
                                            {row.employee !== null && (
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Pilih ${row.employee.name} ${row.date}`}
                                                    checked={selection.isSelected(
                                                        row,
                                                    )}
                                                    onChange={() =>
                                                        selection.toggle(row)
                                                    }
                                                    style={checkboxStyle}
                                                />
                                            )}
                                        </td>
                                    )}
                                    <td
                                        style={{
                                            padding: '12px 18px',
                                        }}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    width: 32,
                                                    height: 32,
                                                    borderRadius: '50%',
                                                    flex: 'none',
                                                    background:
                                                        row.employee
                                                            ?.avatar_color ??
                                                        C.faint,
                                                    color: '#fff',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {row.employee?.initials ?? '?'}
                                            </div>
                                            <span
                                                style={{
                                                    fontSize: 13,
                                                    fontWeight: 500,
                                                    color: C.text,
                                                }}
                                            >
                                                {row.employee?.name ?? '—'}
                                            </span>
                                        </div>
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 12.5,
                                            color: C.muted,
                                        }}
                                    >
                                        {row.shift?.label ?? '—'}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 13,
                                            color: C.text,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {row.clock_in}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 13,
                                            color: C.text,
                                            fontVariantNumeric: 'tabular-nums',
                                        }}
                                    >
                                        {row.clock_out}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 13,
                                            color: C.text,
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {row.date}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 13,
                                            color: C.muted,
                                        }}
                                    >
                                        {row.telat}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 16px',
                                            fontSize: 12.5,
                                        }}
                                    >
                                        {hasPoint ? (
                                            <span
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                    color: location.color,
                                                    fontWeight: 500,
                                                }}
                                            >
                                                <AIcon
                                                    name="map-pin"
                                                    size={13}
                                                    color={location.color}
                                                />
                                                {location.label}
                                                {row.distance_meter !==
                                                    null && (
                                                    <span
                                                        style={{
                                                            color: C.faint,
                                                            fontWeight: 400,
                                                        }}
                                                    >
                                                        · {row.distance_meter} m
                                                    </span>
                                                )}
                                            </span>
                                        ) : (
                                            <span style={{ color: C.faint }}>
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 18px',
                                        }}
                                    >
                                        <span
                                            style={{
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: badge.color,
                                                background: badge.bg,
                                            }}
                                        >
                                            {badge.label}
                                        </span>
                                    </td>
                                    {onCorrect && (
                                        <td
                                            style={{
                                                padding: '12px 18px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <button
                                                type="button"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    onCorrect(row);
                                                }}
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: 5,
                                                    height: 30,
                                                    padding: '0 10px',
                                                    background: '#fff',
                                                    border: `1px solid ${C.border}`,
                                                    borderRadius: 7,
                                                    fontSize: 12.5,
                                                    fontWeight: 500,
                                                    color: C.text,
                                                    cursor: 'pointer',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                <AIcon
                                                    name="pencil"
                                                    size={13}
                                                    color={C.muted}
                                                />
                                                Koreksi
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {/* Pagination footer */}
            <div
                style={{
                    padding: '14px 18px',
                    borderTop: `1px solid ${C.border}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    flexWrap: 'wrap',
                    gap: 12,
                }}
            >
                <div style={{ fontSize: 13, color: C.muted }}>
                    Menampilkan{' '}
                    <span
                        style={{
                            color: C.text,
                            fontWeight: 500,
                        }}
                    >
                        {meta.from ?? 0}–{meta.to ?? 0}
                    </span>{' '}
                    dari{' '}
                    <span
                        style={{
                            color: C.text,
                            fontWeight: 500,
                        }}
                    >
                        {meta.total.toLocaleString('id-ID')}
                    </span>
                </div>
                <div
                    style={{
                        display: 'flex',
                        gap: 6,
                        alignItems: 'center',
                    }}
                >
                    {onPerPage && (
                        <label
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                marginRight: 8,
                                fontSize: 12.5,
                                color: C.muted,
                            }}
                        >
                            Baris
                            <select
                                value={meta.per_page}
                                onChange={(event) =>
                                    onPerPage(Number(event.target.value))
                                }
                                style={{
                                    height: 34,
                                    padding: '0 8px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    color: C.text,
                                    background: '#fff',
                                    cursor: 'pointer',
                                }}
                            >
                                {PER_PAGE_OPTIONS.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <button
                        disabled={meta.current_page <= 1}
                        onClick={() => onGoToPage(meta.current_page - 1)}
                        style={{
                            height: 34,
                            minWidth: 34,
                            padding: '0 10px',
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            borderRadius: 8,
                            fontSize: 13,
                            color: meta.current_page <= 1 ? C.faint : C.text,
                            cursor:
                                meta.current_page <= 1
                                    ? 'not-allowed'
                                    : 'pointer',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                        }}
                    >
                        <AIcon name="chevron-left" size={15} />
                    </button>
                    <span
                        style={{
                            fontSize: 13,
                            color: C.muted,
                            padding: '0 4px',
                        }}
                    >
                        {meta.current_page} / {meta.last_page}
                    </span>
                    <button
                        disabled={meta.current_page >= meta.last_page}
                        onClick={() => onGoToPage(meta.current_page + 1)}
                        style={{
                            height: 34,
                            minWidth: 34,
                            padding: '0 10px',
                            border: `1px solid ${C.border}`,
                            background: '#fff',
                            borderRadius: 8,
                            fontSize: 13,
                            color:
                                meta.current_page >= meta.last_page
                                    ? C.faint
                                    : C.text,
                            cursor:
                                meta.current_page >= meta.last_page
                                    ? 'not-allowed'
                                    : 'pointer',
                            display: 'inline-flex',
                            alignItems: 'center',
                        }}
                    >
                        <AIcon name="chevron-right" size={15} />
                    </button>
                </div>
            </div>
        </div>
    );
}
