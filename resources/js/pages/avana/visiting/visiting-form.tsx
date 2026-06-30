import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    dateInputStyle,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type { EmployeeOption, VisitFormData } from './types';

interface VisitingFormProps {
    form: InertiaFormProps<VisitFormData>;
    employees: EmployeeOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Create form for a field visit, including an optional photo upload. */
export function VisitingForm({
    form,
    employees,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: VisitingFormProps) {
    const { data, setData, errors, processing } = form;

    const onPhotoChange = (event: ChangeEvent<HTMLInputElement>) => {
        setData('photo', event.target.files?.[0] ?? null);
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
                                {employee.name} ({employee.employee_number})
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.employee_id} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Tanggal Kunjungan{' '}
                        <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="date"
                        value={data.visit_date}
                        onChange={(event) =>
                            setData('visit_date', event.target.value)
                        }
                        style={withError(dateInputStyle, !!errors.visit_date)}
                    />
                    <FieldError message={errors.visit_date} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Lokasi <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        placeholder="Alamat / area kunjungan"
                        value={data.location}
                        onChange={(event) =>
                            setData('location', event.target.value)
                        }
                        style={withError(inputStyle, !!errors.location)}
                    />
                    <FieldError message={errors.location} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Klien</label>
                    <input
                        type="text"
                        placeholder="Nama klien / perusahaan"
                        value={data.client_name}
                        onChange={(event) =>
                            setData('client_name', event.target.value)
                        }
                        style={withError(inputStyle, !!errors.client_name)}
                    />
                    <FieldError message={errors.client_name} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Tujuan</label>
                    <textarea
                        rows={2}
                        placeholder="Tujuan kunjungan"
                        value={data.purpose}
                        onChange={(event) =>
                            setData('purpose', event.target.value)
                        }
                        style={withError(textareaStyle, !!errors.purpose)}
                    />
                    <FieldError message={errors.purpose} />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 12,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>Latitude</label>
                        <input
                            type="number"
                            step="any"
                            placeholder="-6.2088"
                            value={data.latitude}
                            onChange={(event) =>
                                setData('latitude', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.latitude)}
                        />
                        <FieldError message={errors.latitude} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Longitude</label>
                        <input
                            type="number"
                            step="any"
                            placeholder="106.8456"
                            value={data.longitude}
                            onChange={(event) =>
                                setData('longitude', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.longitude)}
                        />
                        <FieldError message={errors.longitude} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>Foto Kunjungan</label>
                    <input
                        type="file"
                        accept="image/*"
                        onChange={onPhotoChange}
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
                        JPG / PNG · maks 4 MB
                    </div>
                    <FieldError message={errors.photo} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        rows={2}
                        placeholder="Catatan tambahan"
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

export default VisitingForm;
