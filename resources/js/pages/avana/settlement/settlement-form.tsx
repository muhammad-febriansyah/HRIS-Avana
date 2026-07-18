import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { SearchableSelect } from '@/components/searchable-select';
import { AIcon, btnOut, btnP, C, card, rp, RupiahInput } from '@/lib/avana';
import {
    dateInputStyle,
    Field,
    fieldLabelStyle,
    FieldError,
    selectStyle,
    textareaStyle,
    textInputStyle,
    withError,
} from './components';
import type {
    EmployeeOption,
    SelectOption,
    SettlementAttachmentRow,
    SettlementFormData,
    SettlementLineInput,
} from './types';

interface SettlementFormProps {
    form: InertiaFormProps<SettlementFormData>;
    employees: EmployeeOption[];
    categories: SelectOption[];
    cancelHref: string;
    existingAttachments?: SettlementAttachmentRow[];
    submit: (action: 'draft' | 'submit') => void;
}

const TAX_RATE = 0.11;

const cardHeaderStyle = {
    display: 'flex',
    alignItems: 'center',
    gap: 9,
    fontSize: 15,
    fontWeight: 600,
    color: C.navy,
    padding: '16px 20px',
    borderBottom: `1px solid ${C.line}`,
} as const;

/**
 * Shared create/edit form for a settlement (expense claim): general info,
 * inline expense line items, receipt attachments, and a live summary panel
 * that applies the 11% tax.
 */
export function SettlementForm({
    form,
    employees,
    categories,
    cancelHref,
    existingAttachments,
    submit,
}: SettlementFormProps) {
    const { data, setData, errors, processing } = form;

    const subtotal = data.items.reduce(
        (sum, item) => sum + (Number(item.amount) || 0),
        0,
    );
    const tax = Math.round(subtotal * TAX_RATE);
    const total = subtotal + tax;

    const setItem = (
        index: number,
        patch: Partial<SettlementLineInput>,
    ): void => {
        setData(
            'items',
            data.items.map((item, i) =>
                i === index ? { ...item, ...patch } : item,
            ),
        );
    };

    const addItem = (): void => {
        setData('items', [
            ...data.items,
            { description: '', category: 'transportasi', amount: '' },
        ]);
    };

    const removeItem = (index: number): void => {
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );
    };

    const onFilesChange = (event: ChangeEvent<HTMLInputElement>): void => {
        setData('attachments', Array.from(event.target.files ?? []));
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit('submit');
            }}
            style={{
                display: 'grid',
                gridTemplateColumns: 'minmax(0, 1fr) 330px',
                gap: 20,
                alignItems: 'start',
            }}
        >
            <div
                style={{ display: 'flex', flexDirection: 'column', gap: 20 }}
            >
                {/* ---------- General Information ---------- */}
                <div style={card}>
                    <div style={cardHeaderStyle}>
                        <AIcon name="info" size={17} color={C.primary} />
                        Informasi Umum
                    </div>
                    <div
                        style={{
                            padding: '20px',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                        }}
                    >
                        <Field
                            label="Karyawan"
                            required
                            error={errors.employee_id}
                        >
                            <SearchableSelect
                                value={data.employee_id}
                                onChange={(value) =>
                                    setData('employee_id', value)
                                }
                                options={employees.map((employee) => ({
                                    value: String(employee.id),
                                    label: employee.employee_number
                                        ? `${employee.name} (${employee.employee_number})`
                                        : employee.name,
                                }))}
                                placeholder="Pilih karyawan"
                                searchPlaceholder="Cari nama karyawan…"
                                allowClear
                                style={withError(
                                    selectStyle,
                                    !!errors.employee_id,
                                )}
                            />
                        </Field>

                        <Field
                            label="Judul / Keperluan"
                            required
                            error={errors.title}
                        >
                            <input
                                type="text"
                                placeholder="Contoh: Perjalanan Dinas ke Jakarta"
                                value={data.title}
                                onChange={(event) =>
                                    setData('title', event.target.value)
                                }
                                style={withError(
                                    textInputStyle,
                                    !!errors.title,
                                )}
                            />
                        </Field>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 16,
                            }}
                        >
                            <Field label="Kategori" error={errors.category}>
                                <select
                                    value={data.category}
                                    onChange={(event) =>
                                        setData('category', event.target.value)
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!errors.category,
                                    )}
                                >
                                    <option value="">Pilih kategori</option>
                                    {categories.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field
                                label="Tanggal Pengajuan"
                                required
                                error={errors.submission_date}
                            >
                                <input
                                    type="date"
                                    value={data.submission_date}
                                    onChange={(event) =>
                                        setData(
                                            'submission_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        dateInputStyle,
                                        !!errors.submission_date,
                                    )}
                                />
                            </Field>
                        </div>

                        <Field label="Departemen" error={errors.department}>
                            <input
                                type="text"
                                placeholder="Contoh: Regional Sales"
                                value={data.department}
                                onChange={(event) =>
                                    setData('department', event.target.value)
                                }
                                style={withError(
                                    textInputStyle,
                                    !!errors.department,
                                )}
                            />
                        </Field>
                    </div>
                </div>

                {/* ---------- Expense Items ---------- */}
                <div style={card}>
                    <div
                        style={{
                            ...cardHeaderStyle,
                            justifyContent: 'space-between',
                        }}
                    >
                        <span
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 9,
                            }}
                        >
                            <AIcon
                                name="receipt-text"
                                size={17}
                                color={C.primary}
                            />
                            Rincian Pengeluaran
                        </span>
                        <button
                            type="button"
                            onClick={addItem}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 6,
                                background: 'none',
                                border: 'none',
                                color: C.primary,
                                fontSize: 12.5,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
                            <AIcon name="plus" size={14} color={C.primary} />
                            Tambah Baris
                        </button>
                    </div>
                    <div style={{ padding: '8px 20px 20px' }}>
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    '1fr 150px 150px 34px',
                                gap: 10,
                                padding: '10px 0',
                                fontSize: 11,
                                fontWeight: 600,
                                letterSpacing: '.04em',
                                color: C.faint,
                                textTransform: 'uppercase',
                            }}
                        >
                            <span>Keterangan</span>
                            <span>Kategori</span>
                            <span>Jumlah (Rp)</span>
                            <span />
                        </div>
                        {data.items.map((item, index) => (
                            <div
                                // eslint-disable-next-line react/no-array-index-key
                                key={index}
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        '1fr 150px 150px 34px',
                                    gap: 10,
                                    alignItems: 'center',
                                    paddingBottom: 10,
                                }}
                            >
                                <input
                                    type="text"
                                    placeholder="Tiket pesawat…"
                                    value={item.description}
                                    onChange={(event) =>
                                        setItem(index, {
                                            description: event.target.value,
                                        })
                                    }
                                    style={textInputStyle}
                                />
                                <select
                                    value={item.category}
                                    onChange={(event) =>
                                        setItem(index, {
                                            category: event.target.value,
                                        })
                                    }
                                    style={selectStyle}
                                >
                                    {categories.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <RupiahInput
                                    value={String(item.amount ?? '')}
                                    onChange={(raw) =>
                                        setItem(index, { amount: raw })
                                    }
                                />
                                <button
                                    type="button"
                                    onClick={() => removeItem(index)}
                                    disabled={data.items.length === 1}
                                    title="Hapus baris"
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        height: 34,
                                        width: 34,
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        background: '#fff',
                                        cursor:
                                            data.items.length === 1
                                                ? 'not-allowed'
                                                : 'pointer',
                                        opacity:
                                            data.items.length === 1 ? 0.4 : 1,
                                    }}
                                >
                                    <AIcon
                                        name="trash-2"
                                        size={15}
                                        color={C.red}
                                    />
                                </button>
                            </div>
                        ))}
                        <FieldError message={errors.items} />
                    </div>
                </div>

                {/* ---------- Attachments ---------- */}
                <div style={card}>
                    <div style={cardHeaderStyle}>
                        <AIcon name="paperclip" size={17} color={C.primary} />
                        Lampiran
                    </div>
                    <div style={{ padding: '20px' }}>
                        <label style={fieldLabelStyle}>
                            Bukti / Kuitansi
                        </label>
                        <input
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,application/pdf"
                            onChange={onFilesChange}
                            style={{
                                width: '100%',
                                fontSize: 12.5,
                                color: C.muted,
                            }}
                        />
                        <div
                            style={{
                                fontSize: 11,
                                color: C.faint,
                                marginTop: 6,
                            }}
                        >
                            PDF / JPG / PNG · maks 5 MB per berkas
                        </div>
                        {existingAttachments &&
                            existingAttachments.length > 0 && (
                                <div
                                    style={{
                                        marginTop: 12,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 6,
                                    }}
                                >
                                    {existingAttachments.map((attachment) => (
                                        <a
                                            key={attachment.id}
                                            href={attachment.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 6,
                                                fontSize: 12.5,
                                                color: C.primary,
                                                textDecoration: 'none',
                                            }}
                                        >
                                            <AIcon
                                                name="file-text"
                                                size={14}
                                                color={C.primary}
                                            />
                                            {attachment.name}
                                        </a>
                                    ))}
                                    <span
                                        style={{
                                            fontSize: 11,
                                            color: C.faint,
                                        }}
                                    >
                                        Unggah berkas baru untuk menambah
                                        lampiran.
                                    </span>
                                </div>
                            )}
                        <FieldError message={errors.attachments} />
                    </div>
                </div>
            </div>

            {/* ---------- Summary sidebar ---------- */}
            <div
                style={{
                    ...card,
                    position: 'sticky',
                    top: 20,
                }}
            >
                <div style={cardHeaderStyle}>
                    <AIcon name="chart-column" size={17} color={C.primary} />
                    Ringkasan Settlement
                </div>
                <div
                    style={{
                        padding: '20px',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                    }}
                >
                    <SummaryLine label="Subtotal" value={subtotal} />
                    <SummaryLine label="Pajak (11%)" value={tax} />
                    <div
                        style={{
                            borderTop: `1px solid ${C.line}`,
                            paddingTop: 14,
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                        }}
                    >
                        <span
                            style={{
                                fontSize: 15,
                                fontWeight: 700,
                                color: C.navy,
                            }}
                        >
                            Total
                        </span>
                        <span
                            style={{
                                fontSize: 17,
                                fontWeight: 700,
                                color: C.primary,
                            }}
                        >
                            {rp(total)}
                        </span>
                    </div>

                    <div
                        style={{
                            background: '#ECFDF5',
                            borderLeft: '3px solid #10B981',
                            borderRadius: 6,
                            padding: '11px 13px',
                            fontSize: 12,
                            color: '#047857',
                            lineHeight: 1.5,
                        }}
                    >
                        <strong>Siap diproses.</strong> Setelah diajukan,
                        settlement diperiksa manager lalu diverifikasi &amp;
                        dibayar Finance.
                    </div>

                    <button
                        type="button"
                        onClick={() => submit('submit')}
                        disabled={processing}
                        style={{
                            ...btnP,
                            height: 46,
                            justifyContent: 'center',
                            opacity: processing ? 0.7 : 1,
                            cursor: processing ? 'not-allowed' : 'pointer',
                        }}
                    >
                        <AIcon name="send" size={16} color="#fff" />
                        Ajukan untuk Persetujuan
                    </button>
                    <button
                        type="button"
                        onClick={() => submit('draft')}
                        disabled={processing}
                        style={{
                            ...btnOut,
                            height: 42,
                            justifyContent: 'center',
                            cursor: processing ? 'not-allowed' : 'pointer',
                        }}
                    >
                        Simpan sebagai Draft
                    </button>
                    <Link
                        href={cancelHref}
                        style={{
                            textAlign: 'center',
                            fontSize: 12.5,
                            color: C.faint,
                            textDecoration: 'none',
                        }}
                    >
                        Batal
                    </Link>
                </div>
            </div>
        </form>
    );
}

/** One `label — Rp value` line in the summary panel. */
function SummaryLine({ label, value }: { label: string; value: number }) {
    return (
        <div
            style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                fontSize: 13,
            }}
        >
            <span style={{ color: C.muted }}>{label}</span>
            <span style={{ color: C.text, fontWeight: 600 }}>{rp(value)}</span>
        </div>
    );
}

export default SettlementForm;
