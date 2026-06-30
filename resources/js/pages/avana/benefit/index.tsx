import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import BenefitController from '@/actions/App/Http/Controllers/Avana/BenefitController';
import { AIcon, ActionBtn, btnOut, btnP, C, card, rp, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    selectStyle,
    StatusPill,
    textareaStyle,
    TypeChip,
    withError,
} from './components';
import { emptyAssignForm, typeLabel } from './types';
import type {
    AssignFormData,
    AssignmentRow,
    BenefitIndexProps,
    BenefitRow,
    FlashProps,
} from './types';

const TABS = [
    { key: 'master', label: 'Master Benefit', icon: 'gift' },
    { key: 'assign', label: 'Penetapan Karyawan', icon: 'user-check' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export default function BenefitIndex({
    benefits,
    assignments,
    employees,
}: BenefitIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [activeTab, setActiveTab] = useState<TabKey>('master');
    const [confirm, setConfirm] = useState<BenefitRow | null>(null);
    const [assignModalOpen, setAssignModalOpen] = useState(false);
    const [unassignTarget, setUnassignTarget] = useState<AssignmentRow | null>(
        null,
    );

    const assignForm = useForm<AssignFormData>({ ...emptyAssignForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const deleteBenefit = () => {
        if (!confirm) {
            return;
        }

        router.delete(BenefitController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

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
                        <Link
                            href={BenefitController.create()}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Tambah Benefit
                        </Link>
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
                                            <td style={{ padding: '13px 16px' }}>
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
                                            <td style={{ padding: '13px 16px' }}>
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
                                                        flexWrap: 'wrap',
                                                        justifyContent:
                                                            'flex-end',
                                                    }}
                                                >
                                                    <ActionBtn
                                                        icon="pencil"
                                                        label="Edit"
                                                        variant="neutral"
                                                        onClick={() =>
                                                            router.visit(
                                                                BenefitController.edit(
                                                                    benefit.id,
                                                                ).url,
                                                            )
                                                        }
                                                    />
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        onClick={() =>
                                                            setConfirm(benefit)
                                                        }
                                                    />
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
                                            <td style={{ padding: '13px 16px' }}>
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
                                            <td style={{ padding: '13px 16px' }}>
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
                                            <td style={{ padding: '13px 16px' }}>
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
                                                <ActionBtn
                                                    icon="x"
                                                    label="Batalkan"
                                                    variant="warning"
                                                    onClick={() =>
                                                        setUnassignTarget(row)
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

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
