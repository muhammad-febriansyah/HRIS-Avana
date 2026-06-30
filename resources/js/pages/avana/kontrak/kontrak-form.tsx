import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type { ContractFormData, EmployeeOption } from './types';
import { contractTypeOptions, statusOptions } from './types';

interface KontrakFormProps {
    form: InertiaFormProps<ContractFormData>;
    employees: EmployeeOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for an employee contract. */
export function KontrakForm({
    form,
    employees,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: KontrakFormProps) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} style={{ ...card, maxWidth: 560 }}>
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
                        Karyawan <span style={{ color: C.red }}>*</span>
                    </label>
                    <select
                        value={data.employee_id}
                        onChange={(event) =>
                            setData('employee_id', event.target.value)
                        }
                        style={withError(selectStyle, !!errors.employee_id)}
                    >
                        <option value="">Pilih karyawan…</option>
                        {employees.map((employee) => (
                            <option key={employee.id} value={String(employee.id)}>
                                {employee.name} ({employee.employee_number})
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.employee_id} />
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
                            No Kontrak <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={data.contract_number}
                            onChange={(event) =>
                                setData('contract_number', event.target.value)
                            }
                            placeholder="No. PKWT-2026-001"
                            style={withError(
                                inputStyle,
                                !!errors.contract_number,
                            )}
                        />
                        <FieldError message={errors.contract_number} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Jenis Kontrak{' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.contract_type}
                            onChange={(event) =>
                                setData('contract_type', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.contract_type)}
                        >
                            {contractTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.contract_type} />
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
                            Mulai <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="date"
                            value={data.start_date}
                            onChange={(event) =>
                                setData('start_date', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.start_date)}
                        />
                        <FieldError message={errors.start_date} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Selesai</label>
                        <input
                            type="date"
                            value={data.end_date}
                            onChange={(event) =>
                                setData('end_date', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.end_date)}
                        />
                        <FieldError message={errors.end_date} />
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
                            Gaji Pokok <span style={{ color: C.red }}>*</span>
                        </label>
                        <RupiahInput
                            value={data.basic_salary}
                            onChange={(raw) => setData('basic_salary', raw)}
                            invalid={!!errors.basic_salary}
                        />
                        <FieldError message={errors.basic_salary} />
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
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.status} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        value={data.notes}
                        onChange={(event) => setData('notes', event.target.value)}
                        placeholder="Catatan tambahan (opsional)"
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

export default KontrakForm;
