import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ShiftSwapController from '@/actions/App/Http/Controllers/Avana/ShiftSwapController';
import { ActionBtn, AIcon, btnOut, btnP, C, card, statusBadge, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    FieldError,
    fieldLabelStyle,
    inputStyle,
    KpiRow,
    PageHeader,
    selectStyle,
    textareaStyle,
    withError,
} from './components';
import { emptySwapForm } from './types';
import type { ShiftSwapIndexProps, SwapFormData, SwapRow } from './types';
import type { FlashProps } from './types';

const cellStyle = { padding: '13px 16px', fontSize: 13, color: C.text } as const;

export default function ShiftSwapIndex({ swaps, employees, shifts, kpis }: ShiftSwapIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [modalOpen, setModalOpen] = useState(false);
    const [confirm, setConfirm] = useState<SwapRow | null>(null);

    const form = useForm<SwapFormData>({ ...emptySwapForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const openModal = () => {
        form.clearErrors();
        form.setData({
            ...emptySwapForm,
            requester_id: employees[0] ? String(employees[0].id) : '',
            target_id: employees[1] ? String(employees[1].id) : '',
            date: new Date().toISOString().slice(0, 10),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit(ShiftSwapController.store(), {
            onSuccess: () => closeModal(),
        });
    };

    const approve = (swap: SwapRow) => {
        router.post(ShiftSwapController.approve(swap.id).url, {}, { preserveScroll: true });
    };

    const reject = (swap: SwapRow) => {
        router.post(ShiftSwapController.reject(swap.id).url, {}, { preserveScroll: true });
    };

    const deleteSwap = () => {
        if (!confirm) {
            return;
        }

        router.delete(ShiftSwapController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    return (
        <>
            <Head title="Tukar Shift" />
            <div style={{ padding: '28px 32px' }}>
                <PageHeader
                    crumb="Manajemen"
                    title="Tukar Shift"
                    subtitle="Kelola pengajuan pertukaran shift antar karyawan."
                    actions={
                        <button
                            onClick={openModal}
                            disabled={employees.length < 2}
                            style={{
                                ...btnP,
                                opacity: employees.length < 2 ? 0.6 : 1,
                                cursor: employees.length < 2 ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Ajukan Tukar Shift
                        </button>
                    }
                />

                <KpiRow
                    items={[
                        {
                            label: 'Menunggu',
                            value: kpis.pending,
                            icon: 'clock',
                            color: C.amber,
                        },
                        {
                            label: 'Disetujui',
                            value: kpis.approved,
                            icon: 'circle-check',
                            color: C.green,
                        },
                        {
                            label: 'Ditolak',
                            value: kpis.rejected,
                            icon: 'circle-x',
                            color: C.red,
                        },
                        {
                            label: 'Total Pengajuan',
                            value: kpis.total,
                            icon: 'repeat',
                            color: C.primary,
                        },
                    ]}
                />

                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 880 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Pemohon</th>
                                    <th style={thCell}>Tujuan</th>
                                    <th style={thCell}>Tanggal</th>
                                    <th style={thCell}>Shift</th>
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
                                {swaps.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
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
                                                <AIcon name="repeat" size={28} color={C.faint} />
                                                <div>Belum ada pengajuan tukar shift.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {swaps.map((swap) => {
                                    const badge = statusBadge(swap.status_label);

                                    return (
                                        <tr
                                            key={swap.id}
                                            style={{ borderTop: `1px solid ${C.line}` }}
                                        >
                                            <td
                                                style={{
                                                    ...cellStyle,
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {swap.requester ?? '—'}
                                            </td>
                                            <td style={cellStyle}>{swap.target ?? '—'}</td>
                                            <td style={cellStyle}>{swap.date ?? '—'}</td>
                                            <td style={cellStyle}>
                                                {(swap.requester_shift ?? '—') +
                                                    ' → ' +
                                                    (swap.target_shift ?? '—')}
                                            </td>
                                            <td style={{ padding: '13px 16px' }}>
                                                <span
                                                    style={{
                                                        display: 'inline-block',
                                                        padding: '3px 10px',
                                                        borderRadius: 100,
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                        color: badge.color,
                                                        background: badge.bg,
                                                    }}
                                                >
                                                    {swap.status_label}
                                                </span>
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
                                                        justifyContent: 'flex-end',
                                                    }}
                                                >
                                                    {swap.status === 'pending' && (
                                                        <>
                                                            <ActionBtn
                                                                icon="check"
                                                                label="Setujui"
                                                                variant="success"
                                                                onClick={() => approve(swap)}
                                                            />
                                                            <ActionBtn
                                                                icon="x"
                                                                label="Tolak"
                                                                variant="warning"
                                                                onClick={() => reject(swap)}
                                                            />
                                                        </>
                                                    )}
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        onClick={() => setConfirm(swap)}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Create swap modal */}
            {modalOpen && (
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
                        onClick={closeModal}
                        style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }}
                    />
                    <form
                        onSubmit={submit}
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
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>
                            Ajukan Tukar Shift
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Pemohon <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.requester_id}
                                    onChange={(event) =>
                                        form.setData('requester_id', event.target.value)
                                    }
                                    style={withError(selectStyle, !!form.errors.requester_id)}
                                >
                                    <option value="">Pilih</option>
                                    {employees.map((employee) => (
                                        <option key={employee.id} value={String(employee.id)}>
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.requester_id} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>
                                    Karyawan Tujuan <span style={{ color: C.red }}>*</span>
                                </label>
                                <select
                                    value={form.data.target_id}
                                    onChange={(event) =>
                                        form.setData('target_id', event.target.value)
                                    }
                                    style={withError(selectStyle, !!form.errors.target_id)}
                                >
                                    <option value="">Pilih</option>
                                    {employees.map((employee) => (
                                        <option key={employee.id} value={String(employee.id)}>
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.target_id} />
                            </div>
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>
                                Tanggal <span style={{ color: C.red }}>*</span>
                            </label>
                            <input
                                type="date"
                                value={form.data.date}
                                onChange={(event) => form.setData('date', event.target.value)}
                                style={withError(inputStyle, !!form.errors.date)}
                            />
                            <FieldError message={form.errors.date} />
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                            <div>
                                <label style={fieldLabelStyle}>Shift Pemohon</label>
                                <select
                                    value={form.data.requester_shift_id}
                                    onChange={(event) =>
                                        form.setData('requester_shift_id', event.target.value)
                                    }
                                    style={withError(
                                        selectStyle,
                                        !!form.errors.requester_shift_id,
                                    )}
                                >
                                    <option value="">—</option>
                                    {shifts.map((shift) => (
                                        <option key={shift.id} value={String(shift.id)}>
                                            {shift.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.requester_shift_id} />
                            </div>
                            <div>
                                <label style={fieldLabelStyle}>Shift Tujuan</label>
                                <select
                                    value={form.data.target_shift_id}
                                    onChange={(event) =>
                                        form.setData('target_shift_id', event.target.value)
                                    }
                                    style={withError(selectStyle, !!form.errors.target_shift_id)}
                                >
                                    <option value="">—</option>
                                    {shifts.map((shift) => (
                                        <option key={shift.id} value={String(shift.id)}>
                                            {shift.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={form.errors.target_shift_id} />
                            </div>
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>Alasan</label>
                            <textarea
                                value={form.data.reason}
                                onChange={(event) => form.setData('reason', event.target.value)}
                                placeholder="Alasan pertukaran (opsional)"
                                style={withError(textareaStyle, !!form.errors.reason)}
                            />
                            <FieldError message={form.errors.reason} />
                        </div>

                        <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
                            <button
                                type="button"
                                onClick={closeModal}
                                style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                style={{
                                    ...btnP,
                                    flex: 1,
                                    height: 44,
                                    justifyContent: 'center',
                                    opacity: form.processing ? 0.7 : 1,
                                    cursor: form.processing ? 'not-allowed' : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Confirm delete */}
            {confirm && (
                <ConfirmModal
                    title="Hapus pengajuan?"
                    body={
                        <>
                            Pengajuan tukar shift{' '}
                            <strong style={{ color: C.text }}>{confirm.requester}</strong> akan
                            dihapus.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteSwap}
                />
            )}
        </>
    );
}
