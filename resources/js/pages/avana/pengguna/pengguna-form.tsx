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
import { scopeOptions } from './types';
import type { BranchOption, RoleOption, UserFormData } from './types';

interface PenggunaFormProps {
    form: InertiaFormProps<UserFormData>;
    roles: RoleOption[];
    branches: BranchOption[];
    isEdit: boolean;
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a tenant user, including role & scope assignment. */
export function PenggunaForm({
    form,
    roles,
    branches,
    isEdit,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: PenggunaFormProps) {
    const { data, setData, errors, processing } = form;

    const toggleRole = (id: number) => {
        const next = data.role_ids.includes(id)
            ? data.role_ids.filter((roleId) => roleId !== id)
            : [...data.role_ids, id];

        setData('role_ids', next);
    };

    const toggleBranch = (id: number) => {
        const next = data.branch_ids.includes(id)
            ? data.branch_ids.filter((branchId) => branchId !== id)
            : [...data.branch_ids, id];

        setData('branch_ids', next);
    };

    return (
        <form onSubmit={onSubmit} style={{ ...card, maxWidth: 560 }}>
            <div
                style={{
                    padding: '22px 24px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 14,
                }}
            >
                <div>
                    <label style={fieldLabelStyle}>
                        Nama <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        placeholder="Nama lengkap"
                        style={withError(inputStyle, !!errors.name)}
                    />
                    <FieldError message={errors.name} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Email <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        placeholder="email@perusahaan.com"
                        style={withError(inputStyle, !!errors.email)}
                    />
                    <FieldError message={errors.email} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Telepon</label>
                    <input
                        type="text"
                        value={data.phone}
                        onChange={(event) =>
                            setData('phone', event.target.value)
                        }
                        placeholder="08xxxxxxxxxx"
                        style={withError(inputStyle, !!errors.phone)}
                    />
                    <FieldError message={errors.phone} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Kata Sandi{' '}
                        {!isEdit && <span style={{ color: C.red }}>*</span>}
                    </label>
                    <input
                        type="password"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        placeholder={
                            isEdit
                                ? 'Kosongkan jika tidak diubah'
                                : 'Minimal 8 karakter'
                        }
                        style={withError(inputStyle, !!errors.password)}
                    />
                    <FieldError message={errors.password} />
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
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                    <FieldError message={errors.status} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Peran</label>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        {roles.map((role) => {
                            const checked = data.role_ids.includes(role.id);

                            return (
                                <label
                                    key={role.id}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        padding: '10px 12px',
                                        border: `1px solid ${
                                            checked ? C.primary : C.border
                                        }`,
                                        borderRadius: 9,
                                        background: checked
                                            ? 'rgba(47,84,201,.05)'
                                            : '#fff',
                                        cursor: 'pointer',
                                        transition: '.12s',
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={() => toggleRole(role.id)}
                                        style={{
                                            width: 16,
                                            height: 16,
                                            accentColor: C.primary,
                                        }}
                                    />
                                    <div>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                fontWeight: 500,
                                                color: C.text,
                                            }}
                                        >
                                            {role.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: C.faint,
                                            }}
                                        >
                                            {role.code}
                                        </div>
                                    </div>
                                </label>
                            );
                        })}
                    </div>
                    <FieldError message={errors.role_ids} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Scope Data</label>
                    <select
                        value={data.data_scope}
                        onChange={(event) =>
                            setData('data_scope', event.target.value)
                        }
                        style={withError(selectStyle, !!errors.data_scope)}
                    >
                        {scopeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <FieldError message={errors.data_scope} />
                </div>

                {data.data_scope === 'branch' && (
                    <div>
                        <label style={fieldLabelStyle}>Akses Cabang</label>
                        {branches.length === 0 ? (
                            <div style={{ fontSize: 12.5, color: C.faint }}>
                                Belum ada cabang.
                            </div>
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 8,
                                }}
                            >
                                {branches.map((branch) => {
                                    const checked = data.branch_ids.includes(
                                        branch.id,
                                    );

                                    return (
                                        <label
                                            key={branch.id}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                                padding: '10px 12px',
                                                border: `1px solid ${
                                                    checked
                                                        ? C.primary
                                                        : C.border
                                                }`,
                                                borderRadius: 9,
                                                background: checked
                                                    ? 'rgba(47,84,201,.05)'
                                                    : '#fff',
                                                cursor: 'pointer',
                                                transition: '.12s',
                                            }}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={() =>
                                                    toggleBranch(branch.id)
                                                }
                                                style={{
                                                    width: 16,
                                                    height: 16,
                                                    accentColor: C.primary,
                                                }}
                                            />
                                            <div>
                                                <div
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 500,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {branch.name}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {branch.code}
                                                </div>
                                            </div>
                                        </label>
                                    );
                                })}
                            </div>
                        )}
                        <FieldError message={errors.branch_ids} />
                    </div>
                )}
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

export default PenggunaForm;
