import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import TimesheetController from '@/actions/App/Http/Controllers/Avana/TimesheetController';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, ActionBtn, btnOut, C } from '@/lib/avana';
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
import { emptyProjectForm, projectToForm } from './types';
import type { EmployeeOption, ProjectFormData, ProjectRow } from './types';

interface ProjectModalProps {
    project: ProjectRow | null;
    employees: EmployeeOption[];
    onClose: () => void;
}

/**
 * Create or edit a project, including the rate defaults every entry logged
 * against it is priced with and the members allowed to log against it.
 */
export function ProjectModal({
    project,
    employees,
    onClose,
}: ProjectModalProps) {
    const form = useForm<ProjectFormData>(
        project ? projectToForm(project) : { ...emptyProjectForm },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(
            project
                ? TimesheetController.updateProject(project.id)
                : TimesheetController.storeProject(),
            { onSuccess: onClose },
        );
    };

    const addMember = () => {
        form.setData('members', [
            ...form.data.members,
            { employee_id: '', bill_rate: '', cost_rate: '' },
        ]);
    };

    const setMember = (index: number, key: string, value: string) => {
        form.setData(
            'members',
            form.data.members.map((member, position) =>
                position === index ? { ...member, [key]: value } : member,
            ),
        );
    };

    const removeMember = (index: number) => {
        form.setData(
            'members',
            form.data.members.filter((_, position) => position !== index),
        );
    };

    const takenIds = form.data.members.map((member) => member.employee_id);

    return (
        <ModalShell
            title={project ? 'Ubah Proyek' : 'Tambah Proyek'}
            subtitle="Tarif di sini dipakai untuk menghitung nilai jual dan biaya setiap jam yang disetujui."
            width={620}
            onClose={onClose}
            onSubmit={submit}
        >
            <div>
                <label style={fieldLabelStyle}>
                    Nama Proyek <span style={{ color: C.red }}>*</span>
                </label>
                <input
                    type="text"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    placeholder="Nama proyek"
                    style={withError(inputStyle, !!form.errors.name)}
                />
                <FieldError message={form.errors.name} />
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>Kode</label>
                    <input
                        type="text"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        placeholder="PRJ-01"
                        style={withError(inputStyle, !!form.errors.code)}
                    />
                    <FieldError message={form.errors.code} />
                </div>
                <div>
                    <label style={fieldLabelStyle}>Klien</label>
                    <input
                        type="text"
                        value={form.data.client_name}
                        onChange={(event) =>
                            form.setData('client_name', event.target.value)
                        }
                        placeholder="Nama klien"
                        style={withError(inputStyle, !!form.errors.client_name)}
                    />
                    <FieldError message={form.errors.client_name} />
                </div>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>Mulai</label>
                    <DatePicker
                        value={form.data.start_date}
                        onChange={(value) => form.setData('start_date', value)}
                        placeholder="Pilih tanggal"
                        hasError={!!form.errors.start_date}
                        width="100%"
                    />
                    <FieldError message={form.errors.start_date} />
                </div>
                <div>
                    <label style={fieldLabelStyle}>Selesai</label>
                    <DatePicker
                        value={form.data.end_date}
                        onChange={(value) => form.setData('end_date', value)}
                        placeholder="Pilih tanggal"
                        hasError={!!form.errors.end_date}
                        width="100%"
                    />
                    <FieldError message={form.errors.end_date} />
                </div>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>Penanggung Jawab</label>
                    <SearchableSelect
                        value={form.data.manager_id}
                        onChange={(value) => form.setData('manager_id', value)}
                        options={employees.map((employee) => ({
                            value: String(employee.id),
                            label: employee.name,
                        }))}
                        placeholder="Pilih karyawan"
                        searchPlaceholder="Cari nama karyawan…"
                        allowClear
                    />
                    <FieldError message={form.errors.manager_id} />
                </div>
                <div>
                    <label style={fieldLabelStyle}>Status</label>
                    <select
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData('status', event.target.value)
                        }
                        style={withError(selectStyle, !!form.errors.status)}
                    >
                        <option value="active">Aktif</option>
                        <option value="archived">Arsip</option>
                    </select>
                    <FieldError message={form.errors.status} />
                </div>
            </div>

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
                    style={{ cursor: 'pointer' }}
                />
                Proyek ditagihkan ke klien (billable)
            </label>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>Tarif Jual / Jam</label>
                    <input
                        type="number"
                        min="0"
                        step="1000"
                        value={form.data.default_bill_rate}
                        onChange={(event) =>
                            form.setData('default_bill_rate', event.target.value)
                        }
                        placeholder="150000"
                        style={withError(
                            inputStyle,
                            !!form.errors.default_bill_rate,
                        )}
                    />
                    <FieldError message={form.errors.default_bill_rate} />
                </div>
                <div>
                    <label style={fieldLabelStyle}>Tarif Biaya / Jam</label>
                    <input
                        type="number"
                        min="0"
                        step="1000"
                        value={form.data.default_cost_rate}
                        onChange={(event) =>
                            form.setData('default_cost_rate', event.target.value)
                        }
                        placeholder="Kosongkan: pakai gaji karyawan ÷ 173"
                        style={withError(
                            inputStyle,
                            !!form.errors.default_cost_rate,
                        )}
                    />
                    <FieldError message={form.errors.default_cost_rate} />
                </div>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 14,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>Budget Biaya</label>
                    <input
                        type="number"
                        min="0"
                        step="100000"
                        value={form.data.budget_amount}
                        onChange={(event) =>
                            form.setData('budget_amount', event.target.value)
                        }
                        placeholder="0"
                        style={withError(
                            inputStyle,
                            !!form.errors.budget_amount,
                        )}
                    />
                    <FieldError message={form.errors.budget_amount} />
                </div>
                <div>
                    <label style={fieldLabelStyle}>Budget Jam</label>
                    <input
                        type="number"
                        min="0"
                        step="1"
                        value={form.data.budget_hours}
                        onChange={(event) =>
                            form.setData('budget_hours', event.target.value)
                        }
                        placeholder="0"
                        style={withError(inputStyle, !!form.errors.budget_hours)}
                    />
                    <FieldError message={form.errors.budget_hours} />
                </div>
            </div>

            <div>
                <label style={fieldLabelStyle}>Deskripsi</label>
                <textarea
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    placeholder="Ruang lingkup singkat proyek (opsional)"
                    style={withError(textareaStyle, !!form.errors.description)}
                />
                <FieldError message={form.errors.description} />
            </div>

            {/* Members */}
            <div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 8,
                    }}
                >
                    <label style={{ ...fieldLabelStyle, marginBottom: 0 }}>
                        Anggota Proyek
                    </label>
                    <button type="button" onClick={addMember} style={btnOut}>
                        <AIcon name="plus" size={14} color={C.text} />
                        Tambah Anggota
                    </button>
                </div>
                <div style={{ fontSize: 12, color: C.muted, marginBottom: 10 }}>
                    Kosongkan untuk mengizinkan semua karyawan mencatat jam di
                    proyek ini. Tarif per anggota mengalahkan tarif default.
                </div>

                {form.data.members.map((member, index) => (
                    <div
                        key={index}
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 120px 120px 34px',
                            gap: 8,
                            alignItems: 'center',
                            marginBottom: 8,
                        }}
                    >
                        <SearchableSelect
                            value={member.employee_id}
                            onChange={(value) =>
                                setMember(index, 'employee_id', value)
                            }
                            options={employees
                                .filter(
                                    (employee) =>
                                        String(employee.id) ===
                                            member.employee_id ||
                                        !takenIds.includes(String(employee.id)),
                                )
                                .map((employee) => ({
                                    value: String(employee.id),
                                    label: employee.name,
                                }))}
                            placeholder="Pilih karyawan"
                            searchPlaceholder="Cari nama karyawan…"
                        />
                        <input
                            type="number"
                            min="0"
                            step="1000"
                            value={member.bill_rate}
                            onChange={(event) =>
                                setMember(index, 'bill_rate', event.target.value)
                            }
                            placeholder="Jual"
                            style={inputStyle}
                        />
                        <input
                            type="number"
                            min="0"
                            step="1000"
                            value={member.cost_rate}
                            onChange={(event) =>
                                setMember(index, 'cost_rate', event.target.value)
                            }
                            placeholder="Biaya"
                            style={inputStyle}
                        />
                        <ActionBtn
                            icon="trash-2"
                            label="Hapus anggota"
                            variant="danger"
                            iconOnly
                            onClick={() => removeMember(index)}
                        />
                    </div>
                ))}
                <FieldError message={form.errors.members} />
            </div>

            <ModalActions processing={form.processing} onCancel={onClose} />
        </ModalShell>
    );
}
