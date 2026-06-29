import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import type { FlashProps, NamedOption } from './employees/types';

/* ============================================================
 * Types — mirror the CompanySetupController index payload.
 * ============================================================ */

type Cell = string | number | boolean | null;

/** A loosely-typed master row; columns/fields index by key. */
interface EntityRecord {
    id: number;
    [key: string]: Cell | number[] | undefined;
}

interface SetupOptions {
    departments: NamedOption[];
    branches: NamedOption[];
}

interface PerusahaanProps {
    branches: EntityRecord[];
    departments: EntityRecord[];
    positions: EntityRecord[];
    jobLevels: EntityRecord[];
    workLocations: EntityRecord[];
    shifts: EntityRecord[];
    options: SetupOptions;
}

/** A table column descriptor for an entity tab. */
interface ColumnDef {
    header: string;
    key: string;
    kind?: 'status' | 'time';
    align?: 'left' | 'right';
}

/** A modal form field descriptor for an entity tab. */
interface FieldDef {
    name: string;
    label: string;
    type: 'text' | 'number' | 'time' | 'textarea' | 'select';
    required?: boolean;
    span?: 'half' | 'full';
    default?: string;
    placeholder?: string;
    /** Static `{ value, label }` options for a plain select. */
    options?: { value: string; label: string }[];
    /** Pull `{ id, name }` options from `props.options` for a FK select. */
    optionsKey?: keyof SetupOptions;
}

/** Full configuration for one tab/entity. */
interface TabDef {
    key: string;
    label: string;
    icon: string;
    emptyText: string;
    columns: ColumnDef[];
    fields: FieldDef[];
}

const STATUS_OPTIONS = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

const TABS: TabDef[] = [
    {
        key: 'branches',
        label: 'Cabang',
        icon: 'building-2',
        emptyText: 'Belum ada cabang.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Telepon', key: 'phone' },
            { header: 'Status', key: 'status', kind: 'status' },
        ],
        fields: [
            {
                name: 'code',
                label: 'Kode',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'name',
                label: 'Nama Cabang',
                type: 'text',
                required: true,
                span: 'half',
            },
            { name: 'phone', label: 'Telepon', type: 'text', span: 'half' },
            {
                name: 'timezone',
                label: 'Zona Waktu',
                type: 'text',
                default: 'Asia/Jakarta',
                span: 'half',
            },
            {
                name: 'address',
                label: 'Alamat',
                type: 'textarea',
                span: 'full',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'full',
            },
        ],
    },
    {
        key: 'departments',
        label: 'Departemen',
        icon: 'network',
        emptyText: 'Belum ada departemen.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Status', key: 'status', kind: 'status' },
        ],
        fields: [
            {
                name: 'code',
                label: 'Kode',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'name',
                label: 'Nama Departemen',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'parent_id',
                label: 'Departemen Induk',
                type: 'select',
                optionsKey: 'departments',
                span: 'full',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'full',
            },
        ],
    },
    {
        key: 'positions',
        label: 'Jabatan',
        icon: 'briefcase',
        emptyText: 'Belum ada jabatan.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Departemen', key: 'department_name' },
            { header: 'Status', key: 'status', kind: 'status' },
        ],
        fields: [
            {
                name: 'code',
                label: 'Kode',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'name',
                label: 'Nama Jabatan',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'department_id',
                label: 'Departemen',
                type: 'select',
                optionsKey: 'departments',
                span: 'full',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'full',
            },
        ],
    },
    {
        key: 'job-levels',
        label: 'Jenjang Jabatan',
        icon: 'layers',
        emptyText: 'Belum ada jenjang jabatan.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Urutan', key: 'level_order' },
            { header: 'Status', key: 'status', kind: 'status' },
        ],
        fields: [
            {
                name: 'code',
                label: 'Kode',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'name',
                label: 'Nama Jenjang',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'level_order',
                label: 'Urutan',
                type: 'number',
                default: '0',
                span: 'half',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'half',
            },
        ],
    },
    {
        key: 'work-locations',
        label: 'Lokasi Kerja',
        icon: 'map-pin',
        emptyText: 'Belum ada lokasi kerja.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Cabang', key: 'branch_name' },
            { header: 'Radius (m)', key: 'radius_meter' },
            { header: 'Status', key: 'status', kind: 'status' },
        ],
        fields: [
            {
                name: 'name',
                label: 'Nama Lokasi',
                type: 'text',
                required: true,
                span: 'half',
            },
            { name: 'code', label: 'Kode', type: 'text', span: 'half' },
            {
                name: 'branch_id',
                label: 'Cabang',
                type: 'select',
                optionsKey: 'branches',
                span: 'full',
            },
            {
                name: 'latitude',
                label: 'Latitude',
                type: 'text',
                placeholder: '-6.2146',
                span: 'half',
            },
            {
                name: 'longitude',
                label: 'Longitude',
                type: 'text',
                placeholder: '106.8451',
                span: 'half',
            },
            {
                name: 'radius_meter',
                label: 'Radius (meter)',
                type: 'number',
                default: '100',
                span: 'half',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'half',
            },
            {
                name: 'address',
                label: 'Alamat',
                type: 'textarea',
                span: 'full',
            },
        ],
    },
    {
        key: 'shifts',
        label: 'Shift',
        icon: 'clock',
        emptyText: 'Belum ada shift.',
        columns: [
            { header: 'Nama', key: 'name' },
            { header: 'Jam Masuk', key: 'start_time', kind: 'time' },
            { header: 'Jam Keluar', key: 'end_time', kind: 'time' },
            { header: 'Toleransi', key: 'late_tolerance_minutes' },
            { header: 'Status', key: 'status', kind: 'status' },
        ],
        fields: [
            {
                name: 'name',
                label: 'Nama Shift',
                type: 'text',
                required: true,
                span: 'half',
            },
            { name: 'code', label: 'Kode', type: 'text', span: 'half' },
            {
                name: 'start_time',
                label: 'Jam Masuk',
                type: 'time',
                span: 'half',
            },
            {
                name: 'end_time',
                label: 'Jam Keluar',
                type: 'time',
                span: 'half',
            },
            {
                name: 'late_tolerance_minutes',
                label: 'Toleransi (menit)',
                type: 'number',
                default: '0',
                span: 'half',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'half',
            },
        ],
    },
];

/* ---------- shared field styles (mirror cuti.tsx) ---------- */

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

const textareaStyle: CSSProperties = {
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

/** Render a single table cell based on its column descriptor. */
function renderCell(row: EntityRecord, column: ColumnDef): ReactNode {
    const raw = row[column.key];

    if (column.kind === 'status') {
        return <StatusPill status={String(raw ?? 'inactive')} />;
    }

    if (raw === null || raw === undefined || raw === '') {
        return <span style={{ color: C.faint }}>—</span>;
    }

    if (column.kind === 'time') {
        return String(raw).slice(0, 5);
    }

    return String(raw);
}

/** Build the initial flat form payload for a tab, optionally from a record. */
function buildInitialForm(
    tab: TabDef,
    record: EntityRecord | null,
): Record<string, string> {
    const data: Record<string, string> = {};

    for (const field of tab.fields) {
        if (record) {
            const raw = record[field.name];
            let value = raw === null || raw === undefined ? '' : String(raw);

            if (field.type === 'time') {
                value = value.slice(0, 5);
            }

            data[field.name] = value;
        } else {
            data[field.name] = field.default ?? '';
        }
    }

    return data;
}

/* ============================================================
 * Modal — inline create/edit form for the active tab.
 * ============================================================ */

interface EntityModalProps {
    tab: TabDef;
    options: SetupOptions;
    record: EntityRecord | null;
    onClose: () => void;
}

function EntityModal({ tab, options, record, onClose }: EntityModalProps) {
    const form = useForm<Record<string, string>>(buildInitialForm(tab, record));

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const callbacks = { preserveScroll: true, onSuccess: () => onClose() };

        if (record) {
            form.put(`/avana/perusahaan/${tab.key}/${record.id}`, callbacks);
        } else {
            form.post(`/avana/perusahaan/${tab.key}`, callbacks);
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
                    <div
                        style={{ fontSize: 16, fontWeight: 600, color: C.navy }}
                    >
                        {record ? `Ubah ${tab.label}` : `Tambah ${tab.label}`}
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
                        {tab.fields.map((field) => {
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
                                            {!field.options && (
                                                <option value="">
                                                    Pilih{' '}
                                                    {field.label.toLowerCase()}
                                                </option>
                                            )}
                                            {field.optionsKey &&
                                                options[field.optionsKey].map(
                                                    (option) => (
                                                        <option
                                                            key={option.id}
                                                            value={String(
                                                                option.id,
                                                            )}
                                                        >
                                                            {option.name}
                                                        </option>
                                                    ),
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
                                                    : field.type === 'time'
                                                      ? 'time'
                                                      : 'text'
                                            }
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
 * Page
 * ============================================================ */

export default function Perusahaan(props: PerusahaanProps) {
    const { flash } = usePage<FlashProps>().props;
    const { options } = props;

    const [activeKey, setActiveKey] = useState<string>('branches');
    const [openMenu, setOpenMenu] = useState<number | null>(null);
    const [editing, setEditing] = useState<EntityRecord | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [confirm, setConfirm] = useState<EntityRecord | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const tab = TABS.find((item) => item.key === activeKey) ?? TABS[0];

    const dataMap: Record<string, EntityRecord[]> = {
        branches: props.branches,
        departments: props.departments,
        positions: props.positions,
        'job-levels': props.jobLevels,
        'work-locations': props.workLocations,
        shifts: props.shifts,
    };
    const rows = dataMap[tab.key] ?? [];

    const openCreate = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (row: EntityRecord) => {
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

        router.delete(`/avana/perusahaan/${tab.key}/${confirm.id}`, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Perusahaan" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ marginBottom: 22 }}>
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
                        <span>Pengaturan</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>Perusahaan</span>
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
                        Pengaturan Perusahaan
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Kelola cabang, departemen, jabatan, jenjang, lokasi
                        kerja, dan shift.
                    </div>
                </div>

                {/* Tab bar */}
                <div
                    style={{
                        display: 'flex',
                        gap: 4,
                        flexWrap: 'wrap',
                        borderBottom: `1px solid ${C.border}`,
                        marginBottom: 18,
                    }}
                >
                    {TABS.map((item) => {
                        const active = item.key === activeKey;

                        return (
                            <button
                                key={item.key}
                                onClick={() => {
                                    setActiveKey(item.key);
                                    setOpenMenu(null);
                                }}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 7,
                                    padding: '11px 14px',
                                    background: 'none',
                                    border: 'none',
                                    borderBottom: `2px solid ${active ? C.primary : 'transparent'}`,
                                    marginBottom: -1,
                                    fontSize: 13.5,
                                    fontWeight: active ? 600 : 500,
                                    color: active ? C.primary : C.muted,
                                    cursor: 'pointer',
                                    transition: '.15s',
                                }}
                            >
                                <AIcon
                                    name={item.icon}
                                    size={16}
                                    color={active ? C.primary : C.faint}
                                />
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {/* Entity card */}
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
                                {tab.label}
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                {rows.length.toLocaleString('id-ID')} data
                                terdaftar
                            </div>
                        </div>
                        <button onClick={openCreate} style={btnP}>
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah
                        </button>
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
                                    {tab.columns.map((column) => (
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
                                            colSpan={tab.columns.length + 1}
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
                                                    name={tab.icon}
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>{tab.emptyText}</div>
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
                                        {tab.columns.map((column) => (
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
                                                        justifyContent:
                                                            'center',
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
                <EntityModal
                    key={`${tab.key}-${editing?.id ?? 'new'}`}
                    tab={tab}
                    options={options}
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
                            Hapus data?
                        </div>
                        <div
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                marginTop: 8,
                                lineHeight: 1.55,
                            }}
                        >
                            Data{' '}
                            <strong style={{ color: C.text }}>
                                {String(confirm.name ?? confirm.code ?? '')}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </div>
                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
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
