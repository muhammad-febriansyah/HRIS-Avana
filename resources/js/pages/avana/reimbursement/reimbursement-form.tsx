import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import {
    dateInputStyle,
    Field,
    fieldLabelStyle,
    FieldError,
    selectStyle,
    textareaStyle,
    textInputStyle,
    withError,
} from './components';
import type {
    EmployeeOption,
    ReimbursementFormData,
    SelectOption,
} from './types';

interface ReimbursementFormProps {
    form: InertiaFormProps<ReimbursementFormData>;
    employees: EmployeeOption[];
    categories: SelectOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    existingReceiptUrl?: string | null;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/**
 * Shared create/edit form for a reimbursement, including the receipt upload
 * that backs the claim.
 */
export function ReimbursementForm({
    form,
    employees,
    categories,
    submitLabel,
    submitIcon,
    cancelHref,
    existingReceiptUrl,
    onSubmit,
}: ReimbursementFormProps) {
    const { data, setData, errors, processing } = form;

    const onReceiptChange = (event: ChangeEvent<HTMLInputElement>) => {
        setData('receipt', event.target.files?.[0] ?? null);
    };

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
                            label: employee.employee_number
                                ? `${employee.name} (${employee.employee_number})`
                                : employee.name,
                        }))}
                        placeholder="Pilih karyawan"
                        searchPlaceholder="Cari nama karyawan…"
                        allowClear
                        style={withError(selectStyle, !!errors.employee_id)}
                    />
                </Field>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <Field label="Kategori" required error={errors.category}>
                        <select
                            value={data.category}
                            onChange={(event) =>
                                setData('category', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.category)}
                        >
                            <option value="">Pilih kategori</option>
                            {categories.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        label="Tanggal Pengeluaran"
                        required
                        error={errors.expense_date}
                    >
                        <DatePicker
                            value={data.expense_date}
                            onChange={(nextValue) =>
                                setData('expense_date', nextValue)
                            }
                            placeholder="Pilih tanggal"
                            hasError={!!errors.expense_date}
                            width="100%"
                        />
                    </Field>
                </div>

                <Field label="Judul" required error={errors.title}>
                    <input
                        type="text"
                        placeholder="Contoh: Taksi ke kantor klien"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        style={withError(textInputStyle, !!errors.title)}
                    />
                </Field>

                <Field label="Jumlah (Rp)" required error={errors.amount}>
                    <RupiahInput
                        value={data.amount}
                        onChange={(raw) => setData('amount', raw)}
                        invalid={!!errors.amount}
                    />
                </Field>

                <Field label="Deskripsi" error={errors.description}>
                    <textarea
                        rows={2}
                        placeholder="Rincian biaya yang ditalangi (opsional)"
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        style={withError(textareaStyle, !!errors.description)}
                    />
                </Field>

                <div>
                    <label style={fieldLabelStyle}>Bukti / Kuitansi</label>
                    {existingReceiptUrl && (
                        <div style={{ marginBottom: 8 }}>
                            <a
                                href={existingReceiptUrl}
                                target="_blank"
                                rel="noreferrer"
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    fontSize: 12.5,
                                    color: C.primary,
                                    textDecoration: 'none',
                                }}
                            >
                                <AIcon
                                    name="paperclip"
                                    size={14}
                                    color={C.primary}
                                />
                                Lihat bukti saat ini
                            </a>
                        </div>
                    )}
                    <input
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        onChange={onReceiptChange}
                        style={{
                            width: '100%',
                            fontSize: 12.5,
                            color: C.muted,
                        }}
                    />
                    <div
                        style={{
                            fontSize: 11,
                            color: C.faint,
                            marginTop: 4,
                        }}
                    >
                        JPG / PNG / PDF · maks 4 MB
                        {existingReceiptUrl
                            ? ' · unggah berkas baru untuk mengganti bukti lama'
                            : ''}
                    </div>
                    <FieldError message={errors.receipt} />
                </div>

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
                        Reimbursement adalah{' '}
                        <strong>penggantian biaya yang sudah ditalangi</strong>{' '}
                        karyawan. Setelah disetujui, Finance akan mencatat
                        pembayarannya.
                    </span>
                </div>

                <Field label="Catatan" error={errors.notes}>
                    <textarea
                        rows={2}
                        placeholder="Catatan tambahan (opsional)"
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        style={withError(textareaStyle, !!errors.notes)}
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
                    <AIcon name="x" size={16} color={C.muted} />
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

export default ReimbursementForm;
