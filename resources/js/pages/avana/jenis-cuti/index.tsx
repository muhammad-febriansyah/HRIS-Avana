import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import LeaveTypeController from '@/actions/App/Http/Controllers/Avana/LeaveTypeController';
import { AIcon, btnP, C, card, thCell } from '@/lib/avana';
import {
    ConfirmModal,
    iconBtn,
    StatusPill,
    YesNoPill,
} from './components';
import type {
    FlashProps,
    JenisCutiIndexProps,
    LeaveTypeRow,
} from './types';

export default function JenisCutiIndex({ leaveTypes }: JenisCutiIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<LeaveTypeRow | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

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
                    <Link
                        href={LeaveTypeController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Jenis Cuti
                    </Link>
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
                                                    display: 'inline-flex',
                                                    gap: 6,
                                                }}
                                            >
                                                <Link
                                                    title="Ubah"
                                                    href={LeaveTypeController.edit(
                                                        row.id,
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
                                                        setConfirm(row)
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
            </div>

            {/* Confirm delete modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus jenis cuti?"
                    body={
                        <>
                            Jenis cuti{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.name}
                            </strong>{' '}
                            akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteRecord}
                />
            )}
        </>
    );
}
