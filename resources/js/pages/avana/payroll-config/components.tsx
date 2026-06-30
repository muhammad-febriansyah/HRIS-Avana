import { useForm } from '@inertiajs/react';
import type {
    CSSProperties,
    Dispatch,
    FormEvent,
    ReactNode,
    SetStateAction,
} from 'react';
import PayrollConfigController from '@/actions/App/Http/Controllers/Avana/PayrollConfigController';
import { AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import {
    asPercent,
    asRupiah,
    buildInitialForm,
    type ColumnDef,
    type FlatRecord,
    type SectionDef,
} from './types';

/* ---------- shared field styles (mirror perusahaan.tsx) ---------- */

export const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

export const inputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
};

export const selectStyle: CSSProperties = {
    ...inputStyle,
    color: C.text,
    cursor: 'pointer',
};

export const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
    resize: 'vertical',
    minHeight: 72,
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

const hintTextStyle: CSSProperties = {
    fontSize: 11.5,
    color: C.faint,
    marginTop: 5,
};

export const rowMenuItemStyle: CSSProperties = {
    width: '100%',
    display: 'flex',
    alignItems: 'center',
    gap: 9,
    padding: '8px 10px',
    border: 'none',
    background: 'none',
    borderRadius: 7,
    fontSize: 13,
    color: C.text,
    cursor: 'pointer',
    transition: '.12s',
};

export const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    color: C.muted,
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
};

/** Apply the red error border to a base style when invalid. */
export function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

/** Inline error message rendered under a field. */
export function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div style={errorTextStyle}>
            <AIcon name="circle-alert" size={13} color={C.red} />
            {message}
        </div>
    );
}

/** Active/inactive status pill. */
export function StatusPill({ active }: { active: boolean }) {
    const color = active ? C.green : C.muted;
    const bg = active ? 'rgba(22,163,74,.1)' : 'rgba(107,114,128,.12)';

    return (
        <span
            style={{
                display: 'inline-block',
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color,
                background: bg,
            }}
        >
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

/** Render a single table cell based on its column descriptor. */
export function renderCell(row: FlatRecord, column: ColumnDef): ReactNode {
    const raw = row[column.key];

    if (column.kind === 'active') {
        return <StatusPill active={raw === true || raw === '1' || raw === 1} />;
    }

    if (column.kind === 'percent') {
        return asPercent(raw);
    }

    if (column.kind === 'rupiah') {
        return asRupiah(raw);
    }

    if (column.kind === 'rupiah-range') {
        const max = column.key2 ? row[column.key2] : null;

        return (
            <span>
                {asRupiah(raw)}
                <span style={{ color: C.faint }}> – </span>
                {max === null || max === '' ? (
                    <span style={{ color: C.faint }}>ke atas</span>
                ) : (
                    asRupiah(max)
                )}
            </span>
        );
    }

    if (raw === null || raw === undefined || raw === '') {
        return <span style={{ color: C.faint }}>—</span>;
    }

    return String(raw);
}

/* ============================================================
 * Section card + table — shared by both tabs.
 * ============================================================ */

interface SectionTableProps {
    section: SectionDef;
    rows: FlatRecord[];
    openMenu: number | null;
    setOpenMenu: Dispatch<SetStateAction<number | null>>;
    onCreate: () => void;
    onEdit: (row: FlatRecord) => void;
    onDelete: (row: FlatRecord) => void;
}

export function SectionTable({
    section,
    rows,
    openMenu,
    setOpenMenu,
    onCreate,
    onEdit,
    onDelete,
}: SectionTableProps) {
    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div
                style={{
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.border}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                <div>
                    <div
                        style={{
                            fontSize: 15,
                            fontWeight: 600,
                            color: C.navy,
                        }}
                    >
                        {section.title}
                    </div>
                    <div
                        style={{
                            fontSize: 12.5,
                            color: C.muted,
                            marginTop: 2,
                        }}
                    >
                        {rows.length.toLocaleString('id-ID')} data terdaftar
                    </div>
                </div>
                <button onClick={onCreate} style={btnP}>
                    <AIcon name="plus" size={16} color="#fff" />
                    {section.addLabel}
                </button>
            </div>

            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 680,
                    }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            {section.columns.map((column) => (
                                <th key={column.key} style={thCell}>
                                    {column.header}
                                </th>
                            ))}
                            <th
                                style={{
                                    ...thCell,
                                    textAlign: 'right',
                                    padding: '12px 18px',
                                }}
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                <td
                                    colSpan={section.columns.length + 1}
                                    style={{
                                        padding: '48px 18px',
                                        textAlign: 'center',
                                        fontSize: 13.5,
                                        color: C.muted,
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <AIcon
                                            name={section.icon}
                                            size={28}
                                            color={C.faint}
                                        />
                                        <div>{section.emptyText}</div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => (
                            <tr
                                key={row.id}
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                }}
                            >
                                {section.columns.map((column) => (
                                    <td
                                        key={column.key}
                                        style={{
                                            padding: '13px 16px',
                                            fontSize: 13,
                                            color: C.text,
                                        }}
                                    >
                                        {renderCell(row, column)}
                                    </td>
                                ))}
                                <td
                                    style={{
                                        padding: '13px 18px',
                                        textAlign: 'right',
                                    }}
                                >
                                    <div
                                        style={{
                                            position: 'relative',
                                            display: 'inline-block',
                                        }}
                                    >
                                        <button
                                            onClick={() =>
                                                setOpenMenu((prev) =>
                                                    prev === row.id
                                                        ? null
                                                        : row.id,
                                                )
                                            }
                                            aria-label="Aksi"
                                            style={iconBtn}
                                        >
                                            <AIcon
                                                name="ellipsis-vertical"
                                                size={16}
                                            />
                                        </button>
                                        <div
                                            style={{
                                                display:
                                                    openMenu === row.id
                                                        ? 'block'
                                                        : 'none',
                                                position: 'absolute',
                                                right: 0,
                                                top: 38,
                                                width: 148,
                                                background: '#fff',
                                                border: `1px solid ${C.border}`,
                                                borderRadius: 10,
                                                boxShadow:
                                                    '0 8px 24px rgba(15,23,42,.12)',
                                                zIndex: 20,
                                                padding: 5,
                                                textAlign: 'left',
                                            }}
                                        >
                                            <button
                                                onClick={() => onEdit(row)}
                                                style={rowMenuItemStyle}
                                            >
                                                <AIcon
                                                    name="pencil"
                                                    size={15}
                                                    color={C.muted}
                                                />
                                                Ubah
                                            </button>
                                            <button
                                                onClick={() => onDelete(row)}
                                                style={{
                                                    ...rowMenuItemStyle,
                                                    color: C.red,
                                                }}
                                            >
                                                <AIcon
                                                    name="trash-2"
                                                    size={15}
                                                    color={C.red}
                                                />
                                                Hapus
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/* ============================================================
 * Modal — inline create/edit form for the active section.
 * ============================================================ */

interface ConfigModalProps {
    section: SectionDef;
    record: FlatRecord | null;
    onClose: () => void;
}

export function ConfigModal({ section, record, onClose }: ConfigModalProps) {
    const form = useForm<Record<string, string>>(
        buildInitialForm(section, record),
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (section.key === 'bpjs') {
            if (record) {
                form.submit(
                    PayrollConfigController.updateBpjsProgram(record.id),
                    options,
                );
            } else {
                form.submit(
                    PayrollConfigController.storeBpjsProgram(),
                    options,
                );
            }
        } else if (record) {
            form.submit(
                PayrollConfigController.updateTerRate(record.id),
                options,
            );
        } else {
            form.submit(PayrollConfigController.storeTerRate(), options);
        }
    };

    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <div
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 520,
                    maxHeight: '90vh',
                    overflowY: 'auto',
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        padding: '18px 22px',
                        borderBottom: `1px solid ${C.line}`,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    <div style={{ fontSize: 16, fontWeight: 600, color: C.navy }}>
                        {record
                            ? `Ubah ${section.entityLabel}`
                            : `Tambah ${section.entityLabel}`}
                    </div>
                    <button
                        onClick={onClose}
                        aria-label="Tutup"
                        style={{
                            width: 32,
                            height: 32,
                            border: 'none',
                            background: 'none',
                            borderRadius: 8,
                            cursor: 'pointer',
                            color: C.muted,
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="x" size={18} />
                    </button>
                </div>

                <form onSubmit={submit} style={{ padding: '20px 22px' }}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 16,
                        }}
                    >
                        {section.fields.map((field) => {
                            const hasError = !!form.errors[field.name];
                            const value = form.data[field.name] ?? '';
                            const onChange = (v: string) =>
                                form.setData(field.name, v);

                            return (
                                <div
                                    key={field.name}
                                    style={{
                                        gridColumn:
                                            field.span === 'half'
                                                ? 'span 1'
                                                : 'span 2',
                                    }}
                                >
                                    <label style={fieldLabelStyle}>
                                        {field.label}
                                        {field.required && (
                                            <span style={{ color: C.red }}>
                                                {' '}
                                                *
                                            </span>
                                        )}
                                    </label>

                                    {field.type === 'select' ? (
                                        <select
                                            value={value}
                                            onChange={(event) =>
                                                onChange(event.target.value)
                                            }
                                            style={withError(
                                                selectStyle,
                                                hasError,
                                            )}
                                        >
                                            {!field.options?.some(
                                                (o) => o.value === '',
                                            ) &&
                                                field.name !== 'is_active' && (
                                                    <option value="">
                                                        Pilih{' '}
                                                        {field.label.toLowerCase()}
                                                    </option>
                                                )}
                                            {field.options?.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    ) : field.type === 'textarea' ? (
                                        <textarea
                                            value={value}
                                            placeholder={field.placeholder}
                                            onChange={(event) =>
                                                onChange(event.target.value)
                                            }
                                            style={withError(
                                                textareaStyle,
                                                hasError,
                                            )}
                                        />
                                    ) : (
                                        <input
                                            type={
                                                field.type === 'number'
                                                    ? 'number'
                                                    : field.type === 'date'
                                                      ? 'date'
                                                      : 'text'
                                            }
                                            step={field.step}
                                            value={value}
                                            placeholder={field.placeholder}
                                            onChange={(event) =>
                                                onChange(event.target.value)
                                            }
                                            style={withError(
                                                inputStyle,
                                                hasError,
                                            )}
                                        />
                                    )}

                                    {field.hint && !hasError && (
                                        <div style={hintTextStyle}>
                                            {field.hint}
                                        </div>
                                    )}
                                    <FieldError
                                        message={form.errors[field.name]}
                                    />
                                </div>
                            );
                        })}
                    </div>

                    <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                        <button
                            type="button"
                            onClick={onClose}
                            style={{
                                ...btnOut,
                                flex: 1,
                                justifyContent: 'center',
                            }}
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            style={{
                                ...btnP,
                                flex: 1,
                                justifyContent: 'center',
                                opacity: form.processing ? 0.7 : 1,
                                cursor: form.processing
                                    ? 'not-allowed'
                                    : 'pointer',
                            }}
                        >
                            <AIcon name="check" size={16} color="#fff" />
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

/* ============================================================
 * Destructive-action confirmation modal.
 * ============================================================ */

interface ConfirmModalProps {
    title: string;
    body: ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
}

/** Centered destructive-action confirmation modal. */
export function ConfirmModal({
    title,
    body,
    onCancel,
    onConfirm,
}: ConfirmModalProps) {
    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 80,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 20,
            }}
        >
            <div
                onClick={onCancel}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(14,26,58,.45)',
                }}
            />
            <div
                style={{
                    position: 'relative',
                    width: '100%',
                    maxWidth: 400,
                    background: '#fff',
                    borderRadius: 14,
                    boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                    padding: 26,
                    animation: 'toastIn .2s ease',
                }}
            >
                <div
                    style={{
                        width: 48,
                        height: 48,
                        borderRadius: 12,
                        background: 'rgba(220,38,38,.1)',
                        color: C.red,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: 16,
                    }}
                >
                    <AIcon name="trash-2" size={22} color={C.red} />
                </div>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                <div
                    style={{
                        fontSize: 13.5,
                        color: C.muted,
                        marginTop: 8,
                        lineHeight: 1.55,
                    }}
                >
                    {body}
                </div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button
                        onClick={onCancel}
                        style={{
                            ...btnOut,
                            flex: 1,
                            height: 44,
                            justifyContent: 'center',
                        }}
                    >
                        Batal
                    </button>
                    <button
                        onClick={onConfirm}
                        style={{
                            flex: 1,
                            height: 44,
                            background: C.red,
                            color: '#fff',
                            border: 'none',
                            borderRadius: 9,
                            fontSize: 14,
                            fontWeight: 600,
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 8,
                            transition: '.15s',
                        }}
                    >
                        <AIcon name="trash-2" size={16} />
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    );
}
