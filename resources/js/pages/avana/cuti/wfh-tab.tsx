import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    ApprovalTable,
    dateInputStyle,
    EmployeeSelect,
    Field,
    RequestFormCard,
    textareaStyle,
    withError,
} from './components';
import type { EmployeeOption, WfhFormData, WfhRow } from './types';

interface WfhTabProps {
    form: InertiaFormProps<WfhFormData>;
    employees: EmployeeOption[];
    items: WfhRow[];
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onApprove: (id: number) => void;
    onReject: (id: number) => void;
}

/** "WFH" tab: work-from-home request form plus its approval table. */
export function WfhTab({
    form,
    employees,
    items,
    onSubmit,
    onApprove,
    onReject,
}: WfhTabProps) {
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
                title="Ajukan WFH"
                subtitle="Pengajuan bekerja dari rumah."
                onSubmit={onSubmit}
                processing={form.processing}
            >
                <EmployeeSelect
                    value={form.data.employee_id}
                    onChange={(value) => form.setData('employee_id', value)}
                    error={form.errors.employee_id}
                    employees={employees}
                />
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                    }}
                >
                    <Field
                        label="Mulai"
                        required
                        error={form.errors.start_date}
                    >
                        <input
                            type="date"
                            value={form.data.start_date}
                            onChange={(event) =>
                                form.setData('start_date', event.target.value)
                            }
                            style={withError(
                                dateInputStyle,
                                !!form.errors.start_date,
                            )}
                        />
                    </Field>
                    <Field
                        label="Selesai"
                        required
                        error={form.errors.end_date}
                    >
                        <input
                            type="date"
                            value={form.data.end_date}
                            onChange={(event) =>
                                form.setData('end_date', event.target.value)
                            }
                            style={withError(
                                dateInputStyle,
                                !!form.errors.end_date,
                            )}
                        />
                    </Field>
                </div>
                <Field label="Alasan" error={form.errors.reason}>
                    <textarea
                        rows={3}
                        placeholder="Tuliskan alasan WFH"
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        style={withError(textareaStyle, !!form.errors.reason)}
                    />
                </Field>
            </RequestFormCard>

            <ApprovalTable
                title="Pengajuan WFH"
                headers={['Mulai', 'Selesai', 'Alasan']}
                emptyIcon="house"
                emptyText="Tidak ada pengajuan WFH."
                onApprove={onApprove}
                onReject={onReject}
                items={items.map((row) => ({
                    id: row.id,
                    employee: row.employee,
                    status: row.status,
                    status_label: row.status_label,
                    cells: [
                        row.start_date ?? '—',
                        row.end_date ?? '—',
                        row.reason ?? '—',
                    ],
                }))}
            />
        </div>
    );
}
