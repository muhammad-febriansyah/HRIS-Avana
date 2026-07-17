import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import {
    dateInputStyle,
    Field,
    selectStyle,
    textareaStyle,
    textInputStyle,
    withError,
} from './components';
import type { CashAdvanceFormData, EmployeeOption } from './types';

interface KasbonFormProps {
    form: InertiaFormProps<CashAdvanceFormData>;
    employees: EmployeeOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for an operational cash advance request. */
export function KasbonForm({
    form,
    employees,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: KasbonFormProps) {
    const { data, setData, errors, processing } = form;

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
                <Field label="Karyawan" required error={errors.employee_id}>
                    <SearchableSelect
                        value={data.employee_id}
                        onChange={(value) => setData('employee_id', value)}
                        options={employees.map((employee) => ({
                            value: String(employee.id),
                            label: `${employee.name} (${employee.employee_number})`,
                        }))}
                        placeholder="Pilih karyawan"
                        searchPlaceholder="Cari nama karyawan…"
                        allowClear
                        style={withError(selectStyle, !!errors.employee_id)}
                    />
                </Field>

                <Field label="Keperluan" required error={errors.purpose}>
                    <input
                        type="text"
                        placeholder="Contoh: Uang muka perjalanan dinas Surabaya"
                        value={data.purpose}
                        onChange={(event) =>
                            setData('purpose', event.target.value)
                        }
                        style={withError(textInputStyle, !!errors.purpose)}
                    />
                </Field>

                <Field label="Jumlah (Rp)" required error={errors.amount}>
                    <RupiahInput
                        value={data.amount}
                        onChange={(raw) => setData('amount', raw)}
                        invalid={!!errors.amount}
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

                <Field label="Tanggal Dibutuhkan" error={errors.needed_date}>
                    <input
                        type="date"
                        value={data.needed_date}
                        onChange={(event) =>
                            setData('needed_date', event.target.value)
                        }
                        style={withError(dateInputStyle, !!errors.needed_date)}
                    />
                </Field>

                <div
                    style={{
                        background: C.surface,
                        borderRadius: 8,
                        padding: '11px 13px',
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 9,
                        fontSize: 12.5,
                        color: C.muted,
                        lineHeight: 1.5,
                    }}
                >
                    <AIcon name="info" size={15} color={C.primary} />
                    <span>
                        Uang muka <strong>tidak dipotong dari gaji</strong>.
                        Setelah dicairkan, karyawan wajib membuat settlement dan
                        melampirkan bukti pengeluaran.
                    </span>
                </div>

                <Field label="Alasan" error={errors.reason}>
                    <textarea
                        rows={3}
                        placeholder="Tuliskan alasan pengajuan uang muka"
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
                    <AIcon name="x" size={16} />
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
