import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PayrollConfigController from '@/actions/App/Http/Controllers/Avana/PayrollConfigController';
import { AIcon, btnOut, btnP, C, card, rp, thCell } from '@/lib/avana';
import type { FlashProps } from './employees/types';

/* ============================================================
 * Types — mirror the PayrollConfigController index payload.
 * NOTE: bpjs_programs / bpjs_rates / pph21_ter_rates are GLOBAL
 * master tables (no tenant scope).
 * ============================================================ */

type Numeric = string | number | null;

interface BpjsRate {
    id: number;
    employee_rate: Numeric;
    company_rate: Numeric;
    max_wage: Numeric;
    min_wage: Numeric;
    risk_level: string | null;
    effective_start_date: string | null;
    effective_end_date: string | null;
    is_active: boolean;
}

interface BpjsProgram {
    id: number;
    code: string;
    name: string;
    type: string | null;
    description: string | null;
    is_active: boolean;
    rates: BpjsRate[];
}

interface TerRate {
    id: number;
    category: string;
    income_min: Numeric;
    income_max: Numeric;
    rate: Numeric;
    effective_start_date: string | null;
    effective_end_date: string | null;
    is_active: boolean;
}

interface ProfileStats {
    bpjs_profiles: number;
    tax_profiles: number;
}

interface PayrollConfigProps {
    programs: BpjsProgram[];
    terRates: TerRate[];
    profileStats: ProfileStats;
}

/** A flat record indexed by field/column key — feeds the table and modal. */
type FlatRecord = Record<string, string | number | boolean | null> & {
    id: number;
};

/** A table column descriptor. */
interface ColumnDef {
    header: string;
    key: string;
    /** Secondary key for the range renderer. */
    key2?: string;
    kind?: 'percent' | 'rupiah' | 'rupiah-range' | 'active' | 'date';
    align?: 'left' | 'right';
}

/** A modal form field descriptor. */
interface FieldDef {
    name: string;
    label: string;
    type: 'text' | 'number' | 'date' | 'textarea' | 'select';
    required?: boolean;
    span?: 'half' | 'full';
    step?: string;
    placeholder?: string;
    hint?: string;
    options?: { value: string; label: string }[];
}

/** Full configuration for one tab/section. */
interface SectionDef {
    key: 'bpjs' | 'pph21';
    label: string;
    title: string;
    icon: string;
    emptyText: string;
    columns: ColumnDef[];
    fields: FieldDef[];
    addLabel: string;
    entityLabel: string;
}

const ACTIVE_OPTIONS = [
    { value: '1', label: 'Aktif' },
    { value: '0', label: 'Nonaktif' },
];

const BPJS_TYPE_OPTIONS = [
    { value: 'kesehatan', label: 'Kesehatan' },
    { value: 'jht', label: 'JHT (Jaminan Hari Tua)' },
    { value: 'jp', label: 'JP (Jaminan Pensiun)' },
    { value: 'jkk', label: 'JKK (Kecelakaan Kerja)' },
    { value: 'jkm', label: 'JKM (Kematian)' },
    { value: 'ketenagakerjaan', label: 'Ketenagakerjaan' },
];

const SECTIONS: SectionDef[] = [
    {
        key: 'bpjs',
        label: 'Program BPJS',
        title: 'Program BPJS',
        icon: 'shield-plus',
        emptyText: 'Belum ada program BPJS.',
        addLabel: 'Tambah Program',
        entityLabel: 'Program BPJS',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Jenis', key: 'type' },
            { header: 'Iuran Karyawan', key: 'employee_rate', kind: 'percent' },
            {
                header: 'Iuran Perusahaan',
                key: 'company_rate',
                kind: 'percent',
            },
            { header: 'Status', key: 'is_active', kind: 'active' },
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
                label: 'Nama Program',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'type',
                label: 'Jenis',
                type: 'select',
                required: true,
                span: 'half',
                options: BPJS_TYPE_OPTIONS,
            },
            {
                name: 'effective_start_date',
                label: 'Tanggal Berlaku',
                type: 'date',
                required: true,
                span: 'half',
            },
            {
                name: 'employee_rate',
                label: 'Iuran Karyawan',
                type: 'number',
                required: true,
                span: 'half',
                step: '0.0001',
                hint: 'Desimal, 0.01 = 1%',
            },
            {
                name: 'company_rate',
                label: 'Iuran Perusahaan',
                type: 'number',
                required: true,
                span: 'half',
                step: '0.0001',
                hint: 'Desimal, 0.04 = 4%',
            },
            {
                name: 'min_wage',
                label: 'Upah Minimum',
                type: 'number',
                span: 'half',
                placeholder: '0',
            },
            {
                name: 'max_wage',
                label: 'Upah Maksimum',
                type: 'number',
                span: 'half',
                placeholder: '0',
            },
            {
                name: 'description',
                label: 'Deskripsi',
                type: 'textarea',
                span: 'full',
            },
            {
                name: 'is_active',
                label: 'Status',
                type: 'select',
                span: 'full',
                options: ACTIVE_OPTIONS,
            },
        ],
    },
    {
        key: 'pph21',
        label: 'Tarif PPh 21 (TER)',
        title: 'Tarif PPh 21 (TER)',
        icon: 'percent',
        emptyText: 'Belum ada tarif PPh 21.',
        addLabel: 'Tambah Tarif',
        entityLabel: 'Tarif PPh 21',
        columns: [
            { header: 'Kategori', key: 'category' },
            {
                header: 'Rentang Penghasilan',
                key: 'income_min',
                key2: 'income_max',
                kind: 'rupiah-range',
            },
            { header: 'Tarif', key: 'rate', kind: 'percent' },
            {
                header: 'Tanggal Berlaku',
                key: 'effective_start_date',
                kind: 'date',
            },
            { header: 'Status', key: 'is_active', kind: 'active' },
        ],
        fields: [
            {
                name: 'category',
                label: 'Kategori',
                type: 'text',
                required: true,
                span: 'half',
                placeholder: 'A / B / C',
            },
            {
                name: 'effective_start_date',
                label: 'Tanggal Berlaku',
                type: 'date',
                required: true,
                span: 'half',
            },
            {
                name: 'income_min',
                label: 'Penghasilan Minimum',
                type: 'number',
                required: true,
                span: 'half',
                placeholder: '0',
            },
            {
                name: 'income_max',
                label: 'Penghasilan Maksimum',
                type: 'number',
                span: 'half',
                placeholder: '0',
            },
            {
                name: 'rate',
                label: 'Tarif',
                type: 'number',
                required: true,
                span: 'half',
                step: '0.0001',
                hint: 'Desimal, 0.05 = 5%',
            },
            {
                name: 'is_active',
                label: 'Status',
                type: 'select',
                span: 'half',
                options: ACTIVE_OPTIONS,
            },
        ],
    },
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
    color: C.text,
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

const hintTextStyle: CSSProperties = {
    fontSize: 11.5,
    color: C.faint,
    marginTop: 5,
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
function StatusPill({ active }: { active: boolean }) {
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

/** Format a decimal fraction (0.01) as a percentage string (1%). */
function asPercent(value: string | number | boolean | null): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const pct = Number(value) * 100;

    return `${pct.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

/** Format a numeric value as rupiah, or an em dash when empty. */
function asRupiah(value: string | number | boolean | null): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return rp(Number(value));
}

/** Render a single table cell based on its column descriptor. */
function renderCell(row: FlatRecord, column: ColumnDef): ReactNode {
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

/** Flatten a BPJS program (with its latest rate) into a single record. */
function flattenProgram(program: BpjsProgram): FlatRecord {
    const rate = program.rates[0] ?? null;

    return {
        id: program.id,
        code: program.code,
        name: program.name,
        type: program.type,
        description: program.description,
        is_active: program.is_active,
        employee_rate: rate?.employee_rate ?? null,
        company_rate: rate?.company_rate ?? null,
        min_wage: rate?.min_wage ?? null,
        max_wage: rate?.max_wage ?? null,
        risk_level: rate?.risk_level ?? null,
        effective_start_date: rate?.effective_start_date ?? null,
    };
}

/** Flatten a PPh 21 TER rate into a single record. */
function flattenTerRate(rate: TerRate): FlatRecord {
    return {
        id: rate.id,
        category: rate.category,
        income_min: rate.income_min,
        income_max: rate.income_max,
        rate: rate.rate,
        effective_start_date: rate.effective_start_date,
        is_active: rate.is_active,
    };
}

/** Build the initial flat string form payload for a section. */
function buildInitialForm(
    section: SectionDef,
    record: FlatRecord | null,
): Record<string, string> {
    const data: Record<string, string> = {};

    for (const field of section.fields) {
        if (record) {
            const raw = record[field.name];

            if (field.name === 'is_active') {
                data[field.name] =
                    raw === true || raw === '1' || raw === 1 ? '1' : '0';
            } else if (raw === null || raw === undefined) {
                data[field.name] = '';
            } else {
                data[field.name] = String(raw);
            }
        } else {
            data[field.name] = field.name === 'is_active' ? '1' : '';
        }
    }

    return data;
}

/* ============================================================
 * Modal — inline create/edit form for the active section.
 * ============================================================ */

interface ConfigModalProps {
    section: SectionDef;
    record: FlatRecord | null;
    onClose: () => void;
}

function ConfigModal({ section, record, onClose }: ConfigModalProps) {
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
                    <div
                        style={{ fontSize: 16, fontWeight: 600, color: C.navy }}
                    >
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
 * Page
 * ============================================================ */

export default function PayrollConfig(props: PayrollConfigProps) {
    const { flash } = usePage<FlashProps>().props;

    const [activeKey, setActiveKey] = useState<'bpjs' | 'pph21'>('bpjs');
    const [openMenu, setOpenMenu] = useState<number | null>(null);
    const [editing, setEditing] = useState<FlatRecord | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [confirm, setConfirm] = useState<FlatRecord | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const section =
        SECTIONS.find((item) => item.key === activeKey) ?? SECTIONS[0];

    const rows: FlatRecord[] =
        section.key === 'bpjs'
            ? props.programs.map(flattenProgram)
            : props.terRates.map(flattenTerRate);

    const openCreate = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (row: FlatRecord) => {
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

        const action =
            section.key === 'bpjs'
                ? PayrollConfigController.destroyBpjsProgram(confirm.id)
                : PayrollConfigController.destroyTerRate(confirm.id);

        router.delete(action.url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Konfigurasi BPJS & Pajak" />
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
                        <span>Payroll</span>
                        <AIcon name="chevron-right" size={13} />
                        <span style={{ color: C.muted }}>BPJS &amp; Pajak</span>
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
                        Konfigurasi BPJS &amp; Pajak
                    </h1>
                    <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>
                        Kelola program iuran BPJS dan tarif efektif rata-rata
                        (TER) PPh 21.
                    </div>
                </div>

                {/* Stat strip */}
                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        flexWrap: 'wrap',
                        marginBottom: 20,
                    }}
                >
                    {[
                        {
                            label: 'Program BPJS',
                            value: props.programs.length,
                            icon: 'shield-plus',
                        },
                        {
                            label: 'Tarif PPh 21',
                            value: props.terRates.length,
                            icon: 'percent',
                        },
                        {
                            label: 'Profil BPJS Karyawan',
                            value: props.profileStats.bpjs_profiles,
                            icon: 'users',
                        },
                        {
                            label: 'Profil Pajak Karyawan',
                            value: props.profileStats.tax_profiles,
                            icon: 'receipt',
                        },
                    ].map((stat) => (
                        <div
                            key={stat.label}
                            style={{
                                ...card,
                                flex: '1 1 180px',
                                padding: '14px 16px',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <div
                                style={{
                                    width: 38,
                                    height: 38,
                                    borderRadius: 9,
                                    background: 'rgba(47,84,201,.1)',
                                    color: C.primary,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <AIcon
                                    name={stat.icon}
                                    size={18}
                                    color={C.primary}
                                />
                            </div>
                            <div>
                                <div
                                    style={{
                                        fontSize: 19,
                                        fontWeight: 700,
                                        color: C.navy,
                                    }}
                                >
                                    {stat.value.toLocaleString('id-ID')}
                                </div>
                                <div style={{ fontSize: 12, color: C.muted }}>
                                    {stat.label}
                                </div>
                            </div>
                        </div>
                    ))}
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
                    {SECTIONS.map((item) => {
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

                {/* Section card */}
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
                                {rows.length.toLocaleString('id-ID')} data
                                terdaftar
                            </div>
                        </div>
                        <button onClick={openCreate} style={btnP}>
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
                <ConfigModal
                    key={`${section.key}-${editing?.id ?? 'new'}`}
                    section={section}
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
                                {String(
                                    confirm.name ??
                                        confirm.category ??
                                        confirm.code ??
                                        '',
                                )}
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
