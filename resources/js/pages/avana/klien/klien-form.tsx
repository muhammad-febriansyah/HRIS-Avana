import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
}

/** Shared create/edit form for a client tenant. */
export function KlienForm({
    form,
    packages,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: KlienFormProps) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} style={{ ...card, maxWidth: 620 }}>
            <div style={{ padding: '22px 24px' }}>
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
                            style={withError(inputStyle, !!errors.max_employees)}
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
                            style={withError(inputStyle, !!errors.max_branches)}
                        />
                        <FieldError message={errors.max_branches} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Mulai Langganan</label>
                        <input
                            type="date"
                            value={data.start_date}
                            onChange={(event) =>
                                setData('start_date', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.start_date)}
                        />
                        <FieldError message={errors.start_date} />
                    </div>
                    <div>
                        <label style={fieldLabelStyle}>Selesai Langganan</label>
                        <input
                            type="date"
                            value={data.end_date}
                            onChange={(event) =>
                                setData('end_date', event.target.value)
                            }
                            style={withError(inputStyle, !!errors.end_date)}
                        />
                        <FieldError message={errors.end_date} />
                    </div>
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

export default KlienForm;
