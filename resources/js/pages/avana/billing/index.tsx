import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import BillingController from '@/actions/App/Http/Controllers/Avana/BillingController';
import { ActionBtn, AIcon, btnOut, btnP, C, card, thCell } from '@/lib/avana';
import {
    InvoiceStatusPill,
    KpiCard,
    SubscriptionStatusPill,
} from './components';
import {
    BILLING_CYCLE_LABEL,
    formatDate,
    formatRupiah,
} from './types';
import type {
    BillingIndexProps,
    FlashProps,
    InvoiceRow,
    SubscriptionRow,
} from './types';

const tdCell = { padding: '13px 16px', fontSize: 13, color: C.text, borderTop: `1px solid ${C.line}` };
const sectionHead = { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 };
const sectionTitle = { fontSize: 16, fontWeight: 600, color: C.navy };

export default function BillingIndex({ invoices, subscriptions, kpis }: BillingIndexProps) {
    const { flash } = usePage<FlashProps>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const oneClick = (url: string, message: string, method: 'post' | 'delete' = 'post') => {
        router[method](url, {}, { preserveScroll: true, onSuccess: () => toast.success(message) });
    };

    return (
        <>
            <Head title="Billing & Invoice" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 22 }}>
                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: 0, letterSpacing: '-.01em' }}>Billing & Invoice</h1>
                        <p style={{ fontSize: 13.5, color: C.muted, margin: '6px 0 0' }}>Kelola langganan klien & tagihan platform AvanaHR.</p>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <Link href={BillingController.createSubscription().url} style={{ ...btnOut, textDecoration: 'none' }}>
                            <AIcon name="repeat" size={15} />
                            Buat Langganan
                        </Link>
                        <Link href={BillingController.createInvoice().url} style={{ ...btnP, textDecoration: 'none' }}>
                            <AIcon name="plus" size={15} />
                            Buat Invoice
                        </Link>
                    </div>
                </div>

                {/* KPIs */}
                <div style={{ display: 'flex', gap: 14, marginBottom: 24, flexWrap: 'wrap' }}>
                    <KpiCard icon="circle-dollar-sign" label="Tagihan Tertunggak" value={formatRupiah(kpis.outstanding)} accent={C.amber} />
                    <KpiCard icon="check-check" label="Lunas Bulan Ini" value={formatRupiah(kpis.paid_this_month)} accent={C.green} />
                    <KpiCard icon="triangle-alert" label="Jatuh Tempo" value={kpis.overdue_count} accent={C.red} />
                    <KpiCard icon="repeat" label="Langganan Aktif" value={kpis.active_subscriptions} accent={C.primary} />
                    <KpiCard icon="trending-up" label="MRR" value={formatRupiah(kpis.mrr)} accent={C.sky} />
                </div>

                {/* Subscriptions */}
                <div style={{ ...card, marginBottom: 24, overflow: 'hidden' }}>
                    <div style={{ ...sectionHead, padding: '18px 20px 0' }}>
                        <div style={sectionTitle}>Langganan Klien</div>
                    </div>
                    <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: 12 }}>
                        <thead>
                            <tr style={{ background: C.surface }}>
                                <th style={thCell}>Klien</th>
                                <th style={thCell}>Paket</th>
                                <th style={thCell}>Siklus</th>
                                <th style={thCell}>Harga</th>
                                <th style={thCell}>Status</th>
                                <th style={{ ...thCell, textAlign: 'right' }}>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {subscriptions.length === 0 ? (
                                <tr><td colSpan={6} style={{ ...tdCell, textAlign: 'center', color: C.faint, padding: '28px 0' }}>Belum ada langganan.</td></tr>
                            ) : (
                                subscriptions.map((sub: SubscriptionRow) => (
                                    <tr key={sub.id}>
                                        <td style={{ ...tdCell, fontWeight: 600, color: C.navy }}>{sub.tenant ?? '—'}</td>
                                        <td style={tdCell}>{sub.package ?? '—'}</td>
                                        <td style={tdCell}>{BILLING_CYCLE_LABEL[sub.billing_cycle] ?? sub.billing_cycle}</td>
                                        <td style={tdCell}>{formatRupiah(sub.price)}</td>
                                        <td style={tdCell}><SubscriptionStatusPill status={sub.status} /></td>
                                        <td style={{ ...tdCell, textAlign: 'right' }}>
                                            <div style={{ display: 'inline-flex', justifyContent: 'flex-end' }}>
                                                <ActionBtn
                                                    icon="file-plus"
                                                    label="Generate Invoice"
                                                    variant="primary"
                                                    title="Generate invoice periode ini"
                                                    onClick={() => oneClick(BillingController.generateInvoice(sub.id).url, 'Invoice dibuat')}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Invoices */}
                <div style={{ ...card, overflow: 'hidden' }}>
                    <div style={{ ...sectionHead, padding: '18px 20px 0' }}>
                        <div style={sectionTitle}>Invoice</div>
                    </div>
                    <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: 12 }}>
                        <thead>
                            <tr style={{ background: C.surface }}>
                                <th style={thCell}>Nomor</th>
                                <th style={thCell}>Klien</th>
                                <th style={thCell}>Terbit</th>
                                <th style={thCell}>Jatuh Tempo</th>
                                <th style={thCell}>Total</th>
                                <th style={thCell}>Status</th>
                                <th style={{ ...thCell, textAlign: 'right' }}>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.length === 0 ? (
                                <tr><td colSpan={7} style={{ ...tdCell, textAlign: 'center', color: C.faint, padding: '28px 0' }}>Belum ada invoice.</td></tr>
                            ) : (
                                invoices.map((inv: InvoiceRow) => (
                                    <tr key={inv.id}>
                                        <td style={{ ...tdCell, fontWeight: 600, color: C.navy }}>{inv.invoice_number}</td>
                                        <td style={tdCell}>{inv.tenant ?? '—'}</td>
                                        <td style={tdCell}>{formatDate(inv.issue_date)}</td>
                                        <td style={tdCell}>{formatDate(inv.due_date)}</td>
                                        <td style={{ ...tdCell, fontWeight: 600 }}>{formatRupiah(inv.total)}</td>
                                        <td style={tdCell}><InvoiceStatusPill status={inv.status} /></td>
                                        <td style={{ ...tdCell, textAlign: 'right' }}>
                                            <div style={{ display: 'inline-flex', gap: 6, justifyContent: 'flex-end', flexWrap: 'wrap' }}>
                                                <ActionBtn icon="file-down" label="PDF" variant="primary" href={BillingController.printInvoice(inv.id).url} download />
                                                {inv.status !== 'paid' && inv.status !== 'cancelled' ? (
                                                    <ActionBtn icon="check" label="Lunas" variant="success" onClick={() => oneClick(BillingController.markPaid(inv.id).url, 'Invoice lunas')} />
                                                ) : null}
                                                {inv.status !== 'cancelled' ? (
                                                    <ActionBtn icon="ban" label="Batal" variant="warning" onClick={() => oneClick(BillingController.cancelInvoice(inv.id).url, 'Invoice dibatalkan')} />
                                                ) : null}
                                                <ActionBtn icon="trash-2" label="Hapus" variant="danger" onClick={() => oneClick(BillingController.destroyInvoice(inv.id).url, 'Invoice dihapus', 'delete')} />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
