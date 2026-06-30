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
import type { DepartmentOption, PostingFormData } from './types';
import { EMPLOYMENT_TYPE_OPTIONS, STATUS_OPTIONS } from './types';

interface RekrutmenFormProps {
    form: InertiaFormProps<PostingFormData>;
    departments: DepartmentOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a job posting. */
export function RekrutmenForm({
    form,
    departments,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: RekrutmenFormProps) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} style={{ ...card, maxWidth: 640 }}>
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
                        Judul Lowongan <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        placeholder="Contoh: Software Engineer"
                        style={withError(inputStyle, !!errors.title)}
                    />
                    <FieldError message={errors.title} />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>Departemen</label>
                        <select
                            value={data.department_id}
                            onChange={(event) =>
                                setData('department_id', event.target.value)
                            }
                            style={withError(
                                selectStyle,
                                !!errors.department_id,
                            )}
                        >
                            <option value="">Tanpa departemen</option>
                            {departments.map((department) => (
                                <option
                                    key={department.id}
                                    value={String(department.id)}
                                >
                                    {department.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.department_id} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Lokasi</label>
                        <input
                            type="text"
                            value={data.location}
                            onChange={(event) =>
                                setData('location', event.target.value)
                            }
                            placeholder="Contoh: Jakarta"
                            style={withError(inputStyle, !!errors.location)}
                        />
                        <FieldError message={errors.location} />
                    </div>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Tipe <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.employment_type}
                            onChange={(event) =>
                                setData('employment_type', event.target.value)
                            }
                            style={withError(
                                selectStyle,
                                !!errors.employment_type,
                            )}
                        >
                            {EMPLOYMENT_TYPE_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.employment_type} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Kuota <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="number"
                            min={1}
                            value={data.quota}
                            onChange={(event) =>
                                setData('quota', event.target.value)
                            }
                            placeholder="1"
                            style={withError(inputStyle, !!errors.quota)}
                        />
                        <FieldError message={errors.quota} />
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
                            {STATUS_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.status} />
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
                        <label style={fieldLabelStyle}>Tanggal Dibuka</label>
                        <input
                            type="date"
                            value={data.posted_date}
                            onChange={(event) =>
                                setData('posted_date', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.posted_date)}
                        />
                        <FieldError message={errors.posted_date} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Tanggal Ditutup</label>
                        <input
                            type="date"
                            value={data.closing_date}
                            onChange={(event) =>
                                setData('closing_date', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.closing_date)}
                        />
                        <FieldError message={errors.closing_date} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>Deskripsi</label>
                    <textarea
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Deskripsi pekerjaan & kualifikasi (opsional)"
                        style={withError(textareaStyle, !!errors.description)}
                    />
                    <FieldError message={errors.description} />
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

export default RekrutmenForm;
