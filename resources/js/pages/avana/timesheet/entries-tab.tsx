import { ActionBtn, AIcon, btnOut, C, card, thCell } from '@/lib/avana';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import {
    cellStyle,
    EmptyRow,
    SectionTitle,
    selectStyle,
    StatusBadge,
} from './components';
import { hoursLabel, rupiah } from './types';
import type {
    EmployeeOption,
    EntryStatus,
    ProjectRow,
    TimesheetAbilities,
    TimesheetEntry,
    TimesheetFilters,
} from './types';

interface EntriesTabProps {
    entries: TimesheetEntry[];
    projects: ProjectRow[];
    employees: EmployeeOption[];
    filters: TimesheetFilters;
    can: TimesheetAbilities;
    selected: number[];
    exportUrl: string;
    onFilter: (key: keyof TimesheetFilters, value: string) => void;
    onToggle: (id: number) => void;
    onToggleAll: () => void;
    onEdit: (entry: TimesheetEntry) => void;
    onDelete: (entry: TimesheetEntry) => void;
    onDecide: (ids: number[], action: 'approve' | 'reject') => void;
}

/**
 * The entry ledger: everything logged in the window, what it is worth, and the
 * approve/reject controls for whatever arrived from the phone still pending.
 */
export function EntriesTab({
    entries,
    projects,
    employees,
    filters,
    can,
    selected,
    exportUrl,
    onFilter,
    onToggle,
    onToggleAll,
    onEdit,
    onDelete,
    onDecide,
}: EntriesTabProps) {
    const pending = entries.filter((entry) => entry.status === 'pending');
    const allPendingSelected =
        pending.length > 0 && selected.length === pending.length;
    const totalHours = entries
        .filter((entry) => entry.status === 'approved')
        .reduce((sum, entry) => sum + entry.hours, 0);

    return (
        <>
            <SectionTitle
                actions={
                    <div
                        style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}
                    >
                        <DatePicker
                            value={filters.from}
                            onChange={(value) => onFilter('from', value)}
                            placeholder="Dari tanggal"
                            width={150}
                        />
                        <DatePicker
                            value={filters.to}
                            onChange={(value) => onFilter('to', value)}
                            placeholder="Sampai tanggal"
                            width={150}
                        />
                        <select
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                onFilter('status', event.target.value)
                            }
                            style={{ ...selectStyle, width: 150, height: 38 }}
                        >
                            <option value="">Semua status</option>
                            <option value="pending">Menunggu</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                        <select
                            value={filters.project_id ?? ''}
                            onChange={(event) =>
                                onFilter('project_id', event.target.value)
                            }
                            style={{ ...selectStyle, width: 180, height: 38 }}
                        >
                            <option value="">Semua proyek</option>
                            {projects.map((project) => (
                                <option
                                    key={project.id}
                                    value={String(project.id)}
                                >
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        <SearchableSelect
                            value={filters.employee_id ?? ''}
                            onChange={(value) => onFilter('employee_id', value)}
                            options={employees.map((employee) => ({
                                value: String(employee.id),
                                label: employee.name,
                            }))}
                            placeholder="Semua karyawan"
                            searchPlaceholder="Cari nama karyawan…"
                            allowClear
                            style={{ width: 180 }}
                        />
                        {can.export && (
                            <a href={exportUrl} style={btnOut}>
                                <AIcon
                                    name="download"
                                    size={16}
                                    color={C.text}
                                />
                                Ekspor CSV
                            </a>
                        )}
                    </div>
                }
            >
                Entri Timesheet
            </SectionTitle>

            {can.approve && selected.length > 0 && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        padding: '12px 16px',
                        marginBottom: 12,
                        borderRadius: 10,
                        background: 'rgba(47,84,201,.06)',
                        border: `1px solid rgba(47,84,201,.2)`,
                        fontSize: 13,
                        color: C.navy,
                        flexWrap: 'wrap',
                    }}
                >
                    <strong>{selected.length}</strong> entri dipilih
                    <div style={{ display: 'flex', gap: 8, marginLeft: 'auto' }}>
                        <ActionBtn
                            icon="check"
                            label="Setujui"
                            variant="success"
                            onClick={() => onDecide(selected, 'approve')}
                        />
                        <ActionBtn
                            icon="x"
                            label="Tolak"
                            variant="danger"
                            onClick={() => onDecide(selected, 'reject')}
                        />
                    </div>
                </div>
            )}

            <div style={{ ...card, overflow: 'hidden' }}>
                <div style={{ overflowX: 'auto' }}>
                    <table
                        style={{
                            width: '100%',
                            borderCollapse: 'collapse',
                            minWidth: 1100,
                        }}
                    >
                        <thead>
                            <tr style={{ background: '#FAFBFD' }}>
                                <th style={{ ...thCell, width: 40 }}>
                                    {can.approve && pending.length > 0 && (
                                        <input
                                            type="checkbox"
                                            aria-label="Pilih semua entri menunggu"
                                            checked={allPendingSelected}
                                            onChange={onToggleAll}
                                            style={{ cursor: 'pointer' }}
                                        />
                                    )}
                                </th>
                                <th style={thCell}>Karyawan</th>
                                <th style={thCell}>Proyek</th>
                                <th style={thCell}>Tanggal</th>
                                <th style={thCell}>Jam</th>
                                <th style={thCell}>Tugas</th>
                                <th style={thCell}>Nilai Jual</th>
                                <th style={thCell}>Biaya</th>
                                <th style={thCell}>Status</th>
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
                            {entries.length === 0 && (
                                <EmptyRow
                                    colSpan={10}
                                    icon="clock"
                                    message="Belum ada entri timesheet pada rentang ini."
                                />
                            )}
                            {entries.map((entry) => (
                                <tr
                                    key={entry.id}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td style={cellStyle}>
                                        {can.approve &&
                                            entry.status === 'pending' && (
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Pilih entri ${entry.id}`}
                                                    checked={selected.includes(
                                                        entry.id,
                                                    )}
                                                    onChange={() =>
                                                        onToggle(entry.id)
                                                    }
                                                    style={{
                                                        cursor: 'pointer',
                                                    }}
                                                />
                                            )}
                                    </td>
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 600,
                                            color: C.navy,
                                        }}
                                    >
                                        {entry.employee ?? '—'}
                                        {entry.source === 'mobile' && (
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    fontWeight: 500,
                                                    color: C.muted,
                                                    marginTop: 2,
                                                }}
                                            >
                                                dari aplikasi
                                            </div>
                                        )}
                                    </td>
                                    <td style={cellStyle}>
                                        {entry.project ?? '—'}
                                        {!entry.is_billable && (
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.muted,
                                                    marginTop: 2,
                                                }}
                                            >
                                                non-billable
                                            </div>
                                        )}
                                    </td>
                                    <td style={cellStyle}>
                                        {entry.date ?? '—'}
                                    </td>
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 600,
                                        }}
                                    >
                                        {hoursLabel(entry.hours)}
                                    </td>
                                    <td style={cellStyle}>
                                        {entry.task ?? '—'}
                                    </td>
                                    <td style={cellStyle}>
                                        {rupiah(entry.bill_amount)}
                                    </td>
                                    <td style={cellStyle}>
                                        {rupiah(entry.cost_amount)}
                                    </td>
                                    <td style={cellStyle}>
                                        <StatusBadge
                                            status={entry.status}
                                            label={entry.status_label}
                                        />
                                        {entry.rejection_reason && (
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.muted,
                                                    marginTop: 4,
                                                    maxWidth: 180,
                                                }}
                                            >
                                                {entry.rejection_reason}
                                            </div>
                                        )}
                                    </td>
                                    <td
                                        style={{
                                            padding: '13px 18px',
                                            textAlign: 'right',
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {can.approve &&
                                            entry.status === 'pending' && (
                                                <>
                                                    <ActionBtn
                                                        icon="check"
                                                        label="Setujui"
                                                        variant="success"
                                                        iconOnly
                                                        onClick={() =>
                                                            onDecide(
                                                                [entry.id],
                                                                'approve',
                                                            )
                                                        }
                                                    />
                                                    <ActionBtn
                                                        icon="x"
                                                        label="Tolak"
                                                        variant="warning"
                                                        iconOnly
                                                        onClick={() =>
                                                            onDecide(
                                                                [entry.id],
                                                                'reject',
                                                            )
                                                        }
                                                    />
                                                </>
                                            )}
                                        {can.update && (
                                            <ActionBtn
                                                icon="pencil"
                                                label="Ubah"
                                                iconOnly
                                                onClick={() => onEdit(entry)}
                                            />
                                        )}
                                        {can.archive && (
                                            <ActionBtn
                                                icon="trash-2"
                                                label="Hapus"
                                                variant="danger"
                                                iconOnly
                                                onClick={() => onDelete(entry)}
                                            />
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        {entries.length > 0 && (
                            <tfoot>
                                <tr
                                    style={{
                                        borderTop: `1px solid ${C.border}`,
                                        background: '#FAFBFD',
                                    }}
                                >
                                    <td
                                        colSpan={4}
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 600,
                                            color: C.muted,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Total Jam Disetujui
                                    </td>
                                    <td
                                        style={{
                                            ...cellStyle,
                                            fontWeight: 700,
                                            color: C.navy,
                                        }}
                                    >
                                        {hoursLabel(totalHours)}
                                    </td>
                                    <td colSpan={5} />
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </>
    );
}

export type { EntryStatus };
