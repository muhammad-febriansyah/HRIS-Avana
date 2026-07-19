import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CashAdvanceController from '@/actions/App/Http/Controllers/Avana/CashAdvanceController';
import { ConfirmDialog } from '@/components/avana-ui/confirm-dialog';
import { FileDropzone } from '@/components/avana-ui/file-dropzone';
import { FormDialog } from '@/components/avana-ui/form-dialog';
import { AIcon, ActionBtn, btnP, C, card, rp, RupiahInput } from '@/lib/avana';
import {
    Field,
    KpiCard,
    selectStyle,
    StatusPill,
    textInputStyle,
} from './components';
import { STATUS_OPTIONS } from './types';
import type {
    CashAdvanceRow,
    FlashProps,
    KasbonFilters,
    KasbonIndexProps,
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

interface DisburseForm {
    disbursement_method: string;
    disbursement_reference: string;
    [key: string]: string;
}

interface SettleForm {
    spent_amount: string;
    settlement_note: string;
    receipt: File | null;
    [key: string]: string | File | null;
}

export default function KasbonIndex({
    requests,
    filters,
    disbursementMethods,
    kpis,
    authUserId,
}: KasbonIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = requests.meta;

    const [disbursing, setDisbursing] = useState<CashAdvanceRow | null>(null);
    const [settling, setSettling] = useState<CashAdvanceRow | null>(null);
    const [deleting, setDeleting] = useState<CashAdvanceRow | null>(null);

    const disburseForm = useForm<DisburseForm>({
        disbursement_method: disbursementMethods[0]?.value ?? 'transfer',
        disbursement_reference: '',
    });

    const settleForm = useForm<SettleForm>({
        spent_amount: '',
        settlement_note: '',
        receipt: null,
    });

    /** What still has to move once the spend is known. */
    const settlementSplit = (() => {
        const advanced = settling?.amount ?? 0;
        const spent = Number(settleForm.data.spent_amount) || 0;
        const difference = Math.round((advanced - spent) * 100) / 100;

        return {
            returned: Math.max(difference, 0),
            topup: Math.max(-difference, 0),
        };
    })();

    const submitSettlement = () => {
        if (!settling) {
            return;
        }

        settleForm.post(CashAdvanceController.settle(settling.id).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setSettling(null);
                settleForm.reset();
            },
        });
    };

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const applyFilters = (next: Partial<KasbonFilters>) => {
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

    const approve = (id: number) => {
        router.post(
            CashAdvanceController.approve(id).url,
            {},
            { preserveScroll: true },
        );
    };

    const reject = (id: number) => {
        router.post(
            CashAdvanceController.reject(id).url,
            {},
            { preserveScroll: true },
        );
    };

    const submitDisburse = () => {
        if (!disbursing) {
            return;
        }

        disburseForm.post(CashAdvanceController.disburse(disbursing.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setDisbursing(null);
                disburseForm.reset();
            },
        });
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }

        router.delete(CashAdvanceController.destroy(deleting.id).url, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title="Cash Advance" />
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
                            <span style={{ color: C.muted }}>Cash Advance</span>
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
                            Cash Advance / Uang Muka
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Pengajuan uang muka, approval, dan pencairan oleh
                            Finance
                        </div>
                    </div>
                    <Link
                        href={CashAdvanceController.create()}
                        style={{ ...btnP, textDecoration: 'none' }}
                    >
                        <AIcon name="plus" size={16} color="#fff" />
                        Ajukan Uang Muka
                    </Link>
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
                        label="Menunggu Approval"
                        value={String(kpis.pending)}
                        accent="#D97706"
                    />
                    <KpiCard
                        icon="check"
                        label="Siap Dicairkan"
                        value={String(kpis.approved)}
                        accent="#16A34A"
                    />
                    <KpiCard
                        icon="hand-coins"
                        label="Belum Dipertanggungjawabkan"
                        value={String(kpis.disbursed)}
                        accent={C.primary}
                    />
                    <KpiCard
                        icon="wallet"
                        label="Dana Beredar"
                        value={rp(kpis.outstanding_amount)}
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
                            Daftar Uang Muka
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
                                    placeholder="Cari karyawan"
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
                                        width: 180,
                                    }}
                                />
                            </div>
                            <select
                                value={filters.status ?? ''}
                                onChange={(event) =>
                                    applyFilters({
                                        status: (event.target.value ||
                                            undefined) as KasbonFilters['status'],
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
                        </div>
                    </div>
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
                                    <th style={headThStyle}>Karyawan</th>
                                    <th style={headThStyle}>Keperluan</th>
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
                                                    name="wallet"
                                                    size={28}
                                                    color={C.faint}
                                                />
                                                <div>
                                                    Tidak ada pengajuan uang
                                                    muka.
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
                                        <td
                                            style={{
                                                ...cellStyle,
                                                maxWidth: 220,
                                            }}
                                        >
                                            {row.purpose ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '12px 16px',
                                                fontSize: 13,
                                                fontWeight: 600,
                                                color: C.text,
                                            }}
                                        >
                                            {rp(row.amount)}
                                        </td>
                                        <td style={cellStyle}>
                                            {row.request_date ?? '—'}
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
                                                <ActionBtn
                                                    icon="eye"
                                                    label="Detail"
                                                    variant="warning"
                                                    onClick={() =>
                                                        router.visit(
                                                            CashAdvanceController.show(
                                                                row.id,
                                                            ).url,
                                                        )
                                                    }
                                                />
                                                {row.status === 'pending' && (
                                                    <>
                                                        <ActionBtn
                                                            icon="check"
                                                            label="Setujui"
                                                            variant="primary"
                                                            onClick={() =>
                                                                approve(row.id)
                                                            }
                                                        />
                                                        <ActionBtn
                                                            icon="x"
                                                            label="Tolak"
                                                            variant="warning"
                                                            onClick={() =>
                                                                reject(row.id)
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
                                                {row.status === 'approved' && (
                                                    <ActionBtn
                                                        icon="hand-coins"
                                                        label="Cairkan"
                                                        variant="primary"
                                                        onClick={() =>
                                                            setDisbursing(row)
                                                        }
                                                    />
                                                )}
                                                {row.status === 'disbursed' &&
                                                    (row.disbursed_by ===
                                                    authUserId ? (
                                                        <span
                                                            title="Anda yang mencairkan uang muka ini — pertanggungjawaban harus diperiksa orang lain."
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
                                                            icon="clipboard-check"
                                                            label="Pertanggungjawabkan"
                                                            variant="primary"
                                                            onClick={() =>
                                                                setSettling(row)
                                                            }
                                                        />
                                                    ))}
                                                {row.status === 'rejected' && (
                                                    <span
                                                        style={{
                                                            fontSize: 12.5,
                                                            color: C.faint,
                                                        }}
                                                    >
                                                        —
                                                    </span>
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
                open={disbursing !== null}
                onOpenChange={(open) => !open && setDisbursing(null)}
                title="Pencairan Uang Muka"
                description={
                    disbursing
                        ? `${rp(disbursing.amount)} untuk ${disbursing.employee?.name ?? 'karyawan'}`
                        : undefined
                }
                submitLabel="Catat Pencairan"
                onSubmit={submitDisburse}
                processing={disburseForm.processing}
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
                open={settling !== null}
                onOpenChange={(open) => !open && setSettling(null)}
                title="Pertanggungjawaban Uang Muka"
                description={
                    settling
                        ? `Uang muka ${rp(settling.amount)} untuk ${settling.employee?.name ?? 'karyawan'}`
                        : undefined
                }
                submitLabel="Simpan Pertanggungjawaban"
                onSubmit={submitSettlement}
                processing={settleForm.processing}
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
                    <div
                        style={{
                            background:
                                settlementSplit.topup > 0
                                    ? '#FFF7ED'
                                    : '#F0FDF4',
                            borderLeft: `3px solid ${
                                settlementSplit.topup > 0 ? C.amber : C.green
                            }`,
                            borderRadius: 8,
                            padding: '11px 13px',
                            fontSize: 12.5,
                            lineHeight: 1.55,
                            color:
                                settlementSplit.topup > 0
                                    ? '#9A3412'
                                    : '#166534',
                        }}
                    >
                        {settlementSplit.returned > 0 && (
                            <>
                                Sisa{' '}
                                <strong>{rp(settlementSplit.returned)}</strong>{' '}
                                harus dikembalikan karyawan.
                            </>
                        )}
                        {settlementSplit.topup > 0 && (
                            <>
                                Kekurangan{' '}
                                <strong>{rp(settlementSplit.topup)}</strong>{' '}
                                harus dibayarkan ke karyawan.
                            </>
                        )}
                        {settlementSplit.returned === 0 &&
                            settlementSplit.topup === 0 &&
                            'Uang muka terpakai pas — tidak ada sisa maupun kekurangan.'}
                    </div>
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

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Hapus pengajuan uang muka?"
                description={
                    deleting
                        ? `Pengajuan ${rp(deleting.amount)} atas nama ${deleting.employee?.name ?? 'karyawan'} akan dihapus permanen.`
                        : undefined
                }
                destructive
                onConfirm={confirmDelete}
            />
        </>
    );
}
