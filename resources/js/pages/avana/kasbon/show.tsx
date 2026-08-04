import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CashAdvanceController from '@/actions/App/Http/Controllers/Avana/CashAdvanceController';
import { FileDropzone } from '@/components/avana-ui/file-dropzone';
import { FormDialog } from '@/components/avana-ui/form-dialog';
import { AIcon, btnOut, btnP, C, card, rp, RupiahInput } from '@/lib/avana';
import { Field, selectStyle, StatusPill, textInputStyle } from './components';
import type { CashAdvanceShowProps, FlashProps } from './types';

interface SettleForm {
    spent_amount: string;
    settlement_note: string;
    receipt: File | null;
    [key: string]: string | File | null;
}

interface DisburseForm {
    disbursement_method: string;
    disbursement_reference: string;
    [key: string]: string;
}

const cardHeaderStyle = {
    display: 'flex',
    alignItems: 'center',
    gap: 9,
    fontSize: 15,
    fontWeight: 600,
    color: C.navy,
    padding: '16px 20px',
    borderBottom: `1px solid ${C.line}`,
} as const;

export default function KasbonShow({
    advance,
    disbursementMethods,
    authUserId,
}: CashAdvanceShowProps) {
    const { flash } = usePage<FlashProps>().props;
    const [settling, setSettling] = useState(false);
    const [disbursing, setDisbursing] = useState(false);

    const settleForm = useForm<SettleForm>({
        spent_amount: '',
        settlement_note: '',
        receipt: null,
    });

    const disburseForm = useForm<DisburseForm>({
        disbursement_method: disbursementMethods[0]?.value ?? 'transfer',
        disbursement_reference: '',
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    /** What still has to move once the spend is known. */
    const split = (() => {
        const spent = Number(settleForm.data.spent_amount) || 0;
        const difference = Math.round((advance.amount - spent) * 100) / 100;

        return {
            returned: Math.max(difference, 0),
            topup: Math.max(-difference, 0),
        };
    })();

    const isSettled = advance.status === 'settled';
    const releasedByViewer = advance.disbursed_by === authUserId;
    const approvedByViewer = advance.approved_by === authUserId;

    return (
        <>
            <Head title={`Uang Muka ${advance.employee?.name ?? ''}`} />
            <div style={{ padding: '28px 32px' }}>
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
                        href={CashAdvanceController.index().url}
                        style={{ color: C.faint, textDecoration: 'none' }}
                    >
                        Cash Advance
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Detail</span>
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 12,
                        marginBottom: 22,
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
                        {advance.purpose}
                    </h1>
                    <StatusPill label={advance.status_label} />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) 330px',
                        gap: 20,
                        alignItems: 'start',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 20,
                        }}
                    >
                        {/* Summary */}
                        <div style={card}>
                            <div style={cardHeaderStyle}>
                                <AIcon
                                    name="wallet"
                                    size={17}
                                    color={C.primary}
                                />
                                Ringkasan Uang Muka
                            </div>
                            <div
                                style={{
                                    padding: 20,
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'repeat(auto-fit, minmax(150px, 1fr))',
                                    gap: 18,
                                }}
                            >
                                <Fact
                                    label="Jumlah"
                                    value={rp(advance.amount)}
                                    accent
                                />
                                <Fact
                                    label="Karyawan"
                                    value={advance.employee?.name ?? '—'}
                                />
                                <Fact
                                    label="Diajukan"
                                    value={advance.request_date ?? '—'}
                                />
                                <Fact
                                    label="Dibutuhkan"
                                    value={advance.needed_date ?? '—'}
                                />
                            </div>
                            {advance.reason && (
                                <div
                                    style={{
                                        margin: '0 20px 18px',
                                        background: C.surface,
                                        borderRadius: 8,
                                        padding: '11px 13px',
                                        fontSize: 12.5,
                                        color: C.muted,
                                        lineHeight: 1.55,
                                    }}
                                >
                                    {advance.reason}
                                </div>
                            )}
                        </div>

                        {/* How the money was handed over */}
                        {advance.disbursed_at && (
                            <div style={card}>
                                <div style={cardHeaderStyle}>
                                    <AIcon
                                        name="hand-coins"
                                        size={17}
                                        color={C.primary}
                                    />
                                    Pencairan
                                </div>
                                <div
                                    style={{
                                        padding: 20,
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 18,
                                    }}
                                >
                                    <Fact
                                        label="Tanggal Cair"
                                        value={advance.disbursed_at}
                                    />
                                    <Fact
                                        label="Metode"
                                        value={
                                            advance.disbursement_method_label ??
                                            '—'
                                        }
                                    />
                                    <Fact
                                        label="Nomor Referensi"
                                        value={
                                            advance.disbursement_reference ??
                                            '—'
                                        }
                                    />
                                    <Fact
                                        label="Dicairkan Oleh"
                                        value={advance.disbursed_by_name ?? '—'}
                                    />
                                </div>
                            </div>
                        )}

                        {/* What the money went on */}
                        {isSettled && (
                            <div style={card}>
                                <div style={cardHeaderStyle}>
                                    <AIcon
                                        name="clipboard-check"
                                        size={17}
                                        color={C.primary}
                                    />
                                    Pertanggungjawaban
                                </div>
                                <div
                                    style={{
                                        padding: 20,
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 18,
                                    }}
                                >
                                    <Fact
                                        label="Jumlah Terpakai"
                                        value={rp(advance.spent_amount ?? 0)}
                                        accent
                                    />
                                    <Fact
                                        label="Diperiksa Oleh"
                                        value={advance.settled_by_name ?? '—'}
                                    />
                                </div>

                                <div style={{ margin: '0 20px 18px' }}>
                                    <Balance
                                        returned={advance.returned_amount}
                                        topup={advance.topup_amount}
                                    />
                                </div>

                                {advance.settlement_note && (
                                    <div
                                        style={{
                                            margin: '0 20px 18px',
                                            fontSize: 12.5,
                                            color: C.muted,
                                            lineHeight: 1.55,
                                        }}
                                    >
                                        {advance.settlement_note}
                                    </div>
                                )}

                                {advance.settlement_receipt_url && (
                                    <div style={{ margin: '0 20px 20px' }}>
                                        <a
                                            href={
                                                advance.settlement_receipt_url
                                            }
                                            target="_blank"
                                            rel="noreferrer"
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 7,
                                                fontSize: 12.5,
                                                color: C.primary,
                                                textDecoration: 'none',
                                            }}
                                        >
                                            <AIcon
                                                name="file-text"
                                                size={15}
                                                color={C.primary}
                                            />
                                            Lihat bukti pengeluaran
                                        </a>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Sidebar: trail + the action open to this advance */}
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 20,
                        }}
                    >
                        <div style={card}>
                            <div style={cardHeaderStyle}>
                                <AIcon
                                    name="git-commit-horizontal"
                                    size={17}
                                    color={C.primary}
                                />
                                Alur Proses
                            </div>
                            <div style={{ padding: '18px 20px' }}>
                                <Step
                                    label="Diajukan"
                                    at={advance.request_date}
                                    done
                                />
                                <Step
                                    label={
                                        advance.status === 'rejected'
                                            ? 'Ditolak'
                                            : 'Disetujui'
                                    }
                                    at={advance.approved_at}
                                    by={advance.approved_by_name}
                                    done={
                                        advance.approved_at !== null ||
                                        advance.status === 'rejected'
                                    }
                                />
                                <Step
                                    label="Dicairkan Finance"
                                    at={advance.disbursed_at}
                                    by={advance.disbursed_by_name}
                                    done={advance.disbursed_at !== null}
                                />
                                <Step
                                    label="Dipertanggungjawabkan"
                                    at={advance.settled_at_full}
                                    by={advance.settled_by_name}
                                    done={isSettled}
                                    isLast
                                />
                            </div>
                        </div>

                        {advance.status === 'approved' && (
                            <div style={card}>
                                <div style={cardHeaderStyle}>Pencairan</div>
                                <div style={{ padding: 20 }}>
                                    <p
                                        style={{
                                            fontSize: 12.5,
                                            color: C.muted,
                                            margin: '0 0 14px',
                                            lineHeight: 1.55,
                                        }}
                                    >
                                        {approvedByViewer
                                            ? 'Anda yang menyetujui uang muka ini. Pencairan harus dilakukan orang lain.'
                                            : 'Catat penyerahan uang muka ke karyawan.'}
                                    </p>
                                    {!approvedByViewer && (
                                        <button
                                            type="button"
                                            onClick={() => setDisbursing(true)}
                                            style={{
                                                ...btnP,
                                                width: '100%',
                                                justifyContent: 'center',
                                            }}
                                        >
                                            <AIcon
                                                name="hand-coins"
                                                size={16}
                                                color="#fff"
                                            />
                                            Cairkan
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}

                        {advance.status === 'disbursed' && (
                            <div style={card}>
                                <div style={cardHeaderStyle}>
                                    Pertanggungjawaban
                                </div>
                                <div style={{ padding: 20 }}>
                                    {releasedByViewer ? (
                                        <div
                                            style={{
                                                background: '#FFFBEB',
                                                border: `1px solid ${C.amber}55`,
                                                borderRadius: 8,
                                                padding: '12px 13px',
                                                fontSize: 12.5,
                                                color: '#92400E',
                                                display: 'flex',
                                                gap: 9,
                                                lineHeight: 1.55,
                                            }}
                                        >
                                            <AIcon
                                                name="user-x"
                                                size={16}
                                                color={C.amber}
                                            />
                                            <span>
                                                Anda yang mencairkan uang muka
                                                ini. Pertanggungjawaban harus
                                                diperiksa orang lain.
                                            </span>
                                        </div>
                                    ) : (
                                        <>
                                            <p
                                                style={{
                                                    fontSize: 12.5,
                                                    color: C.muted,
                                                    margin: '0 0 14px',
                                                    lineHeight: 1.55,
                                                }}
                                            >
                                                Catat berapa yang benar-benar
                                                terpakai beserta buktinya.
                                            </p>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setSettling(true)
                                                }
                                                style={{
                                                    ...btnP,
                                                    width: '100%',
                                                    justifyContent: 'center',
                                                }}
                                            >
                                                <AIcon
                                                    name="clipboard-check"
                                                    size={16}
                                                    color="#fff"
                                                />
                                                Pertanggungjawabkan
                                            </button>
                                        </>
                                    )}
                                </div>
                            </div>
                        )}

                        <Link
                            href={CashAdvanceController.index().url}
                            style={{
                                ...btnOut,
                                justifyContent: 'center',
                                textDecoration: 'none',
                            }}
                        >
                            <AIcon name="arrow-left" size={15} color={C.text} />
                            Kembali ke Daftar
                        </Link>
                    </div>
                </div>
            </div>

            <FormDialog
                open={disbursing}
                onOpenChange={setDisbursing}
                title="Pencairan Uang Muka"
                description={`${rp(advance.amount)} untuk ${advance.employee?.name ?? 'karyawan'}`}
                submitLabel="Catat Pencairan"
                processing={disburseForm.processing}
                onSubmit={() =>
                    disburseForm.post(
                        CashAdvanceController.disburse(advance.route_key).url,
                        {
                            preserveScroll: true,
                            onSuccess: () => setDisbursing(false),
                        },
                    )
                }
            >
                <Field
                    label="Metode Pencairan"
                    required
                    error={disburseForm.errors.disbursement_method}
                >
                    <select
                        value={disburseForm.data.disbursement_method}
                        onChange={(event) =>
                            disburseForm.setData(
                                'disbursement_method',
                                event.target.value,
                            )
                        }
                        style={selectStyle}
                    >
                        {disbursementMethods.map((method) => (
                            <option key={method.value} value={method.value}>
                                {method.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Nomor Referensi"
                    error={disburseForm.errors.disbursement_reference}
                >
                    <input
                        type="text"
                        placeholder="Nomor transaksi / bukti transfer"
                        value={disburseForm.data.disbursement_reference}
                        onChange={(event) =>
                            disburseForm.setData(
                                'disbursement_reference',
                                event.target.value,
                            )
                        }
                        style={textInputStyle}
                    />
                </Field>
            </FormDialog>

            <FormDialog
                open={settling}
                onOpenChange={setSettling}
                title="Pertanggungjawaban Uang Muka"
                description={`Uang muka ${rp(advance.amount)} untuk ${advance.employee?.name ?? 'karyawan'}`}
                submitLabel="Simpan Pertanggungjawaban"
                processing={settleForm.processing}
                onSubmit={() =>
                    settleForm.post(
                        CashAdvanceController.settle(advance.route_key).url,
                        {
                            preserveScroll: true,
                            forceFormData: true,
                            onSuccess: () => {
                                setSettling(false);
                                settleForm.reset();
                            },
                        },
                    )
                }
            >
                <Field
                    label="Jumlah Terpakai"
                    required
                    error={settleForm.errors.spent_amount}
                >
                    <RupiahInput
                        value={settleForm.data.spent_amount}
                        onChange={(raw) =>
                            settleForm.setData('spent_amount', raw)
                        }
                        invalid={!!settleForm.errors.spent_amount}
                    />
                </Field>

                {settleForm.data.spent_amount !== '' && (
                    <Balance returned={split.returned} topup={split.topup} />
                )}

                <Field
                    label="Bukti Pengeluaran"
                    error={settleForm.errors.receipt}
                >
                    <FileDropzone
                        files={
                            settleForm.data.receipt
                                ? [settleForm.data.receipt]
                                : []
                        }
                        onChange={(files) =>
                            settleForm.setData('receipt', files[0] ?? null)
                        }
                        label="Seret kuitansi ke sini"
                    />
                </Field>

                <Field
                    label="Catatan"
                    error={settleForm.errors.settlement_note}
                >
                    <textarea
                        rows={2}
                        placeholder="Rincian singkat penggunaan dana…"
                        value={settleForm.data.settlement_note}
                        onChange={(event) =>
                            settleForm.setData(
                                'settlement_note',
                                event.target.value,
                            )
                        }
                        style={{ ...textInputStyle, resize: 'vertical' }}
                    />
                </Field>
            </FormDialog>
        </>
    );
}

/** Which way the money still has to move, if at all. */
function Balance({ returned, topup }: { returned: number; topup: number }) {
    const owed = topup > 0;

    return (
        <div
            style={{
                background: owed ? '#FFF7ED' : '#F0FDF4',
                borderLeft: `3px solid ${owed ? C.amber : C.green}`,
                borderRadius: 8,
                padding: '11px 13px',
                fontSize: 12.5,
                lineHeight: 1.55,
                color: owed ? '#9A3412' : '#166534',
            }}
        >
            {returned > 0 && (
                <>
                    Sisa <strong>{rp(returned)}</strong> harus dikembalikan
                    karyawan.
                </>
            )}
            {owed && (
                <>
                    Kekurangan <strong>{rp(topup)}</strong> harus dibayarkan ke
                    karyawan.
                </>
            )}
            {returned === 0 &&
                !owed &&
                'Uang muka terpakai pas — tidak ada sisa maupun kekurangan.'}
        </div>
    );
}

function Fact({
    label,
    value,
    accent,
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
                    color: C.faint,
                    textTransform: 'uppercase',
                    letterSpacing: '.04em',
                    marginBottom: 5,
                }}
            >
                {label}
            </div>
            <div
                style={{
                    fontSize: accent ? 18 : 13.5,
                    fontWeight: accent ? 700 : 500,
                    color: accent ? C.primary : C.text,
                }}
            >
                {value}
            </div>
        </div>
    );
}

/** One node of the request → approval → disbursement → settlement trail. */
function Step({
    label,
    at,
    by,
    done,
    isLast,
}: {
    label: string;
    at?: string | null;
    by?: string | null;
    done: boolean;
    isLast?: boolean;
}) {
    return (
        <div style={{ display: 'flex', gap: 12 }}>
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                }}
            >
                <div
                    style={{
                        width: 18,
                        height: 18,
                        borderRadius: '50%',
                        border: `2px solid ${done ? C.green : C.border}`,
                        background: done ? C.green : '#fff',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                    }}
                >
                    {done && <AIcon name="check" size={11} color="#fff" />}
                </div>
                {!isLast && (
                    <div
                        style={{
                            width: 2,
                            flex: 1,
                            minHeight: 26,
                            background: C.border,
                        }}
                    />
                )}
            </div>
            <div style={{ paddingBottom: isLast ? 0 : 16 }}>
                <div
                    style={{
                        fontSize: 13,
                        fontWeight: 600,
                        color: done ? C.text : C.faint,
                    }}
                >
                    {label}
                </div>
                {(at || by) && (
                    <div
                        style={{
                            fontSize: 11.5,
                            color: C.faint,
                            marginTop: 2,
                        }}
                    >
                        {[by, at].filter(Boolean).join(' · ')}
                    </div>
                )}
            </div>
        </div>
    );
}
