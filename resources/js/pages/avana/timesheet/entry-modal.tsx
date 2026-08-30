import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import TimesheetController from '@/actions/App/Http/Controllers/Avana/TimesheetController';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { C } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    ModalActions,
    ModalShell,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import { emptyEntryForm, entryToForm, rupiah } from './types';
import type {
    EmployeeOption,
    EntryFormData,
    ProjectRow,
    TimesheetEntry,
} from './types';

interface EntryModalProps {
    entry: TimesheetEntry | null;
    projects: ProjectRow[];
    employees: EmployeeOption[];
    onClose: () => void;
}

/** Log or correct one entry: who, which project, when, and for how long. */
export function EntryModal({
    entry,
    projects,
    employees,
    onClose,
}: EntryModalProps) {
    const form = useForm<EntryFormData>(
        entry
            ? entryToForm(entry)
            : {
                  ...emptyEntryForm,
                  project_id: projects[0] ? String(projects[0].id) : '',
                  employee_id: employees[0] ? String(employees[0].id) : '',
                  date: new Date().toISOString().slice(0, 10),
              },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(
            entry
                ? TimesheetController.update(entry.id)
                : TimesheetController.store(),
            { onSuccess: onClose },
        );
    };

    const project = projects.find(
        (row) => String(row.id) === form.data.project_id,
    );
    const member = project?.members.find(
        (row) => String(row.employee_id) === form.data.employee_id,
    );
    const billRate = member?.bill_rate ?? project?.default_bill_rate ?? null;
    const hours = Number.parseFloat(form.data.hours);
    const preview =
        billRate !== null && Number.isFinite(hours) && hours > 0
            ? rupiah(billRate * hours)
            : null;

    return (
        <ModalShell
            title={entry ? 'Ubah Entri Timesheet' : 'Tambah Entri Timesheet'}
            onClose={onClose}
            onSubmit={submit}
        >
            <div>
                <label style={fieldLabelStyle}>
                    Karyawan <span style={{ color: C.red }}>*</span>
                </label>
                <SearchableSelect
                    value={form.data.employee_id}
                    onChange={(value) => form.setData('employee_id', value)}
                    options={employees.map((employee) => ({
                        value: String(employee.id),
                        label: employee.name,
                    }))}
                    placeholder="Pilih karyawan"
                    searchPlaceholder="Cari nama karyawan…"
                    allowClear
                />
                <FieldError message={form.errors.employee_id} />
            </div>

            <div>
                <label style={fieldLabelStyle}>
                    Proyek <span style={{ color: C.red }}>*</span>
                </label>
                <select
                    value={form.data.project_id}
                    onChange={(event) =>
                        form.setData('project_id', event.target.value)
                    }
                    style={withError(selectStyle, !!form.errors.project_id)}
                >
                    <option value="">Pilih proyek</option>
                    {projects.map((row) => (
                        <option key={row.id} value={String(row.id)}>
                            {row.name}
                        </option>
                    ))}
                </select>
                <FieldError message={form.errors.project_id} />
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>
                        Tanggal <span style={{ color: C.red }}>*</span>
                    </label>
                    <DatePicker
                        value={form.data.date}
                        onChange={(value) => form.setData('date', value)}
                        placeholder="Pilih tanggal"
                        hasError={!!form.errors.date}
                        width="100%"
                    />
                    <FieldError message={form.errors.date} />
                </div>
                <div>
                    <label style={fieldLabelStyle}>
                        Jam <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="number"
                        step="0.5"
                        min="0.5"
                        max="24"
                        value={form.data.hours}
                        onChange={(event) =>
                            form.setData('hours', event.target.value)
                        }
                        placeholder="8"
                        style={withError(inputStyle, !!form.errors.hours)}
                    />
                    <FieldError message={form.errors.hours} />
                </div>
            </div>

            {preview && (
                <div style={{ fontSize: 12.5, color: C.muted, marginTop: -4 }}>
                    Perkiraan nilai jual: <strong>{preview}</strong>
                </div>
            )}

            <label
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 9,
                    fontSize: 13.5,
                    color: C.text,
                    cursor: 'pointer',
                }}
            >
                <input
                    type="checkbox"
                    checked={form.data.is_billable}
                    onChange={(event) =>
                        form.setData('is_billable', event.target.checked)
                    }
                    disabled={project ? !project.is_billable : false}
                    style={{ cursor: 'pointer' }}
                />
                Jam ini ditagihkan ke klien
                {project && !project.is_billable && ' (proyek internal)'}
            </label>

            <div>
                <label style={fieldLabelStyle}>Tugas</label>
                <input
                    type="text"
                    value={form.data.task}
                    onChange={(event) => form.setData('task', event.target.value)}
                    placeholder="Deskripsi singkat pekerjaan"
                    style={withError(inputStyle, !!form.errors.task)}
                />
                <FieldError message={form.errors.task} />
            </div>

            <div>
                <label style={fieldLabelStyle}>Catatan</label>
                <textarea
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                    placeholder="Catatan (opsional)"
                    style={withError(textareaStyle, !!form.errors.notes)}
                />
                <FieldError message={form.errors.notes} />
            </div>

            <ModalActions processing={form.processing} onCancel={onClose} />
        </ModalShell>
    );
}
