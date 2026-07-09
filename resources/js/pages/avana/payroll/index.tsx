import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import PositionComponentController from '@/actions/App/Http/Controllers/Avana/PositionComponentController';
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
    const { flash } = usePage<FlashProps>().props;
    const meta = periods.meta;
    const isLocked = summary.status === 'locked';
    const isApproved = summary.status === 'approved';
    const hasRun = summary.status === 'calculated' || isApproved || isLocked;
    const [bank, setBank] = useState('generic');
    const [exportOpen, setExportOpen] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const runPayroll = () => {
        if (isLocked) {
            return;
        }

        router.post(PayrollController.run().url, {}, { preserveScroll: true });
    };

    const approvePayroll = () => {
        if (isLocked) {
            return;
        }

        router.post(
            PayrollController.approve().url,
            {},
            { preserveScroll: true },
        );
    };

    const lockPayroll = () => {
        if (isLocked) {
            return;
        }

        router.post(PayrollController.lock().url, {}, { preserveScroll: true });
    };

    const exportPayroll = () => {
        window.location.href = '/avana/laporan/export/payroll';
    };

    const generateThr = () => {
        router.post(PayrollController.thr().url, {}, { preserveScroll: true });
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
                            href={PositionComponentController.index().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="sliders-horizontal" size={16} />
                            Komponen per Jabatan
                        </Link>
                        <Link
                            href={PayrollController.attendanceUpload().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="upload-cloud" size={16} />
                            Upload Absensi
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
                                        <a
                                            href={
                                                PayrollController.bpjsFile().url
                                            }
                                            onClick={() => setExportOpen(false)}
                                            style={exportItem}
                                        >
                                            <AIcon
                                                name="shield-check"
                                                size={16}
                                                color={C.muted}
                                            />
                                            Export BPJS
                                        </a>
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
                                            <a
                                                href={`${PayrollController.transferFile().url}?bank=${bank}`}
                                                onClick={() =>
                                                    setExportOpen(false)
                                                }
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
                                            </a>
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
                        onClick={runPayroll}
                    />
                    <StepArrow />
                    <ProcessStep
                        n={2}
                        label={isApproved || isLocked ? 'Disetujui' : 'Setujui'}
                        icon="check-check"
                        done={isApproved || isLocked}
                        disabled={!hasRun || isLocked || isApproved}
                        onClick={approvePayroll}
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
                </div>

                {isLocked && <LockedAlert />}

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
                    />

                    {/* Slip gaji detail */}
                    <SlipDetail slip={slip} period={summary.period} />
                </div>
            </div>
        </>
    );
}
