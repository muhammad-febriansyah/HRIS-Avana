import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    ToggleField,
    withError,
} from './components';
import type { LeaveSubTypeFormData, LeaveTypeFormData } from './types';
import { emptySubType, INHERIT_OPTIONS, STATUS_OPTIONS } from './types';

interface JenisCutiFormProps {
    form: InertiaFormProps<LeaveTypeFormData>;
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a leave type definition. */
export function JenisCutiForm({
    form,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: JenisCutiFormProps) {
    const { data, setData, errors, processing } = form;

    // Branching is implied by having sub-types, so the toggle seeds the first
    // empty row on the way in and clears the list on the way out.
    const branched = data.children.length > 0;

    const toggleBranching = (value: boolean) => {
        setData('children', value ? [{ ...emptySubType }] : []);
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
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Kode <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={data.code}
                            onChange={(event) =>
                                setData('code', event.target.value)
                            }
                            placeholder="TAHUNAN"
                            style={withError(inputStyle, !!errors.code)}
                        />
                        <FieldError message={errors.code} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Nama <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            placeholder="Cuti Tahunan"
                            style={withError(inputStyle, !!errors.name)}
                        />
                        <FieldError message={errors.name} />
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

                    <div>
                        <label style={fieldLabelStyle}>
                            Jatah Cuti / Tahun{' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="number"
                            min={0}
                            max={365}
                            value={data.default_quota}
                            onChange={(event) =>
                                setData('default_quota', event.target.value)
                            }
                            placeholder="12"
                            style={withError(
                                inputStyle,
                                !!errors.default_quota,
                            )}
                        />
                        <FieldError message={errors.default_quota} />
                        <div
                            style={{
                                fontSize: 12,
                                color: C.muted,
                                marginTop: 6,
                            }}
                        >
                            Jumlah hari per tahun. Dipakai sebagai saldo awal
                            karyawan yang belum punya saldo khusus.
                        </div>
                    </div>
                </div>

                <ToggleField
                    label="Saldo Minus"
                    description="Izinkan saldo cuti menjadi minus."
                    checked={data.allow_negative}
                    onChange={(value) => setData('allow_negative', value)}
                />

                <ToggleField
                    label="Wajib Lampiran"
                    description="Wajibkan unggah dokumen pendukung."
                    checked={data.requires_attachment}
                    onChange={(value) => setData('requires_attachment', value)}
                />

                <ToggleField
                    label="Bercabang (Sub-Jenis)"
                    description={`Pecah jenis cuti ini menjadi beberapa sub-jenis yang berbagi jatah ${data.default_quota || 0} hari.`}
                    checked={branched}
                    onChange={toggleBranching}
                />

                {branched && (
                    <SubTypeRepeater
                        rows={data.children}
                        parentQuota={data.default_quota}
                        errors={errors as Record<string, string | undefined>}
                        onChange={(rows) => setData('children', rows)}
                    />
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

/**
 * Editable list of sub-types under one parent. Each row carries its own code,
 * name, optional cap against the parent quota, and tri-state overrides for the
 * two toggles it would otherwise inherit.
 */
function SubTypeRepeater({
    rows,
    parentQuota,
    errors,
    onChange,
}: {
    rows: LeaveSubTypeFormData[];
    parentQuota: string;
    errors: Record<string, string | undefined>;
    onChange: (rows: LeaveSubTypeFormData[]) => void;
}) {
    const patch = (index: number, changes: Partial<LeaveSubTypeFormData>) => {
        onChange(
            rows.map((row, i) => (i === index ? { ...row, ...changes } : row)),
        );
    };

    const capped = rows.reduce(
        (total, row) => total + (Number(row.sub_limit) || 0),
        0,
    );
    const quota = Number(parentQuota) || 0;

    return (
        <div
            style={{
                border: `1px solid ${C.border}`,
                borderRadius: 10,
                background: C.surface,
                padding: 14,
                display: 'flex',
                flexDirection: 'column',
                gap: 12,
            }}
        >
            {rows.map((row, index) => (
                <div
                    key={row.id ?? `new-${index}`}
                    style={{
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 9,
                        padding: 14,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 12,
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                        }}
                    >
                        <span
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 6,
                                fontSize: 12,
                                fontWeight: 700,
                                color: C.muted,
                                letterSpacing: '.04em',
                            }}
                        >
                            <AIcon
                                name="corner-down-right"
                                size={14}
                                color={C.muted}
                            />
                            SUB-JENIS {index + 1}
                        </span>
                        <button
                            type="button"
                            onClick={() =>
                                onChange(rows.filter((_, i) => i !== index))
                            }
                            title="Hapus sub-jenis"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 5,
                                height: 30,
                                padding: '0 10px',
                                borderRadius: 7,
                                border: '1px solid rgba(220,38,38,.35)',
                                background: 'rgba(220,38,38,.07)',
                                color: C.red,
                                fontSize: 12,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
                            <AIcon name="trash-2" size={13} color={C.red} />
                            Hapus
                        </button>
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr 150px',
                            gap: 12,
                        }}
                    >
                        <div>
                            <label style={fieldLabelStyle}>
                                Kode <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="text"
                                value={row.code}
                                onChange={(event) =>
                                    patch(index, { code: event.target.value })
                                }
                                placeholder="CUTI-BERSAMA"
                                style={withError(
                                    inputStyle,
                                    !!errors[`children.${index}.code`],
                                )}
                            />
                            <FieldError
                                message={errors[`children.${index}.code`]}
                            />
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Nama <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="text"
                                value={row.name}
                                onChange={(event) =>
                                    patch(index, { name: event.target.value })
                                }
                                placeholder="Cuti Bersama"
                                style={withError(
                                    inputStyle,
                                    !!errors[`children.${index}.name`],
                                )}
                            />
                            <FieldError
                                message={errors[`children.${index}.name`]}
                            />
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>Batas / Tahun</label>
                            <input
                                type="number"
                                min={1}
                                max={quota || undefined}
                                value={row.sub_limit}
                                onChange={(event) =>
                                    patch(index, {
                                        sub_limit: event.target.value,
                                    })
                                }
                                placeholder="Bebas"
                                style={withError(
                                    inputStyle,
                                    !!errors[`children.${index}.sub_limit`],
                                )}
                            />
                            <FieldError
                                message={errors[`children.${index}.sub_limit`]}
                            />
                        </div>
                    </div>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr 1fr',
                            gap: 12,
                        }}
                    >
                        <div>
                            <label style={fieldLabelStyle}>Status</label>
                            <select
                                value={row.status}
                                onChange={(event) =>
                                    patch(index, { status: event.target.value })
                                }
                                style={selectStyle}
                            >
                                {STATUS_OPTIONS.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>Saldo Minus</label>
                            <select
                                value={row.allow_negative}
                                onChange={(event) =>
                                    patch(index, {
                                        allow_negative: event.target
                                            .value as LeaveSubTypeFormData['allow_negative'],
                                    })
                                }
                                style={selectStyle}
                            >
                                {INHERIT_OPTIONS.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Wajib Lampiran
                            </label>
                            <select
                                value={row.requires_attachment}
                                onChange={(event) =>
                                    patch(index, {
                                        requires_attachment: event.target
                                            .value as LeaveSubTypeFormData['requires_attachment'],
                                    })
                                }
                                style={selectStyle}
                            >
                                {INHERIT_OPTIONS.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>
            ))}

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                }}
            >
                <div style={{ fontSize: 12, color: C.muted }}>
                    Semua sub-jenis menarik dari jatah induk ({quota} hari).
                    Kosongkan <strong>Batas / Tahun</strong> agar sub-jenis
                    bebas memakai sisa jatah.
                    {capped > quota && quota > 0 && (
                        <div style={{ color: C.amber, marginTop: 4 }}>
                            Total batas sub-jenis ({capped} hari) melebihi jatah
                            induk — tidak semuanya akan terpakai.
                        </div>
                    )}
                </div>
                <button
                    type="button"
                    onClick={() => onChange([...rows, { ...emptySubType }])}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 7,
                        height: 36,
                        padding: '0 13px',
                        borderRadius: 8,
                        border: 'none',
                        background: C.sky,
                        color: '#fff',
                        fontSize: 13,
                        fontWeight: 600,
                        cursor: 'pointer',
                        whiteSpace: 'nowrap',
                    }}
                >
                    <AIcon name="plus" size={15} color="#fff" />
                    Tambah Sub-Jenis
                </button>
            </div>
        </div>
    );
}

export default JenisCutiForm;
