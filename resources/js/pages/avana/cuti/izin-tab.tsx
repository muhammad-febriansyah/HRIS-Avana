import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import {
    ApprovalTable,
    dateInputStyle,
    EmployeeSelect,
    Field,
    RequestFormCard,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type { EmployeeOption, IzinFormData, PermissionRow } from './types';

interface IzinTabProps {
    form: InertiaFormProps<IzinFormData>;
    employees: EmployeeOption[];
    items: PermissionRow[];
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onApprove: (id: number) => void;
    onReject: (id: number) => void;
}

/** Render an izin's span, collapsing a same-day range to one date. */
function dateRange(start: string | null, end: string | null): string {
    if (!start) {
        return '—';
    }

    return !end || end === start ? start : `${start} – ${end}`;
}

/** "Izin" tab: permission request form plus its approval table. */
export function IzinTab({
    form,
    employees,
    items,
    onSubmit,
    onApprove,
    onReject,
}: IzinTabProps) {
    // Times narrow a single day to part of it; across a range the izin covers
    // whole days, and the server rejects times there.
    const isSingleDay =
        !!form.data.start_date && form.data.start_date === form.data.end_date;

    const setDate = (field: 'start_date' | 'end_date', value: string) => {
        const next = { ...form.data, [field]: value };

        // Drop any times the range just outgrew, so a stale value can't ride
        // along and get rejected by the server.
        if (next.start_date !== next.end_date) {
            next.start_time = '';
            next.end_time = '';
        }

        form.setData(next);
    };

    return (
        <div
            className="avn-abs"
            style={{
                display: 'grid',
                gridTemplateColumns: '380px 1fr',
                gap: 18,
                alignItems: 'start',
            }}
        >
            <RequestFormCard
                title="Ajukan Izin"
                subtitle="Izin jam atau keluar kantor."
                onSubmit={onSubmit}
                processing={form.processing}
            >
                <EmployeeSelect
                    value={form.data.employee_id}
                    onChange={(value) => form.setData('employee_id', value)}
                    error={form.errors.employee_id}
                    employees={employees}
                />
                <Field label="Tipe Izin" required error={form.errors.type}>
                    <select
                        value={form.data.type}
                        onChange={(event) =>
                            form.setData('type', event.target.value)
                        }
                        style={withError(selectStyle, !!form.errors.type)}
                    >
                        <option value="izin_jam">Izin Jam</option>
                        <option value="keluar_kantor">Keluar Kantor</option>
                    </select>
                </Field>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                    }}
                >
                    <Field
                        label="Tanggal Mulai"
                        required
                        error={form.errors.start_date}
                    >
                        <DatePicker
                            value={form.data.start_date}
                            onChange={(nextValue) =>
                                setDate('start_date', nextValue)
                            }
                            placeholder="Pilih tanggal"
                            hasError={!!form.errors.start_date}
                            width="100%"
                        />
                    </Field>
                    <Field
                        label="Tanggal Selesai"
                        required
                        error={form.errors.end_date}
                    >
                        <DatePicker
                            value={form.data.end_date}
                            onChange={(nextValue) =>
                                setDate('end_date', nextValue)
                            }
                            placeholder="Pilih tanggal"
                            hasError={!!form.errors.end_date}
                            width="100%"
                        />
                    </Field>
                </div>
                {isSingleDay && (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 12,
                        }}
                    >
                        <Field label="Jam Mulai" error={form.errors.start_time}>
                            <input
                                type="time"
                                value={form.data.start_time}
                                onChange={(event) =>
                                    form.setData(
                                        'start_time',
                                        event.target.value,
                                    )
                                }
                                style={withError(
                                    dateInputStyle,
                                    !!form.errors.start_time,
                                )}
                            />
                        </Field>
                        <Field label="Jam Selesai" error={form.errors.end_time}>
                            <input
                                type="time"
                                value={form.data.end_time}
                                onChange={(event) =>
                                    form.setData('end_time', event.target.value)
                                }
                                style={withError(
                                    dateInputStyle,
                                    !!form.errors.end_time,
                                )}
                            />
                        </Field>
                    </div>
                )}
                <Field label="Alasan" error={form.errors.reason}>
                    <textarea
                        rows={3}
                        placeholder="Tuliskan alasan izin"
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        style={withError(textareaStyle, !!form.errors.reason)}
                    />
                </Field>
            </RequestFormCard>

            <ApprovalTable
                title="Pengajuan Izin"
                headers={['Tanggal', 'Tipe', 'Jam', 'Alasan']}
                emptyIcon="door-open"
                emptyText="Tidak ada pengajuan izin."
                onApprove={onApprove}
                onReject={onReject}
                items={items.map((row) => ({
                    id: row.id,
                    employee: row.employee,
                    status: row.status,
                    status_label: row.status_label,
                    cells: [
                        dateRange(row.start_date, row.end_date),
                        row.type === 'keluar_kantor'
                            ? 'Keluar Kantor'
                            : 'Izin Jam',
                        row.start_time && row.end_time
                            ? `${row.start_time}–${row.end_time}`
                            : '—',
                        row.reason ?? '—',
                    ],
                }))}
            />
        </div>
    );
}
