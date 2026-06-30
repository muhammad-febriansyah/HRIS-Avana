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
    ObjectiveFormData,
    SelectOption,
} from './types';

interface OkrFormProps {
    form: InertiaFormProps<ObjectiveFormData>;
    cycles: CycleOption[];
    employees: EmployeeOption[];
    levels: SelectOption[];
    statuses: SelectOption[];
    perspectives: SelectOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for an objective. */
export function OkrForm({
    form,
    cycles,
    employees,
    levels,
    statuses,
    perspectives,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: OkrFormProps) {
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
                <div>
                    <label style={fieldLabelStyle}>
                        Judul Objective <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        placeholder="Tingkatkan kepuasan pelanggan"
                        style={withError(inputStyle, !!errors.title)}
                    />
                    <FieldError message={errors.title} />
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
                            Level <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.level}
                            onChange={(event) =>
                                setData('level', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.level)}
                        >
                            {levels.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.level} />
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

                <div>
                    <label style={fieldLabelStyle}>
                        Perspektif Balanced Scorecard
                    </label>
                    <select
                        value={data.perspective}
                        onChange={(event) =>
                            setData('perspective', event.target.value)
                        }
                        style={withError(selectStyle, !!errors.perspective)}
                    >
                        <option value="">Tanpa perspektif</option>
                        {perspectives.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.perspective} />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>Siklus</label>
                        <select
                            value={data.cycle_id}
                            onChange={(event) =>
                                setData('cycle_id', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.cycle_id)}
                        >
                            <option value="">Tanpa siklus</option>
                            {cycles.map((cycle) => (
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
                        <label style={fieldLabelStyle}>Pemilik</label>
                        <select
                            value={data.employee_id}
                            onChange={(event) =>
                                setData('employee_id', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.employee_id)}
                        >
                            <option value="">Tanpa pemilik</option>
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
                </div>

                <div>
                    <label style={fieldLabelStyle}>Deskripsi</label>
                    <textarea
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Deskripsi objective (opsional)"
                        style={withError(textareaStyle, !!errors.description)}
                    />
                    <FieldError message={errors.description} />
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

export default OkrForm;
