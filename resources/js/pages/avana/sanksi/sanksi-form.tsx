import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
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
        <form onSubmit={onSubmit} style={{ ...card }}>
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
                    <SearchableSelect
                        value={data.employee_id}
                        onChange={(value) => setData('employee_id', value)}
                        options={employees.map((employee) => ({
                            value: String(employee.id),
                            label: `${employee.name} (${employee.employee_number})`,
                        }))}
                        placeholder="Pilih karyawan…"
                        searchPlaceholder="Cari nama karyawan…"
                        allowClear
                        style={withError(selectStyle, !!errors.employee_id)}
                    />
                    <FieldError message={errors.employee_id} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Tanggal <span style={{ color: C.red }}>*</span>
                    </label>
                    <DatePicker
                        value={data.date}
                        onChange={(nextValue) => setData('date', nextValue)}
                        placeholder="Pilih tanggal"
                        hasError={!!errors.date}
                        width="100%"
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
                            style={withError(
                                selectStyle,
                                !!errors.penalty_type,
                            )}
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
                    <RupiahInput
                        value={data.amount}
                        onChange={(raw) => setData('amount', raw)}
                        invalid={!!errors.amount}
                    />
                    <FieldError message={errors.amount} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
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
                    <AIcon name="x" size={16} color={C.text} />
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
