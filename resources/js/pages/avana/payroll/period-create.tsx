import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import PayrollController from '@/actions/App/Http/Controllers/Avana/PayrollController';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, btnSave, C, card } from '@/lib/avana';
import type { FlashProps } from './types';

const periodLabel = {
    display: 'block',
    fontSize: 13,
    fontWeight: 500,
    marginBottom: 7,
    color: C.text,
} as const;
const periodInput = {
    width: '100%',
    height: 42,
    padding: '0 13px',
    border: `1px solid ${C.border}`,
    borderRadius: 8,
    fontSize: 13.5,
    color: C.text,
    background: '#fff',
    outline: 'none',
} as const;

function FieldErr({ msg }: { msg?: string }) {
    if (!msg) {
        return null;
    }

    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 5 }}>{msg}</div>
    );
}

export default function PeriodCreate() {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({
        name: '',
        cycle: 'monthly',
        start_date: '',
        end_date: '',
        pay_date: '',
    });
    const { data, setData, errors, processing } = form;

    /** Auto-fill the end date when a cycle + start date imply a fixed window. */
    const applyCycleWindow = (cycle: string, start: string) => {
        if (!start) {
            return;
        }

        // Built and formatted in local time throughout. `toISOString()` converts
        // to UTC first, so east of Greenwich it moved the answer back a day: a
        // monthly period starting 1 September ended 29 September, and every
        // prorated salary in it was short by a day.
        const [year, month, day] = start.split('-').map(Number);
        const format = (d: Date) =>
            `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

        const addDays = (n: number) => format(new Date(year, month - 1, day + n));

        if (cycle === 'weekly') {
            setData('end_date', addDays(6));
        } else if (cycle === 'biweekly') {
            setData('end_date', addDays(13));
        } else {
            // Day 0 of the next month is the last day of this one.
            setData('end_date', format(new Date(year, month, 0)));
        }
    };

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    /** Inclusive day count of the chosen range, or null while incomplete. */
    const rangeDays =
        data.start_date && data.end_date
            ? Math.round(
                  (new Date(data.end_date).getTime() -
                      new Date(data.start_date).getTime()) /
                      86_400_000,
              ) + 1
            : null;

    // A "monthly" period far shorter than a month silently shrinks every
    // prorated salary with it — the 66-thousand-rupiah mystery. Say so before
    // Simpan, not after the payslips look wrong.
    const shortMonthly =
        data.cycle === 'monthly' && rangeDays !== null && rangeDays < 21;

    return (
        <>
            <Head title="Buat Periode" />
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
                        href={PayrollController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Payroll
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Periode</span>
                </div>
                <h1
                    style={{
                        fontSize: 24,
                        fontWeight: 600,
                        color: C.navy,
                        margin: '0 0 24px',
                        letterSpacing: '-.01em',
                    }}
                >
                    Buat Periode Payroll
                </h1>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(PayrollController.storePeriod().url);
                    }}
                    style={{ ...card }}
                >
                    <div
                        style={{
                            padding: '22px 24px',
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 14,
                        }}
                    >
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label style={periodLabel}>Nama Periode</label>
                            <input
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                style={periodInput}
                                placeholder="Gaji Minggu 1 Juli 2026"
                            />
                            <FieldErr msg={errors.name} />
                        </div>
                        <div>
                            <label style={periodLabel}>Siklus</label>
                            <select
                                value={data.cycle}
                                onChange={(e) => {
                                    setData('cycle', e.target.value);
                                    applyCycleWindow(
                                        e.target.value,
                                        data.start_date,
                                    );
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
                            <DatePicker
                                value={data.pay_date}
                                onChange={(nextValue) =>
                                    setData('pay_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                            <FieldErr msg={errors.pay_date} />
                        </div>
                        <div>
                            <label style={periodLabel}>Mulai</label>
                            <DatePicker
                                value={data.start_date}
                                onChange={(nextValue) => {
                                    setData('start_date', nextValue);
                                    applyCycleWindow(data.cycle, nextValue);
                                }}
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                            <FieldErr msg={errors.start_date} />
                        </div>
                        <div>
                            <label style={periodLabel}>Selesai</label>
                            <DatePicker
                                value={data.end_date}
                                onChange={(nextValue) =>
                                    setData('end_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                            <FieldErr msg={errors.end_date} />
                        </div>

                        {rangeDays !== null && (
                            <div
                                style={{
                                    gridColumn: '1 / -1',
                                    fontSize: 12.5,
                                    color: shortMonthly ? '#92400E' : C.muted,
                                    background: shortMonthly
                                        ? '#FFFBEB'
                                        : 'transparent',
                                    border: shortMonthly
                                        ? '1px solid #FDE68A'
                                        : 'none',
                                    borderRadius: 8,
                                    padding: shortMonthly ? '10px 12px' : 0,
                                }}
                            >
                                Rentang periode: <strong>{rangeDays} hari</strong>
                                {shortMonthly && (
                                    <>
                                        {' '}
                                        — pendek untuk siklus bulanan. Komponen
                                        yang diprorata akan dihitung sebatas
                                        rentang ini, jadi gaji tampak jauh lebih
                                        kecil dari nominal bulanannya.
                                    </>
                                )}
                            </div>
                        )}
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            gap: 10,
                            justifyContent: 'flex-end',
                            padding: '16px 24px',
                            borderTop: `1px solid ${C.line}`,
                        }}
                    >
                        <Link
                            href={PayrollController.index().url}
                            style={{
                                ...btnOut,
                                height: 44,
                                justifyContent: 'center',
                                textDecoration: 'none',
                            }}
                        >
                            <AIcon name="x" size={16} color={C.text} />
                            Batal
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            style={{
                                ...btnSave,
                                height: 44,
                                justifyContent: 'center',
                                opacity: processing ? 0.7 : 1,
                                cursor: processing ? 'not-allowed' : 'pointer',
                            }}
                        >
                            <AIcon
                                name="calendar-plus"
                                size={16}
                                color="#fff"
                            />
                            Simpan Periode
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
