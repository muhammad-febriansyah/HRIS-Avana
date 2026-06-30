import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { AIcon, C, card, statusBadge } from '@/lib/avana';
import type {
    EmployeeOption,
    RequestEmployee,
    RequestStatus,
    RequestStatusLabel,
} from './types';

/* ---------- shared field styles (prototype form style) ---------- */

export const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
};

export const selectStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.muted,
    background: '#fff',
    outline: 'none',
    cursor: 'pointer',
};

export const dateInputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 11px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 12.5,
    color: C.muted,
    outline: 'none',
};

export const textInputStyle: CSSProperties = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    outline: 'none',
};

export const textareaStyle: CSSProperties = {
    width: '100%',
    padding: '11px 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    outline: 'none',
    resize: 'vertical',
};

export const tabBarStyle: CSSProperties = {
    display: 'inline-flex',
    gap: 4,
    background: C.surface,
    padding: 4,
    borderRadius: 10,
    marginBottom: 18,
};

export const headThStyle: CSSProperties = {
    padding: '11px 18px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

/** Inline error message rendered under a field, prototype error style. */
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

/** Apply the red error border to a base input/select style when invalid. */
export function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

/** A single labelled field wrapper matching the prototype form style. */
export function Field({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label style={fieldLabelStyle}>
                {label} {required && <span style={{ color: C.red }}>*</span>}
            </label>
            {children}
            <FieldError message={error} />
        </div>
    );
}

/** Reusable employee dropdown shared by the Lembur / Izin / WFH forms. */
export function EmployeeSelect({
    value,
    onChange,
    error,
    employees,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
    employees: EmployeeOption[];
}) {
    return (
        <Field label="Karyawan" required error={error}>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                style={withError(selectStyle, !!error)}
            >
                <option value="">Pilih karyawan</option>
                {employees.map((employee) => (
                    <option key={employee.id} value={String(employee.id)}>
                        {employee.name} ({employee.employee_number})
                    </option>
                ))}
            </select>
        </Field>
    );
}

/** Card wrapper for the compact "Ajukan" forms, mirroring the Cuti form card. */
export function RequestFormCard({
    title,
    subtitle,
    onSubmit,
    processing,
    children,
}: {
    title: string;
    subtitle: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    processing: boolean;
    children: ReactNode;
}) {
    return (
        <div style={card}>
            <div
                style={{
                    padding: '18px 22px',
                    borderBottom: `1px solid ${C.line}`,
                }}
            >
                <div style={{ fontSize: 15, fontWeight: 600, color: C.navy }}>
                    {title}
                </div>
                <div style={{ fontSize: 12.5, color: C.muted, marginTop: 3 }}>
                    {subtitle}
                </div>
            </div>
            <form
                onSubmit={onSubmit}
                style={{
                    padding: '20px 22px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 16,
                }}
            >
                {children}
                <button
                    type="submit"
                    disabled={processing}
                    style={{
                        width: '100%',
                        height: 44,
                        background: C.primary,
                        color: '#fff',
                        border: 'none',
                        borderRadius: 8,
                        fontSize: 14,
                        fontWeight: 600,
                        cursor: processing ? 'not-allowed' : 'pointer',
                        opacity: processing ? 0.7 : 1,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 8,
                        transition: '.15s',
                    }}
                >
                    <AIcon name="send" size={16} color="#fff" />
                    Kirim Pengajuan
                </button>
            </form>
        </div>
    );
}

/** A precomputed row for the generic approval table. */
export interface ApprovalItem {
    id: number;
    employee: RequestEmployee | null;
    subtitle?: string;
    status: RequestStatus;
    status_label: RequestStatusLabel;
    cells: ReactNode[];
}

/** Generic list + approval table shared by the Lembur / Izin / WFH tabs. */
export function ApprovalTable({
    title,
    headers,
    items,
    onApprove,
    onReject,
    emptyIcon,
    emptyText,
}: {
    title: string;
    headers: string[];
    items: ApprovalItem[];
    onApprove: (id: number) => void;
    onReject: (id: number) => void;
    emptyIcon: string;
    emptyText: string;
}) {
    const colSpan = headers.length + 3;

    return (
        <div style={{ ...card, overflow: 'hidden' }}>
            <div
                style={{
                    padding: '16px 18px',
                    borderBottom: `1px solid ${C.border}`,
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                }}
            >
                {title}
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table
                    style={{
                        width: '100%',
                        borderCollapse: 'collapse',
                        minWidth: 640,
                    }}
                >
                    <thead>
                        <tr style={{ background: '#FAFBFD' }}>
                            <th style={headThStyle}>Karyawan</th>
                            {headers.map((header) => (
                                <th key={header} style={headThStyle}>
                                    {header}
                                </th>
                            ))}
                            <th style={headThStyle}>Status</th>
                            <th style={{ ...headThStyle, textAlign: 'right' }}>
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                <td
                                    colSpan={colSpan}
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
                                            name={emptyIcon}
                                            size={28}
                                            color={C.faint}
                                        />
                                        <div>{emptyText}</div>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {items.map((item) => {
                            const badge = statusBadge(item.status_label);

                            return (
                                <tr
                                    key={item.id}
                                    style={{ borderTop: `1px solid ${C.line}` }}
                                >
                                    <td style={{ padding: '12px 18px' }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 10,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    width: 32,
                                                    height: 32,
                                                    borderRadius: '50%',
                                                    flex: 'none',
                                                    background:
                                                        item.employee
                                                            ?.avatar_color ??
                                                        C.faint,
                                                    color: '#fff',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                }}
                                            >
                                                {item.employee?.initials ?? '?'}
                                            </div>
                                            <div>
                                                <div
                                                    style={{
                                                        fontSize: 13,
                                                        fontWeight: 500,
                                                        color: C.text,
                                                    }}
                                                >
                                                    {item.employee?.name ?? '—'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {item.subtitle ??
                                                        item.employee
                                                            ?.employee_number ??
                                                        ''}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    {item.cells.map((cell, index) => (
                                        <td
                                            key={index}
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                color: C.text,
                                            }}
                                        >
                                            {cell}
                                        </td>
                                    ))}
                                    <td style={{ padding: '12px 16px' }}>
                                        <span
                                            style={{
                                                padding: '3px 10px',
                                                borderRadius: 100,
                                                fontSize: 11.5,
                                                fontWeight: 600,
                                                color: badge.color,
                                                background: badge.bg,
                                            }}
                                        >
                                            {badge.label}
                                        </span>
                                    </td>
                                    <td
                                        style={{
                                            padding: '12px 18px',
                                            textAlign: 'right',
                                        }}
                                    >
                                        {item.status === 'pending' ? (
                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    gap: 6,
                                                }}
                                            >
                                                <button
                                                    onClick={() =>
                                                        onApprove(item.id)
                                                    }
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 5,
                                                        height: 30,
                                                        padding: '0 11px',
                                                        border: 'none',
                                                        borderRadius: 7,
                                                        background:
                                                            'rgba(22,163,74,.1)',
                                                        color: C.green,
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                        cursor: 'pointer',
                                                        transition: '.15s',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="check"
                                                        size={14}
                                                        color={C.green}
                                                    />
                                                    Setujui
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        onReject(item.id)
                                                    }
                                                    style={{
                                                        width: 30,
                                                        height: 30,
                                                        border: 'none',
                                                        borderRadius: 7,
                                                        background:
                                                            'rgba(220,38,38,.08)',
                                                        color: C.red,
                                                        cursor: 'pointer',
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        transition: '.15s',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="x"
                                                        size={14}
                                                        color={C.red}
                                                    />
                                                </button>
                                            </div>
                                        ) : (
                                            <span
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.faint,
                                                }}
                                            >
                                                —
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
