import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import type { FlashProps } from './types';

const periodLabel = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7, color: C.text } as const;
const periodInput = { width: '100%', height: 42, padding: '0 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, color: C.text, background: '#fff', outline: 'none' } as const;

function FieldErr({ msg }: { msg?: string }) {
    if (!msg) {
        return null;
    }

    return <div style={{ fontSize: 12, color: C.red, marginTop: 5 }}>{msg}</div>;
}

export default function PeriodCreate() {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({ name: '', cycle: 'monthly', start_date: '', end_date: '', pay_date: '' });
    const { data, setData, errors, processing } = form;

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
            setData('end_date', addDays(6));
        } else if (cycle === 'biweekly') {
            setData('end_date', addDays(13));
        } else {
            const end = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0);
            setData('end_date', end.toISOString().slice(0, 10));
        }
    };

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    return (
        <>
            <Head title="Buat Periode" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                    <Link href={PayrollController.index()} style={{ color: C.faint, textDecoration: 'none', cursor: 'pointer' }}>
                        Payroll
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Periode</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: '0 0 24px', letterSpacing: '-.01em' }}>
                    Buat Periode Payroll
                </h1>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(PayrollController.storePeriod().url);
                    }}
                    style={{ ...card, maxWidth: 560 }}
                >
                    <div style={{ padding: '22px 24px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label style={periodLabel}>Nama Periode</label>
                            <input value={data.name} onChange={(e) => setData('name', e.target.value)} style={periodInput} placeholder="Gaji Minggu 1 Juli 2026" />
                            <FieldErr msg={errors.name} />
                        </div>
                        <div>
                            <label style={periodLabel}>Siklus</label>
                            <select
                                value={data.cycle}
                                onChange={(e) => {
                                    setData('cycle', e.target.value);
                                    applyCycleWindow(e.target.value, data.start_date);
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
                            <input type="date" value={data.pay_date} onChange={(e) => setData('pay_date', e.target.value)} style={periodInput} />
                            <FieldErr msg={errors.pay_date} />
                        </div>
                        <div>
                            <label style={periodLabel}>Mulai</label>
                            <input
                                type="date"
                                value={data.start_date}
                                onChange={(e) => {
                                    setData('start_date', e.target.value);
                                    applyCycleWindow(data.cycle, e.target.value);
                                }}
                                style={periodInput}
                            />
                            <FieldErr msg={errors.start_date} />
                        </div>
                        <div>
                            <label style={periodLabel}>Selesai</label>
                            <input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} style={periodInput} />
                            <FieldErr msg={errors.end_date} />
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', padding: '16px 24px', borderTop: `1px solid ${C.line}` }}>
                        <Link href={PayrollController.index().url} style={{ ...btnOut, height: 44, justifyContent: 'center', textDecoration: 'none' }}>
                            Batal
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            style={{ ...btnP, height: 44, justifyContent: 'center', opacity: processing ? 0.7 : 1, cursor: processing ? 'not-allowed' : 'pointer' }}
                        >
                            <AIcon name="calendar-plus" size={16} color="#fff" />
                            Simpan Periode
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
