import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    withError,
} from './components';
import { STATUS_OPTIONS } from './types';
import type { PackageOption, TenantFormData } from './types';

interface KlienFormProps {
    form: InertiaFormProps<TenantFormData>;
    packages: PackageOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    /** Create only: the client also needs its first Admin Tenant / HR login. */
    withAdminAccount?: boolean;
}

/** Shared create/edit form for a client tenant. */
export function KlienForm({
    form,
    packages,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
    withAdminAccount = false,
}: KlienFormProps) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} style={{ ...card }}>
            <div style={{ padding: '22px 24px' }}>
                {withAdminAccount && (
                    <div
                        style={{
                            fontSize: 13,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 14,
                        }}
                    >
                        1. Data Klien
                    </div>
                )}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Nama Klien <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            placeholder="PT Nusantara Jaya"
                            style={withError(inputStyle, !!errors.name)}
                        />
                        <FieldError message={errors.name} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Nama Perusahaan</label>
                        <input
                            value={data.company_name}
                            onChange={(event) =>
                                setData('company_name', event.target.value)
                            }
                            placeholder="PT Nusantara Jaya"
                            style={withError(inputStyle, !!errors.company_name)}
                        />
                        <FieldError message={errors.company_name} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Slug</label>
                        <input
                            value={data.slug}
                            onChange={(event) =>
                                setData('slug', event.target.value)
                            }
                            placeholder="otomatis dari nama"
                            style={withError(inputStyle, !!errors.slug)}
                        />
                        <FieldError message={errors.slug} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Paket</label>
                        <select
                            value={data.package_id}
                            onChange={(event) =>
                                setData('package_id', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.package_id)}
                        >
                            <option value="">Tanpa paket</option>
                            {packages.map((pkg) => (
                                <option key={pkg.id} value={String(pkg.id)}>
                                    {pkg.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.package_id} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Status</label>
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
                    <div>
                        <label style={fieldLabelStyle}>Status Tagihan</label>
                        <input
                            value={data.billing_status}
                            onChange={(event) =>
                                setData('billing_status', event.target.value)
                            }
                            placeholder="active / overdue"
                            style={withError(
                                inputStyle,
                                !!errors.billing_status,
                            )}
                        />
                        <FieldError message={errors.billing_status} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Maks. Pengguna</label>
                        <input
                            type="number"
                            min={0}
                            value={data.max_users}
                            onChange={(event) =>
                                setData('max_users', event.target.value)
                            }
                            placeholder="cth. 25"
                            style={withError(inputStyle, !!errors.max_users)}
                        />
                        <FieldError message={errors.max_users} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Maks. Karyawan</label>
                        <input
                            type="number"
                            min={0}
                            value={data.max_employees}
                            onChange={(event) =>
                                setData('max_employees', event.target.value)
                            }
                            placeholder="cth. 100"
                            style={withError(
                                inputStyle,
                                !!errors.max_employees,
                            )}
                        />
                        <FieldError message={errors.max_employees} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Maks. Cabang</label>
                        <input
                            type="number"
                            min={0}
                            value={data.max_branches}
                            onChange={(event) =>
                                setData('max_branches', event.target.value)
                            }
                            placeholder="cth. 5"
                            style={withError(inputStyle, !!errors.max_branches)}
                        />
                        <FieldError message={errors.max_branches} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Mulai Langganan</label>
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
                        <label style={fieldLabelStyle}>Selesai Langganan</label>
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
            </div>

            {withAdminAccount && (
                <div
                    style={{
                        padding: '22px 24px',
                        borderTop: `1px solid ${C.line}`,
                        background: '#F8FAFF',
                    }}
                >
                    <div
                        style={{
                            fontSize: 13,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 4,
                        }}
                    >
                        2. Akun Admin Tenant
                    </div>
                    <p
                        style={{
                            fontSize: 12.5,
                            color: C.muted,
                            margin: '0 0 16px',
                            lineHeight: 1.55,
                        }}
                    >
                        Login pertama untuk klien ini, otomatis dapat role{' '}
                        <strong>Admin Tenant / HR</strong> beserta seluruh menu
                        dan hak aksesnya. Password ditampilkan sekali setelah
                        klien dibuat.
                    </p>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 16,
                        }}
                    >
                        <div>
                            <label style={fieldLabelStyle}>
                                Nama Admin{' '}
                                <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                value={data.admin_name}
                                onChange={(event) =>
                                    setData('admin_name', event.target.value)
                                }
                                placeholder="cth. Rina Anggraeni"
                                style={withError(
                                    inputStyle,
                                    !!errors.admin_name,
                                )}
                            />
                            <FieldError message={errors.admin_name} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>
                                Email Admin{' '}
                                <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="email"
                                value={data.admin_email}
                                onChange={(event) =>
                                    setData('admin_email', event.target.value)
                                }
                                placeholder="admin@klien.co.id"
                                style={withError(
                                    inputStyle,
                                    !!errors.admin_email,
                                )}
                            />
                            <FieldError message={errors.admin_email} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Password</label>
                            <input
                                type="text"
                                value={data.admin_password}
                                onChange={(event) =>
                                    setData(
                                        'admin_password',
                                        event.target.value,
                                    )
                                }
                                placeholder="kosongkan = dibuat otomatis"
                                style={withError(
                                    inputStyle,
                                    !!errors.admin_password,
                                )}
                            />
                            <FieldError message={errors.admin_password} />
                        </div>
                    </div>
                </div>
            )}

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

export default KlienForm;
