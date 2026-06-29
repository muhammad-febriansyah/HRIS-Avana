import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import LeaveTypeController from '@/actions/App/Http/Controllers/Avana/LeaveTypeController';
import { AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import type { FlashProps } from './employees/types';

/* ============================================================
 * Types — mirror the LeaveTypeController index payload.
 * ============================================================ */

interface LeaveType {
    id: number;
    code: string;
    name: string;
    default_quota: number;
    allow_negative: boolean;
    requires_attachment: boolean;
    status: 'active' | 'inactive';
    usage: number;
}

interface JenisCutiProps {
    leaveTypes: LeaveType[];
}

/** Flat form payload backing the create/edit modal. */
interface LeaveTypeFormData {
    code: string;
    name: string;
    default_quota: string;
    allow_negative: boolean;
    requires_attachment: boolean;
    status: 'active' | 'inactive';
}

const STATUS_OPTIONS = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

/* ---------- shared field styles (mirror perusahaan.tsx) ---------- */

const fieldLabelStyle: CSSProperties = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
};

const inputStyle: CSSProperties = {
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

const selectStyle: CSSProperties = {
    ...inputStyle,
    color: C.muted,
    cursor: 'pointer',
};

const errorTextStyle: CSSProperties = {
    fontSize: 12,
    color: C.red,
    marginTop: 6,
    display: 'flex',
    alignItems: 'center',
    gap: 5,
};

const rowMenuItemStyle: CSSProperties = {
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

/** Apply the red error border to a base style when invalid. */
function withError(base: CSSProperties, hasError: boolean): CSSProperties {
    return hasError
        ? {
              ...base,
              border: `1px solid ${C.red}`,
              boxShadow: '0 0 0 3px rgba(220,38,38,.08)',
          }
        : base;
}

/** Inline error message rendered under a field. */
function FieldError({ message }: { message?: string }) {
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
function StatusPill({ status }: { status: string }) {
    const active = status === 'active';
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

/** Ya/Tidak pill for boolean columns. */
function YesNoPill({ value }: { value: boolean }) {
    const color = value ? C.primary : C.muted;
    const bg = value ? 'rgba(47,84,201,.1)' : 'rgba(107,114,128,.12)';

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
            {value ? 'Ya' : 'Tidak'}
        </span>
    );
}

/** A labelled toggle switch row used for the boolean fields. */
function ToggleField({
    label,
    description,
    checked,
    onChange,
}: {
    label: string;
    description: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onChange(!checked)}
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 12,
                width: '100%',
                padding: '11px 13px',
                border: `1px solid ${C.border}`,
                borderRadius: 8,
                background: '#fff',
                cursor: 'pointer',
                textAlign: 'left',
            }}
        >
            <div>
                <div style={{ fontSize: 13, fontWeight: 500, color: C.text }}>
                    {label}
                </div>
                <div style={{ fontSize: 11.5, color: C.muted, marginTop: 2 }}>
                    {description}
                </div>
            </div>
            <span
                style={{
                    flex: 'none',
                    width: 38,
                    height: 22,
                    borderRadius: 100,
                    background: checked ? C.primary : C.border,
                    position: 'relative',
                    transition: '.15s',
                }}
            >
                <span
                    style={{
                        position: 'absolute',
                        top: 2,
                        left: checked ? 18 : 2,
                        width: 18,
                        height: 18,
                        borderRadius: '50%',
                        background: '#fff',
                        boxShadow: '0 1px 2px rgba(15,23,42,.25)',
                        transition: '.15s',
                    }}
                />
            </span>
        </button>
    );
}

/** Build the initial form payload, optionally from an existing record. */
function buildInitialForm(record: LeaveType | null): LeaveTypeFormData {
    return {
        code: record?.code ?? '',
        name: record?.name ?? '',
        default_quota: record ? String(record.default_quota) : '12',
        allow_negative: record?.allow_negative ?? false,
        requires_attachment: record?.requires_attachment ?? false,
        status: record?.status ?? 'active',
    };
}

/* ============================================================
 * Modal — create/edit form.
 * ============================================================ */

function LeaveTypeModal({
    record,
    onClose,
}: {
    record: LeaveType | null;
    onClose: () => void;
}) {
    const form = useForm<LeaveTypeFormData>(buildInitialForm(record));

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const callbacks = { preserveScroll: true, onSuccess: () => onClose() };

        if (record) {
            form.submit(LeaveTypeController.update(record.id), callbacks);
        } else {
            form.submit(LeaveTypeController.store(), callbacks);
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
                        {record ? 'Ubah Jenis Cuti' : 'Tambah Jenis Cuti'}
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
                        <div style={{ gridColumn: 'span 1' }}>
                            <label style={fieldLabelStyle}>
                                Kode <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.code}
                                placeholder="TAHUNAN"
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                style={withError(inputStyle, !!form.errors.code)}
                            />
                            <FieldError message={form.errors.code} />
                        </div>

                        <div style={{ gridColumn: 'span 1' }}>
                            <label style={fieldLabelStyle}>
                                Nama <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.name}
                                placeholder="Cuti Tahunan"
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                style={withError(inputStyle, !!form.errors.name)}
                            />
                            <FieldError message={form.errors.name} />
                        </div>

                        <div style={{ gridColumn: 'span 1' }}>
                            <label style={fieldLabelStyle}>
                                Kuota Default (hari){' '}
                                <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="number"
                                min="0"
                                value={form.data.default_quota}
                                onChange={(event) =>
                                    form.setData(
                                        'default_quota',
                                        event.target.value,
                                    )
                                }
                                style={withError(
                                    inputStyle,
                                    !!form.errors.default_quota,
                                )}
                            />
                            <FieldError message={form.errors.default_quota} />
                        </div>

                        <div style={{ gridColumn: 'span 1' }}>
                            <label style={fieldLabelStyle}>Status</label>
                            <select
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData(
                                        'status',
                                        event.target.value as
                                            | 'active'
                                            | 'inactive',
                                    )
                                }
                                style={withError(
                                    selectStyle,
                                    !!form.errors.status,
                                )}
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
                            <FieldError message={form.errors.status} />
                        </div>

                        <div style={{ gridColumn: 'span 2' }}>
                            <ToggleField
                                label="Saldo Minus"
                                description="Izinkan saldo cuti menjadi minus."
                                checked={form.data.allow_negative}
                                onChange={(value) =>
                                    form.setData('allow_negative', value)
                                }
                            />
                        </div>

                        <div style={{ gridColumn: 'span 2' }}>
                            <ToggleField
                                label="Wajib Lampiran"
                                description="Wajibkan unggah dokumen pendukung."
                                checked={form.data.requires_attachment}
                                onChange={(value) =>
                                    form.setData('requires_attachment', value)
                                }
                            />
                        </div>
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
 * Page
 * ============================================================ */

export default function JenisCuti({ leaveTypes }: JenisCutiProps) {
    const { flash } = usePage<FlashProps>().props;

    const [openMenu, setOpenMenu] = useState<number | null>(null);
    const [editing, setEditing] = useState<LeaveType | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [confirm, setConfirm] = useState<LeaveType | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openCreate = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (row: LeaveType) => {
        setEditing(row);
        setModalOpen(true);
        setOpenMenu(null);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
    };

    const deleteRecord = () => {
        if (!confirm) {
            return;
        }

        router.delete(LeaveTypeController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Jenis Cuti" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 12.5,
                                color: C.faint,
                                marginBottom: 7,
                            }}
                        >
                            <Link
                                href="/avana/cuti"
                                style={{ color: C.faint, textDecoration: 'none' }}
                            >
                                Cuti &amp; Lembur
                            </Link>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Jenis Cuti</span>
                        </div>
                        <h1
                            style={{
                                fontSize: 24,
                                fontWeight: 600,
                                color: C.navy,
                                margin: 0,
                                letterSpacing: '-.01em',
                            }}
                        >
                            Jenis Cuti
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola jenis cuti, kuota default, dan aturan
                            pengajuannya.
                        </div>
                    </div>
                    <button onClick={openCreate} style={btnP}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Jenis Cuti
                    </button>
                </div>

                {/* Entity card */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Jenis Cuti
                        </div>
                        <div
                            style={{
                                fontSize: 12.5,
                                color: C.muted,
                                marginTop: 2,
                            }}
                        >
                            {leaveTypes.length.toLocaleString('id-ID')} jenis
                            terdaftar
                        </div>
                    </div>

                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 720,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Kode</th>
                                    <th style={thCell}>Nama</th>
                                    <th style={thCell}>Kuota Default (hari)</th>
                                    <th style={thCell}>Saldo Minus</th>
                                    <th style={thCell}>Wajib Lampiran</th>
                                    <th style={thCell}>Status</th>
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
                                {leaveTypes.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={7}
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
                                                    name="palmtree"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Belum ada jenis cuti.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {leaveTypes.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.text,
                                            }}
                                        >
                                            {row.code}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {row.name}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 16px',
                                                fontSize: 13,
                                                color: C.text,
                                            }}
                                        >
                                            {row.default_quota} hari
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <YesNoPill
                                                value={row.allow_negative}
                                            />
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <YesNoPill
                                                value={row.requires_attachment}
                                            />
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <StatusPill status={row.status} />
                                        </td>
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
                                                    style={{
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
                                                    }}
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
                                                        onClick={() =>
                                                            openEdit(row)
                                                        }
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
                                                        onClick={() => {
                                                            setConfirm(row);
                                                            setOpenMenu(null);
                                                        }}
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
            </div>

            {/* Create / edit modal */}
            {modalOpen && (
                <LeaveTypeModal
                    key={editing?.id ?? 'new'}
                    record={editing}
                    onClose={closeModal}
                />
            )}

            {/* Confirm delete modal */}
            {confirm && (
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
                        onClick={() => setConfirm(null)}
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
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Hapus jenis cuti?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Jenis cuti{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.name}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </div>
                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            <button
                                onClick={() => setConfirm(null)}
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
                                onClick={deleteRecord}
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
            )}
        </>
    );
}
