import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import {
    employmentStatusOptions,
    movementOptions,
    type EmployeeOption,
    type MovementFormData,
    type NamedOption,
} from './types';

interface MutasiFormProps {
    form: InertiaFormProps<MovementFormData>;
    employees: EmployeeOption[];
    positions: NamedOption[];
    departments: NamedOption[];
    branches: NamedOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared form for recording a new employee career movement. */
export function MutasiForm({
    form,
    employees,
    positions,
    departments,
    branches,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: MutasiFormProps) {
    const { data, setData, errors, processing } = form;

    /** Select an employee and prefill its current placement into the form. */
    const selectEmployee = (value: string) => {
        const employee = employees.find((item) => String(item.id) === value);

        setData((current) => ({
            ...current,
            employee_id: value,
            position_id: employee?.position_id
                ? String(employee.position_id)
                : '',
            department_id: employee?.department_id
                ? String(employee.department_id)
                : '',
            branch_id: employee?.branch_id ? String(employee.branch_id) : '',
        }));
    };

    return (
        <form onSubmit={onSubmit} style={{ ...card, maxWidth: 560 }}>
            <div
                style={{
                    padding: '22px 24px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 14,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>
                        Karyawan <span style={{ color: C.red }}>*</span>
                    </label>
                    <select
                        value={data.employee_id}
                        onChange={(event) => selectEmployee(event.target.value)}
                        style={withError(selectStyle, !!errors.employee_id)}
                    >
                        <option value="">Pilih karyawan…</option>
                        {employees.map((employee) => (
                            <option key={employee.id} value={employee.id}>
                                {employee.name} ({employee.employee_number})
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.employee_id} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Jenis Mutasi <span style={{ color: C.red }}>*</span>
                    </label>
                    <select
                        value={data.movement_type}
                        onChange={(event) =>
                            setData('movement_type', event.target.value)
                        }
                        style={withError(selectStyle, !!errors.movement_type)}
                    >
                        {movementOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.movement_type} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Tanggal Efektif <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="date"
                        value={data.effective_date}
                        onChange={(event) =>
                            setData('effective_date', event.target.value)
                        }
                        style={withError(inputStyle, !!errors.effective_date)}
                    />
                    <FieldError message={errors.effective_date} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Posisi Baru</label>
                    <select
                        value={data.position_id}
                        onChange={(event) =>
                            setData('position_id', event.target.value)
                        }
                        style={withError(selectStyle, !!errors.position_id)}
                    >
                        <option value="">Tidak berubah</option>
                        {positions.map((position) => (
                            <option key={position.id} value={position.id}>
                                {position.name}
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.position_id} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Departemen Baru</label>
                    <select
                        value={data.department_id}
                        onChange={(event) =>
                            setData('department_id', event.target.value)
                        }
                        style={withError(selectStyle, !!errors.department_id)}
                    >
                        <option value="">Tidak berubah</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.department_id} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Cabang Baru</label>
                    <select
                        value={data.branch_id}
                        onChange={(event) =>
                            setData('branch_id', event.target.value)
                        }
                        style={withError(selectStyle, !!errors.branch_id)}
                    >
                        <option value="">Tidak berubah</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.branch_id} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Status Kepegawaian</label>
                    <select
                        value={data.employment_status}
                        onChange={(event) =>
                            setData('employment_status', event.target.value)
                        }
                        style={withError(
                            selectStyle,
                            !!errors.employment_status,
                        )}
                    >
                        <option value="">Tidak berubah</option>
                        {employmentStatusOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.employment_status} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        placeholder="Alasan atau keterangan mutasi…"
                        style={withError(textareaStyle, !!errors.notes)}
                    />
                    <FieldError message={errors.notes} />
                </div>
            </div>

            <div
                style={{
                    display: 'flex',
                    gap: 10,
                    justifyContent: 'flex-end',
                    padding: '16px 24px',
                    borderTop: `1px solid ${C.line}`,
                }}
            >
                <Link
                    href={cancelHref}
                    style={{
                        ...btnOut,
                        height: 44,
                        justifyContent: 'center',
                        textDecoration: 'none',
                    }}
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    disabled={processing}
                    style={{
                        ...btnP,
                        height: 44,
                        justifyContent: 'center',
                        opacity: processing ? 0.7 : 1,
                        cursor: processing ? 'not-allowed' : 'pointer',
                    }}
                >
                    <AIcon name={submitIcon} size={16} color="#fff" />
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}

export default MutasiForm;
