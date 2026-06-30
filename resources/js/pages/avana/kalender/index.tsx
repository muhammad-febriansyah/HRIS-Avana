import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { CSSProperties, FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CalendarController from '@/actions/App/Http/Controllers/Avana/CalendarController';
import { AIcon, btnOut, btnP, C, card } from '@/lib/avana';
import type { FlashProps } from '../employees/types';

interface CalendarEventRow {
    id: number;
    title: string;
    type: string;
    start_date: string | null;
    end_date: string | null;
    all_day: boolean;
    color: string | null;
    description: string | null;
}

interface SelectOption {
    value: string;
    label: string;
}

interface KalenderIndexProps {
    month: string; // YYYY-MM
    monthLabel: string;
    events: CalendarEventRow[];
    types: SelectOption[];
    kpis: { this_month: number };
}

interface EventFormData {
    title: string;
    type: string;
    start_date: string;
    end_date: string;
    description: string;
}

const emptyForm: EventFormData = { title: '', type: 'event', start_date: '', end_date: '', description: '' };

/** Colour token (text, bg) per event type. */
const TYPE_COLORS: Record<string, [string, string]> = {
    holiday: ['#DC2626', 'rgba(220,38,38,.12)'],
    meeting: ['#2F54C9', 'rgba(47,84,201,.12)'],
    training: ['#6E9BE6', 'rgba(110,155,230,.18)'],
    event: ['#16A34A', 'rgba(22,163,74,.12)'],
    deadline: ['#D97706', 'rgba(217,119,6,.12)'],
};

const WEEKDAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const labelStyle: CSSProperties = { display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 7, color: C.text };
const inputStyle: CSSProperties = { width: '100%', height: 42, padding: '0 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, color: C.text, background: '#fff', outline: 'none' };
const textareaStyle: CSSProperties = { width: '100%', padding: '11px 13px', border: `1px solid ${C.border}`, borderRadius: 8, fontSize: 13.5, color: C.text, outline: 'none', resize: 'vertical', minHeight: 72 };

/** Zero-pad to two digits. */
function pad(n: number): string {
    return String(n).padStart(2, '0');
}

/** Build a yyyy-mm-dd string from numeric parts (1-based month). */
function ymd(year: number, month: number, day: number): string {
    return `${year}-${pad(month)}-${pad(day)}`;
}

interface DayCell {
    date: string;
    day: number;
    inMonth: boolean;
}

/** Compute the leading/in-month/trailing day cells for the month grid. */
function buildCells(month: string): DayCell[] {
    const [year, monthNo] = month.split('-').map(Number);
    const firstWeekday = new Date(year, monthNo - 1, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(year, monthNo, 0).getDate();
    const daysInPrev = new Date(year, monthNo - 1, 0).getDate();

    const cells: DayCell[] = [];

    // Leading days from the previous month.
    for (let i = firstWeekday - 1; i >= 0; i--) {
        const day = daysInPrev - i;
        const prevMonth = monthNo === 1 ? 12 : monthNo - 1;
        const prevYear = monthNo === 1 ? year - 1 : year;
        cells.push({ date: ymd(prevYear, prevMonth, day), day, inMonth: false });
    }

    // In-month days.
    for (let day = 1; day <= daysInMonth; day++) {
        cells.push({ date: ymd(year, monthNo, day), day, inMonth: true });
    }

    // Trailing days to complete the final week.
    let nextDay = 1;
    while (cells.length % 7 !== 0) {
        const nextMonth = monthNo === 12 ? 1 : monthNo + 1;
        const nextYear = monthNo === 12 ? year + 1 : year;
        cells.push({ date: ymd(nextYear, nextMonth, nextDay), day: nextDay, inMonth: false });
        nextDay++;
    }

    return cells;
}

/** Shift a YYYY-MM string by +/- one month. */
function shiftMonth(month: string, delta: number): string {
    const [year, monthNo] = month.split('-').map(Number);
    const total = (year * 12 + (monthNo - 1)) + delta;
    const newYear = Math.floor(total / 12);
    const newMonth = (total % 12) + 1;

    return `${newYear}-${pad(newMonth)}`;
}

export default function KalenderIndex({ month, monthLabel, events, types, kpis }: KalenderIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<CalendarEventRow | null>(null);
    const form = useForm<EventFormData>({ ...emptyForm });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const cells = buildCells(month);
    const today = ymd(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate());

    /** Events that intersect a given day. */
    const eventsOn = (date: string): CalendarEventRow[] =>
        events.filter((event) => {
            const start = event.start_date ?? '';
            const end = event.end_date ?? event.start_date ?? '';

            return start <= date && date <= end;
        });

    const navigate = (delta: number) => {
        router.get(CalendarController.index().url, { month: shiftMonth(month, delta) }, { preserveScroll: true, preserveState: false });
    };

    const openAdd = (date: string) => {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm, start_date: date });
        setModalOpen(true);
    };

    const openEdit = (event: CalendarEventRow) => {
        setEditing(event);
        form.clearErrors();
        form.setData({
            title: event.title,
            type: event.type,
            start_date: event.start_date ?? '',
            end_date: event.end_date ?? '',
            description: event.description ?? '',
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const action = editing ? CalendarController.update(editing.id) : CalendarController.store();
        form.submit(action, { preserveScroll: true, onSuccess: () => closeModal() });
    };

    const remove = () => {
        if (!editing) {
            return;
        }
        router.delete(CalendarController.destroy(editing.id).url, { preserveScroll: true, onSuccess: () => closeModal() });
    };

    return (
        <>
            <Head title="Kalender Acara" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Sistem</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Kalender Acara</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Kalender Acara</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>{kpis.this_month} acara pada bulan ini.</div>
                    </div>
                    <button onClick={() => openAdd(today)} style={{ ...btnP, cursor: 'pointer' }}>
                        <AIcon name="plus" size={16} color="#fff" />
                        Tambah Acara
                    </button>
                </div>

                {/* Month navigation */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
                    <button onClick={() => navigate(-1)} style={{ ...btnOut, height: 38, cursor: 'pointer' }}>
                        <AIcon name="chevron-left" size={16} color={C.text} />
                        Sebelumnya
                    </button>
                    <div style={{ fontSize: 17, fontWeight: 600, color: C.navy }}>{monthLabel}</div>
                    <button onClick={() => navigate(1)} style={{ ...btnOut, height: 38, cursor: 'pointer' }}>
                        Berikutnya
                        <AIcon name="chevron-right" size={16} color={C.text} />
                    </button>
                </div>

                {/* Month grid */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', background: '#FAFBFD', borderBottom: `1px solid ${C.border}` }}>
                        {WEEKDAYS.map((wd) => (
                            <div key={wd} style={{ padding: '11px 12px', fontSize: 11.5, fontWeight: 600, color: C.faint, letterSpacing: '.04em', textTransform: 'uppercase', textAlign: 'center' }}>
                                {wd}
                            </div>
                        ))}
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)' }}>
                        {cells.map((cell, idx) => {
                            const dayEvents = eventsOn(cell.date);
                            const isToday = cell.date === today;

                            return (
                                <div
                                    key={cell.date + idx}
                                    onClick={() => openAdd(cell.date)}
                                    style={{
                                        minHeight: 104,
                                        padding: '8px 8px 10px',
                                        borderRight: (idx + 1) % 7 === 0 ? 'none' : `1px solid ${C.line}`,
                                        borderBottom: `1px solid ${C.line}`,
                                        background: cell.inMonth ? '#fff' : '#FAFBFD',
                                        cursor: 'pointer',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 5,
                                    }}
                                >
                                    <div
                                        style={{
                                            alignSelf: 'flex-end',
                                            width: 24,
                                            height: 24,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            borderRadius: '50%',
                                            fontSize: 12.5,
                                            fontWeight: isToday ? 700 : 500,
                                            color: isToday ? '#fff' : cell.inMonth ? C.text : C.faint,
                                            background: isToday ? C.primary : 'transparent',
                                        }}
                                    >
                                        {cell.day}
                                    </div>
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                                        {dayEvents.slice(0, 3).map((event) => {
                                            const [color, bg] = TYPE_COLORS[event.type] ?? TYPE_COLORS.event;

                                            return (
                                                <button
                                                    key={event.id}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        openEdit(event);
                                                    }}
                                                    title={event.title}
                                                    style={{
                                                        display: 'block',
                                                        width: '100%',
                                                        textAlign: 'left',
                                                        border: 'none',
                                                        borderLeft: `3px solid ${color}`,
                                                        background: bg,
                                                        color,
                                                        borderRadius: 5,
                                                        padding: '3px 7px',
                                                        fontSize: 11,
                                                        fontWeight: 600,
                                                        cursor: 'pointer',
                                                        whiteSpace: 'nowrap',
                                                        overflow: 'hidden',
                                                        textOverflow: 'ellipsis',
                                                    }}
                                                >
                                                    {event.title}
                                                </button>
                                            );
                                        })}
                                        {dayEvents.length > 3 && (
                                            <span style={{ fontSize: 10.5, color: C.faint, paddingLeft: 4 }}>+{dayEvents.length - 3} lainnya</span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Legend */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, marginTop: 16 }}>
                    {types.map((t) => {
                        const [color] = TYPE_COLORS[t.value] ?? TYPE_COLORS.event;

                        return (
                            <span key={t.value} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12.5, color: C.muted }}>
                                <span style={{ width: 11, height: 11, borderRadius: 3, background: color }} />
                                {t.label}
                            </span>
                        );
                    })}
                </div>
            </div>

            {/* Add / edit modal */}
            {modalOpen && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={closeModal} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
                    <form onSubmit={submit} style={{ position: 'relative', width: '100%', maxWidth: 460, maxHeight: '90vh', overflowY: 'auto', background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                        <div style={{ fontSize: 18, fontWeight: 600, color: C.navy, marginBottom: 4 }}>{editing ? 'Ubah Acara' : 'Tambah Acara'}</div>
                        <div style={{ fontSize: 13, color: C.muted, marginBottom: 18 }}>Catat agenda, libur, rapat, atau tenggat.</div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <div>
                                <label style={labelStyle}>Judul <span style={{ color: C.red }}>*</span></label>
                                <input type="text" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Nama acara" style={{ ...inputStyle, ...(form.errors.title ? { borderColor: C.red } : {}) }} />
                                {form.errors.title && <div style={{ fontSize: 12, color: C.red, marginTop: 6 }}>{form.errors.title}</div>}
                            </div>
                            <div>
                                <label style={labelStyle}>Tipe <span style={{ color: C.red }}>*</span></label>
                                <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} style={{ ...inputStyle, cursor: 'pointer' }}>
                                    {types.map((t) => (
                                        <option key={t.value} value={t.value}>{t.label}</option>
                                    ))}
                                </select>
                                {form.errors.type && <div style={{ fontSize: 12, color: C.red, marginTop: 6 }}>{form.errors.type}</div>}
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                <div>
                                    <label style={labelStyle}>Mulai <span style={{ color: C.red }}>*</span></label>
                                    <input type="date" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} style={{ ...inputStyle, ...(form.errors.start_date ? { borderColor: C.red } : {}) }} />
                                    {form.errors.start_date && <div style={{ fontSize: 12, color: C.red, marginTop: 6 }}>{form.errors.start_date}</div>}
                                </div>
                                <div>
                                    <label style={labelStyle}>Selesai</label>
                                    <input type="date" value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} style={{ ...inputStyle, ...(form.errors.end_date ? { borderColor: C.red } : {}) }} />
                                    {form.errors.end_date && <div style={{ fontSize: 12, color: C.red, marginTop: 6 }}>{form.errors.end_date}</div>}
                                </div>
                            </div>
                            <div>
                                <label style={labelStyle}>Deskripsi</label>
                                <textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Catatan (opsional)" style={textareaStyle} />
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                            {editing && (
                                <button type="button" onClick={remove} style={{ height: 44, padding: '0 16px', background: 'rgba(220,38,38,.07)', color: C.red, border: `1px solid rgba(220,38,38,.35)`, borderRadius: 9, fontSize: 13.5, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 7 }}>
                                    <AIcon name="trash-2" size={16} color={C.red} />
                                    Hapus
                                </button>
                            )}
                            <button type="button" onClick={closeModal} style={{ ...btnOut, flex: 1, height: 44, justifyContent: 'center' }}>Batal</button>
                            <button type="submit" disabled={form.processing} style={{ ...btnP, flex: 1, height: 44, justifyContent: 'center', opacity: form.processing ? 0.7 : 1, cursor: form.processing ? 'not-allowed' : 'pointer' }}>
                                <AIcon name="check" size={16} color="#fff" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}
