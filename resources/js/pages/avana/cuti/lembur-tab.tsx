import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
    OvertimeFormData,
    OvertimeRow,
} from './types';

interface LemburTabProps {
    form: InertiaFormProps<OvertimeFormData>;
    employees: EmployeeOption[];
    items: OvertimeRow[];
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onApprove: (id: number) => void;
    onReject: (id: number) => void;
}

/** "Lembur" tab: overtime request form plus its approval table. */
export function LemburTab({
    form,
    employees,
    items,
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
                    <input
                        type="date"
                        value={form.data.date}
                        onChange={(event) =>
                            form.setData('date', event.target.value)
                        }
                        style={withError(dateInputStyle, !!form.errors.date)}
                    />
                </Field>
                <Field
                    label="Durasi (jam)"
                    required
                    error={form.errors.hours}
                >
                    <input
                        type="number"
                        step="0.5"
                        min="0.5"
                        placeholder="2"
                        value={form.data.hours}
                        onChange={(event) =>
                            form.setData('hours', event.target.value)
                        }
                        style={withError(textInputStyle, !!form.errors.hours)}
                    />
                </Field>
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
            </RequestFormCard>

            <ApprovalTable
                title="Pengajuan Lembur"
                headers={['Tanggal', 'Durasi', 'Alasan']}
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
                        `${row.hours} jam`,
                        row.reason ?? '—',
                    ],
                }))}
            />
        </div>
    );
}
