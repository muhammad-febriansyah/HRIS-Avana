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
import type { EmployeeOption, PenaltyFormData } from './types';
import { violationOptions } from './types';

interface SanksiFormProps {
    form: InertiaFormProps<PenaltyFormData>;
    employees: EmployeeOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared form for issuing a manual attendance penalty. */
export function SanksiForm({
    form,
    employees,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: SanksiFormProps) {
    const { data, setData, errors, processing } = form;
    const isDeduction = data.penalty_type === 'deduction';

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

                <div>
                    <label style={fieldLabelStyle}>
                        Tanggal <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="date"
                        value={data.date}
                        onChange={(event) => setData('date', event.target.value)}
                        style={withError(inputStyle, !!errors.date)}
                    />
                    <FieldError message={errors.date} />
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
                            Pelanggaran <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.violation_type}
                            onChange={(event) =>
                                setData('violation_type', event.target.value)
                            }
                            style={withError(
                                selectStyle,
                                !!errors.violation_type,
                            )}
                        >
                            {violationOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.violation_type} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Jenis Sanksi <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.penalty_type}
                            onChange={(event) =>
                                setData('penalty_type', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.penalty_type)}
                        >
                            <option value="warning">Peringatan</option>
                            <option value="deduction">Potongan</option>
                        </select>
                        <FieldError message={errors.penalty_type} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Nominal Potongan{' '}
                        {isDeduction && <span style={{ color: C.red }}>*</span>}
                    </label>
                    <input
                        type="number"
                        min={0}
                        value={data.amount}
                        onChange={(event) =>
                            setData('amount', event.target.value)
                        }
                        placeholder="0"
                        disabled={!isDeduction}
                        style={{
                            ...withError(inputStyle, !!errors.amount),
                            background: isDeduction ? '#fff' : C.surface,
                        }}
                    />
                    <FieldError message={errors.amount} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        value={data.notes}
                        onChange={(event) => setData('notes', event.target.value)}
                        placeholder="Keterangan tambahan…"
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

export default SanksiForm;
