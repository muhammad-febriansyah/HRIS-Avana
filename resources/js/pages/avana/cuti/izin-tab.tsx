import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import type {
    EmployeeOption,
    IzinFormData,
    PermissionRow,
} from './types';

interface IzinTabProps {
    form: InertiaFormProps<IzinFormData>;
    employees: EmployeeOption[];
    items: PermissionRow[];
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onApprove: (id: number) => void;
    onReject: (id: number) => void;
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
                <Field label="Tanggal" required error={form.errors.date}>
                    <input
                        type="date"
                        value={form.data.date}
                        onChange={(event) =>
                            form.setData('date', event.target.value)
                        }
                        style={withError(dateInputStyle, !!form.errors.date)}
                    />
                </Field>
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
                                form.setData('start_time', event.target.value)
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
                        row.date ?? '—',
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
