import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import BenefitController from '@/actions/App/Http/Controllers/Avana/BenefitController';
import { AIcon, btnOut, btnP, C, card, rp, thCell } from '@/lib/avana';
import type { FlashProps } from './employees/types';

/* ============================================================
 * Types — mirror the BenefitController@index payload.
 * ============================================================ */

/** A benefit master row. */
interface BenefitRow {
    id: number;
    code: string;
    name: string;
    type: string;
    value: number;
    description: string | null;
    status: string;
}

/** An employee benefit assignment row. */
interface AssignmentRow {
    id: number;
    employee: { name: string | null; employee_number: string | null } | null;
    benefit: { name: string | null; type: string } | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
}

/** A selectable employee `{ id, name, employee_number }`. */
interface EmployeeOption {
    id: number;
    name: string | null;
    employee_number: string | null;
}

interface BenefitProps {
    benefits: BenefitRow[];
    assignments: AssignmentRow[];
    employees: EmployeeOption[];
}

/** Flat form payload backing the create/edit benefit modal. */
interface BenefitFormData {
    code: string;
    name: string;
    type: string;
    value: string;
    description: string;
    status: string;
}

const emptyBenefitForm: BenefitFormData = {
    code: '',
    name: '',
    type: 'allowance',
    value: '0',
    description: '',
    status: 'active',
};

/** Flat form payload backing the assign modal. */
interface AssignFormData {
    employee_id: string;
    benefit_id: string;
    start_date: string;
    end_date: string;
    notes: string;
}

const emptyAssignForm: AssignFormData = {
    employee_id: '',
    benefit_id: '',
    start_date: '',
    end_date: '',
    notes: '',
};

const TYPE_OPTIONS: { value: string; label: string }[] = [
    { value: 'insurance', label: 'Asuransi' },
    { value: 'allowance', label: 'Tunjangan' },
    { value: 'facility', label: 'Fasilitas' },
];

/** Indonesian label for a benefit type enum value. */
function typeLabel(type: string): string {
    return TYPE_OPTIONS.find((option) => option.value === type)?.label ?? type;
}

/* ---------- shared field styles (mirror pengguna.tsx) ---------- */

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

const iconBtn: CSSProperties = {
    width: 32,
    height: 32,
    border: `1px solid ${C.border}`,
    background: '#fff',
    borderRadius: 8,
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: '.15s',
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

/** Colored chip describing a benefit type. */
function TypeChip({ type }: { type: string }) {
    return (
        <span
            style={{
                display: 'inline-block',
                padding: '3px 10px',
                borderRadius: 100,
                fontSize: 11.5,
                fontWeight: 600,
                color: C.primary,
                background: 'rgba(47,84,201,.1)',
            }}
        >
            {typeLabel(type)}
        </span>
    );
}

/* ============================================================
 * Page
 * ============================================================ */

const TABS = [
    { key: 'master', label: 'Master Benefit', icon: 'gift' },
    { key: 'assign', label: 'Penetapan Karyawan', icon: 'user-check' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export default function AvanaBenefit({
    benefits,
    assignments,
    employees,
}: BenefitProps) {
    const { flash } = usePage<FlashProps>().props;

    const [activeTab, setActiveTab] = useState<TabKey>('master');

    const [benefitModalOpen, setBenefitModalOpen] = useState(false);
    const [editing, setEditing] = useState<BenefitRow | null>(null);
    const [confirm, setConfirm] = useState<BenefitRow | null>(null);

    const [assignModalOpen, setAssignModalOpen] = useState(false);
    const [unassignTarget, setUnassignTarget] = useState<AssignmentRow | null>(
        null,
    );

    const benefitForm = useForm<BenefitFormData>({ ...emptyBenefitForm });
    const assignForm = useForm<AssignFormData>({ ...emptyAssignForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    /* ---------- master benefit handlers ---------- */

    const openCreateBenefit = () => {
        setEditing(null);
        benefitForm.clearErrors();
        benefitForm.setData({ ...emptyBenefitForm });
        setBenefitModalOpen(true);
    };

    const openEditBenefit = (benefit: BenefitRow) => {
        setEditing(benefit);
        benefitForm.clearErrors();
        benefitForm.setData({
            code: benefit.code,
            name: benefit.name,
            type: benefit.type,
            value: String(benefit.value),
            description: benefit.description ?? '',
            status: benefit.status,
        });
        setBenefitModalOpen(true);
    };

    const closeBenefitModal = () => {
        setBenefitModalOpen(false);
        setEditing(null);
        benefitForm.reset();
        benefitForm.clearErrors();
    };

    const submitBenefit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = { onSuccess: () => closeBenefitModal() };

        if (editing) {
            benefitForm.submit(BenefitController.update(editing.id), options);
        } else {
            benefitForm.submit(BenefitController.store(), options);
        }
    };

    const deleteBenefit = () => {
        if (!confirm) {
            return;
        }

        router.delete(BenefitController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    /* ---------- assignment handlers ---------- */

    const openAssign = () => {
        assignForm.clearErrors();
        assignForm.setData({ ...emptyAssignForm });
        setAssignModalOpen(true);
    };

    const closeAssignModal = () => {
        setAssignModalOpen(false);
        assignForm.reset();
        assignForm.clearErrors();
    };

    const submitAssign = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        assignForm.submit(BenefitController.assign(), {
            onSuccess: () => closeAssignModal(),
        });
    };

    const deleteAssignment = () => {
        if (!unassignTarget) {
            return;
        }

        router.delete(BenefitController.unassign(unassignTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setUnassignTarget(null),
        });
    };

    /** Format an assignment period as "start – end". */
    const periodLabel = (row: AssignmentRow): string => {
        if (!row.start_date && !row.end_date) {
            return '—';
        }

        return `${row.start_date ?? '…'} – ${row.end_date ?? '…'}`;
    };

    return (
        <>
            <Head title="Benefit" />
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
                            <span>Manajemen</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Benefit</span>
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
                            Benefit Management
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola master benefit &amp; penetapannya ke
                            karyawan.
                        </div>
                    </div>
                    {activeTab === 'master' ? (
                        <button onClick={openCreateBenefit} style={btnP}>
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Benefit
                        </button>
                    ) : (
                        <button onClick={openAssign} style={btnP}>
                            <AIcon name="plus" size={16} color="#fff" />
                            Tetapkan
                        </button>
                    )}
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
                        const active = item.key === activeTab;

                        return (
                            <button
                                key={item.key}
                                onClick={() => setActiveTab(item.key)}
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

                {/* Master Benefit tab */}
                {activeTab === 'master' && (
                    <div style={{ ...card, overflow: 'hidden' }}>
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
                                        <th style={thCell}>Jenis</th>
                                        <th style={thCell}>Nilai</th>
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
                                    {benefits.length === 0 && (
                                        <tr
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                colSpan={6}
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
                                                        name="gift"
                                                        size={28}
                                                        color={C.faint}
                                                    />
                                                    <div>
                                                        Belum ada benefit.
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {benefits.map((benefit) => (
                                        <tr
                                            key={benefit.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {benefit.code}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.text,
                                                }}
                                            >
                                                {benefit.name}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <TypeChip type={benefit.type} />
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.text,
                                                }}
                                            >
                                                {rp(benefit.value)}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <StatusPill
                                                    status={benefit.status}
                                                />
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 18px',
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        display: 'inline-flex',
                                                        gap: 6,
                                                    }}
                                                >
                                                    <button
                                                        title="Ubah"
                                                        onClick={() =>
                                                            openEditBenefit(
                                                                benefit,
                                                            )
                                                        }
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name="pencil"
                                                            size={15}
                                                            color={C.muted}
                                                        />
                                                    </button>
                                                    <button
                                                        title="Hapus"
                                                        onClick={() =>
                                                            setConfirm(benefit)
                                                        }
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name="trash-2"
                                                            size={15}
                                                            color={C.red}
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Penetapan Karyawan tab */}
                {activeTab === 'assign' && (
                    <div style={{ ...card, overflow: 'hidden' }}>
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
                                        <th style={thCell}>Karyawan</th>
                                        <th style={thCell}>Benefit</th>
                                        <th style={thCell}>Periode</th>
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
                                    {assignments.length === 0 && (
                                        <tr
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                colSpan={5}
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
                                                        name="user-check"
                                                        size={28}
                                                        color={C.faint}
                                                    />
                                                    <div>
                                                        Belum ada penetapan
                                                        benefit.
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {assignments.map((row) => (
                                        <tr
                                            key={row.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: 13.5,
                                                        fontWeight: 600,
                                                        color: C.navy,
                                                    }}
                                                >
                                                    {row.employee?.name ?? '—'}
                                                </div>
                                                <div
                                                    style={{
                                                        fontSize: 12,
                                                        color: C.faint,
                                                    }}
                                                >
                                                    {row.employee
                                                        ?.employee_number ?? ''}
                                                </div>
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: 13,
                                                        color: C.text,
                                                        marginBottom: 4,
                                                    }}
                                                >
                                                    {row.benefit?.name ?? '—'}
                                                </div>
                                                {row.benefit && (
                                                    <TypeChip
                                                        type={row.benefit.type}
                                                    />
                                                )}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 16px',
                                                    fontSize: 13,
                                                    color: C.muted,
                                                }}
                                            >
                                                {periodLabel(row)}
                                            </td>
                                            <td
                                                style={{ padding: '13px 16px' }}
                                            >
                                                <StatusPill
                                                    status={row.status}
                                                />
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 18px',
                                                    textAlign: 'right',
                                                }}
                                            >
                                                <button
                                                    title="Batalkan"
                                                    onClick={() =>
                                                        setUnassignTarget(row)
                                                    }
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="trash-2"
                                                        size={15}
                                                        color={C.red}
                                                    />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* Create / edit benefit modal */}
            {benefitModalOpen && (
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
                        onClick={closeBenefitModal}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitBenefit}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 460,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            {editing ? 'Ubah Benefit' : 'Tambah Benefit'}
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Tentukan kode, jenis, dan nilai benefit.
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Kode <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={benefitForm.data.code}
                                    onChange={(event) =>
                                        benefitForm.setData(
                                            'code',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="BNF-001"
                                    style={withError(
                                        inputStyle,
                                        !!benefitForm.errors.code,
                                    )}
                                />
                                <FieldError message={benefitForm.errors.code} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Nama <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="text"
                                    value={benefitForm.data.name}
                                    onChange={(event) =>
                                        benefitForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Nama benefit"
                                    style={withError(
                                        inputStyle,
                                        !!benefitForm.errors.name,
                                    )}
                                />
                                <FieldError message={benefitForm.errors.name} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Jenis{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={benefitForm.data.type}
                                    onChange={(event) =>
                                        benefitForm.setData(
                                            'type',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!benefitForm.errors.type,
                                    )}
                                >
                                    {TYPE_OPTIONS.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={benefitForm.errors.type} />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Nilai (Rp){' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="number"
                                    min={0}
                                    value={benefitForm.data.value}
                                    onChange={(event) =>
                                        benefitForm.setData(
                                            'value',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0"
                                    style={withError(
                                        inputStyle,
                                        !!benefitForm.errors.value,
                                    )}
                                />
                                <FieldError
                                    message={benefitForm.errors.value}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Deskripsi</label>
                                <textarea
                                    value={benefitForm.data.description}
                                    onChange={(event) =>
                                        benefitForm.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Keterangan benefit (opsional)"
                                    style={withError(
                                        textareaStyle,
                                        !!benefitForm.errors.description,
                                    )}
                                />
                                <FieldError
                                    message={benefitForm.errors.description}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Status{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={benefitForm.data.status}
                                    onChange={(event) =>
                                        benefitForm.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!benefitForm.errors.status,
                                    )}
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                                <FieldError
                                    message={benefitForm.errors.status}
                                />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeBenefitModal}
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
                                type="submit"
                                disabled={benefitForm.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: benefitForm.processing ? 0.7 : 1,
                                    cursor: benefitForm.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <AIcon
                                    name={editing ? 'check' : 'plus'}
                                    size={16}
                                    color="#fff"
                                />
                                {editing ? 'Simpan' : 'Tambah'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Assign modal */}
            {assignModalOpen && (
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
                        onClick={closeAssignModal}
                        style={{
                            position: 'absolute',
                            inset: 0,
                            background: 'rgba(14,26,58,.45)',
                        }}
                    />
                    <form
                        onSubmit={submitAssign}
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth: 460,
                            maxHeight: '90vh',
                            overflowY: 'auto',
                            background: '#fff',
                            borderRadius: 14,
                            boxShadow: '0 20px 50px rgba(15,23,42,.25)',
                            padding: 26,
                            animation: 'toastIn .2s ease',
                        }}
                    >
                        <div
                            style={{
                                fontSize: 18,
                                fontWeight: 600,
                                color: C.navy,
                                marginBottom: 4,
                            }}
                        >
                            Tetapkan Benefit
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            Pilih karyawan dan benefit yang akan ditetapkan.
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            <div>
                                <label style={fieldLabelStyle}>
                                    Karyawan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={assignForm.data.employee_id}
                                    onChange={(event) =>
                                        assignForm.setData(
                                            'employee_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!assignForm.errors.employee_id,
                                    )}
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.name}
                                            {employee.employee_number
                                                ? ` (${employee.employee_number})`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={assignForm.errors.employee_id}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Benefit{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={assignForm.data.benefit_id}
                                    onChange={(event) =>
                                        assignForm.setData(
                                            'benefit_id',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!assignForm.errors.benefit_id,
                                    )}
                                >
                                    <option value="">Pilih benefit</option>
                                    {benefits.map((benefit) => (
                                        <option
                                            key={benefit.id}
                                            value={String(benefit.id)}
                                        >
                                            {benefit.name} —{' '}
                                            {typeLabel(benefit.type)}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={assignForm.errors.benefit_id}
                                />
                            </div>

                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 14,
                                }}
                            >
                                <div>
                                    <label style={fieldLabelStyle}>Mulai</label>
                                    <input
                                        type="date"
                                        value={assignForm.data.start_date}
                                        onChange={(event) =>
                                            assignForm.setData(
                                                'start_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!assignForm.errors.start_date,
                                        )}
                                    />
                                    <FieldError
                                        message={assignForm.errors.start_date}
                                    />
                                </div>
                                <div>
                                    <label style={fieldLabelStyle}>
                                        Selesai
                                    </label>
                                    <input
                                        type="date"
                                        value={assignForm.data.end_date}
                                        onChange={(event) =>
                                            assignForm.setData(
                                                'end_date',
                                                event.target.value,
                                            )
                                        }
                                        style={withError(
                                            inputStyle,
                                            !!assignForm.errors.end_date,
                                        )}
                                    />
                                    <FieldError
                                        message={assignForm.errors.end_date}
                                    />
                                </div>
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>Catatan</label>
                                <textarea
                                    value={assignForm.data.notes}
                                    onChange={(event) =>
                                        assignForm.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Catatan (opsional)"
                                    style={withError(
                                        textareaStyle,
                                        !!assignForm.errors.notes,
                                    )}
                                />
                                <FieldError message={assignForm.errors.notes} />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeAssignModal}
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
                                type="submit"
                                disabled={assignForm.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: assignForm.processing ? 0.7 : 1,
                                    cursor: assignForm.processing
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Tetapkan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete benefit modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus benefit?"
                    body={
                        <>
                            Benefit{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.name}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteBenefit}
                />
            )}

            {/* Confirm unassign modal */}
            {unassignTarget && (
                <ConfirmModal
                    title="Batalkan penetapan?"
                    body={
                        <>
                            Penetapan benefit untuk{' '}
                            <strong style={{ color: C.text }}>
                                {unassignTarget.employee?.name ??
                                    'karyawan ini'}
                            </strong>{' '}
                            akan dibatalkan.
                        </>
                    }
                    onCancel={() => setUnassignTarget(null)}
                    onConfirm={deleteAssignment}
                />
            )}
        </>
    );
}

/* ============================================================
 * Shared confirm modal
 * ============================================================ */

interface ConfirmModalProps {
    title: string;
    body: React.ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
}

function ConfirmModal({ title, body, onCancel, onConfirm }: ConfirmModalProps) {
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
