import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    PlaceholderLegend,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type {
    PlaceholderToken,
    SelectOption,
    TemplateFormData,
} from './types';

interface SuratFormProps {
    form: InertiaFormProps<TemplateFormData>;
    types: SelectOption[];
    placeholders: PlaceholderToken[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a letter template. */
export function SuratForm({
    form,
    types,
    placeholders,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: SuratFormProps) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} style={{ ...card, maxWidth: 720 }}>
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
                            Nama Template{' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            placeholder="Contoh: Surat Keterangan Kerja"
                            style={withError(inputStyle, !!errors.name)}
                        />
                        <FieldError message={errors.name} />
                    </div>

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
                            {types.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.type} />
                    </div>
                </div>

                <PlaceholderLegend placeholders={placeholders} />

                <div>
                    <label style={fieldLabelStyle}>
                        Isi Surat <span style={{ color: C.red }}>*</span>
                    </label>
                    <textarea
                        value={data.body}
                        onChange={(event) => setData('body', event.target.value)}
                        placeholder={
                            'Tulis isi surat di sini. Gunakan placeholder seperti {{nama}}, {{jabatan}}, dan {{perusahaan}} yang akan diganti otomatis saat surat dibuat.'
                        }
                        style={withError(
                            { ...textareaStyle, minHeight: 280 },
                            !!errors.body,
                        )}
                    />
                    <FieldError message={errors.body} />
                </div>

                <label
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        cursor: 'pointer',
                    }}
                >
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(event) =>
                            setData('is_active', event.target.checked)
                        }
                        style={{
                            width: 17,
                            height: 17,
                            accentColor: C.primary,
                            cursor: 'pointer',
                        }}
                    />
                    <span style={{ fontSize: 13.5, color: C.text }}>
                        Template aktif (tersedia untuk membuat surat)
                    </span>
                </label>
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

export default SuratForm;
