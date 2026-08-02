import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import {
    ApprovalTable,
    dateInputStyle,
    EmployeeSelect,
    Field,
    RequestFormCard,
    textareaStyle,
    textInputStyle,
    withError,
} from './components';
import type {
    EmployeeOption,
    OvertimeDayType,
    OvertimeFormData,
    OvertimeLimits,
    OvertimeRow,
} from './types';

interface LemburTabProps {
    form: InertiaFormProps<OvertimeFormData>;
    employees: EmployeeOption[];
    items: OvertimeRow[];
    dayTypes: OvertimeDayType[];
    limits: OvertimeLimits;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onApprove: (id: number) => void;
    onReject: (id: number) => void;
}

/** "Lembur" tab: overtime request form plus its approval table. */
export function LemburTab({
    form,
    employees,
    items,
    dayTypes,
    limits,
    onSubmit,
    onApprove,
    onReject,
}: LemburTabProps) {
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
                title="Ajukan Lembur"
                subtitle="Catat jam lembur karyawan."
                onSubmit={onSubmit}
                processing={form.processing}
            >
                <EmployeeSelect
                    value={form.data.employee_id}
                    onChange={(value) => form.setData('employee_id', value)}
                    error={form.errors.employee_id}
                    employees={employees}
                />
                <Field label="Tanggal" required error={form.errors.date}>
                    <DatePicker
                        value={form.data.date}
                        onChange={(nextValue) =>
                            form.setData('date', nextValue)
                        }
                        placeholder="Pilih tanggal"
                        hasError={!!form.errors.date}
                        width="100%"
                    />
                </Field>
                <Field
                    label="Jenis Hari"
                    required
                    error={form.errors.day_type}
                >
                    <select
                        value={form.data.day_type}
                        onChange={(event) =>
                            form.setData('day_type', event.target.value)
                        }
                        style={withError(
                            textInputStyle,
                            !!form.errors.day_type,
                        )}
                    >
                        {dayTypes.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
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
                        label="Jam Mulai"
                        required
                        error={form.errors.start_time}
                    >
                        <input
                            type="time"
                            value={form.data.start_time}
                            onChange={(event) =>
                                form.setData('start_time', event.target.value)
                            }
                            style={withError(
                                textInputStyle,
                                !!form.errors.start_time,
                            )}
                        />
                    </Field>
                    <Field
                        label="Jam Selesai"
                        required
                        error={form.errors.end_time}
                    >
                        <input
                            type="time"
                            value={form.data.end_time}
                            onChange={(event) =>
                                form.setData('end_time', event.target.value)
                            }
                            style={withError(
                                textInputStyle,
                                !!form.errors.end_time,
                            )}
                        />
                    </Field>
                </div>
                <Field label="Alasan" error={form.errors.reason}>
                    <textarea
                        rows={3}
                        placeholder="Tuliskan alasan lembur"
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        style={withError(textareaStyle, !!form.errors.reason)}
                    />
                </Field>
                {limits.enforced && (
                    <div style={{ fontSize: 11.5, color: '#94A3B8' }}>
                        Batas PP 35/2021: maks. {limits.per_day} jam/hari dan{' '}
                        {limits.per_week} jam/minggu.
                    </div>
                )}
            </RequestFormCard>

            <ApprovalTable
                title="Pengajuan Lembur"
                headers={['Tanggal', 'Jenis Hari', 'Durasi', 'Alasan']}
                emptyIcon="clock"
                emptyText="Tidak ada pengajuan lembur."
                onApprove={onApprove}
                onReject={onReject}
                items={items.map((row) => ({
                    id: row.id,
                    employee: row.employee,
                    status: row.status,
                    status_label: row.status_label,
                    cells: [
                        row.date ?? '—',
                        row.day_type_label,
                        row.time_range
                            ? `${row.hours} jam · ${row.time_range}`
                            : `${row.hours} jam`,
                        row.reason ?? '—',
                    ],
                }))}
            />
        </div>
    );
}
