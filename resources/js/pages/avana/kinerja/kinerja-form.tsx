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
import type {
    CycleOption,
    EmployeeOption,
    ReviewFormData,
    SelectOption,
} from './types';

interface KinerjaFormProps {
    form: InertiaFormProps<ReviewFormData>;
    employees: EmployeeOption[];
    cycleOptions: CycleOption[];
    statuses: SelectOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a performance review. */
export function KinerjaForm({
    form,
    employees,
    cycleOptions,
    statuses,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: KinerjaFormProps) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} style={{ ...card, maxWidth: 640 }}>
            <div
                style={{
                    padding: '22px 24px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 16,
                }}
            >
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Siklus <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.cycle_id}
                            onChange={(event) =>
                                setData('cycle_id', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.cycle_id)}
                        >
                            <option value="">Pilih siklus</option>
                            {cycleOptions.map((cycle) => (
                                <option
                                    key={cycle.id}
                                    value={String(cycle.id)}
                                >
                                    {cycle.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.cycle_id} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Status <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.status}
                            onChange={(event) =>
                                setData('status', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.status)}
                        >
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.status} />
                    </div>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Karyawan <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.employee_id}
                            onChange={(event) =>
                                setData('employee_id', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.employee_id)}
                        >
                            <option value="">Pilih karyawan</option>
                            {employees.map((employee) => (
                                <option
                                    key={employee.id}
                                    value={String(employee.id)}
                                >
                                    {employee.name}
                                    {employee.employee_number
                                        ? ` (${employee.employee_number})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.employee_id} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Penilai</label>
                        <select
                            value={data.reviewer_id}
                            onChange={(event) =>
                                setData('reviewer_id', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.reviewer_id)}
                        >
                            <option value="">Tanpa penilai</option>
                            {employees.map((employee) => (
                                <option
                                    key={employee.id}
                                    value={String(employee.id)}
                                >
                                    {employee.name}
                                    {employee.employee_number
                                        ? ` (${employee.employee_number})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.reviewer_id} />
                    </div>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>Skor Mandiri</label>
                        <input
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            value={data.self_score}
                            onChange={(event) =>
                                setData('self_score', event.target.value)
                            }
                            placeholder="0 - 100"
                            style={withError(inputStyle, !!errors.self_score)}
                        />
                        <FieldError message={errors.self_score} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Skor Atasan</label>
                        <input
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            value={data.manager_score}
                            onChange={(event) =>
                                setData('manager_score', event.target.value)
                            }
                            placeholder="0 - 100"
                            style={withError(inputStyle, !!errors.manager_score)}
                        />
                        <FieldError message={errors.manager_score} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Skor Akhir</label>
                        <input
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            value={data.final_score}
                            onChange={(event) =>
                                setData('final_score', event.target.value)
                            }
                            placeholder="0 - 100"
                            style={withError(inputStyle, !!errors.final_score)}
                        />
                        <FieldError message={errors.final_score} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>Tanggal Penilaian</label>
                    <input
                        type="date"
                        value={data.review_date}
                        onChange={(event) =>
                            setData('review_date', event.target.value)
                        }
                        style={withError(inputStyle, !!errors.review_date)}
                    />
                    <FieldError message={errors.review_date} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        placeholder="Catatan penilaian (opsional)"
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

export default KinerjaForm;
