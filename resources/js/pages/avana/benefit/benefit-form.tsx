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
import type { BenefitFormData } from './types';
import { TYPE_OPTIONS } from './types';

interface BenefitFormProps {
    form: InertiaFormProps<BenefitFormData>;
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a benefit definition. */
export function BenefitForm({
    form,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: BenefitFormProps) {
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
                            placeholder="BNF-001"
                            style={withError(inputStyle, !!errors.code)}
                        />
                        <FieldError message={errors.code} />
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
                </div>

                <div>
                    <label style={fieldLabelStyle}>
                        Nama <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        placeholder="Nama benefit"
                        style={withError(inputStyle, !!errors.name)}
                    />
                    <FieldError message={errors.name} />
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
                            Jenis <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.type}
                            onChange={(event) =>
                                setData('type', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.type)}
                        >
                            {TYPE_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.type} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Nilai (Rp) <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="number"
                            min={0}
                            value={data.value}
                            onChange={(event) =>
                                setData('value', event.target.value)
                            }
                            placeholder="0"
                            style={withError(inputStyle, !!errors.value)}
                        />
                        <FieldError message={errors.value} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>Deskripsi</label>
                    <textarea
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Keterangan benefit (opsional)"
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

export default BenefitForm;
