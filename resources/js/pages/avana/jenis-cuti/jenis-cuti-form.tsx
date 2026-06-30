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
import type { LeaveTypeFormData } from './types';
import { STATUS_OPTIONS } from './types';

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
                            Kuota Default (hari){' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="number"
                            min={0}
                            value={data.default_quota}
                            onChange={(event) =>
                                setData('default_quota', event.target.value)
                            }
                            placeholder="0"
                            style={withError(inputStyle, !!errors.default_quota)}
                        />
                        <FieldError message={errors.default_quota} />
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

export default JenisCutiForm;
