import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import AssetController from '@/actions/App/Http/Controllers/Avana/AssetController';
import { AIcon, btnP, C, card, rp, thCell } from '@/lib/avana';
import {
    ConditionBadge,
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    iconBtn,
    inputStyle,
    selectStyle,
    StatusPill,
    textareaStyle,
    withError,
} from './components';
import { emptyAssignForm } from './types';
import type {
    AsetIndexProps,
    AssetRow,
    AssignFormData,
    FlashProps,
} from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 170px',
};

const cellStyle: CSSProperties = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
};

export default function AsetIndex({
    assets,
    assignments,
    employees,
    categories,
    conditions,
    statuses,
    kpis,
}: AsetIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<AssetRow | null>(null);
    const [assignTarget, setAssignTarget] = useState<AssetRow | null>(null);

    const assignForm = useForm<AssignFormData>({ ...emptyAssignForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const deleteAsset = () => {
        if (!confirm) {
            return;
        }

        router.delete(AssetController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const openAssign = (asset: AssetRow) => {
        assignForm.clearErrors();
        assignForm.setData({
            ...emptyAssignForm,
            employee_id: employees[0] ? String(employees[0].id) : '',
            assigned_date: new Date().toISOString().slice(0, 10),
        });
        setAssignTarget(asset);
    };

    const closeAssign = () => {
        setAssignTarget(null);
        assignForm.reset();
        assignForm.clearErrors();
    };

    const submitAssign = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!assignTarget) {
            return;
        }

        assignForm.post(AssetController.assign(assignTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => closeAssign(),
        });
    };

    /** Return a currently assigned asset by its active assignment. */
    const returnAsset = (assignmentId: number) => {
        router.post(
            AssetController.returnAsset(assignmentId).url,
            {},
            { preserveScroll: true },
        );
    };

    const kpiItems = [
        {
            label: 'Total Aset',
            value: String(kpis.total),
            icon: 'package',
            color: C.primary,
        },
        {
            label: 'Tersedia',
            value: String(kpis.available),
            icon: 'circle-check',
            color: C.green,
        },
        {
            label: 'Dipakai',
            value: String(kpis.assigned),
            icon: 'user-check',
            color: C.sky,
        },
        {
            label: 'Perbaikan',
            value: String(kpis.maintenance),
            icon: 'wrench',
            color: C.amber,
        },
        {
            label: 'Total Nilai',
            value: rp(kpis.total_value),
            icon: 'wallet',
            color: C.navy,
        },
    ];

    return (
        <>
            <Head title="Manajemen Aset" />
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
                            <span style={{ color: C.muted }}>Aset</span>
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
                            Manajemen Aset
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola inventaris aset &amp; penugasan ke karyawan.
                        </div>
                    </div>
                    <Link
                        href={AssetController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Aset
                    </Link>
                </div>

                {/* KPI cards */}
                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 14,
                        marginBottom: 22,
                    }}
                >
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    marginBottom: 10,
                                }}
                            >
                                <div
                                    style={{
                                        width: 34,
                                        height: 34,
                                        borderRadius: 9,
                                        background: `${item.color}1a`,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <AIcon
                                        name={item.icon}
                                        size={17}
                                        color={item.color}
                                    />
                                </div>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: C.muted,
                                        fontWeight: 500,
                                    }}
                                >
                                    {item.label}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 22,
                                    fontWeight: 700,
                                    color: C.navy,
                                    letterSpacing: '-.02em',
                                }}
                            >
                                {item.value}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Assets table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Daftar Aset
                </div>
                <div style={{ ...card, overflow: 'hidden', marginBottom: 28 }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 920,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Kode</th>
                                    <th style={thCell}>Nama</th>
                                    <th style={thCell}>Kategori</th>
                                    <th style={thCell}>Kondisi</th>
                                    <th style={thCell}>Status</th>
                                    <th style={thCell}>Nilai Buku</th>
                                    <th style={thCell}>Pemegang</th>
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
                                {assets.length === 0 && (
                                    <tr
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            colSpan={8}
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
                                                    name="package"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada aset.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {assets.map((asset) => (
                                    <tr
                                        key={asset.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {asset.code}
                                        </td>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {asset.name}
                                        </td>
                                        <td style={cellStyle}>
                                            {asset.category}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <ConditionBadge
                                                condition={asset.condition}
                                            />
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <StatusPill status={asset.status} />
                                        </td>
                                        <td style={cellStyle}>
                                            <div
                                                style={{
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {rp(asset.book_value)}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {rp(asset.purchase_cost)} ·{' '}
                                                {asset.depreciation_years} thn
                                            </div>
                                        </td>
                                        <td style={cellStyle}>
                                            {asset.current_assignment ? (
                                                <div>
                                                    <div
                                                        style={{
                                                            fontWeight: 600,
                                                            color: C.text,
                                                        }}
                                                    >
                                                        {asset
                                                            .current_assignment
                                                            .employee_name ??
                                                            '—'}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                            marginTop: 2,
                                                        }}
                                                    >
                                                        {asset
                                                            .current_assignment
                                                            .employee_number ??
                                                            ''}
                                                    </div>
                                                </div>
                                            ) : (
                                                <span
                                                    style={{ color: C.faint }}
                                                >
                                                    —
                                                </span>
                                            )}
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
                                                {asset.current_assignment ? (
                                                    <button
                                                        title="Kembalikan aset"
                                                        onClick={() =>
                                                            returnAsset(
                                                                asset
                                                                    .current_assignment!
                                                                    .id,
                                                            )
                                                        }
                                                        style={iconBtn}
                                                    >
                                                        <AIcon
                                                            name="undo-2"
                                                            size={15}
                                                            color={C.amber}
                                                        />
                                                    </button>
                                                ) : (
                                                    <button
                                                        title="Tugaskan ke karyawan"
                                                        onClick={() =>
                                                            openAssign(asset)
                                                        }
                                                        disabled={
                                                            employees.length ===
                                                            0
                                                        }
                                                        style={{
                                                            ...iconBtn,
                                                            opacity:
                                                                employees.length ===
                                                                0
                                                                    ? 0.5
                                                                    : 1,
                                                            cursor:
                                                                employees.length ===
                                                                0
                                                                    ? 'not-allowed'
                                                                    : 'pointer',
                                                        }}
                                                    >
                                                        <AIcon
                                                            name="user-plus"
                                                            size={15}
                                                            color={C.primary}
                                                        />
                                                    </button>
                                                )}
                                                <Link
                                                    title="Ubah"
                                                    href={AssetController.edit(
                                                        asset.id,
                                                    )}
                                                    style={iconBtn}
                                                >
                                                    <AIcon
                                                        name="pencil"
                                                        size={15}
                                                        color={C.muted}
                                                    />
                                                </Link>
                                                <button
                                                    title="Hapus"
                                                    onClick={() =>
                                                        setConfirm(asset)
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

                {/* Active assignments */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Penugasan Aktif
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 760,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Aset</th>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Tgl Ditugaskan</th>
                                    <th style={thCell}>Catatan</th>
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
                                                padding: '40px 18px',
                                                textAlign: 'center',
                                                fontSize: 13.5,
                                                color: C.muted,
                                            }}
                                        >
                                            Belum ada penugasan aktif.
                                        </td>
                                    </tr>
                                )}
                                {assignments.map((assignment) => (
                                    <tr
                                        key={assignment.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                ...cellStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {assignment.asset
                                                ? `${assignment.asset.code} · ${assignment.asset.name}`
                                                : '—'}
                                        </td>
                                        <td style={cellStyle}>
                                            {assignment.employee?.name ?? '—'}
                                            {assignment.employee
                                                ?.employee_number && (
                                                <span
                                                    style={{
                                                        color: C.faint,
                                                        marginLeft: 6,
                                                        fontSize: 11.5,
                                                    }}
                                                >
                                                    (
                                                    {
                                                        assignment.employee
                                                            .employee_number
                                                    }
                                                    )
                                                </span>
                                            )}
                                        </td>
                                        <td style={cellStyle}>
                                            {assignment.assigned_date ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                color: C.muted,
                                            }}
                                        >
                                            {assignment.condition_note ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '13px 18px',
                                                textAlign: 'right',
                                            }}
                                        >
                                            <button
                                                title="Kembalikan aset"
                                                onClick={() =>
                                                    returnAsset(assignment.id)
                                                }
                                                style={iconBtn}
                                            >
                                                <AIcon
                                                    name="undo-2"
                                                    size={15}
                                                    color={C.amber}
                                                />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Assign asset modal */}
            {assignTarget && (
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
                        onClick={closeAssign}
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
                            maxWidth: 440,
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
                            Tugaskan Aset
                        </div>
                        <div
                            style={{
                                fontSize: 13,
                                color: C.muted,
                                marginBottom: 18,
                            }}
                        >
                            {assignTarget.code} · {assignTarget.name}
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
                                    Tanggal Ditugaskan{' '}
                                    <span style={{ color: C.red }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    value={assignForm.data.assigned_date}
                                    onChange={(event) =>
                                        assignForm.setData(
                                            'assigned_date',
                                            event.target.value,
                                        )
                                    }
                                    style={withError(
                                        inputStyle,
                                        !!assignForm.errors.assigned_date,
                                    )}
                                />
                                <FieldError
                                    message={assignForm.errors.assigned_date}
                                />
                            </div>

                            <div>
                                <label style={fieldLabelStyle}>
                                    Catatan Kondisi
                                </label>
                                <textarea
                                    value={assignForm.data.condition_note}
                                    onChange={(event) =>
                                        assignForm.setData(
                                            'condition_note',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Catatan kondisi saat diserahkan (opsional)"
                                    style={withError(
                                        textareaStyle,
                                        !!assignForm.errors.condition_note,
                                    )}
                                />
                                <FieldError
                                    message={assignForm.errors.condition_note}
                                />
                            </div>
                        </div>

                        <div
                            style={{ display: 'flex', gap: 10, marginTop: 22 }}
                        >
                            <button
                                type="button"
                                onClick={closeAssign}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    background: '#fff',
                                    color: C.text,
                                    border: `1px solid ${C.border}`,
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
                                Tugaskan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete asset modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus aset?"
                    body={
                        <>
                            Aset{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.code} · {confirm.name}
                            </strong>{' '}
                            beserta riwayat penugasannya akan dihapus. Tindakan
                            ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteAsset}
                />
            )}
        </>
    );
}
