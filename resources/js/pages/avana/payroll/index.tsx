import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import PositionComponentController from '@/actions/App/Http/Controllers/Avana/PositionComponentController';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import { LockedAlert } from './components';
import { PeriodTable } from './period-table';
import { SlipDetail } from './slip-detail';
import { SummaryCard } from './summary-card';
import type { FlashProps, PayrollProps } from './types';

export default function AvanaPayroll({
    periods,
    summary,
    slip,
    filters,
}: PayrollProps) {
    const { flash } = usePage<FlashProps>().props;
    const meta = periods.meta;
    const isLocked = summary.status === 'locked';

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const runPayroll = () => {
        if (isLocked) {
            return;
        }

        router.post(PayrollController.run().url, {}, { preserveScroll: true });
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
                        <button onClick={exportPayroll} style={btnOut}>
                            <AIcon name="download" size={16} />
                            Export
                        </button>
                        <button onClick={generateThr} style={btnOut}>
                            <AIcon name="gift" size={16} />
                            Generate THR
                        </button>
                        <a
                            href={PayrollController.transferFile().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="banknote" size={16} />
                            File Transfer Bank
                        </a>
                        <Link
                            href={PayrollController.createPeriod().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="calendar-plus" size={16} />
                            Buat Periode
                        </Link>
                        <button
                            onClick={lockPayroll}
                            disabled={isLocked}
                            style={{
                                ...btnOut,
                                opacity: isLocked ? 0.5 : 1,
                                cursor: isLocked ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon name="lock" size={16} />
                            Kunci Periode
                        </button>
                        <button
                            onClick={runPayroll}
                            disabled={isLocked}
                            style={{
                                ...btnP,
                                opacity: isLocked ? 0.5 : 1,
                                cursor: isLocked ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon name="play" size={16} color="#fff" />
                            Jalankan Payroll
                        </button>
                    </div>
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
