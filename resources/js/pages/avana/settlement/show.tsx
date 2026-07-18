import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import SettlementController from '@/actions/App/Http/Controllers/Avana/SettlementController';
import { AIcon, btnOut, btnP, C, card, rp } from '@/lib/avana';
import { Field, selectStyle, StatusPill, textareaStyle } from './components';
import type {
    FlashProps,
    SettlementShowProps,
    SettlementStatus,
} from './types';

/** Timeline step render state. */
type StepState = 'done' | 'current' | 'pending';

export default function SettlementShow({
    settlement,
    paymentMethods,
}: SettlementShowProps) {
    const { flash } = usePage<FlashProps>().props;
    const [rejecting, setRejecting] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const verify = useForm({
        payment_method: 'transfer',
        payment_reference: '',
        confirm_bank: false as boolean,
        confirm_receipts: false as boolean,
    });

    const rejectForm = useForm({ rejection_reason: '' });

    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    const isDraft =
        settlement.status === 'draft' || settlement.status === 'rejected';

    return (
        <>
            <Head title={`Settlement ${settlement.number}`} />
            <div style={{ padding: '28px 32px' }}>
                {/* ---------- header ---------- */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 7,
                        fontSize: 12.5,
                        color: C.faint,
                        marginBottom: 12,
                    }}
                >
                    <Link
                        href={SettlementController.index().url}
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Settlement
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>{settlement.number}</span>
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 16,
                        marginBottom: 24,
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
                        {settlement.title}
                    </h1>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        {isDraft && (
                            <>
                                <Link
                                    href={
                                        SettlementController.edit(settlement.id)
                                            .url
                                    }
                                    style={{
                                        ...btnOut,
                                        height: 38,
                                        textDecoration: 'none',
                                    }}
                                >
                                    <AIcon name="pencil" size={15} />
                                    Ubah
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (confirm('Hapus settlement ini?')) {
                                            router.delete(
                                                SettlementController.destroy(
                                                    settlement.id,
                                                ).url,
                                            );
                                        }
                                    }}
                                    style={{
                                        ...btnOut,
                                        height: 38,
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
                            </>
                        )}
                        <StatusPill label={settlement.status_label} />
                    </div>
                </div>

                {settlement.status === 'rejected' &&
                    settlement.rejection_reason && (
                        <div
                            style={{
                                background: '#FEF2F2',
                                border: `1px solid ${C.red}33`,
                                borderRadius: 10,
                                padding: '12px 16px',
                                marginBottom: 20,
                                fontSize: 13,
                                color: '#B91C1C',
                            }}
                        >
                            <strong>Dikembalikan:</strong>{' '}
                            {settlement.rejection_reason}
                        </div>
                    )}

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) 330px',
                        gap: 20,
                        alignItems: 'start',
                    }}
                >
                    {/* ---------- left column ---------- */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 20,
                        }}
                    >
                        {/* Settlement summary */}
                        <div style={{ ...card, padding: 22 }}>
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                    marginBottom: 18,
                                }}
                            >
                                Ringkasan Settlement
                            </div>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'repeat(4, minmax(0, 1fr))',
                                    gap: 16,
                                }}
                            >
                                <SummaryFact
                                    label="Total"
                                    value={rp(settlement.total)}
                                    accent
                                />
                                <SummaryFact
                                    label="Karyawan"
                                    value={settlement.employee?.name ?? '—'}
                                />
                                <SummaryFact
                                    label="Departemen"
                                    value={settlement.department ?? '—'}
                                />
                                <SummaryFact
                                    label="Tanggal Diajukan"
                                    value={settlement.submission_date ?? '—'}
                                />
                            </div>
                            <div
                                style={{
                                    marginTop: 16,
                                    paddingTop: 14,
                                    borderTop: `1px solid ${C.line}`,
                                    display: 'flex',
                                    gap: 24,
                                    fontSize: 12.5,
                                    color: C.muted,
                                }}
                            >
                                <span>
                                    Subtotal{' '}
                                    <strong style={{ color: C.text }}>
                                        {rp(settlement.subtotal)}
                                    </strong>
                                </span>
                                <span>
                                    Pajak 11%{' '}
                                    <strong style={{ color: C.text }}>
                                        {rp(settlement.tax_amount)}
                                    </strong>
                                </span>
                            </div>
                        </div>

                        {/* Payout account */}
                        <div style={card}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                    padding: '16px 20px',
                                    borderBottom: `1px solid ${C.line}`,
                                }}
                            >
                                <AIcon
                                    name="landmark"
                                    size={17}
                                    color={C.primary}
                                />
                                Rekening Pembayaran
                            </div>
                            <div
                                style={{
                                    padding: 20,
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 18,
                                }}
                            >
                                <SummaryFact
                                    label="Nama Bank"
                                    value={settlement.bank_name ?? '—'}
                                />
                                <SummaryFact
                                    label="Nomor Rekening"
                                    value={
                                        settlement.bank_account_number ?? '—'
                                    }
                                />
                                <SummaryFact
                                    label="Atas Nama"
                                    value={
                                        settlement.bank_account_holder ?? '—'
                                    }
                                />
                                <SummaryFact
                                    label="SWIFT / BIC"
                                    value={settlement.bank_swift ?? '—'}
                                />
                            </div>
                            {!settlement.bank_name && (
                                <div
                                    style={{
                                        margin: '0 20px 18px',
                                        background: C.surface,
                                        borderRadius: 8,
                                        padding: '10px 13px',
                                        fontSize: 12,
                                        color: C.muted,
                                    }}
                                >
                                    Karyawan belum punya rekening bank
                                    tersimpan.
                                </div>
                            )}
                        </div>

                        {/* Expense line items */}
                        <div style={card}>
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                    padding: '16px 20px',
                                    borderBottom: `1px solid ${C.line}`,
                                }}
                            >
                                Rincian Pengeluaran
                            </div>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    fontSize: 13,
                                }}
                            >
                                <tbody>
                                    {settlement.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            style={{
                                                borderBottom: `1px solid ${C.line}`,
                                            }}
                                        >
                                            <td
                                                style={{
                                                    padding: '13px 20px',
                                                    color: C.text,
                                                }}
                                            >
                                                {item.description}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 12px',
                                                    color: C.muted,
                                                }}
                                            >
                                                {item.category_label}
                                            </td>
                                            <td
                                                style={{
                                                    padding: '13px 20px',
                                                    textAlign: 'right',
                                                    fontWeight: 600,
                                                    color: C.navy,
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {rp(item.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Attachments */}
                        {settlement.attachments.length > 0 && (
                            <div style={card}>
                                <div
                                    style={{
                                        fontSize: 15,
                                        fontWeight: 600,
                                        color: C.navy,
                                        padding: '16px 20px',
                                        borderBottom: `1px solid ${C.line}`,
                                    }}
                                >
                                    Dokumen Pendukung
                                </div>
                                <div
                                    style={{
                                        padding: 16,
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 12,
                                    }}
                                >
                                    {settlement.attachments.map(
                                        (attachment) => (
                                            <a
                                                key={attachment.id}
                                                href={attachment.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 10,
                                                    border: `1px solid ${C.border}`,
                                                    borderRadius: 8,
                                                    padding: '11px 13px',
                                                    textDecoration: 'none',
                                                    color: C.text,
                                                    fontSize: 12.5,
                                                }}
                                            >
                                                <AIcon
                                                    name="file-text"
                                                    size={17}
                                                    color={C.primary}
                                                />
                                                <span
                                                    style={{
                                                        overflow: 'hidden',
                                                        textOverflow:
                                                            'ellipsis',
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    {attachment.name}
                                                </span>
                                            </a>
                                        ),
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* ---------- right column ---------- */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 20,
                        }}
                    >
                        {/* Process timeline */}
                        <div style={{ ...card, padding: 22 }}>
                            <div
                                style={{
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: C.navy,
                                    marginBottom: 18,
                                }}
                            >
                                Alur Proses
                            </div>
                            <Timeline settlement={settlement} />
                        </div>

                        {/* Fraud / authenticity screening */}
                        {settlement.fraud_checked_at && (
                            <FraudPanel
                                settlement={settlement}
                                canRescan={
                                    settlement.status === 'submitted' ||
                                    settlement.status === 'manager_approved'
                                }
                                onRescan={() =>
                                    post(
                                        SettlementController.rescan(
                                            settlement.id,
                                        ).url,
                                    )
                                }
                            />
                        )}

                        {/* Action panel */}
                        {isDraft && (
                            <ActionCard title="Ajukan Settlement">
                                <p style={helpText}>
                                    Ajukan settlement ini agar diproses manager
                                    lalu Finance.
                                </p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        post(
                                            SettlementController.submit(
                                                settlement.id,
                                            ).url,
                                        )
                                    }
                                    style={fullPrimary}
                                >
                                    <AIcon name="send" size={16} color="#fff" />
                                    Ajukan untuk Persetujuan
                                </button>
                            </ActionCard>
                        )}

                        {settlement.status === 'submitted' && (
                            <ActionCard title="Persetujuan Manager">
                                <p style={helpText}>
                                    Periksa jumlah yang diklaim, lalu setujui
                                    untuk diteruskan ke Finance.
                                </p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        post(
                                            SettlementController.managerApprove(
                                                settlement.id,
                                            ).url,
                                        )
                                    }
                                    style={fullPrimary}
                                >
                                    <AIcon
                                        name="check"
                                        size={16}
                                        color="#fff"
                                    />
                                    Setujui (Manager)
                                </button>
                                <RejectAction
                                    open={rejecting}
                                    setOpen={setRejecting}
                                    form={rejectForm}
                                    onSubmit={() =>
                                        rejectForm.post(
                                            SettlementController.reject(
                                                settlement.id,
                                            ).url,
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setRejecting(false),
                                            },
                                        )
                                    }
                                />
                            </ActionCard>
                        )}

                        {settlement.status === 'manager_approved' && (
                            <ActionCard title="Verifikasi Finance">
                                <p style={helpText}>
                                    Verifikasi akan memicu pembayaran ke
                                    rekening karyawan. Tindakan ini final.
                                </p>
                                {settlement.fraud_level === 'high' && (
                                    <div
                                        style={{
                                            background: '#FEF2F2',
                                            border: `1px solid ${C.red}55`,
                                            borderRadius: 8,
                                            padding: '10px 12px',
                                            fontSize: 12,
                                            color: '#B91C1C',
                                            display: 'flex',
                                            gap: 8,
                                            lineHeight: 1.5,
                                        }}
                                    >
                                        <AIcon
                                            name="triangle-alert"
                                            size={15}
                                            color={C.red}
                                        />
                                        <span>
                                            Bukti berisiko tinggi dimanipulasi —
                                            tinjau panel Pemeriksaan Keaslian
                                            sebelum membayar.
                                        </span>
                                    </div>
                                )}
                                <Field label="Metode Pembayaran" required>
                                    <select
                                        value={verify.data.payment_method}
                                        onChange={(event) =>
                                            verify.setData(
                                                'payment_method',
                                                event.target.value,
                                            )
                                        }
                                        style={selectStyle}
                                    >
                                        {paymentMethods.map((method) => (
                                            <option
                                                key={method.value}
                                                value={method.value}
                                            >
                                                {method.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Nomor Referensi">
                                    <input
                                        type="text"
                                        placeholder="Nomor transaksi / bukti transfer"
                                        value={verify.data.payment_reference}
                                        onChange={(event) =>
                                            verify.setData(
                                                'payment_reference',
                                                event.target.value,
                                            )
                                        }
                                        style={{
                                            width: '100%',
                                            height: 42,
                                            padding: '0 13px',
                                            border: `1px solid ${C.border}`,
                                            borderRadius: 8,
                                            fontSize: 13.5,
                                            outline: 'none',
                                        }}
                                    />
                                </Field>
                                <CheckRow
                                    checked={verify.data.confirm_bank}
                                    onChange={(v) =>
                                        verify.setData('confirm_bank', v)
                                    }
                                    label="Rekening tujuan sudah diverifikasi"
                                />
                                <CheckRow
                                    checked={verify.data.confirm_receipts}
                                    onChange={(v) =>
                                        verify.setData('confirm_receipts', v)
                                    }
                                    label="Bukti sesuai & taat pajak"
                                />
                                <button
                                    type="button"
                                    disabled={
                                        !verify.data.confirm_bank ||
                                        !verify.data.confirm_receipts ||
                                        verify.processing
                                    }
                                    onClick={() =>
                                        verify.post(
                                            SettlementController.financeVerify(
                                                settlement.id,
                                            ).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                    style={{
                                        ...fullPrimary,
                                        opacity:
                                            !verify.data.confirm_bank ||
                                            !verify.data.confirm_receipts
                                                ? 0.5
                                                : 1,
                                        cursor:
                                            !verify.data.confirm_bank ||
                                            !verify.data.confirm_receipts
                                                ? 'not-allowed'
                                                : 'pointer',
                                    }}
                                >
                                    <AIcon
                                        name="badge-check"
                                        size={16}
                                        color="#fff"
                                    />
                                    Verifikasi &amp; Bayar
                                </button>
                                <RejectAction
                                    open={rejecting}
                                    setOpen={setRejecting}
                                    form={rejectForm}
                                    onSubmit={() =>
                                        rejectForm.post(
                                            SettlementController.reject(
                                                settlement.id,
                                            ).url,
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setRejecting(false),
                                            },
                                        )
                                    }
                                />
                            </ActionCard>
                        )}

                        {settlement.status === 'paid' && (
                            <ActionCard title="Pembayaran Selesai">
                                <p style={helpText}>
                                    Dibayar {settlement.paid_at} oleh{' '}
                                    {settlement.finance_verified_by ?? '—'}.
                                </p>
                                <div
                                    style={{ fontSize: 12.5, color: C.muted }}
                                >
                                    Metode:{' '}
                                    <strong style={{ color: C.text }}>
                                        {settlement.payment_method_label ?? '—'}
                                    </strong>
                                    {settlement.payment_reference && (
                                        <>
                                            {' · Ref: '}
                                            <strong style={{ color: C.text }}>
                                                {settlement.payment_reference}
                                            </strong>
                                        </>
                                    )}
                                </div>
                            </ActionCard>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

const helpText = {
    fontSize: 12.5,
    color: C.muted,
    lineHeight: 1.5,
    margin: 0,
} as const;

const fullPrimary = {
    ...btnP,
    height: 44,
    width: '100%',
    justifyContent: 'center',
} as const;

/** One label + value fact block. */
function SummaryFact({
    label,
    value,
    accent = false,
}: {
    label: string;
    value: string;
    accent?: boolean;
}) {
    return (
        <div>
            <div
                style={{
                    fontSize: 11,
                    letterSpacing: '.04em',
                    textTransform: 'uppercase',
                    color: C.faint,
                    marginBottom: 5,
                }}
            >
                {label}
            </div>
            <div
                style={{
                    fontSize: accent ? 18 : 13.5,
                    fontWeight: accent ? 700 : 600,
                    color: accent ? C.primary : C.text,
                }}
            >
                {value}
            </div>
        </div>
    );
}

/** Wrapper card for a right-column action panel. */
function ActionCard({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div style={{ ...card, padding: 22 }}>
            <div
                style={{
                    fontSize: 15,
                    fontWeight: 600,
                    color: C.navy,
                    marginBottom: 14,
                }}
            >
                {title}
            </div>
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 13,
                }}
            >
                {children}
            </div>
        </div>
    );
}

/** A labelled checkbox row for the finance verification gate. */
function CheckRow({
    checked,
    onChange,
    label,
}: {
    checked: boolean;
    onChange: (value: boolean) => void;
    label: string;
}) {
    return (
        <label
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 9,
                fontSize: 12.5,
                color: C.text,
                cursor: 'pointer',
            }}
        >
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                style={{ width: 16, height: 16, cursor: 'pointer' }}
            />
            {label}
        </label>
    );
}

/** Reject-with-reason toggle used in both approval stages. */
function RejectAction({
    open,
    setOpen,
    form,
    onSubmit,
}: {
    open: boolean;
    setOpen: (value: boolean) => void;
    form: ReturnType<typeof useForm<{ rejection_reason: string }>>;
    onSubmit: () => void;
}) {
    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                style={{
                    background: 'none',
                    border: 'none',
                    color: C.red,
                    fontSize: 12.5,
                    fontWeight: 600,
                    cursor: 'pointer',
                    padding: 0,
                }}
            >
                Kembalikan ke karyawan
            </button>
        );
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <textarea
                rows={2}
                placeholder="Alasan pengembalian…"
                value={form.data.rejection_reason}
                onChange={(event) =>
                    form.setData('rejection_reason', event.target.value)
                }
                style={textareaStyle}
            />
            <div style={{ display: 'flex', gap: 8 }}>
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={form.processing}
                    style={{
                        ...btnP,
                        height: 38,
                        background: C.red,
                        flex: 1,
                        justifyContent: 'center',
                    }}
                >
                    Kembalikan
                </button>
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    style={{ ...btnOut, height: 38 }}
                >
                    Batal
                </button>
            </div>
        </div>
    );
}

/** The four-step process timeline, driven by the settlement status. */
function Timeline({
    settlement,
}: {
    settlement: SettlementShowProps['settlement'];
}) {
    const status: SettlementStatus = settlement.status;
    const order: Record<SettlementStatus, number> = {
        draft: 0,
        rejected: 0,
        submitted: 1,
        manager_approved: 2,
        paid: 3,
    };
    // A rejected claim was submitted at least once, so its first step is done.
    const level = status === 'rejected' ? 1 : order[status];

    // The level at which each milestone becomes complete.
    const doneAt = [1, 2, 3, 3];
    const done = doneAt.map((threshold) => level >= threshold);
    // The current step is the first one not yet done — unless returned.
    const currentIndex =
        status === 'rejected' ? -1 : done.findIndex((flag) => !flag);

    const stepState = (index: number): StepState => {
        if (done[index]) {
            return 'done';
        }
        return index === currentIndex ? 'current' : 'pending';
    };

    const steps: {
        state: StepState;
        title: string;
        sub: string | null;
    }[] = [
        {
            state: stepState(0),
            title: 'Diajukan Karyawan',
            sub: settlement.submission_date,
        },
        {
            state: stepState(1),
            title: 'Disetujui Manager',
            sub: settlement.manager_approved_at
                ? `${settlement.manager_approved_by ?? ''} · ${settlement.manager_approved_at}`
                : null,
        },
        {
            state: stepState(2),
            title: 'Verifikasi Finance',
            sub: settlement.paid_at ? 'Selesai' : null,
        },
        {
            state: stepState(3),
            title: 'Pembayaran',
            sub: settlement.paid_at,
        },
    ];

    return (
        <div style={{ display: 'flex', flexDirection: 'column' }}>
            {steps.map((step, index) => (
                <div
                    // eslint-disable-next-line react/no-array-index-key
                    key={index}
                    style={{ display: 'flex', gap: 12 }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                        }}
                    >
                        <StepDot state={step.state} />
                        {index < steps.length - 1 && (
                            <div
                                style={{
                                    width: 2,
                                    flex: 1,
                                    minHeight: 26,
                                    background:
                                        step.state === 'done'
                                            ? '#10B981'
                                            : C.line,
                                }}
                            />
                        )}
                    </div>
                    <div style={{ paddingBottom: 18 }}>
                        <div
                            style={{
                                fontSize: 13,
                                fontWeight: 600,
                                color:
                                    step.state === 'pending'
                                        ? C.faint
                                        : C.navy,
                            }}
                        >
                            {step.title}
                        </div>
                        {step.sub && (
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.muted,
                                    marginTop: 2,
                                }}
                            >
                                {step.sub}
                            </div>
                        )}
                        {step.state === 'current' && !step.sub && (
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.primary,
                                    marginTop: 2,
                                    fontWeight: 600,
                                }}
                            >
                                Sedang diproses
                            </div>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

/** Timeline node marker. */
function StepDot({ state }: { state: StepState }) {
    if (state === 'done') {
        return (
            <div
                style={{
                    width: 24,
                    height: 24,
                    borderRadius: 100,
                    background: '#10B981',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}
            >
                <AIcon name="check" size={14} color="#fff" />
            </div>
        );
    }

    if (state === 'current') {
        return (
            <div
                style={{
                    width: 24,
                    height: 24,
                    borderRadius: 100,
                    border: `2px solid ${C.primary}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}
            >
                <div
                    style={{
                        width: 9,
                        height: 9,
                        borderRadius: 100,
                        background: C.primary,
                    }}
                />
            </div>
        );
    }

    return (
        <div
            style={{
                width: 24,
                height: 24,
                borderRadius: 100,
                border: `2px solid ${C.line}`,
                background: '#fff',
            }}
        />
    );
}

/** Label + colour for a fraud risk level. */
function fraudMeta(level: 'low' | 'medium' | 'high' | null): {
    label: string;
    color: string;
    bg: string;
} {
    switch (level) {
        case 'high':
            return { label: 'Berisiko Tinggi', color: '#B91C1C', bg: '#FEF2F2' };
        case 'medium':
            return { label: 'Perlu Ditinjau', color: '#B45309', bg: '#FFFBEB' };
        case 'low':
            return { label: 'Terlihat Wajar', color: '#047857', bg: '#ECFDF5' };
        default:
            return { label: 'Belum Diperiksa', color: C.muted, bg: C.surface };
    }
}

/** Tamper/authenticity screening summary for a settlement's documents. */
function FraudPanel({
    settlement,
    canRescan,
    onRescan,
}: {
    settlement: SettlementShowProps['settlement'];
    canRescan: boolean;
    onRescan: () => void;
}) {
    const overall = fraudMeta(settlement.fraud_level);

    return (
        <div style={{ ...card, padding: 22 }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    marginBottom: 14,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 9,
                        fontSize: 15,
                        fontWeight: 600,
                        color: C.navy,
                    }}
                >
                    <AIcon name="shield-check" size={17} color={C.primary} />
                    Pemeriksaan Keaslian Bukti
                </div>
                {canRescan && (
                    <button
                        type="button"
                        onClick={onRescan}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                            background: 'none',
                            border: 'none',
                            color: C.primary,
                            fontSize: 12,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="refresh-cw" size={13} color={C.primary} />
                        Pindai Ulang
                    </button>
                )}
            </div>

            <div
                style={{
                    background: overall.bg,
                    borderRadius: 8,
                    padding: '11px 13px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    marginBottom: 12,
                }}
            >
                <span style={{ fontSize: 12.5, color: C.muted }}>
                    Risiko keseluruhan
                </span>
                <span
                    style={{
                        fontSize: 12.5,
                        fontWeight: 700,
                        color: overall.color,
                    }}
                >
                    {overall.label}
                </span>
            </div>

            {settlement.attachments.length === 0 && (
                <div style={{ fontSize: 12.5, color: C.muted }}>
                    Tidak ada bukti yang dilampirkan untuk diperiksa.
                </div>
            )}

            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 12,
                }}
            >
                {settlement.attachments.map((attachment) => {
                    const meta = fraudMeta(attachment.fraud_level ?? null);
                    return (
                        <div
                            key={attachment.id}
                            style={{
                                border: `1px solid ${C.border}`,
                                borderRadius: 8,
                                padding: '11px 13px',
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    gap: 8,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: C.text,
                                        fontWeight: 500,
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {attachment.name}
                                </span>
                                <span
                                    style={{
                                        flex: 'none',
                                        padding: '2px 8px',
                                        borderRadius: 100,
                                        fontSize: 11,
                                        fontWeight: 600,
                                        color: meta.color,
                                        background: meta.bg,
                                    }}
                                >
                                    {attachment.fraud_score ?? 0} · {meta.label}
                                </span>
                            </div>

                            {attachment.vision_summary && (
                                <div
                                    style={{
                                        fontSize: 11.5,
                                        color: C.muted,
                                        marginTop: 6,
                                        lineHeight: 1.5,
                                    }}
                                >
                                    {attachment.vision_summary}
                                </div>
                            )}

                            {(attachment.fraud_flags ?? []).length > 0 && (
                                <ul
                                    style={{
                                        margin: '8px 0 0',
                                        paddingLeft: 0,
                                        listStyle: 'none',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 4,
                                    }}
                                >
                                    {(attachment.fraud_flags ?? []).map(
                                        (flag, index) => (
                                            <li
                                                // eslint-disable-next-line react/no-array-index-key
                                                key={index}
                                                style={{
                                                    display: 'flex',
                                                    gap: 6,
                                                    fontSize: 11.5,
                                                    color:
                                                        flag.severity === 'high'
                                                            ? '#B91C1C'
                                                            : C.muted,
                                                }}
                                            >
                                                <AIcon
                                                    name={
                                                        flag.severity === 'high'
                                                            ? 'triangle-alert'
                                                            : 'info'
                                                    }
                                                    size={12}
                                                    color={
                                                        flag.severity === 'high'
                                                            ? '#B91C1C'
                                                            : C.faint
                                                    }
                                                />
                                                <span>{flag.label}</span>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}

                            {attachment.extracted_amount != null && (
                                <div
                                    style={{
                                        fontSize: 11,
                                        color: C.faint,
                                        marginTop: 6,
                                    }}
                                >
                                    Terbaca dari bukti:{' '}
                                    {rp(attachment.extracted_amount)}
                                    {attachment.extracted_vendor
                                        ? ` · ${attachment.extracted_vendor}`
                                        : ''}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            <div
                style={{
                    fontSize: 10.5,
                    color: C.faint,
                    marginTop: 12,
                    lineHeight: 1.5,
                }}
            >
                Skor ini sinyal untuk ditinjau manusia, bukan vonis. Diperiksa{' '}
                {settlement.fraud_checked_at}.
            </div>
        </div>
    );
}
