import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import BillingController from '@/actions/App/Http/Controllers/Avana/BillingController';
import { ActionBtn, AIcon, btnOut, btnP, C, card, RupiahInput, thCell } from '@/lib/avana';
import {
    fieldLabelStyle,
    iconBtn,
    inputStyle,
    InvoiceStatusPill,
    KpiCard,
    Modal,
    selectStyle,
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

export default function BillingIndex({ invoices, subscriptions, tenants, packages, kpis, subscriptionStatuses, billingCycles }: BillingIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const [modal, setModal] = useState<null | 'subscription' | 'invoice'>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const subForm = useForm({
        tenant_id: '',
        package_id: '',
        status: 'active',
        billing_cycle: 'monthly',
        price: '',
        start_date: '',
        end_date: '',
    });

    const invForm = useForm<{
        tenant_id: string;
        subscription_id: string;
        issue_date: string;
        due_date: string;
        tax: string;
        notes: string;
        items: { description: string; quantity: string; unit_price: string }[];
    }>({
        tenant_id: '',
        subscription_id: '',
        issue_date: '',
        due_date: '',
        tax: '0',
        notes: '',
        items: [{ description: '', quantity: '1', unit_price: '' }],
    });

    const invoiceSubtotal = invForm.data.items.reduce(
        (sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0),
        0,
    );
    const invoiceTotal = invoiceSubtotal + (Number(invForm.data.tax) || 0);

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
                        <button onClick={() => setModal('subscription')} style={btnOut}>
                            <AIcon name="repeat" size={15} />
                            Buat Langganan
                        </button>
                        <button onClick={() => setModal('invoice')} style={btnP}>
                            <AIcon name="plus" size={15} />
                            Buat Invoice
                        </button>
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

            {/* ---------- Subscription modal ---------- */}
            {modal === 'subscription' ? (
                <Modal title="Buat Langganan" onClose={() => setModal(null)}>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            subForm.submit(BillingController.storeSubscription(), { onSuccess: () => { subForm.reset(); setModal(null); } });
                        }}
                        style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}
                    >
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label style={fieldLabelStyle}>Klien</label>
                            <select value={subForm.data.tenant_id} onChange={(e) => subForm.setData('tenant_id', e.target.value)} style={selectStyle}>
                                <option value="">Pilih klien</option>
                                {tenants.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Paket</label>
                            <select
                                value={subForm.data.package_id}
                                onChange={(e) => {
                                    const pkg = packages.find((p) => String(p.id) === e.target.value);
                                    subForm.setData((data) => ({
                                        ...data,
                                        package_id: e.target.value,
                                        price: pkg ? String(pkg.price) : data.price,
                                        billing_cycle: pkg ? pkg.billing_cycle : data.billing_cycle,
                                    }));
                                }}
                                style={selectStyle}
                            >
                                <option value="">Tanpa paket</option>
                                {packages.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Harga</label>
                            <RupiahInput value={subForm.data.price} onChange={(v) => subForm.setData('price', v)} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Siklus</label>
                            <select value={subForm.data.billing_cycle} onChange={(e) => subForm.setData('billing_cycle', e.target.value)} style={selectStyle}>
                                {billingCycles.map((c) => <option key={c} value={c}>{BILLING_CYCLE_LABEL[c] ?? c}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Status</label>
                            <select value={subForm.data.status} onChange={(e) => subForm.setData('status', e.target.value)} style={selectStyle}>
                                {subscriptionStatuses.map((s) => <option key={s} value={s}>{s}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Mulai</label>
                            <input type="date" value={subForm.data.start_date} onChange={(e) => subForm.setData('start_date', e.target.value)} style={inputStyle} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Berakhir</label>
                            <input type="date" value={subForm.data.end_date} onChange={(e) => subForm.setData('end_date', e.target.value)} style={inputStyle} />
                        </div>
                        <div style={{ gridColumn: '1 / -1', display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 6 }}>
                            <button type="button" onClick={() => setModal(null)} style={btnOut}>Batal</button>
                            <button type="submit" disabled={subForm.processing} style={btnP}>Simpan</button>
                        </div>
                    </form>
                </Modal>
            ) : null}

            {/* ---------- Invoice modal ---------- */}
            {modal === 'invoice' ? (
                <Modal title="Buat Invoice" onClose={() => setModal(null)} width={680}>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            invForm.submit(BillingController.storeInvoice(), { onSuccess: () => { invForm.reset(); setModal(null); } });
                        }}
                        style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}
                    >
                        <div>
                            <label style={fieldLabelStyle}>Klien</label>
                            <select value={invForm.data.tenant_id} onChange={(e) => invForm.setData('tenant_id', e.target.value)} style={selectStyle}>
                                <option value="">Pilih klien</option>
                                {tenants.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Langganan (opsional)</label>
                            <select
                                value={invForm.data.subscription_id}
                                onChange={(e) => {
                                    const sub = subscriptions.find((s) => String(s.id) === e.target.value);
                                    invForm.setData((data) => ({
                                        ...data,
                                        subscription_id: e.target.value,
                                        tenant_id: sub ? String(sub.tenant_id) : data.tenant_id,
                                        items: sub
                                            ? [{ description: `Langganan ${sub.package ?? 'AvanaHR'}`, quantity: '1', unit_price: String(sub.price) }]
                                            : data.items,
                                    }));
                                }}
                                style={selectStyle}
                            >
                                <option value="">—</option>
                                {subscriptions.map((s) => <option key={s.id} value={s.id}>{s.tenant} · {s.package ?? '—'}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Tanggal Terbit</label>
                            <input type="date" value={invForm.data.issue_date} onChange={(e) => invForm.setData('issue_date', e.target.value)} style={inputStyle} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Jatuh Tempo</label>
                            <input type="date" value={invForm.data.due_date} onChange={(e) => invForm.setData('due_date', e.target.value)} style={inputStyle} />
                        </div>

                        <div style={{ gridColumn: '1 / -1' }}>
                            <label style={fieldLabelStyle}>Item</label>
                            {invForm.data.items.map((item, index) => (
                                <div key={index} style={{ display: 'grid', gridTemplateColumns: '2fr 0.7fr 1fr auto', gap: 8, marginBottom: 8 }}>
                                    <input
                                        placeholder="Deskripsi"
                                        value={item.description}
                                        onChange={(e) => invForm.setData('items', invForm.data.items.map((it, i) => i === index ? { ...it, description: e.target.value } : it))}
                                        style={inputStyle}
                                    />
                                    <input
                                        type="number"
                                        placeholder="Qty"
                                        value={item.quantity}
                                        onChange={(e) => invForm.setData('items', invForm.data.items.map((it, i) => i === index ? { ...it, quantity: e.target.value } : it))}
                                        style={inputStyle}
                                    />
                                    <RupiahInput
                                        value={item.unit_price}
                                        onChange={(v) => invForm.setData('items', invForm.data.items.map((it, i) => i === index ? { ...it, unit_price: v } : it))}
                                    />
                                    <button
                                        type="button"
                                        title="Hapus item"
                                        onClick={() => invForm.setData('items', invForm.data.items.filter((_, i) => i !== index))}
                                        disabled={invForm.data.items.length === 1}
                                        style={iconBtn}
                                    >
                                        <AIcon name="x" size={15} color={C.red} />
                                    </button>
                                </div>
                            ))}
                            <button
                                type="button"
                                onClick={() => invForm.setData('items', [...invForm.data.items, { description: '', quantity: '1', unit_price: '' }])}
                                style={{ ...btnOut, height: 34, marginTop: 2 }}
                            >
                                <AIcon name="plus" size={14} />
                                Tambah Item
                            </button>
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>Pajak</label>
                            <RupiahInput value={invForm.data.tax} onChange={(v) => invForm.setData('tax', v)} />
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', justifyContent: 'flex-end' }}>
                            <div style={{ fontSize: 12.5, color: C.muted }}>Subtotal: {formatRupiah(invoiceSubtotal)}</div>
                            <div style={{ fontSize: 16, fontWeight: 700, color: C.navy }}>Total: {formatRupiah(invoiceTotal)}</div>
                        </div>

                        <div style={{ gridColumn: '1 / -1', display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 6 }}>
                            <button type="button" onClick={() => setModal(null)} style={btnOut}>Batal</button>
                            <button type="submit" disabled={invForm.processing} style={btnP}>Buat Invoice</button>
                        </div>
                    </form>
                </Modal>
            ) : null}
        </>
    );
}
