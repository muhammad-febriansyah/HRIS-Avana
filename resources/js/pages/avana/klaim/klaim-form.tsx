import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import {
    dateInputStyle,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type { ClaimFormData, EmployeeOption, SelectOption } from './types';

interface KlaimFormProps {
    form: InertiaFormProps<ClaimFormData>;
    employees: EmployeeOption[];
    claimTypes: SelectOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    existingReceiptUrl?: string | null;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a claim, including a receipt upload. */
export function KlaimForm({
    form,
    employees,
    claimTypes,
    submitLabel,
    submitIcon,
    cancelHref,
    existingReceiptUrl,
    onSubmit,
}: KlaimFormProps) {
    const { data, setData, errors, processing } = form;

    const onReceiptChange = (event: ChangeEvent<HTMLInputElement>) => {
        setData('receipt', event.target.files?.[0] ?? null);
    };

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

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Jenis Klaim <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.claim_type}
                            onChange={(event) =>
                                setData('claim_type', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.claim_type)}
                        >
                            {claimTypes.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.claim_type} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Tanggal Klaim{' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="date"
                            value={data.claim_date}
                            onChange={(event) =>
                                setData('claim_date', event.target.value)
                            }
                            style={withError(
                                dateInputStyle,
                                !!errors.claim_date,
                            )}
                        />
                        <FieldError message={errors.claim_date} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Judul <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        placeholder="Ringkasan klaim"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        style={withError(inputStyle, !!errors.title)}
                    />
                    <FieldError message={errors.title} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Nominal (Rp) <span style={{ color: C.red }}>*</span>
                    </label>
                    <RupiahInput
                        value={data.amount}
                        onChange={(raw) => setData('amount', raw)}
                        invalid={!!errors.amount}
                    />
                    <FieldError message={errors.amount} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Deskripsi</label>
                    <textarea
                        rows={2}
                        placeholder="Keterangan klaim (opsional)"
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        style={withError(textareaStyle, !!errors.description)}
                    />
                    <FieldError message={errors.description} />
                </div>

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
                        accept="image/*,application/pdf"
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
                    </div>
                    <FieldError message={errors.receipt} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        rows={2}
                        placeholder="Catatan tambahan (opsional)"
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
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

export default KlaimForm;
