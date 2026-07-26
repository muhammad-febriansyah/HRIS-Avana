import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import {
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import type { TrainingFormData } from './types';
import { STATUS_OPTIONS, TYPE_OPTIONS } from './types';

interface PembelajaranFormProps {
    form: InertiaFormProps<TrainingFormData>;
    submitLabel: string;
    submitIcon: string;
    cancelHref: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

/** Shared create/edit form for a training. */
export function PembelajaranForm({
    form,
    submitLabel,
    submitIcon,
    cancelHref,
    onSubmit,
}: PembelajaranFormProps) {
    const { data, setData, errors, processing } = form;

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
                <div>
                    <label style={fieldLabelStyle}>
                        Judul Pelatihan <span style={{ color: C.red }}>*</span>
                    </label>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        placeholder="Contoh: Pelatihan K3 Dasar"
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
                        <label style={fieldLabelStyle}>
                            Kategori <span style={{ color: C.red }}>*</span>
                        </label>
                        <input
                            type="text"
                            value={data.category}
                            onChange={(event) =>
                                setData('category', event.target.value)
                            }
                            placeholder="Contoh: Teknis"
                            style={withError(inputStyle, !!errors.category)}
                        />
                        <FieldError message={errors.category} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>
                            Tipe <span style={{ color: C.red }}>*</span>
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
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>Tanggal Mulai</label>
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
                        <label style={fieldLabelStyle}>Tanggal Selesai</label>
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

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr 1fr',
                        gap: 16,
                    }}
                >
                    <div>
                        <label style={fieldLabelStyle}>
                            Biaya (Rp) <span style={{ color: C.red }}>*</span>
                        </label>
                        <RupiahInput
                            value={data.cost}
                            onChange={(raw) => setData('cost', raw)}
                            invalid={!!errors.cost}
                        />
                        <FieldError message={errors.cost} />
                    </div>

                    <div>
                        <label style={fieldLabelStyle}>Kuota</label>
                        <input
                            type="number"
                            min={1}
                            value={data.quota}
                            onChange={(event) =>
                                setData('quota', event.target.value)
                            }
                            placeholder="Opsional"
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

                <div>
                    <label style={fieldLabelStyle}>Instruktur</label>
                    <input
                        type="text"
                        value={data.instructor}
                        onChange={(event) =>
                            setData('instructor', event.target.value)
                        }
                        placeholder="Nama instruktur (opsional)"
                        style={withError(inputStyle, !!errors.instructor)}
                    />
                    <FieldError message={errors.instructor} />
                </div>

                <div>
                    <label style={fieldLabelStyle}>Deskripsi</label>
                    <textarea
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Deskripsi materi & tujuan pelatihan (opsional)"
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
                    <AIcon name="x" size={16} />
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

export default PembelajaranForm;
