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
import type { ContractFormData, EmployeeOption } from './types';
import { contractTypeOptions, statusOptions } from './types';

interface KontrakFormProps {
    form: InertiaFormProps<ContractFormData>;
    employees: EmployeeOption[];
    submitLabel: string;
    submitIcon: string;
    /** The document already attached, when editing. */
    existingDocument?: { name: string; href: string } | null;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for an employee contract. */
export function KontrakForm({
    form,
    employees,
    submitLabel,
    submitIcon,
    existingDocument = null,
    cancelHref,
    onSubmit,
}: KontrakFormProps) {
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
                            style={withError(
                                selectStyle,
                                !!errors.contract_type,
                            )}
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
                        <DatePicker
                            value={data.start_date}
                            onChange={(nextValue) =>
                                setData('start_date', nextValue)
                            }
                            placeholder="Pilih tanggal"
                            hasError={!!errors.start_date}
                            width="100%"
                        />
                        <FieldError message={errors.start_date} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Selesai</label>
                        <DatePicker
                            value={data.end_date}
                            onChange={(nextValue) =>
                                setData('end_date', nextValue)
                            }
                            placeholder="Pilih tanggal"
                            hasError={!!errors.end_date}
                            width="100%"
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
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        placeholder="Catatan tambahan (opsional)"
                        style={withError(textareaStyle, !!errors.notes)}
                    />
                    <FieldError message={errors.notes} />
                </div>

                <div style={{ gridColumn: '1 / -1' }}>
                    <label style={fieldLabelStyle}>Dokumen Kontrak</label>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(event) =>
                            setData('document', event.target.files?.[0] ?? null)
                        }
                        style={withError(inputStyle, !!errors.document)}
                    />
                    <FieldError message={errors.document} />
                    {existingDocument ? (
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                marginTop: 8,
                                fontSize: 12.5,
                                color: C.muted,
                            }}
                        >
                            <AIcon name="paperclip" size={14} color={C.muted} />
                            <a
                                href={existingDocument.href}
                                style={{ color: C.primary, textDecoration: 'none' }}
                            >
                                {existingDocument.name}
                            </a>
                            <span style={{ color: C.faint }}>
                                — unggah berkas baru untuk menggantinya
                            </span>
                        </div>
                    ) : (
                        <div style={{ fontSize: 12.5, color: C.faint, marginTop: 8 }}>
                            PDF atau gambar, maksimal 10 MB. Disimpan privat dan hanya
                            bisa diunduh lewat aplikasi.
                        </div>
                    )}
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

export default KontrakForm;
