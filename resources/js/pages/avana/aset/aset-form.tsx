import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type { AssetFormData, SelectOption } from './types';

interface AsetFormProps {
    form: InertiaFormProps<AssetFormData>;
    categories: string[];
    conditions: SelectOption[];
    statuses: SelectOption[];
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for an asset. */
export function AsetForm({
    form,
    categories,
    conditions,
    statuses,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: AsetFormProps) {
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
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 2fr',
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
                            placeholder="AST-1001"
                            style={withError(inputStyle, !!errors.code)}
                        />
                        <FieldError message={errors.code} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Nama Aset <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            placeholder="Contoh: Laptop Dell Latitude"
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
                            Kategori <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.category}
                            onChange={(event) =>
                                setData('category', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.category)}
                        >
                            {categories.map((category) => (
                                <option key={category} value={category}>
                                    {category}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.category} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Tanggal Pembelian</label>
                        <input
                            type="date"
                            value={data.purchase_date}
                            onChange={(event) =>
                                setData('purchase_date', event.target.value)
                            }
                            style={withError(
                                inputStyle,
                                !!errors.purchase_date,
                            )}
                        />
                        <FieldError message={errors.purchase_date} />
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
                            Harga Beli (Rp){' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <RupiahInput
                            value={data.purchase_cost}
                            onChange={(raw) => setData('purchase_cost', raw)}
                            invalid={!!errors.purchase_cost}
                        />
                        <FieldError message={errors.purchase_cost} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Masa Penyusutan (tahun){' '}
                            <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="number"
                            min={1}
                            value={data.depreciation_years}
                            onChange={(event) =>
                                setData(
                                    'depreciation_years',
                                    event.target.value,
                                )
                            }
                            placeholder="4"
                            style={withError(
                                inputStyle,
                                !!errors.depreciation_years,
                            )}
                        />
                        <FieldError message={errors.depreciation_years} />
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
                            Kondisi <span style={{ color: C.red }}>*</span>
                        </label>
                        <select
                            value={data.condition}
                            onChange={(event) =>
                                setData('condition', event.target.value)
                            }
                            style={withError(selectStyle, !!errors.condition)}
                        >
                            {conditions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.condition} />
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
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.status} />
                    </div>
                </div>

                <div>
                    <label style={fieldLabelStyle}>Catatan</label>
                    <textarea
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        placeholder="Catatan tambahan (opsional)"
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

export default AsetForm;
