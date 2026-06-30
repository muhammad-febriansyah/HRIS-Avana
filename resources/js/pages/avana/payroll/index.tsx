import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import PositionComponentController from '@/actions/App/Http/Controllers/Avana/PositionComponentController';
import { AIcon, btnOut, btnP, C } from '@/lib/avana';
import { LockedAlert } from './components';
import { PeriodTable } from './period-table';
import { SlipDetail } from './slip-detail';
import { SummaryCard } from './summary-card';
import type { FlashProps, PayrollProps } from './types';

const periodLabel = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7, color: C.text } as const;
const periodInput = { width: '100%', height: 42, padding: '0 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, color: C.text, background: '#fff', outline: 'none' } as const;

function FieldErr({ msg }: { msg: string }) {
    return <div style={{ fontSize: 12, color: C.red, marginTop: 5 }}>{msg}</div>;
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
    const [showPeriodModal, setShowPeriodModal] = useState(false);

    const periodForm = useForm({ name: '', cycle: 'monthly', start_date: '', end_date: '', pay_date: '' });

    /** Auto-fill the end date when a cycle + start date imply a fixed window. */
    const applyCycleWindow = (cycle: string, start: string) => {
        if (!start) {
            return;
        }
        const startDate = new Date(start);
        const addDays = (n: number) => {
            const d = new Date(startDate);
            d.setDate(d.getDate() + n);
            return d.toISOString().slice(0, 10);
        };
        if (cycle === 'weekly') {
            periodForm.setData('end_date', addDays(6));
        } else if (cycle === 'biweekly') {
            periodForm.setData('end_date', addDays(13));
        } else {
            const end = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0);
            periodForm.setData('end_date', end.toISOString().slice(0, 10));
        }
    };

    const submitPeriod = () => {
        periodForm.post(PayrollController.storePeriod().url, {
            preserveScroll: true,
            onSuccess: () => {
                periodForm.reset();
                setShowPeriodModal(false);
            },
        });
    };

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
                        <button onClick={() => setShowPeriodModal(true)} style={btnOut}>
                            <AIcon name="calendar-plus" size={16} />
                            Buat Periode
                        </button>
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

            {showPeriodModal && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'flex-start', justifyContent: 'center', padding: '52px 20px', overflowY: 'auto' }}>
                    <div onClick={() => setShowPeriodModal(false)} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <div style={{ position: 'relative', width: '100%', maxWidth: 520, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 18 }}>Buat Periode Payroll</div>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                submitPeriod();
                            }}
                            style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}
                        >
                            <div style={{ gridColumn: '1 / -1' }}>
                                <label style={periodLabel}>Nama Periode</label>
                                <input value={periodForm.data.name} onChange={(e) => periodForm.setData('name', e.target.value)} style={periodInput} placeholder="Gaji Minggu 1 Juli 2026" />
                                {periodForm.errors.name && <FieldErr msg={periodForm.errors.name} />}
                            </div>
                            <div>
                                <label style={periodLabel}>Siklus</label>
                                <select
                                    value={periodForm.data.cycle}
                                    onChange={(e) => {
                                        periodForm.setData('cycle', e.target.value);
                                        applyCycleWindow(e.target.value, periodForm.data.start_date);
                                    }}
                                    style={{ ...periodInput, cursor: 'pointer' }}
                                >
                                    <option value="monthly">Bulanan</option>
                                    <option value="weekly">Mingguan</option>
                                    <option value="biweekly">Dwi-Mingguan</option>
                                </select>
                            </div>
                            <div>
                                <label style={periodLabel}>Tanggal Bayar</label>
                                <input type="date" value={periodForm.data.pay_date} onChange={(e) => periodForm.setData('pay_date', e.target.value)} style={periodInput} />
                            </div>
                            <div>
                                <label style={periodLabel}>Mulai</label>
                                <input
                                    type="date"
                                    value={periodForm.data.start_date}
                                    onChange={(e) => {
                                        periodForm.setData('start_date', e.target.value);
                                        applyCycleWindow(periodForm.data.cycle, e.target.value);
                                    }}
                                    style={periodInput}
                                />
                                {periodForm.errors.start_date && <FieldErr msg={periodForm.errors.start_date} />}
                            </div>
                            <div>
                                <label style={periodLabel}>Selesai</label>
                                <input type="date" value={periodForm.data.end_date} onChange={(e) => periodForm.setData('end_date', e.target.value)} style={periodInput} />
                                {periodForm.errors.end_date && <FieldErr msg={periodForm.errors.end_date} />}
                            </div>
                            <div style={{ gridColumn: '1 / -1', display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 6 }}>
                                <button type="button" onClick={() => setShowPeriodModal(false)} style={btnOut}>Batal</button>
                                <button type="submit" disabled={periodForm.processing} style={btnP}>Simpan Periode</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}
