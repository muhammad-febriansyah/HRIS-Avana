import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card, rp } from '@/lib/avana';
import {
    dateInputStyle,
    Field,
    selectStyle,
    textareaStyle,
    textInputStyle,
    withError,
} from './components';
import type { CashAdvanceFormData, EmployeeOption } from './types';

/** Inclusive rupiah preview of the per-month deduction (0 when incomplete). */
function monthlyDeduction(amount: string, installments: string): number {
    const value = Number(amount);
    const count = Number(installments);

    if (
        !Number.isFinite(value) ||
        value <= 0 ||
        !Number.isFinite(count) ||
        count < 1
    ) {
        return 0;
    }

    return Math.round(value / count);
}

interface KasbonFormProps {
    form: InertiaFormProps<CashAdvanceFormData>;
    employees: EmployeeOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create form for a cash advance request (store-only). */
export function KasbonForm({
    form,
    employees,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: KasbonFormProps) {
    const { data, setData, errors, processing } = form;

    const previewDeduction = monthlyDeduction(data.amount, data.installments);

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
                            <option
                                key={employee.id}
                                value={String(employee.id)}
                            >
                                {employee.name} ({employee.employee_number})
                            </option>
                        ))}
                    </select>
                </Field>

                <Field label="Jumlah (Rp)" required error={errors.amount}>
                    <input
                        type="number"
                        min="1"
                        step="1000"
                        placeholder="1000000"
                        value={data.amount}
                        onChange={(event) =>
                            setData('amount', event.target.value)
                        }
                        style={withError(textInputStyle, !!errors.amount)}
                    />
                </Field>

                <Field
                    label="Jumlah Cicilan (bulan)"
                    required
                    error={errors.installments}
                >
                    <input
                        type="number"
                        min="1"
                        step="1"
                        placeholder="1"
                        value={data.installments}
                        onChange={(event) =>
                            setData('installments', event.target.value)
                        }
                        style={withError(textInputStyle, !!errors.installments)}
                    />
                </Field>

                <Field
                    label="Tanggal Pengajuan"
                    required
                    error={errors.request_date}
                >
                    <input
                        type="date"
                        value={data.request_date}
                        onChange={(event) =>
                            setData('request_date', event.target.value)
                        }
                        style={withError(dateInputStyle, !!errors.request_date)}
                    />
                </Field>

                <div
                    style={{
                        background: C.surface,
                        borderRadius: 8,
                        padding: '11px 13px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 9,
                        fontSize: 12.5,
                        color: C.muted,
                    }}
                >
                    <AIcon name="info" size={15} color={C.primary} />
                    Potongan/bln&nbsp;
                    <span style={{ color: C.text, fontWeight: 600 }}>
                        {rp(previewDeduction)}
                    </span>
                </div>

                <Field label="Alasan" error={errors.reason}>
                    <textarea
                        rows={3}
                        placeholder="Tuliskan alasan pengajuan kasbon"
                        value={data.reason}
                        onChange={(event) =>
                            setData('reason', event.target.value)
                        }
                        style={withError(textareaStyle, !!errors.reason)}
                    />
                </Field>
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

export default KasbonForm;
