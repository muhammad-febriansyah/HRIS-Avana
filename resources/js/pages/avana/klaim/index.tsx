import { Head, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ClaimController from '@/actions/App/Http/Controllers/Avana/ClaimController';
import { AIcon, ActionBtn, btnP, C, card, rp, thCell } from '@/lib/avana';
import { ConfirmModal, StatusBadge, TypeChip } from './components';
import type { ClaimRow, FlashProps, KlaimIndexProps } from './types';

const kpiCardStyle: CSSProperties = {
    ...card,
    padding: '18px 20px',
    flex: '1 1 180px',
};

const tdStyle: CSSProperties = {
    padding: '13px 16px',
    fontSize: 13,
    color: C.text,
};

export default function KlaimIndex({ claims, kpis }: KlaimIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    const [confirm, setConfirm] = useState<ClaimRow | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const deleteClaim = () => {
        if (!confirm) {
            return;
        }

        router.delete(ClaimController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    /** Fire a status-transition POST (approve / reject / pay) for a claim. */
    const transition = (url: string) => {
        router.post(url, {}, { preserveScroll: true });
    };

    const kpiItems = [
        {
            label: 'Menunggu',
            value: String(kpis.pending),
            icon: 'clock',
            color: C.amber,
        },
        {
            label: 'Disetujui',
            value: String(kpis.approved),
            icon: 'circle-check',
            color: C.green,
        },
        {
            label: 'Dibayar',
            value: String(kpis.paid),
            icon: 'banknote',
            color: C.primary,
        },
        {
            label: 'Total Nominal',
            value: rp(kpis.total_amount),
            icon: 'wallet',
            color: C.sky,
        },
    ];

    return (
        <>
            <Head title="Klaim & Reimbursement" />
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
                            <span style={{ color: C.muted }}>
                                Klaim &amp; Reimbursement
                            </span>
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
                            Klaim &amp; Reimbursement
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola pengajuan klaim &amp; proses pembayaran.
                        </div>
                    </div>
                    <Link
                        href={ClaimController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Klaim
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
                                    fontSize: 26,
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

                {/* Claims table */}
                <div
                    style={{
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 12,
                    }}
                >
                    Daftar Klaim
                </div>
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 880,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Karyawan</th>
                                    <th style={thCell}>Jenis</th>
                                    <th style={thCell}>Nominal</th>
                                    <th style={thCell}>Tanggal</th>
                                    <th style={thCell}>Bukti</th>
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
                                {claims.length === 0 && (
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
                                                    name="receipt"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>Belum ada klaim.</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {claims.map((claim) => (
                                    <tr
                                        key={claim.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td style={tdStyle}>
                                            <div
                                                style={{
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                }}
                                            >
                                                {claim.employee?.name ?? '—'}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: C.faint,
                                                    marginTop: 2,
                                                }}
                                            >
                                                {claim.title}
                                            </div>
                                        </td>
                                        <td style={tdStyle}>
                                            <TypeChip type={claim.claim_type} />
                                        </td>
                                        <td
                                            style={{
                                                ...tdStyle,
                                                fontWeight: 600,
                                                color: C.navy,
                                            }}
                                        >
                                            {rp(claim.amount)}
                                        </td>
                                        <td style={tdStyle}>
                                            {claim.claim_date ?? '—'}
                                        </td>
                                        <td style={tdStyle}>
                                            {claim.receipt_url ? (
                                                <a
                                                    href={claim.receipt_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    title="Lihat bukti"
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 5,
                                                        fontSize: 12.5,
                                                        color: C.primary,
                                                        textDecoration: 'none',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="paperclip"
                                                        size={14}
                                                        color={C.primary}
                                                    />
                                                    Bukti
                                                </a>
                                            ) : (
                                                <span
                                                    style={{ color: C.faint }}
                                                >
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td style={{ padding: '13px 16px' }}>
                                            <StatusBadge status={claim.status} />
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
                                                {claim.status === 'pending' && (
                                                    <>
                                                        <ActionBtn
                                                            icon="check"
                                                            label="Setujui"
                                                            variant="success"
                                                            onClick={() =>
                                                                transition(
                                                                    ClaimController.approve(
                                                                        claim.id,
                                                                    ).url,
                                                                )
                                                            }
                                                        />
                                                        <ActionBtn
                                                            icon="x"
                                                            label="Tolak"
                                                            variant="warning"
                                                            onClick={() =>
                                                                transition(
                                                                    ClaimController.reject(
                                                                        claim.id,
                                                                    ).url,
                                                                )
                                                            }
                                                        />
                                                    </>
                                                )}
                                                {claim.status === 'approved' && (
                                                    <ActionBtn
                                                        icon="banknote"
                                                        label="Tandai Dibayar"
                                                        variant="success"
                                                        onClick={() =>
                                                            transition(
                                                                ClaimController.markPaid(
                                                                    claim.id,
                                                                ).url,
                                                            )
                                                        }
                                                    />
                                                )}
                                                <ActionBtn
                                                    icon="pencil"
                                                    label="Edit"
                                                    variant="neutral"
                                                    onClick={() =>
                                                        router.visit(
                                                            ClaimController.edit(
                                                                claim.id,
                                                            ).url,
                                                        )
                                                    }
                                                />
                                                <ActionBtn
                                                    icon="trash-2"
                                                    label="Hapus"
                                                    variant="danger"
                                                    onClick={() =>
                                                        setConfirm(claim)
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
            </div>

            {/* Confirm delete claim modal */}
            {confirm && (
                <ConfirmModal
                    title="Hapus klaim?"
                    body={
                        <>
                            Klaim{' '}
                            <strong style={{ color: C.text }}>
                                {confirm.title}
                            </strong>{' '}
                            beserta buktinya akan dihapus. Tindakan ini tidak
                            dapat dibatalkan.
                        </>
                    }
                    onCancel={() => setConfirm(null)}
                    onConfirm={deleteClaim}
                />
            )}
        </>
    );
}
