import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    DocumentField,
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
    existingDocument?: { name: string; size?: number | null; href: string } | null;
    /** Detach that document; absent on the create form, which has none yet. */
    onRemoveDocument?: () => void;
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
    onRemoveDocument,
    cancelHref,
    onSubmit,
}: KontrakFormProps) {
    const { data, setData, errors, processing } = form;

    const selectedEmployee =
        employees.find((employee) => String(employee.id) === data.employee_id) ??
        null;

    const formatRupiah = (value: number) =>
        `Rp ${Math.round(value).toLocaleString('id-ID')}`;

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
                        <label style={fieldLabelStyle}>Gaji Pokok</label>
                        <div
                            style={{
                                ...inputStyle,
                                display: 'flex',
                                alignItems: 'center',
                                background: '#F3F5FA',
                                color: C.text,
                                fontWeight: 700,
                            }}
                        >
                            {selectedEmployee
                                ? formatRupiah(selectedEmployee.salary)
                                : '—'}
                        </div>
                        <div
                            style={{
                                marginTop: 6,
                                fontSize: 12,
                                color: C.muted,
                            }}
                        >
                            Otomatis mengikuti Payroll → Master Gaji — bukan
                            input kontrak.
                        </div>
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

                <DocumentField
                    file={data.document}
                    onPick={(picked) => setData('document', picked)}
                    existing={existingDocument}
                    onRemoveExisting={onRemoveDocument}
                    error={errors.document}
                />
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
