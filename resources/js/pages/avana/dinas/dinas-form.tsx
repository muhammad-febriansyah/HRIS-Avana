import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import {
    dateInputStyle,
    Field,
    selectStyle,
    textareaStyle,
    textInputStyle,
    withError,
} from './components';
import type { EmployeeOption, TravelFormData } from './types';

interface DinasFormProps {
    form: InertiaFormProps<TravelFormData>;
    employees: EmployeeOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create form for a duty travel request. */
export function DinasForm({
    form,
    employees,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: DinasFormProps) {
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
                <Field label="Karyawan" required error={errors.employee_id}>
                    <select
                        value={data.employee_id}
                        onChange={(event) =>
                            setData('employee_id', event.target.value)
                        }
                        style={withError(selectStyle, !!errors.employee_id)}
                    >
                        <option value="">Pilih karyawan</option>
                        {employees.map((employee) => (
                            <option key={employee.id} value={String(employee.id)}>
                                {employee.name} ({employee.employee_number})
                            </option>
                        ))}
                    </select>
                </Field>

                <Field label="Tujuan" required error={errors.destination}>
                    <input
                        type="text"
                        placeholder="Kota / lokasi tujuan"
                        value={data.destination}
                        onChange={(event) =>
                            setData('destination', event.target.value)
                        }
                        style={withError(textInputStyle, !!errors.destination)}
                    />
                </Field>

                <Field label="Keperluan" error={errors.purpose}>
                    <textarea
                        rows={2}
                        placeholder="Tuliskan keperluan perjalanan dinas"
                        value={data.purpose}
                        onChange={(event) =>
                            setData('purpose', event.target.value)
                        }
                        style={withError(textareaStyle, !!errors.purpose)}
                    />
                </Field>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                    }}
                >
                    <Field label="Mulai" required error={errors.start_date}>
                        <input
                            type="date"
                            value={data.start_date}
                            onChange={(event) =>
                                setData('start_date', event.target.value)
                            }
                            style={withError(dateInputStyle, !!errors.start_date)}
                        />
                    </Field>
                    <Field label="Selesai" required error={errors.end_date}>
                        <input
                            type="date"
                            value={data.end_date}
                            onChange={(event) =>
                                setData('end_date', event.target.value)
                            }
                            style={withError(dateInputStyle, !!errors.end_date)}
                        />
                    </Field>
                </div>

                <Field label="Transportasi" error={errors.transport}>
                    <input
                        type="text"
                        placeholder="Pesawat / kereta / mobil"
                        value={data.transport}
                        onChange={(event) =>
                            setData('transport', event.target.value)
                        }
                        style={withError(textInputStyle, !!errors.transport)}
                    />
                </Field>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                    }}
                >
                    <Field label="Estimasi Biaya" error={errors.estimated_cost}>
                        <RupiahInput
                            value={data.estimated_cost}
                            onChange={(raw) => setData('estimated_cost', raw)}
                            invalid={!!errors.estimated_cost}
                        />
                    </Field>
                    <Field label="Uang Saku" error={errors.per_diem}>
                        <RupiahInput
                            value={data.per_diem}
                            onChange={(raw) => setData('per_diem', raw)}
                            invalid={!!errors.per_diem}
                        />
                    </Field>
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

export default DinasForm;
