import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import SalaryMasterController from '@/actions/App/Http/Controllers/Avana/SalaryMasterController';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import { LockedAlert } from './components';
import { PeriodTable } from './period-table';
import { SlipDetail } from './slip-detail';
import { SummaryCard } from './summary-card';
import type { CSSProperties } from 'react';
import type { FlashProps, PayrollProps } from './types';

const exportItem: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    gap: 10,
    width: '100%',
    textAlign: 'left',
    padding: '9px 8px',
    borderRadius: 8,
    border: 'none',
    background: 'transparent',
    cursor: 'pointer',
    fontSize: 13.5,
    color: C.text,
    textDecoration: 'none',
};

/** A single step in the payroll process bar. */
function ProcessStep({
    n,
    label,
    icon,
    done = false,
    disabled = false,
    primary = false,
    onClick,
}: {
    n: number;
    label: string;
    icon: string;
    done?: boolean;
    disabled?: boolean;
    primary?: boolean;
    onClick: () => void;
}) {
    const bg = done ? '#EAF7EF' : primary && !disabled ? C.primary : '#fff';
    const fg = done
        ? C.green
        : primary && !disabled
          ? '#fff'
          : disabled
            ? C.faint
            : C.text;
    const border = done ? C.green : primary && !disabled ? C.primary : C.border;

    return (
        <button
            onClick={onClick}
            disabled={disabled}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 8,
                padding: '9px 15px',
                borderRadius: 9,
                fontSize: 13.5,
                fontWeight: 600,
                border: `1px solid ${border}`,
                background: bg,
                color: fg,
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.65 : 1,
            }}
        >
            <span
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    width: 18,
                    height: 18,
                    borderRadius: 999,
                    fontSize: 11,
                    fontWeight: 700,
                    background: done
                        ? C.green
                        : primary && !disabled
                          ? 'rgba(255,255,255,.25)'
                          : C.line,
                    color: done || (primary && !disabled) ? '#fff' : C.muted,
                }}
            >
                {done ? <AIcon name="check" size={12} color="#fff" /> : n}
            </span>
            <AIcon name={icon} size={15} color={fg} />
            {label}
        </button>
    );
}

function StepArrow() {
    return <AIcon name="chevron-right" size={16} color={C.faint} />;
}

export default function AvanaPayroll({
    periods,
    summary,
    slip,
    filters,
}: PayrollProps) {
    const { flash, errors } = usePage<FlashProps>().props;
    const meta = periods.meta;
    const isLocked = summary.status === 'locked';
    const isApproved = summary.status === 'approved';
    const hasRun = summary.status === 'calculated' || isApproved || isLocked;
    const [bank, setBank] = useState('generic');
    const [exportOpen, setExportOpen] = useState(false);
    const [runOpen, setRunOpen] = useState(false);
    const [payDate, setPayDate] = useState('');
    const [processing, setProcessing] = useState(false);
    const [approvalOpen, setApprovalOpen] = useState(false);
    const [approvalNote, setApprovalNote] = useState('');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    useEffect(() => {
        if (errors?.payroll) {
            toast.error(errors.payroll, { id: errors.payroll });
        }
    }, [errors?.payroll]);

    const openRun = () => {
        if (isLocked) {
            return;
        }

        setPayDate(summary.pay_date ?? new Date().toISOString().slice(0, 10));
        setRunOpen(true);
    };

    const confirmRun = () => {
        router.post(
            PayrollController.run().url,
            { pay_date: payDate },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setRunOpen(false),
            },
        );
    };

    const openApproval = () => {
        if (isLocked || !hasRun || isApproved) {
            return;
        }

        setApprovalNote('');
        setApprovalOpen(true);
    };

    const confirmApprove = () => {
        router.post(
            PayrollController.approve().url,
            { note: approvalNote },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setApprovalOpen(false),
            },
        );
    };

    const confirmReject = () => {
        if (approvalNote.trim().length < 3) {
            toast.error('Alasan penolakan minimal 3 karakter.');
            return;
        }

        router.post(
            PayrollController.reject().url,
            { note: approvalNote.trim() },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setApprovalOpen(false),
            },
        );
    };

    const lockPayroll = () => {
        if (isLocked) {
            return;
        }

        router.post(PayrollController.lock().url, {}, { preserveScroll: true });
    };

    const unlockPayroll = () => {
        if (!isLocked || summary.period_id == null) {
            return;
        }

        const reason = window.prompt(
            'Alasan membuka kembali periode terkunci (min. 5 karakter). Tindakan ini dicatat di Audit Trail dan membatalkan pemotongan cicilan periode ini.',
        );

        if (reason == null) {
            return;
        }

        if (reason.trim().length < 5) {
            toast.error('Alasan minimal 5 karakter.');
            return;
        }

        router.post(
            PayrollController.unlock().url,
            { payroll_period_id: summary.period_id, reason: reason.trim() },
            { preserveScroll: true },
        );
    };

    const exportPayroll = () => {
        window.location.href = '/avana/laporan/export/payroll';
    };

    const generateThr = () => {
        router.post(PayrollController.thr().url, {}, { preserveScroll: true });
    };

    // Per-row lifecycle actions — target a specific period id, so THR and older
    // periods can be advanced/disbursed directly from their row.
    const rowApprove = (id: number) =>
        router.post(
            PayrollController.approve().url,
            { payroll_period_id: id },
            { preserveScroll: true },
        );

    const rowLock = (id: number) => {
        if (
            !window.confirm(
                'Kunci periode ini? Cicilan pinjaman/kasbon akan dipotong.',
            )
        ) {
            return;
        }

        router.post(
            PayrollController.lock().url,
            { payroll_period_id: id },
            { preserveScroll: true },
        );
    };

    // Fetch the transfer CSV as a blob so the browser reliably saves it to
    // Downloads, and surface a clear error instead of a silent page reload when
    // the period is not locked (the endpoint redirects back in that case).
    const downloadTransfer = async (url: string) => {
        try {
            const res = await fetch(url, {
                headers: { Accept: 'text/csv' },
            });
            const contentType = res.headers.get('Content-Type') ?? '';

            if (!res.ok || !contentType.includes('csv')) {
                toast.error(
                    'Kunci periode dulu sebelum unduh file transfer bank.',
                );
                return;
            }

            const blob = await res.blob();
            const disposition = res.headers.get('Content-Disposition') ?? '';
            const filename =
                disposition.match(/filename=([^;]+)/)?.[1]?.trim() ??
                'transfer.csv';

            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(objectUrl);
            toast.success('File transfer terunduh.');
        } catch {
            toast.error('Gagal mengunduh file transfer.');
        }
    };

    const rowTransfer = (id: number) => {
        downloadTransfer(
            `/avana/payroll/transfer?bank=generic&payroll_period_id=${id}`,
        );
    };

    const goToPage = (page: number) => {
        router.get(
            window.location.pathname,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Payroll" />
            <div style={{ padding: '28px 32px' }}>
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
                            <span>Beranda</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Payroll</span>
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
                            Payroll
                        </h1>
                        <div
                            style={{
                                fontSize: 14,
                                color: C.muted,
                                marginTop: 4,
                            }}
                        >
                            Kelola penggajian &amp; slip gaji karyawan
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        <Link
                            href={SalaryMasterController.index().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="file-cog" size={16} />
                            Master Gaji
                        </Link>
                        <button onClick={generateThr} style={btnOut}>
                            <AIcon name="gift" size={16} />
                            Generate THR
                        </button>
                        <Link
                            href={PayrollController.createPeriod().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="calendar-plus" size={16} />
                            Buat Periode
                        </Link>

                        {/* Export dropdown */}
                        <div style={{ position: 'relative' }}>
                            <button
                                onClick={() => setExportOpen((o) => !o)}
                                style={btnOut}
                            >
                                <AIcon name="download" size={16} />
                                Export
                                <AIcon name="chevron-down" size={14} />
                            </button>
                            {exportOpen && (
                                <>
                                    <div
                                        onClick={() => setExportOpen(false)}
                                        style={{
                                            position: 'fixed',
                                            inset: 0,
                                            zIndex: 40,
                                        }}
                                    />
                                    <div
                                        style={{
                                            position: 'absolute',
                                            right: 0,
                                            top: 46,
                                            width: 268,
                                            background: '#fff',
                                            border: `1px solid ${C.border}`,
                                            borderRadius: 10,
                                            boxShadow:
                                                '0 12px 32px rgba(15,23,42,.14)',
                                            zIndex: 50,
                                            padding: 6,
                                        }}
                                    >
                                        <button
                                            onClick={() => {
                                                exportPayroll();
                                                setExportOpen(false);
                                            }}
                                            style={exportItem}
                                        >
                                            <AIcon
                                                name="file-spreadsheet"
                                                size={16}
                                                color={C.muted}
                                            />
                                            Laporan Payroll (Excel)
                                        </button>
                                        {isLocked ? (
                                            <a
                                                href={
                                                    PayrollController.bpjsFile()
                                                        .url
                                                }
                                                onClick={() =>
                                                    setExportOpen(false)
                                                }
                                                style={exportItem}
                                            >
                                                <AIcon
                                                    name="shield-check"
                                                    size={16}
                                                    color={C.muted}
                                                />
                                                Export BPJS
                                            </a>
                                        ) : (
                                            <div
                                                title="Kunci periode dulu"
                                                style={{
                                                    ...exportItem,
                                                    opacity: 0.5,
                                                    cursor: 'not-allowed',
                                                }}
                                            >
                                                <AIcon
                                                    name="shield-check"
                                                    size={16}
                                                    color={C.faint}
                                                />
                                                Export BPJS
                                            </div>
                                        )}
                                        <div
                                            style={{
                                                borderTop: `1px solid ${C.line}`,
                                                margin: '6px 4px',
                                                paddingTop: 8,
                                                fontSize: 11,
                                                fontWeight: 600,
                                                color: C.faint,
                                                paddingLeft: 6,
                                                textTransform: 'uppercase',
                                                letterSpacing: '.04em',
                                            }}
                                        >
                                            File Transfer Bank
                                            {!isLocked && (
                                                <span
                                                    style={{
                                                        display: 'block',
                                                        textTransform: 'none',
                                                        fontWeight: 500,
                                                        letterSpacing: 0,
                                                        color: C.amber,
                                                        marginTop: 2,
                                                    }}
                                                >
                                                    Kunci periode dulu
                                                </span>
                                            )}
                                        </div>
                                        <div
                                            style={{
                                                display: 'flex',
                                                gap: 6,
                                                padding: '2px 4px 4px',
                                            }}
                                        >
                                            <select
                                                value={bank}
                                                onChange={(e) =>
                                                    setBank(e.target.value)
                                                }
                                                style={{
                                                    flex: 1,
                                                    height: 36,
                                                    padding: '0 10px',
                                                    border: `1px solid ${C.border}`,
                                                    borderRadius: 8,
                                                    fontSize: 13,
                                                    cursor: 'pointer',
                                                }}
                                            >
                                                <option value="generic">
                                                    Format Umum
                                                </option>
                                                <option value="bca">BCA</option>
                                                <option value="mandiri">
                                                    Mandiri
                                                </option>
                                                <option value="bni">BNI</option>
                                                <option value="bri">BRI</option>
                                            </select>
                                            {isLocked ? (
                                                <button
                                                    onClick={() => {
                                                        setExportOpen(false);
                                                        downloadTransfer(
                                                            `${PayrollController.transferFile().url}?bank=${bank}`,
                                                        );
                                                    }}
                                                    style={{
                                                        ...btnP,
                                                        height: 36,
                                                        textDecoration: 'none',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="banknote"
                                                        size={15}
                                                        color="#fff"
                                                    />
                                                    Unduh
                                                </button>
                                            ) : (
                                                <span
                                                    title="Kunci periode dulu"
                                                    style={{
                                                        ...btnP,
                                                        height: 36,
                                                        opacity: 0.5,
                                                        cursor: 'not-allowed',
                                                    }}
                                                >
                                                    <AIcon
                                                        name="banknote"
                                                        size={15}
                                                        color="#fff"
                                                    />
                                                    Unduh
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                {/* Proses gaji — stepper: Jalankan → Setujui → Kunci */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        flexWrap: 'wrap',
                        background: '#fff',
                        border: `1px solid ${C.border}`,
                        borderRadius: 12,
                        padding: '14px 18px',
                        margin: '20px 0 18px',
                    }}
                >
                    <span
                        style={{
                            fontSize: 12.5,
                            fontWeight: 700,
                            color: C.muted,
                            textTransform: 'uppercase',
                            letterSpacing: '.05em',
                            marginRight: 4,
                        }}
                    >
                        Proses Gaji
                    </span>

                    <ProcessStep
                        n={1}
                        label="Jalankan"
                        icon="play"
                        primary
                        done={hasRun}
                        disabled={isLocked}
                        onClick={openRun}
                    />
                    <StepArrow />
                    <ProcessStep
                        n={2}
                        label={isApproved || isLocked ? 'Disetujui' : 'Setujui'}
                        icon="check-check"
                        done={isApproved || isLocked}
                        disabled={!hasRun || isLocked || isApproved}
                        onClick={openApproval}
                    />
                    <StepArrow />
                    <ProcessStep
                        n={3}
                        label={isLocked ? 'Terkunci' : 'Kunci Periode'}
                        icon="lock"
                        done={isLocked}
                        disabled={!isApproved || isLocked}
                        onClick={lockPayroll}
                    />

                    {/* Optional correction step — available after a run, before approval */}
                    {hasRun && !isApproved && !isLocked && (
                        <Link
                            href="/avana/payroll/koreksi"
                            style={{
                                marginLeft: 'auto',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.primary,
                                textDecoration: 'none',
                            }}
                        >
                            <AIcon name="pencil" size={15} color={C.primary} />
                            Perlu koreksi?
                        </Link>
                    )}

                    {/* Authorized unlock — reopens a finalized period for adjustment */}
                    {isLocked && (
                        <button
                            onClick={unlockPayroll}
                            style={{
                                marginLeft: 'auto',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                fontSize: 13,
                                fontWeight: 600,
                                color: C.red,
                                background: 'transparent',
                                border: `1px solid ${C.red}`,
                                borderRadius: 8,
                                padding: '7px 14px',
                                cursor: 'pointer',
                            }}
                        >
                            <AIcon name="lock-open" size={15} color={C.red} />
                            Buka Kembali
                        </button>
                    )}
                </div>

                {isLocked && <LockedAlert />}

                {!isLocked && summary.rejection_note && (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            gap: 10,
                            padding: '12px 16px',
                            borderRadius: 10,
                            background: '#FEF2F2',
                            border: `1px solid #FECACA`,
                            marginBottom: 16,
                        }}
                    >
                        <AIcon name="x-circle" size={18} color={C.red} />
                        <div style={{ fontSize: 13, color: '#991B1B' }}>
                            <strong>Payroll ditolak.</strong>{' '}
                            {summary.rejection_note} — perbaiki lalu jalankan &
                            ajukan ulang.
                        </div>
                    </div>
                )}

                {isApproved && summary.approval_note && (
                    <div
                        style={{
                            fontSize: 13,
                            color: C.muted,
                            marginBottom: 16,
                        }}
                    >
                        <strong style={{ color: C.navy }}>
                            Catatan persetujuan:
                        </strong>{' '}
                        {summary.approval_note}
                    </div>
                )}

                {/* Run summary */}
                <SummaryCard summary={summary} />

                <div
                    className="avn-2col"
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1.25fr 1fr',
                        gap: 18,
                        alignItems: 'start',
                    }}
                >
                    {/* Periods */}
                    <PeriodTable
                        periods={periods.data}
                        meta={meta}
                        onGoToPage={goToPage}
                        onApprove={rowApprove}
                        onLock={rowLock}
                        onTransfer={rowTransfer}
                    />

                    {/* Slip gaji detail */}
                    <SlipDetail slip={slip} period={summary.period} />
                </div>
            </div>

            {runOpen && (
                <RunConfirmModal
                    summary={summary}
                    payDate={payDate}
                    setPayDate={setPayDate}
                    processing={processing}
                    onCancel={() => setRunOpen(false)}
                    onConfirm={confirmRun}
                />
            )}

            {approvalOpen && (
                <ApprovalModal
                    note={approvalNote}
                    setNote={setApprovalNote}
                    processing={processing}
                    onCancel={() => setApprovalOpen(false)}
                    onApprove={confirmApprove}
                    onReject={confirmReject}
                />
            )}
        </>
    );
}

/**
 * Approval decision (BPR manual 1.3.1): approve with an optional keterangan, or
 * reject with a mandatory reason that sends the run back to the processor.
 */
function ApprovalModal({
    note,
    setNote,
    processing,
    onCancel,
    onApprove,
    onReject,
}: {
    note: string;
    setNote: (v: string) => void;
    processing: boolean;
    onCancel: () => void;
    onApprove: () => void;
    onReject: () => void;
}) {
    return (
        <div
            onClick={onCancel}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(15,23,42,.45)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 80,
                padding: 20,
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    width: 440,
                    maxWidth: '100%',
                    background: '#fff',
                    borderRadius: 14,
                    padding: 24,
                    boxShadow: '0 24px 60px rgba(15,23,42,.24)',
                }}
            >
                <div
                    style={{
                        fontSize: 16,
                        fontWeight: 700,
                        color: C.navy,
                        marginBottom: 4,
                    }}
                >
                    Persetujuan Proses Gaji
                </div>
                <div
                    style={{
                        fontSize: 12.5,
                        color: C.muted,
                        marginBottom: 14,
                    }}
                >
                    Setujui untuk lanjut ke penguncian, atau tolak dengan alasan
                    untuk dikembalikan ke pemroses.
                </div>

                <label
                    style={{
                        display: 'block',
                        fontSize: 13,
                        fontWeight: 600,
                        color: C.navy,
                        marginBottom: 6,
                    }}
                >
                    Keterangan{' '}
                    <span style={{ color: C.faint, fontWeight: 400 }}>
                        (wajib bila menolak)
                    </span>
                </label>
                <textarea
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    rows={3}
                    placeholder="Catatan persetujuan / alasan penolakan…"
                    style={{
                        width: '100%',
                        padding: '10px 12px',
                        borderRadius: 8,
                        border: `1px solid ${C.line}`,
                        fontSize: 13.5,
                        color: C.text,
                        outline: 'none',
                        resize: 'vertical',
                        fontFamily: 'inherit',
                    }}
                />

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        gap: 10,
                        marginTop: 20,
                    }}
                >
                    <button
                        onClick={onReject}
                        disabled={processing}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 7,
                            padding: '9px 16px',
                            borderRadius: 8,
                            border: `1px solid ${C.red}`,
                            background: 'transparent',
                            color: C.red,
                            fontSize: 13,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        <AIcon name="x" size={15} color={C.red} />
                        Tolak
                    </button>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button
                            onClick={onCancel}
                            disabled={processing}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            Batal
                        </button>
                        <button
                            onClick={onApprove}
                            disabled={processing}
                            style={{ ...btnP, opacity: processing ? 0.6 : 1 }}
                        >
                            <AIcon name="check-check" size={15} color="#fff" />
                            {processing ? 'Memproses…' : 'Setujui'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Pre-run confirmation (BPR manual 1.3.1): show the period being processed and
 * let HR verify/adjust the pay date — it drives the bank disbursement — before
 * executing.
 */
function RunConfirmModal({
    summary,
    payDate,
    setPayDate,
    processing,
    onCancel,
    onConfirm,
}: {
    summary: PayrollProps['summary'];
    payDate: string;
    setPayDate: (v: string) => void;
    processing: boolean;
    onCancel: () => void;
    onConfirm: () => void;
}) {
    const row = (label: string, value: string) => (
        <div
            style={{
                display: 'flex',
                justifyContent: 'space-between',
                gap: 16,
                padding: '9px 0',
                borderBottom: `1px solid ${C.line}`,
                fontSize: 13.5,
            }}
        >
            <span style={{ color: C.muted }}>{label}</span>
            <span style={{ color: C.text, fontWeight: 600 }}>{value}</span>
        </div>
    );

    return (
        <div
            onClick={onCancel}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(15,23,42,.45)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 80,
                padding: 20,
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    width: 440,
                    maxWidth: '100%',
                    background: '#fff',
                    borderRadius: 14,
                    padding: 24,
                    boxShadow: '0 24px 60px rgba(15,23,42,.24)',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        marginBottom: 6,
                    }}
                >
                    <div
                        style={{
                            width: 40,
                            height: 40,
                            borderRadius: 10,
                            background: '#EAF2FF',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <AIcon name="calculator" size={20} color={C.primary} />
                    </div>
                    <div>
                        <div
                            style={{
                                fontSize: 16,
                                fontWeight: 700,
                                color: C.navy,
                            }}
                        >
                            Proses Perhitungan Gaji
                        </div>
                        <div style={{ fontSize: 12.5, color: C.muted }}>
                            Periksa detail sebelum mengeksekusi.
                        </div>
                    </div>
                </div>

                <div style={{ margin: '14px 0 4px' }}>
                    {row('Periode', summary.period ?? '—')}
                    {row(
                        'Rentang',
                        summary.start_date && summary.end_date
                            ? `${summary.start_date} – ${summary.end_date}`
                            : '—',
                    )}
                    {row('Karyawan', String(summary.employee_count))}
                </div>

                <div style={{ margin: '14px 0 4px' }}>
                    <label
                        style={{
                            display: 'block',
                            fontSize: 13,
                            fontWeight: 600,
                            color: C.navy,
                            marginBottom: 6,
                        }}
                    >
                        Tanggal Bayar
                    </label>
                    <input
                        type="date"
                        value={payDate}
                        onChange={(e) => setPayDate(e.target.value)}
                        style={{
                            width: '100%',
                            padding: '10px 12px',
                            borderRadius: 8,
                            border: `1px solid ${C.line}`,
                            fontSize: 13.5,
                            color: C.text,
                            outline: 'none',
                        }}
                    />
                    <div
                        style={{
                            fontSize: 12,
                            color: C.faint,
                            marginTop: 6,
                        }}
                    >
                        Pastikan tanggal bayar sudah sesuai — menentukan
                        pengiriman uang ke rekening pegawai.
                    </div>
                </div>

                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 10,
                        marginTop: 20,
                    }}
                >
                    <button
                        onClick={onCancel}
                        disabled={processing}
                        style={{ ...btnOut, textDecoration: 'none' }}
                    >
                        Batal
                    </button>
                    <button
                        onClick={onConfirm}
                        disabled={processing || !payDate}
                        style={{
                            ...btnP,
                            opacity: processing || !payDate ? 0.6 : 1,
                        }}
                    >
                        <AIcon name="play" size={15} color="#fff" />
                        {processing ? 'Memproses…' : 'Proses Gaji'}
                    </button>
                </div>
            </div>
        </div>
    );
}
