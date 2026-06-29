import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { AIcon, C, card } from '@/lib/avana';
import type { EmployeeFormData, EmployeeFormOptions } from './types';

const labelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    color: C.text,
    marginBottom: 7,
};

const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
    transition: '.15s',
};

const selectStyle: CSSProperties = { ...inputStyle, cursor: 'pointer' };

const errorBorder: CSSProperties = {
    border: `1px solid ${C.red}`,
    boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

const sectionGrid: CSSProperties = {
    padding: '20px 22px',
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: '16px 20px',
};

const req = <span style={{ color: C.red }}>*</span>;

interface EmployeeFormProps {
    form: InertiaFormProps<EmployeeFormData>;
    options: EmployeeFormOptions;
    submitLabel: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

function SectionHeader({
    icon,
    title,
    desc,
}: {
    icon: string;
    title: string;
    desc?: string;
}) {
    return (
        <div
            style={{
                padding: '18px 22px',
                borderBottom: `1px solid ${C.line}`,
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                <AIcon name={icon} size={18} color={C.primary} />
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
            </div>
            {desc ? (
                <div
                    style={{
                        fontSize: 12.5,
                        color: C.muted,
                        marginTop: 3,
                        marginLeft: 27,
                    }}
                >
                    {desc}
                </div>
            ) : null}
        </div>
    );
}

function Field({
    htmlFor,
    label,
    required = false,
    fullWidth = false,
    error,
    children,
}: {
    htmlFor: string;
    label: string;
    required?: boolean;
    fullWidth?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div style={fullWidth ? { gridColumn: '1/-1' } : undefined}>
            <label htmlFor={htmlFor} style={labelStyle}>
                {label} {required ? req : null}
            </label>
            {children}
            {error ? (
                <div style={errorTextStyle}>
                    <AIcon name="circle-alert" size={13} color={C.red} />
                    {error}
                </div>
            ) : null}
        </div>
    );
}

/**
 * Shared employee create/edit form rendered with the AvanaHR prototype markup.
 * The parent owns the Inertia form instance and submits to the right route.
 */
export function EmployeeForm({
    form,
    options,
    submitLabel,
    cancelHref,
    onSubmit,
}: EmployeeFormProps) {
    const { data, setData, errors, processing } = form;

    const styleFor = (hasError: boolean, base: CSSProperties): CSSProperties =>
        hasError ? { ...base, ...errorBorder } : base;

    return (
        <form
            onSubmit={onSubmit}
            style={{ display: 'flex', flexDirection: 'column', gap: 18 }}
        >
            {/* Data Personal */}
            <div style={card}>
                <SectionHeader
                    icon="user"
                    title="Data Personal"
                    desc="Identitas dasar karyawan sesuai KTP."
                />
                <div className="avn-2col" style={sectionGrid}>
                    <Field
                        htmlFor="full_name"
                        label="Nama Lengkap"
                        required
                        error={errors.full_name}
                    >
                        <input
                            id="full_name"
                            value={data.full_name}
                            onChange={(event) =>
                                setData('full_name', event.target.value)
                            }
                            placeholder="Masukkan nama sesuai KTP"
                            style={styleFor(!!errors.full_name, inputStyle)}
                        />
                    </Field>

                    <Field htmlFor="nik" label="NIK (KTP)" error={errors.nik}>
                        <input
                            id="nik"
                            inputMode="numeric"
                            maxLength={16}
                            value={data.nik}
                            onChange={(event) =>
                                setData('nik', event.target.value)
                            }
                            placeholder="16 digit NIK"
                            style={styleFor(!!errors.nik, inputStyle)}
                        />
                    </Field>

                    <Field htmlFor="email" label="Email" error={errors.email}>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                            placeholder="nama@perusahaan.co.id"
                            style={styleFor(!!errors.email, inputStyle)}
                        />
                    </Field>

                    <Field
                        htmlFor="phone"
                        label="No. Telepon"
                        error={errors.phone}
                    >
                        <input
                            id="phone"
                            value={data.phone}
                            onChange={(event) =>
                                setData('phone', event.target.value)
                            }
                            placeholder="08xx-xxxx-xxxx"
                            style={styleFor(!!errors.phone, inputStyle)}
                        />
                    </Field>

                    <Field
                        htmlFor="birth_date"
                        label="Tanggal Lahir"
                        error={errors.birth_date}
                    >
                        <input
                            id="birth_date"
                            type="date"
                            value={data.birth_date}
                            onChange={(event) =>
                                setData('birth_date', event.target.value)
                            }
                            style={styleFor(!!errors.birth_date, {
                                ...inputStyle,
                                color: C.muted,
                            })}
                        />
                    </Field>

                    <Field
                        htmlFor="birth_place"
                        label="Tempat Lahir"
                        error={errors.birth_place}
                    >
                        <input
                            id="birth_place"
                            value={data.birth_place}
                            onChange={(event) =>
                                setData('birth_place', event.target.value)
                            }
                            placeholder="cth. Jakarta"
                            style={styleFor(!!errors.birth_place, inputStyle)}
                        />
                    </Field>

                    <Field
                        htmlFor="gender"
                        label="Jenis Kelamin"
                        error={errors.gender}
                    >
                        <select
                            id="gender"
                            value={data.gender}
                            onChange={(event) =>
                                setData('gender', event.target.value)
                            }
                            style={styleFor(!!errors.gender, selectStyle)}
                        >
                            <option value="">Pilih jenis kelamin</option>
                            {options.genders.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        htmlFor="marital_status"
                        label="Status Pernikahan"
                        error={errors.marital_status}
                    >
                        <input
                            id="marital_status"
                            value={data.marital_status}
                            onChange={(event) =>
                                setData('marital_status', event.target.value)
                            }
                            placeholder="cth. Menikah"
                            style={styleFor(
                                !!errors.marital_status,
                                inputStyle,
                            )}
                        />
                    </Field>

                    <Field
                        htmlFor="religion"
                        label="Agama"
                        error={errors.religion}
                    >
                        <input
                            id="religion"
                            value={data.religion}
                            onChange={(event) =>
                                setData('religion', event.target.value)
                            }
                            placeholder="cth. Islam"
                            style={styleFor(!!errors.religion, inputStyle)}
                        />
                    </Field>

                    <Field
                        htmlFor="address"
                        label="Alamat Domisili"
                        fullWidth
                        error={errors.address}
                    >
                        <textarea
                            id="address"
                            rows={2}
                            value={data.address}
                            onChange={(event) =>
                                setData('address', event.target.value)
                            }
                            placeholder="Alamat lengkap tempat tinggal"
                            style={styleFor(!!errors.address, {
                                ...inputStyle,
                                height: undefined,
                                padding: '11px 13px',
                                resize: 'vertical',
                            })}
                        />
                    </Field>
                </div>
            </div>

            {/* Kepegawaian */}
            <div style={card}>
                <SectionHeader
                    icon="briefcase"
                    title="Kepegawaian"
                    desc="Posisi & detail kontrak kerja."
                />
                <div className="avn-2col" style={sectionGrid}>
                    <Field
                        htmlFor="employment_status"
                        label="Status Kepegawaian"
                        required
                        error={errors.employment_status}
                    >
                        <select
                            id="employment_status"
                            value={data.employment_status}
                            onChange={(event) =>
                                setData('employment_status', event.target.value)
                            }
                            style={styleFor(
                                !!errors.employment_status,
                                selectStyle,
                            )}
                        >
                            <option value="">Pilih status</option>
                            {options.employmentStatuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        htmlFor="join_date"
                        label="Tanggal Masuk"
                        error={errors.join_date}
                    >
                        <input
                            id="join_date"
                            type="date"
                            value={data.join_date}
                            onChange={(event) =>
                                setData('join_date', event.target.value)
                            }
                            style={styleFor(!!errors.join_date, {
                                ...inputStyle,
                                color: C.muted,
                            })}
                        />
                    </Field>

                    <Field
                        htmlFor="branch_id"
                        label="Cabang"
                        error={errors.branch_id}
                    >
                        <select
                            id="branch_id"
                            value={data.branch_id}
                            onChange={(event) =>
                                setData('branch_id', event.target.value)
                            }
                            style={styleFor(!!errors.branch_id, selectStyle)}
                        >
                            <option value="">Pilih cabang</option>
                            {options.branches.map((branch) => (
                                <option
                                    key={branch.id}
                                    value={String(branch.id)}
                                >
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        htmlFor="department_id"
                        label="Departemen"
                        error={errors.department_id}
                    >
                        <select
                            id="department_id"
                            value={data.department_id}
                            onChange={(event) =>
                                setData('department_id', event.target.value)
                            }
                            style={styleFor(
                                !!errors.department_id,
                                selectStyle,
                            )}
                        >
                            <option value="">Pilih departemen</option>
                            {options.departments.map((department) => (
                                <option
                                    key={department.id}
                                    value={String(department.id)}
                                >
                                    {department.name}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        htmlFor="position_id"
                        label="Jabatan"
                        error={errors.position_id}
                    >
                        <select
                            id="position_id"
                            value={data.position_id}
                            onChange={(event) =>
                                setData('position_id', event.target.value)
                            }
                            style={styleFor(!!errors.position_id, selectStyle)}
                        >
                            <option value="">Pilih jabatan</option>
                            {options.positions.map((position) => (
                                <option
                                    key={position.id}
                                    value={String(position.id)}
                                >
                                    {position.name}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        htmlFor="job_level_id"
                        label="Jenjang Jabatan"
                        error={errors.job_level_id}
                    >
                        <select
                            id="job_level_id"
                            value={data.job_level_id}
                            onChange={(event) =>
                                setData('job_level_id', event.target.value)
                            }
                            style={styleFor(!!errors.job_level_id, selectStyle)}
                        >
                            <option value="">Pilih jenjang</option>
                            {options.jobLevels.map((level) => (
                                <option key={level.id} value={String(level.id)}>
                                    {level.name}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        htmlFor="manager_id"
                        label="Atasan Langsung"
                        error={errors.manager_id}
                    >
                        <select
                            id="manager_id"
                            value={data.manager_id}
                            onChange={(event) =>
                                setData('manager_id', event.target.value)
                            }
                            style={styleFor(!!errors.manager_id, selectStyle)}
                        >
                            <option value="">Pilih atasan</option>
                            {options.managers.map((manager) => (
                                <option
                                    key={manager.id}
                                    value={String(manager.id)}
                                >
                                    {manager.name} ({manager.employee_number})
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        htmlFor="status"
                        label="Status Karyawan"
                        error={errors.status}
                    >
                        <select
                            id="status"
                            value={data.status}
                            onChange={(event) =>
                                setData('status', event.target.value)
                            }
                            style={styleFor(!!errors.status, selectStyle)}
                        >
                            {options.statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>
            </div>

            {/* Footer */}
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'flex-end',
                    gap: 10,
                    padding: '4px 0 8px',
                    position: 'sticky',
                    bottom: 0,
                }}
            >
                <Link
                    href={cancelHref}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 8,
                        height: 42,
                        padding: '0 18px',
                        background: '#fff',
                        color: C.text,
                        border: `1px solid ${C.border}`,
                        borderRadius: 8,
                        fontSize: 13.5,
                        fontWeight: 500,
                        cursor: 'pointer',
                        textDecoration: 'none',
                        transition: '.15s',
                    }}
                >
                    <AIcon name="x" size={16} />
                    Batal
                </Link>
                <button
                    type="submit"
                    disabled={processing}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 8,
                        height: 42,
                        padding: '0 20px',
                        background: C.primary,
                        color: '#fff',
                        border: 'none',
                        borderRadius: 8,
                        fontSize: 13.5,
                        fontWeight: 600,
                        cursor: processing ? 'not-allowed' : 'pointer',
                        opacity: processing ? 0.7 : 1,
                        transition: '.15s',
                    }}
                >
                    <AIcon name="save" size={16} color="#fff" />
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}

export default EmployeeForm;
