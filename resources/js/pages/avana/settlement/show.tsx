import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import SettlementController from '@/actions/App/Http/Controllers/Avana/SettlementController';
import { ConfirmDialog } from '@/components/avana-ui/confirm-dialog';
import { FormDialog } from '@/components/avana-ui/form-dialog';
import {
    AIcon,
    ActionBtn,
    btnOut,
    btnP,
    C,
    card,
    rp,
    RupiahInput,
} from '@/lib/avana';
import {
    dateInputStyle,
    Field,
    OutcomePill,
    outcomeColor,
    selectStyle,
    StatusPill,
    SummaryRow,
    textInputStyle,
    withError,
} from './components';
import type {
    FlashProps,
    SettlementItemFormData,
    SettlementItemRow,
    SettlementShowProps,
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

interface ReturnFormData {
    returned_amount: string;
    [key: string]: string;
}

interface TopupFormData {
    topup_amount: string;
    topup_method: string;
    topup_reference: string;
    [key: string]: string;
}

interface RejectFormData {
    rejection_reason: string;
    [key: string]: string;
}

export default function SettlementShow({
    settlement,
    categories,
    paymentMethods,
}: SettlementShowProps) {
    const { flash } = usePage<FlashProps>().props;

    const [rejecting, setRejecting] = useState(false);
    const [returning, setReturning] = useState(false);
    const [toppingUp, setToppingUp] = useState(false);
    const [deletingItem, setDeletingItem] = useState<SettlementItemRow | null>(
        null,
    );

    const isOpen =
        settlement.status === 'draft' || settlement.status === 'rejected';

    const itemForm = useForm<SettlementItemFormData>({
        category: categories[0]?.value ?? 'operasional',
        description: '',
        spent_date: '',
        amount: '',
        receipt: null,
    });

    // Seeded empty and filled when the dialog opens: useForm only reads its
    // initial values on mount, so seeding from `outstanding` here would pin the
    // amount to whatever it was before any receipts were added.
    const returnForm = useForm<ReturnFormData>({ returned_amount: '' });

    const topupForm = useForm<TopupFormData>({
        topup_amount: '',
        topup_method: paymentMethods[0]?.value ?? 'transfer',
        topup_reference: '',
    });

    const rejectForm = useForm<RejectFormData>({ rejection_reason: '' });

    const openReturn = () => {
        returnForm.setData('returned_amount', String(settlement.outstanding));
        setReturning(true);
    };

    const openTopup = () => {
        topupForm.setData('topup_amount', String(settlement.outstanding));
        setToppingUp(true);
    };

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const addItem = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        itemForm.post(SettlementController.storeItem(settlement.id).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () =>
                itemForm.reset(
                    'description',
                    'spent_date',
                    'amount',
                    'receipt',
                ),
        });
    };

    const confirmDeleteItem = () => {
        if (!deletingItem) {
            return;
        }

        router.delete(
            SettlementController.destroyItem({
                settlement: settlement.id,
                item: deletingItem.id,
            }).url,
            { preserveScroll: true, onFinish: () => setDeletingItem(null) },
        );
    };

    const submitForReview = () => {
        router.post(
            SettlementController.submit(settlement.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const approve = () => {
        router.post(
            SettlementController.approve(settlement.id).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Settlement ${settlement.number ?? ''}`} />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 14,
                    }}
                >
                    <Link
                        href={SettlementController.index()}
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Settlement
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>
                        {settlement.number ?? 'Detail'}
                    </span>
                </div>

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
                                gap: 10,
                            }}
                        >
                            <h1
                                style={{
                                    fontSize: 24,
                                    fontWeight: 600,
                                    color: C.navy,
                                    margin: 0,
                                    letterSpacing: '-.01em',
                                }}
                            >
                                {settlement.number ?? 'Settlement'}
                            </h1>
                            <StatusPill label={settlement.status_label} />
                        </div>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            {settlement.employee?.name ?? '—'} ·{' '}
                            {settlement.purpose ?? 'Tanpa keperluan'} ·{' '}
                            {settlement.settlement_date ?? '—'}
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        {isOpen && (
                            <button
                                onClick={submitForReview}
                                disabled={settlement.items.length === 0}
                                style={{
                                    ...btnP,
                                    opacity:
                                        settlement.items.length === 0 ? 0.5 : 1,
                                    cursor:
                                        settlement.items.length === 0
                                            ? 'not-allowed'
                                            : 'pointer',
                                }}
                            >
                                <AIcon name="check" size={16} color="#fff" />
                                Ajukan Verifikasi
                            </button>
                        )}
                        {settlement.status === 'submitted' && (
                            <>
                                <button onClick={approve} style={{ ...btnP }}>
                                    <AIcon
                                        name="check"
                                        size={16}
                                        color="#fff"
                                    />
                                    Setujui
                                </button>
                                <button
                                    onClick={() => setRejecting(true)}
                                    style={{ ...btnOut }}
                                >
                                    <AIcon name="x" size={16} />
                                    Tolak
                                </button>
                            </>
                        )}
                        {settlement.status === 'approved' &&
                            settlement.outcome === 'return' &&
                            settlement.outstanding > 0 && (
                                <button
                                    onClick={openReturn}
                                    style={{ ...btnP }}
                                >
                                    <AIcon
                                        name="hand-coins"
                                        size={16}
                                        color="#fff"
                                    />
                                    Catat Pengembalian
                                </button>
                            )}
                        {settlement.status === 'approved' &&
                            settlement.outcome === 'topup' &&
                            settlement.outstanding > 0 && (
                                <button onClick={openTopup} style={{ ...btnP }}>
                                    <AIcon
                                        name="wallet"
                                        size={16}
                                        color="#fff"
                                    />
                                    Bayar Kekurangan
                                </button>
                            )}
                    </div>
                </div>

                {settlement.rejection_reason && (
                    <div
                        style={{
                            background: 'rgba(220,38,38,.08)',
                            border: `1px solid rgba(220,38,38,.2)`,
                            borderRadius: 10,
                            padding: '13px 15px',
                            marginBottom: 18,
                            display: 'flex',
                            gap: 10,
                            alignItems: 'flex-start',
                        }}
                    >
                        <AIcon name="circle-alert" size={16} color={C.red} />
                        <div>
                            <div
                                style={{
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: C.red,
                                }}
                            >
                                Dikembalikan untuk diperbaiki
                            </div>
                            <div
                                style={{
                                    fontSize: 12.5,
                                    color: C.muted,
                                    marginTop: 3,
                                }}
                            >
                                {settlement.rejection_reason}
                            </div>
                        </div>
                    </div>
                )}

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'minmax(0, 1fr) minmax(260px, 320px)',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Receipt lines */}
                    <div style={{ ...card, overflow: 'hidden' }}>
                        <div
                            style={{
                                padding: '16px 18px',
                                borderBottom: `1px solid ${C.border}`,
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Bukti Pengeluaran
                        </div>

                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    minWidth: 620,
                                }}
                            >
                                <thead>
                                    <tr style={{ background: '#FAFBFD' }}>
                                        <th style={headThStyle}>Kategori</th>
                                        <th style={headThStyle}>Keterangan</th>
                                        <th style={headThStyle}>Tanggal</th>
                                        <th style={headThStyle}>Jumlah</th>
                                        <th style={headThStyle}>Bukti</th>
                                        {isOpen && (
                                            <th
                                                style={{
                                                    ...headThStyle,
                                                    textAlign: 'right',
                                                }}
                                            >
                                                Aksi
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {settlement.items.length === 0 && (
                                        <tr
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                colSpan={isOpen ? 6 : 5}
                                                style={{
                                                    padding: '40px 18px',
                                                    textAlign: 'center',
                                                    fontSize: 13.5,
                                                    color: C.muted,
                                                }}
                                            >
                                                Belum ada bukti pengeluaran.
                                            </td>
                                        </tr>
                                    )}
                                    {settlement.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td style={cellStyle}>
                                                {item.category_label}
                                            </td>
                                            <td
                                                style={{
                                                    ...cellStyle,
                                                    color: C.text,
                                                }}
                                            >
                                                {item.description}
                                            </td>
                                            <td style={cellStyle}>
                                                {item.spent_date ?? '—'}
                                            </td>
                                            <td
                                                style={{
                                                    ...cellStyle,
                                                    color: C.text,
                                                    fontWeight: 600,
                                                    fontSize: 13,
                                                }}
                                            >
                                                {rp(item.amount)}
                                            </td>
                                            <td style={cellStyle}>
                                                {item.receipt_url ? (
                                                    <a
                                                        href={item.receipt_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        style={{
                                                            color: C.primary,
                                                            textDecoration:
                                                                'none',
                                                            fontWeight: 500,
                                                        }}
                                                    >
                                                        Lihat
                                                    </a>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            {isOpen && (
                                                <td
                                                    style={{
                                                        padding: '12px 16px',
                                                        textAlign: 'right',
                                                    }}
                                                >
                                                    <ActionBtn
                                                        icon="trash-2"
                                                        label="Hapus"
                                                        variant="danger"
                                                        onClick={() =>
                                                            setDeletingItem(
                                                                item,
                                                            )
                                                        }
                                                    />
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {isOpen && (
                            <form
                                onSubmit={addItem}
                                style={{
                                    borderTop: `1px solid ${C.border}`,
                                    padding: '18px',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 14,
                                    background: '#FAFBFD',
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 13.5,
                                        fontWeight: 600,
                                        color: C.navy,
                                    }}
                                >
                                    Tambah Bukti Pengeluaran
                                </div>

                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns:
                                            'repeat(auto-fit, minmax(180px, 1fr))',
                                        gap: 12,
                                    }}
                                >
                                    <Field
                                        label="Kategori"
                                        required
                                        error={itemForm.errors.category}
                                    >
                                        <select
                                            value={itemForm.data.category}
                                            onChange={(event) =>
                                                itemForm.setData(
                                                    'category',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                selectStyle,
                                                !!itemForm.errors.category,
                                            )}
                                        >
                                            {categories.map((category) => (
                                                <option
                                                    key={category.value}
                                                    value={category.value}
                                                >
                                                    {category.label}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field
                                        label="Keterangan"
                                        required
                                        error={itemForm.errors.description}
                                    >
                                        <input
                                            type="text"
                                            placeholder="Contoh: Tiket kereta Jakarta–Surabaya"
                                            value={itemForm.data.description}
                                            onChange={(event) =>
                                                itemForm.setData(
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                textInputStyle,
                                                !!itemForm.errors.description,
                                            )}
                                        />
                                    </Field>

                                    <Field
                                        label="Tanggal"
                                        required
                                        error={itemForm.errors.spent_date}
                                    >
                                        <input
                                            type="date"
                                            value={itemForm.data.spent_date}
                                            onChange={(event) =>
                                                itemForm.setData(
                                                    'spent_date',
                                                    event.target.value,
                                                )
                                            }
                                            style={withError(
                                                dateInputStyle,
                                                !!itemForm.errors.spent_date,
                                            )}
                                        />
                                    </Field>

                                    <Field
                                        label="Jumlah (Rp)"
                                        required
                                        error={itemForm.errors.amount}
                                    >
                                        <RupiahInput
                                            value={itemForm.data.amount}
                                            onChange={(raw) =>
                                                itemForm.setData('amount', raw)
                                            }
                                            invalid={!!itemForm.errors.amount}
                                        />
                                    </Field>

                                    <Field
                                        label="Scan Bukti"
                                        error={itemForm.errors.receipt}
                                    >
                                        <input
                                            type="file"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            onChange={(event) =>
                                                itemForm.setData(
                                                    'receipt',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            style={{
                                                ...textInputStyle,
                                                padding: '9px 11px',
                                                height: 'auto',
                                                fontSize: 12.5,
                                            }}
                                        />
                                    </Field>
                                </div>

                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'flex-end',
                                    }}
                                >
                                    <button
                                        type="submit"
                                        disabled={itemForm.processing}
                                        style={{
                                            ...btnP,
                                            opacity: itemForm.processing
                                                ? 0.7
                                                : 1,
                                            cursor: itemForm.processing
                                                ? 'not-allowed'
                                                : 'pointer',
                                        }}
                                    >
                                        <AIcon
                                            name="plus"
                                            size={16}
                                            color="#fff"
                                        />
                                        Tambah Bukti
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>

                    {/* Balance summary */}
                    <div
                        style={{
                            ...card,
                            padding: '18px',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: C.navy,
                            }}
                        >
                            Ringkasan
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 10,
                            }}
                        >
                            <SummaryRow
                                label="Uang muka"
                                value={settlement.advance_amount}
                            />
                            <SummaryRow
                                label="Total pengeluaran"
                                value={settlement.total_spent}
                            />
                            <div
                                style={{
                                    height: 1,
                                    background: C.line,
                                }}
                            />
                            <SummaryRow
                                label={
                                    settlement.outcome === 'topup'
                                        ? 'Kekurangan'
                                        : 'Sisa dana'
                                }
                                value={Math.abs(settlement.balance)}
                                accent={outcomeColor(settlement.outcome)}
                                strong
                            />
                            <div>
                                <OutcomePill
                                    outcome={settlement.outcome}
                                    label={settlement.outcome_label}
                                />
                            </div>
                        </div>

                        {(settlement.returned_amount > 0 ||
                            settlement.topup_amount > 0) && (
                            <div
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                    paddingTop: 12,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 10,
                                }}
                            >
                                {settlement.returned_amount > 0 && (
                                    <SummaryRow
                                        label="Sudah dikembalikan"
                                        value={settlement.returned_amount}
                                        accent="#16A34A"
                                    />
                                )}
                                {settlement.topup_amount > 0 && (
                                    <SummaryRow
                                        label="Sudah dibayarkan"
                                        value={settlement.topup_amount}
                                        accent="#16A34A"
                                    />
                                )}
                                {settlement.outstanding > 0 && (
                                    <SummaryRow
                                        label="Belum diselesaikan"
                                        value={settlement.outstanding}
                                        accent={C.red}
                                    />
                                )}
                            </div>
                        )}

                        {settlement.approver && (
                            <div
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                    paddingTop: 12,
                                    fontSize: 12.5,
                                    color: C.muted,
                                    lineHeight: 1.6,
                                }}
                            >
                                <div>
                                    Diverifikasi oleh{' '}
                                    <span
                                        style={{
                                            color: C.text,
                                            fontWeight: 500,
                                        }}
                                    >
                                        {settlement.approver}
                                    </span>
                                </div>
                                <div style={{ color: C.faint }}>
                                    {settlement.approved_at ?? ''}
                                </div>
                            </div>
                        )}

                        {settlement.notes && (
                            <div
                                style={{
                                    borderTop: `1px solid ${C.line}`,
                                    paddingTop: 12,
                                    fontSize: 12.5,
                                    color: C.muted,
                                    lineHeight: 1.6,
                                }}
                            >
                                <div
                                    style={{
                                        fontWeight: 600,
                                        color: C.text,
                                        marginBottom: 3,
                                    }}
                                >
                                    Catatan
                                </div>
                                {settlement.notes}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <FormDialog
                open={returning}
                onOpenChange={setReturning}
                title="Pengembalian Sisa Dana"
                description={`Sisa yang belum dikembalikan: ${rp(settlement.outstanding)}`}
                submitLabel="Catat Pengembalian"
                onSubmit={() =>
                    returnForm.post(
                        SettlementController.recordReturn(settlement.id).url,
                        {
                            preserveScroll: true,
                            onSuccess: () => setReturning(false),
                        },
                    )
                }
                processing={returnForm.processing}
            >
                <Field
                    label="Jumlah Dikembalikan (Rp)"
                    required
                    error={returnForm.errors.returned_amount}
                >
                    <RupiahInput
                        value={returnForm.data.returned_amount}
                        onChange={(raw) =>
                            returnForm.setData('returned_amount', raw)
                        }
                        invalid={!!returnForm.errors.returned_amount}
                    />
                </Field>
            </FormDialog>

            <FormDialog
                open={toppingUp}
                onOpenChange={setToppingUp}
                title="Pembayaran Kekurangan"
                description={`Kekurangan yang belum dibayar: ${rp(settlement.outstanding)}`}
                submitLabel="Catat Pembayaran"
                onSubmit={() =>
                    topupForm.post(
                        SettlementController.recordTopup(settlement.id).url,
                        {
                            preserveScroll: true,
                            onSuccess: () => setToppingUp(false),
                        },
                    )
                }
                processing={topupForm.processing}
            >
                <Field
                    label="Jumlah Dibayar (Rp)"
                    required
                    error={topupForm.errors.topup_amount}
                >
                    <RupiahInput
                        value={topupForm.data.topup_amount}
                        onChange={(raw) =>
                            topupForm.setData('topup_amount', raw)
                        }
                        invalid={!!topupForm.errors.topup_amount}
                    />
                </Field>
                <Field
                    label="Metode Pembayaran"
                    required
                    error={topupForm.errors.topup_method}
                >
                    <select
                        value={topupForm.data.topup_method}
                        onChange={(event) =>
                            topupForm.setData(
                                'topup_method',
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
                    error={topupForm.errors.topup_reference}
                >
                    <input
                        type="text"
                        placeholder="Nomor transaksi / bukti transfer"
                        value={topupForm.data.topup_reference}
                        onChange={(event) =>
                            topupForm.setData(
                                'topup_reference',
                                event.target.value,
                            )
                        }
                        style={textInputStyle}
                    />
                </Field>
            </FormDialog>

            <FormDialog
                open={rejecting}
                onOpenChange={setRejecting}
                title="Tolak Settlement"
                description="Settlement dikembalikan ke karyawan untuk diperbaiki."
                submitLabel="Tolak"
                onSubmit={() =>
                    rejectForm.post(
                        SettlementController.reject(settlement.id).url,
                        {
                            preserveScroll: true,
                            onSuccess: () => {
                                setRejecting(false);
                                rejectForm.reset();
                            },
                        },
                    )
                }
                processing={rejectForm.processing}
            >
                <Field
                    label="Alasan Penolakan"
                    required
                    error={rejectForm.errors.rejection_reason}
                >
                    <textarea
                        rows={3}
                        placeholder="Jelaskan apa yang perlu diperbaiki"
                        value={rejectForm.data.rejection_reason}
                        onChange={(event) =>
                            rejectForm.setData(
                                'rejection_reason',
                                event.target.value,
                            )
                        }
                        style={withError(
                            {
                                width: '100%',
                                padding: '11px 13px',
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                fontSize: 13.5,
                                outline: 'none',
                                resize: 'vertical',
                            },
                            !!rejectForm.errors.rejection_reason,
                        )}
                    />
                </Field>
            </FormDialog>

            <ConfirmDialog
                open={deletingItem !== null}
                onOpenChange={(open) => !open && setDeletingItem(null)}
                title="Hapus bukti pengeluaran?"
                description={
                    deletingItem
                        ? `"${deletingItem.description}" sebesar ${rp(deletingItem.amount)} akan dihapus.`
                        : undefined
                }
                destructive
                onConfirm={confirmDeleteItem}
            />
        </>
    );
}
