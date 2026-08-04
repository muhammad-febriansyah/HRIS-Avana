import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ReimbursementController from '@/actions/App/Http/Controllers/Avana/ReimbursementController';
import { ConfirmDialog } from '@/components/avana-ui/confirm-dialog';
import { FormDialog } from '@/components/avana-ui/form-dialog';
import { usePermission } from '@/hooks/use-permission';
import { AIcon, ActionBtn, btnP, C, card, rp } from '@/lib/avana';
import {
    CategoryPill,
    Field,
    KpiCard,
    selectStyle,
    StatusPill,
    textareaStyle,
    textInputStyle,
    withError,
} from './components';
import { STATUS_OPTIONS } from './types';
import type {
    FlashProps,
    ReimbursementFilters,
    ReimbursementIndexProps,
    ReimbursementRow,
} from './types';

const headThStyle: CSSProperties = {
    padding: '11px 16px',
    textAlign: 'left',
    fontSize: 11.5,
    fontWeight: 600,
    color: C.faint,
    textTransform: 'uppercase',
};

const cellStyle: CSSProperties = {
    padding: '12px 16px',
    fontSize: 12.5,
    color: C.muted,
};

const receiptLinkStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 4,
    fontSize: 12.5,
    color: C.primary,
    textDecoration: 'none',
    fontWeight: 500,
};

interface RejectForm {
    rejection_reason: string;
    [key: string]: string;
}

interface PayForm {
    payment_method: string;
    payment_reference: string;
    [key: string]: string;
}

export default function ReimbursementIndex({
    requests,
    filters,
    categories,
    paymentMethods,
    kpis,
}: ReimbursementIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const { can } = usePermission();
    const meta = requests.meta;

    const [rejecting, setRejecting] = useState<ReimbursementRow | null>(null);
    const [paying, setPaying] = useState<ReimbursementRow | null>(null);
    const [deleting, setDeleting] = useState<ReimbursementRow | null>(null);

    const rejectForm = useForm<RejectForm>({ rejection_reason: '' });

    const payForm = useForm<PayForm>({
        payment_method: paymentMethods[0]?.value ?? 'transfer',
        payment_reference: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const applyFilters = (next: Partial<ReimbursementFilters>) => {
        router.get(
            window.location.pathname,
            { ...filters, ...next, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const approve = (routeKey: string) => {
        router.post(
            ReimbursementController.approve(routeKey).url,
            {},
            { preserveScroll: true },
        );
    };

    const submitReject = () => {
        if (!rejecting) {
            return;
        }

        rejectForm.post(ReimbursementController.reject(rejecting.route_key).url, {
            preserveScroll: true,
            onSuccess: () => {
                setRejecting(null);
                rejectForm.reset();
            },
        });
    };

    const submitPay = () => {
        if (!paying) {
            return;
        }

        payForm.post(ReimbursementController.markPaid(paying.route_key).url, {
            preserveScroll: true,
            onSuccess: () => {
                setPaying(null);
                payForm.reset();
            },
        });
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }

        router.delete(ReimbursementController.destroy(deleting.route_key).url, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title="Reimbursement" />
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
                            <span>Finance</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Reimbursement
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
                            Reimbursement
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Penggantian biaya karyawan yang sudah ditalangi,
                            approval, dan pembayaran oleh Finance
                        </div>
                    </div>
                    {can('claim.create') && (
                        <Link
                            href={ReimbursementController.create()}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Ajukan Reimbursement
                        </Link>
                    )}
                </div>

                {/* KPIs */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(200px, 1fr))',
                        gap: 14,
                        marginBottom: 20,
                    }}
                >
                    <KpiCard
                        icon="clock"
                        label="Menunggu"
                        value={String(kpis.pending)}
                        accent="#D97706"
                    />
                    <KpiCard
                        icon="check"
                        label="Disetujui"
                        value={String(kpis.approved)}
                        accent="#16A34A"
                    />
                    <KpiCard
                        icon="hand-coins"
                        label="Dibayar"
                        value={String(kpis.paid)}
                        accent={C.primary}
                    />
                    <KpiCard
                        icon="wallet"
                        label="Total Perlu Dibayar"
                        value={rp(kpis.payable_amount)}
                        accent="#8b5cf6"
                    />
                </div>

                {/* List + approval */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div
                        style={{
                            padding: '16px 18px',
                            borderBottom: `1px solid ${C.border}`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 12,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Daftar Reimbursement
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 8,
                                alignItems: 'center',
                            }}
                        >
                            <div style={{ position: 'relative' }}>
                                <span
                                    style={{
                                        position: 'absolute',
                                        left: 10,
                                        top: '50%',
                                        transform: 'translateY(-50%)',
                                        display: 'inline-flex',
                                    }}
                                >
                                    <AIcon
                                        name="search"
                                        size={15}
                                        color={C.faint}
                                    />
                                </span>
                                <input
                                    type="search"
                                    placeholder="Cari nomor / judul / karyawan"
                                    defaultValue={filters.search ?? ''}
                                    onChange={(event) =>
                                        applyFilters({
                                            search:
                                                event.target.value || undefined,
                                        })
                                    }
                                    style={{
                                        height: 36,
                                        padding: '0 12px 0 32px',
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        fontSize: 12.5,
                                        color: C.text,
                                        outline: 'none',
                                        width: 200,
                                    }}
                                />
                            </div>
                            <select
                                value={filters.status ?? ''}
                                onChange={(event) =>
                                    applyFilters({
                                        status: (event.target.value ||
                                            undefined) as ReimbursementFilters['status'],
                                    })
                                }
                                style={{
                                    height: 36,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    color: C.muted,
                                    background: '#fff',
                                    outline: 'none',
                                    cursor: 'pointer',
                                }}
                            >
                                <option value="">Semua Status</option>
                                {STATUS_OPTIONS.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={filters.category ?? ''}
                                onChange={(event) =>
                                    applyFilters({
                                        category:
                                            event.target.value || undefined,
                                    })
                                }
                                style={{
                                    height: 36,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 8,
                                    fontSize: 12.5,
                                    color: C.muted,
                                    background: '#fff',
                                    outline: 'none',
                                    cursor: 'pointer',
                                }}
                            >
                                <option value="">Semua Kategori</option>
                                {categories.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div style={{ overflowX: 'auto' }}>
                        <table
                            style={{
                                width: '100%',
                                borderCollapse: 'collapse',
                                minWidth: 1040,
                            }}
                        >
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={headThStyle}>No.</th>
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Kategori</th>
                                    <th style={headThStyle}>Judul</th>
                                    <th style={headThStyle}>Jumlah</th>
                                    <th style={headThStyle}>Tanggal</th>
                                    <th style={headThStyle}>Status</th>
                                    <th
                                        style={{
                                            ...headThStyle,
                                            textAlign: 'right',
                                        }}
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {requests.data.length === 0 && (
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
                                                    name="receipt"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Tidak ada pengajuan
                                                    reimbursement.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {requests.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        style={{
                                            borderTop: `1px solid ${C.line}`,
                                        }}
                                    >
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 12.5,
                                                fontWeight: 500,
                                                color: C.text,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {row.number}
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
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
                                                            row.employee
                                                                ?.avatar_color ??
                                                            C.faint,
                                                        color: '#fff',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        fontSize: 11.5,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {row.employee?.initials ??
                                                        '?'}
                                                </div>
                                                <div>
                                                    <div
                                                        style={{
                                                            fontSize: 13,
                                                            fontWeight: 500,
                                                            color: C.text,
                                                        }}
                                                    >
                                                        {row.employee?.name ??
                                                            '—'}
                                                    </div>
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        {row.employee
                                                            ?.employee_number ??
                                                            ''}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <CategoryPill
                                                label={row.category_label}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                maxWidth: 240,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    color: C.text,
                                                    fontSize: 13,
                                                }}
                                            >
                                                {row.title}
                                            </div>
                                            {row.status === 'rejected' &&
                                                row.rejection_reason && (
                                                    <div
                                                        style={{
                                                            fontSize: 11.5,
                                                            color: C.red,
                                                            marginTop: 3,
                                                        }}
                                                    >
                                                        Ditolak:{' '}
                                                        {row.rejection_reason}
                                                    </div>
                                                )}
                                            {row.receipt_url && (
                                                <a
                                                    href={row.receipt_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    style={{
                                                        ...receiptLinkStyle,
                                                        marginTop: 4,
                                                    }}
                                                >
                                                    <AIcon
                                                        name="paperclip"
                                                        size={13}
                                                        color={C.primary}
                                                    />
                                                    Bukti
                                                </a>
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.text,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {rp(row.amount)}
                                        </td>
                                        <td
                                            style={{
                                                ...cellStyle,
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {row.expense_date ?? '—'}
                                        </td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <StatusPill
                                                label={row.status_label}
                                            />
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
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
                                                {row.status === 'pending' && (
                                                    <>
                                                        <ActionBtn
                                                            icon="check"
                                                            label="Setujui"
                                                            variant="primary"
                                                            onClick={() =>
                                                                approve(row.route_key)
                                                            }
                                                        />
                                                        <ActionBtn
                                                            icon="x"
                                                            label="Tolak"
                                                            variant="warning"
                                                            onClick={() =>
                                                                setRejecting(
                                                                    row,
                                                                )
                                                            }
                                                        />
                                                    </>
                                                )}
                                                {row.status === 'approved' &&
                                                    (row.self_approved ? (
                                                        <span
                                                            title="Anda yang menyetujui reimbursement ini — pembayaran harus dilakukan orang lain."
                                                            style={{
                                                                display:
                                                                    'inline-flex',
                                                                alignItems:
                                                                    'center',
                                                                gap: 5,
                                                                fontSize: 12,
                                                                color: C.faint,
                                                                whiteSpace:
                                                                    'nowrap',
                                                            }}
                                                        >
                                                            <AIcon
                                                                name="user-x"
                                                                size={14}
                                                                color={C.faint}
                                                            />
                                                            Menunggu orang lain
                                                        </span>
                                                    ) : (
                                                        <ActionBtn
                                                            icon="hand-coins"
                                                            label="Bayar"
                                                            variant="primary"
                                                            onClick={() =>
                                                                setPaying(row)
                                                            }
                                                        />
                                                    ))}
                                                {(row.status === 'pending' ||
                                                    row.status ===
                                                        'rejected') && (
                                                    <>
                                                        <ActionBtn
                                                            icon="pencil"
                                                            label="Ubah"
                                                            variant="success"
                                                            onClick={() =>
                                                                router.visit(
                                                                    ReimbursementController.edit(row.route_key,
                                                                    ).url,
                                                                )
                                                            }
                                                        />
                                                        <ActionBtn
                                                            icon="trash-2"
                                                            label="Hapus"
                                                            variant="danger"
                                                            onClick={() =>
                                                                setDeleting(row)
                                                            }
                                                        />
                                                    </>
                                                )}
                                                {row.status === 'paid' &&
                                                    !row.receipt_url && (
                                                        <span
                                                            style={{
                                                                fontSize: 12.5,
                                                                color: C.faint,
                                                            }}
                                                        >
                                                            —
                                                        </span>
                                                    )}
                                                {row.status === 'paid' &&
                                                    row.receipt_url && (
                                                        <a
                                                            href={
                                                                row.receipt_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            style={
                                                                receiptLinkStyle
                                                            }
                                                        >
                                                            <AIcon
                                                                name="paperclip"
                                                                size={13}
                                                                color={
                                                                    C.primary
                                                                }
                                                            />
                                                            Bukti
                                                        </a>
                                                    )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination footer */}
                    <div
                        style={{
                            padding: '14px 18px',
                            borderTop: `1px solid ${C.border}`,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            flexWrap: 'wrap',
                            gap: 12,
                        }}
                    >
                        <div style={{ fontSize: 13, color: C.muted }}>
                            Menampilkan{' '}
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.from ?? 0}–{meta.to ?? 0}
                            </span>{' '}
                            dari{' '}
                            <span style={{ color: C.text, fontWeight: 500 }}>
                                {meta.total.toLocaleString('id-ID')}
                            </span>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                gap: 6,
                                alignItems: 'center',
                            }}
                        >
                            <button
                                disabled={meta.current_page <= 1}
                                onClick={() => goToPage(meta.current_page - 1)}
                                style={{
                                    height: 34,
                                    minWidth: 34,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color:
                                        meta.current_page <= 1
                                            ? C.faint
                                            : C.text,
                                    cursor:
                                        meta.current_page <= 1
                                            ? 'not-allowed'
                                            : 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 5,
                                }}
                            >
                                <AIcon name="chevron-left" size={15} />
                            </button>
                            <span
                                style={{
                                    fontSize: 13,
                                    color: C.muted,
                                    padding: '0 4px',
                                }}
                            >
                                {meta.current_page} / {meta.last_page}
                            </span>
                            <button
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => goToPage(meta.current_page + 1)}
                                style={{
                                    height: 34,
                                    minWidth: 34,
                                    padding: '0 10px',
                                    border: `1px solid ${C.border}`,
                                    background: '#fff',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    color:
                                        meta.current_page >= meta.last_page
                                            ? C.faint
                                            : C.text,
                                    cursor:
                                        meta.current_page >= meta.last_page
                                            ? 'not-allowed'
                                            : 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                }}
                            >
                                <AIcon name="chevron-right" size={15} />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <FormDialog
                open={rejecting !== null}
                onOpenChange={(open) => !open && setRejecting(null)}
                title="Tolak Reimbursement"
                description={
                    rejecting
                        ? `${rejecting.number} · ${rp(rejecting.amount)} atas nama ${rejecting.employee?.name ?? 'karyawan'}`
                        : undefined
                }
                submitLabel="Tolak Pengajuan"
                onSubmit={submitReject}
                processing={rejectForm.processing}
            >
                <Field
                    label="Alasan Penolakan"
                    required
                    error={rejectForm.errors.rejection_reason}
                >
                    <textarea
                        rows={3}
                        placeholder="Jelaskan alasan penolakan"
                        value={rejectForm.data.rejection_reason}
                        onChange={(event) =>
                            rejectForm.setData(
                                'rejection_reason',
                                event.target.value,
                            )
                        }
                        style={withError(
                            textareaStyle,
                            !!rejectForm.errors.rejection_reason,
                        )}
                    />
                </Field>
            </FormDialog>

            <FormDialog
                open={paying !== null}
                onOpenChange={(open) => !open && setPaying(null)}
                title="Pembayaran Reimbursement"
                description={
                    paying
                        ? `${rp(paying.amount)} untuk ${paying.employee?.name ?? 'karyawan'}`
                        : undefined
                }
                submitLabel="Catat Pembayaran"
                onSubmit={submitPay}
                processing={payForm.processing}
            >
                <Field
                    label="Metode Pembayaran"
                    required
                    error={payForm.errors.payment_method}
                >
                    <select
                        value={payForm.data.payment_method}
                        onChange={(event) =>
                            payForm.setData(
                                'payment_method',
                                event.target.value,
                            )
                        }
                        style={selectStyle}
                    >
                        {paymentMethods.map((method) => (
                            <option key={method.value} value={method.value}>
                                {method.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Nomor Referensi"
                    error={payForm.errors.payment_reference}
                >
                    <input
                        type="text"
                        placeholder="Nomor transaksi / bukti transfer"
                        value={payForm.data.payment_reference}
                        onChange={(event) =>
                            payForm.setData(
                                'payment_reference',
                                event.target.value,
                            )
                        }
                        style={textInputStyle}
                    />
                </Field>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Hapus pengajuan reimbursement?"
                description={
                    deleting
                        ? `Pengajuan ${deleting.number} sebesar ${rp(deleting.amount)} atas nama ${deleting.employee?.name ?? 'karyawan'} akan dihapus permanen.`
                        : undefined
                }
                destructive
                onConfirm={confirmDelete}
            />
        </>
    );
}
