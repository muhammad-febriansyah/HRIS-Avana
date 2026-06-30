import { Head, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import JournalController from '@/actions/App/Http/Controllers/Avana/JournalController';
import { ActionBtn, AIcon, btnP, C, card, rp, thCell } from '@/lib/avana';
import type { FlashProps } from '../employees/types';

interface JournalEntryRow {
    id: number;
    entry_date: string | null;
    account_code: string;
    account_name: string;
    description: string | null;
    debit: number;
    credit: number;
    period: string | null;
}

interface LatestRun {
    id: number;
    period: string | null;
    total_gross: number;
    total_deduction: number;
    total_tax: number;
    total_net: number;
}

interface JurnalKpis {
    total_entries: number;
    total_debit: number;
    total_credit: number;
    balanced: boolean;
}

interface JurnalIndexProps {
    entries: JournalEntryRow[];
    latestRun: LatestRun | null;
    kpis: JurnalKpis;
}

const kpiCardStyle: CSSProperties = { ...card, padding: '18px 20px', flex: '1 1 180px' };

const tdStyle: CSSProperties = { padding: '12px 16px', fontSize: 13, color: C.text };

/** Format a yyyy-mm-dd date as a readable Indonesian date. */
function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    const date = new Date(value + 'T00:00:00');

    return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function JurnalIndex({ entries, latestRun, kpis }: JurnalIndexProps) {
    const { flash } = usePage<FlashProps & { flash?: { error?: string } }>().props;
    const [confirm, setConfirm] = useState<JournalEntryRow | null>(null);
    const [generating, setGenerating] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    /** Group the flat entry list by entry date, preserving order. */
    const groups = useMemo(() => {
        const map = new Map<string, JournalEntryRow[]>();
        for (const entry of entries) {
            const key = entry.entry_date ?? '—';
            const bucket = map.get(key) ?? [];
            bucket.push(entry);
            map.set(key, bucket);
        }

        return Array.from(map.entries());
    }, [entries]);

    const generate = () => {
        setGenerating(true);
        router.post(
            JournalController.generate().url,
            {},
            { preserveScroll: true, onFinish: () => setGenerating(false) },
        );
    };

    const remove = () => {
        if (!confirm) {
            return;
        }
        router.delete(JournalController.destroy(confirm.id).url, {
            preserveScroll: true,
            onSuccess: () => setConfirm(null),
        });
    };

    const kpiItems = [
        { label: 'Total Entri', value: String(kpis.total_entries), icon: 'book-open', color: C.primary },
        { label: 'Total Debit', value: rp(kpis.total_debit), icon: 'arrow-down-left', color: C.sky },
        { label: 'Total Kredit', value: rp(kpis.total_credit), icon: 'arrow-up-right', color: C.amber },
    ];

    return (
        <>
            <Head title="Jurnal Akuntansi" />
            <div style={{ padding: '28px 32px' }}>
                {/* Header */}
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, marginBottom: 22 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 7 }}>
                            <span>Sistem</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>Jurnal Akuntansi</span>
                        </div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Jurnal Akuntansi</h1>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 4 }}>Jurnal umum hasil ekspor dari payroll.</div>
                    </div>
                    <button
                        onClick={generate}
                        disabled={!latestRun || generating}
                        title={latestRun ? undefined : 'Belum ada payroll run'}
                        style={{ ...btnP, opacity: !latestRun || generating ? 0.6 : 1, cursor: !latestRun || generating ? 'not-allowed' : 'pointer' }}
                    >
                        <AIcon name="sparkles" size={16} color="#fff" />
                        Generate dari Payroll
                    </button>
                </div>

                {/* KPI cards */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 22 }}>
                    {kpiItems.map((item) => (
                        <div key={item.label} style={kpiCardStyle}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                                <div style={{ width: 34, height: 34, borderRadius: 9, background: `${item.color}1a`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <AIcon name={item.icon} size={17} color={item.color} />
                                </div>
                                <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>{item.label}</span>
                            </div>
                            <div style={{ fontSize: 22, fontWeight: 700, color: C.navy, letterSpacing: '-.02em' }}>{item.value}</div>
                        </div>
                    ))}
                    <div style={kpiCardStyle}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                            <div style={{ width: 34, height: 34, borderRadius: 9, background: kpis.balanced ? 'rgba(22,163,74,.1)' : 'rgba(220,38,38,.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <AIcon name={kpis.balanced ? 'scale' : 'triangle-alert'} size={17} color={kpis.balanced ? C.green : C.red} />
                            </div>
                            <span style={{ fontSize: 12.5, color: C.muted, fontWeight: 500 }}>Keseimbangan</span>
                        </div>
                        <div style={{ fontSize: 18, fontWeight: 700, color: kpis.balanced ? C.green : C.red, letterSpacing: '-.02em' }}>
                            {kpis.balanced ? 'Balanced' : 'Tidak Seimbang'}
                        </div>
                    </div>
                </div>

                {latestRun && (
                    <div style={{ ...card, padding: '14px 18px', marginBottom: 20, display: 'flex', alignItems: 'center', gap: 10, background: '#FAFBFD' }}>
                        <AIcon name="info" size={16} color={C.primary} />
                        <span style={{ fontSize: 13, color: C.muted }}>
                            Payroll run terbaru{latestRun.period ? ` (${latestRun.period})` : ''}: bruto {rp(latestRun.total_gross)} · potongan {rp(latestRun.total_deduction)} · pajak {rp(latestRun.total_tax)} · neto {rp(latestRun.total_net)}.
                        </span>
                    </div>
                )}

                {/* Journal table */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 760 }}>
                            <thead>
                                <tr style={{ background: '#FAFBFD' }}>
                                    <th style={thCell}>Akun</th>
                                    <th style={thCell}>Keterangan</th>
                                    <th style={{ ...thCell, textAlign: 'right' }}>Debit</th>
                                    <th style={{ ...thCell, textAlign: 'right' }}>Kredit</th>
                                    <th style={{ ...thCell, textAlign: 'right', padding: '12px 18px' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.length === 0 && (
                                    <tr style={{ borderTop: `1px solid ${C.line}` }}>
                                        <td colSpan={5} style={{ padding: '48px 18px', textAlign: 'center', fontSize: 13.5, color: C.muted }}>
                                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                                                <AIcon name="book-open" size={28} color={C.faint} />
                                                <div>Belum ada jurnal. Klik "Generate dari Payroll".</div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {groups.map(([date, rows]) => (
                                    <DateGroup key={date} date={date} rows={rows} onDelete={setConfirm} />
                                ))}
                            </tbody>
                            {entries.length > 0 && (
                                <tfoot>
                                    <tr style={{ borderTop: `2px solid ${C.border}`, background: '#FAFBFD' }}>
                                        <td style={{ ...tdStyle, fontWeight: 700, color: C.navy }} colSpan={2}>
                                            Total
                                        </td>
                                        <td style={{ ...tdStyle, textAlign: 'right', fontWeight: 700, color: C.navy }}>{rp(kpis.total_debit)}</td>
                                        <td style={{ ...tdStyle, textAlign: 'right', fontWeight: 700, color: C.navy }}>{rp(kpis.total_credit)}</td>
                                        <td style={{ ...tdStyle, textAlign: 'right' }}>
                                            <span style={{ display: 'inline-block', padding: '3px 10px', borderRadius: 100, fontSize: 11.5, fontWeight: 600, color: kpis.balanced ? C.green : C.red, background: kpis.balanced ? 'rgba(22,163,74,.1)' : 'rgba(220,38,38,.1)' }}>
                                                {kpis.balanced ? 'Seimbang' : 'Selisih'}
                                            </span>
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>
            </div>

            {confirm && (
                <ConfirmDelete
                    title="Hapus entri jurnal?"
                    body={`Entri ${confirm.account_code} — ${confirm.account_name} akan dihapus.`}
                    onCancel={() => setConfirm(null)}
                    onConfirm={remove}
                />
            )}
        </>
    );
}

interface DateGroupProps {
    date: string;
    rows: JournalEntryRow[];
    onDelete: (row: JournalEntryRow) => void;
}

/** A date-grouped block of journal rows (header row + entries). */
function DateGroup({ date, rows, onDelete }: DateGroupProps) {
    return (
        <>
            <tr style={{ borderTop: `1px solid ${C.line}`, background: 'rgba(47,84,201,.04)' }}>
                <td colSpan={5} style={{ padding: '9px 16px', fontSize: 12, fontWeight: 600, color: C.primary, letterSpacing: '.02em' }}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 7 }}>
                        <AIcon name="calendar" size={13} color={C.primary} />
                        {formatDate(date)}
                    </span>
                </td>
            </tr>
            {rows.map((row) => (
                <tr key={row.id} style={{ borderTop: `1px solid ${C.line}` }}>
                    <td style={tdStyle}>
                        <div style={{ fontWeight: 600, color: C.navy }}>{row.account_name}</div>
                        <div style={{ fontSize: 11.5, color: C.faint }}>{row.account_code}</div>
                    </td>
                    <td style={{ ...tdStyle, color: C.muted }}>{row.description ?? '—'}</td>
                    <td style={{ ...tdStyle, textAlign: 'right', fontWeight: row.debit ? 600 : 400, color: row.debit ? C.text : C.faint }}>{row.debit ? rp(row.debit) : '—'}</td>
                    <td style={{ ...tdStyle, textAlign: 'right', fontWeight: row.credit ? 600 : 400, color: row.credit ? C.text : C.faint }}>{row.credit ? rp(row.credit) : '—'}</td>
                    <td style={{ ...tdStyle, textAlign: 'right' }}>
                        <ActionBtn icon="trash-2" label="Hapus" variant="danger" onClick={() => onDelete(row)} />
                    </td>
                </tr>
            ))}
        </>
    );
}

interface ConfirmDeleteProps {
    title: string;
    body: string;
    onCancel: () => void;
    onConfirm: () => void;
}

/** Minimal centered destructive-action confirmation modal. */
function ConfirmDelete({ title, body, onCancel, onConfirm }: ConfirmDeleteProps) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 80, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
            <div onClick={onCancel} style={{ position: 'absolute', inset: 0, background: 'rgba(14,26,58,.45)' }} />
            <div style={{ position: 'relative', width: '100%', maxWidth: 400, background: '#fff', borderRadius: 14, boxShadow: '0 20px 50px rgba(15,23,42,.25)', padding: 26 }}>
                <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(220,38,38,.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                    <AIcon name="trash-2" size={22} color={C.red} />
                </div>
                <div style={{ fontSize: 18, fontWeight: 600, color: C.navy }}>{title}</div>
                <div style={{ fontSize: 13.5, color: C.muted, marginTop: 8, lineHeight: 1.55 }}>{body}</div>
                <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
                    <button onClick={onCancel} style={{ flex: 1, height: 44, background: '#fff', color: C.text, border: `1px solid ${C.border}`, borderRadius: 9, fontSize: 14, fontWeight: 500, cursor: 'pointer' }}>
                        Batal
                    </button>
                    <button onClick={onConfirm} style={{ flex: 1, height: 44, background: C.red, color: '#fff', border: 'none', borderRadius: 9, fontSize: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                        <AIcon name="trash-2" size={16} />
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    );
}
