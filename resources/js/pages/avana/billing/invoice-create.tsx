import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import BillingController from '@/actions/App/Http/Controllers/Avana/BillingController';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import { fieldLabelStyle, iconBtn, inputStyle, selectStyle } from './components';
import { formatRupiah } from './types';
import type { FlashProps, TenantOption } from './types';

/** Reduced subscription option as serialized by `BillingController@createInvoice`. */
interface InvoiceSubscriptionOption {
    id: number;
    tenant_id: number;
    tenant: string | null;
    package: string | null;
    price: number;
}

interface InvoiceCreateProps {
    tenants: TenantOption[];
    subscriptions: InvoiceSubscriptionOption[];
}

function FieldErr({ msg }: { msg?: string }) {
    if (!msg) {
        return null;
    }

    return <div style={{ fontSize: 12, color: C.red, marginTop: 5 }}>{msg}</div>;
}

export default function InvoiceCreate({ tenants, subscriptions }: InvoiceCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm<{
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

    const { data, setData, errors, processing } = form;

    const subtotal = data.items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0);
    const total = subtotal + (Number(data.tax) || 0);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    return (
        <>
            <Head title="Buat Invoice" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                    <Link href={BillingController.index()} style={{ color: C.faint, textDecoration: 'none', cursor: 'pointer' }}>
                        Billing &amp; Invoice
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Invoice</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: '0 0 24px', letterSpacing: '-.01em' }}>
                    Buat Invoice Baru
                </h1>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(BillingController.storeInvoice().url);
                    }}
                    style={{ ...card, maxWidth: 760 }}
                >
                    <div style={{ padding: '22px 24px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                        <div>
                            <label style={fieldLabelStyle}>Klien</label>
                            <select value={data.tenant_id} onChange={(e) => setData('tenant_id', e.target.value)} style={selectStyle}>
                                <option value="">Pilih klien</option>
                                {tenants.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
                            <FieldErr msg={errors.tenant_id} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Langganan (opsional)</label>
                            <select
                                value={data.subscription_id}
                                onChange={(e) => {
                                    const sub = subscriptions.find((s) => String(s.id) === e.target.value);
                                    setData((current) => ({
                                        ...current,
                                        subscription_id: e.target.value,
                                        tenant_id: sub ? String(sub.tenant_id) : current.tenant_id,
                                        items: sub
                                            ? [{ description: `Langganan ${sub.package ?? 'AvanaHR'}`, quantity: '1', unit_price: String(sub.price) }]
                                            : current.items,
                                    }));
                                }}
                                style={selectStyle}
                            >
                                <option value="">—</option>
                                {subscriptions.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.tenant} · {s.package ?? '—'}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Tanggal Terbit</label>
                            <input type="date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} style={inputStyle} />
                            <FieldErr msg={errors.issue_date} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Jatuh Tempo</label>
                            <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} style={inputStyle} />
                            <FieldErr msg={errors.due_date} />
                        </div>

                        <div style={{ gridColumn: '1 / -1' }}>
                            <label style={fieldLabelStyle}>Item</label>
                            {data.items.map((item, index) => (
                                <div key={index} style={{ display: 'grid', gridTemplateColumns: '2fr 0.7fr 1fr auto', gap: 8, marginBottom: 8 }}>
                                    <input
                                        placeholder="Deskripsi"
                                        value={item.description}
                                        onChange={(e) => setData('items', data.items.map((it, i) => (i === index ? { ...it, description: e.target.value } : it)))}
                                        style={inputStyle}
                                    />
                                    <input
                                        type="number"
                                        placeholder="Qty"
                                        value={item.quantity}
                                        onChange={(e) => setData('items', data.items.map((it, i) => (i === index ? { ...it, quantity: e.target.value } : it)))}
                                        style={inputStyle}
                                    />
                                    <RupiahInput
                                        value={item.unit_price}
                                        onChange={(v) => setData('items', data.items.map((it, i) => (i === index ? { ...it, unit_price: v } : it)))}
                                    />
                                    <button
                                        type="button"
                                        title="Hapus item"
                                        onClick={() => setData('items', data.items.filter((_, i) => i !== index))}
                                        disabled={data.items.length === 1}
                                        style={iconBtn}
                                    >
                                        <AIcon name="x" size={15} color={C.red} />
                                    </button>
                                </div>
                            ))}
                            {errors.items ? <FieldErr msg={errors.items} /> : null}
                            <button
                                type="button"
                                onClick={() => setData('items', [...data.items, { description: '', quantity: '1', unit_price: '' }])}
                                style={{ ...btnOut, height: 34, marginTop: 2 }}
                            >
                                <AIcon name="plus" size={14} />
                                Tambah Item
                            </button>
                        </div>

                        <div>
                            <label style={fieldLabelStyle}>Pajak</label>
                            <RupiahInput value={data.tax} onChange={(v) => setData('tax', v)} />
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', justifyContent: 'flex-end' }}>
                            <div style={{ fontSize: 12.5, color: C.muted }}>Subtotal: {formatRupiah(subtotal)}</div>
                            <div style={{ fontSize: 16, fontWeight: 700, color: C.navy }}>Total: {formatRupiah(total)}</div>
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', padding: '16px 24px', borderTop: `1px solid ${C.line}` }}>
                        <Link href={BillingController.index().url} style={{ ...btnOut, height: 44, justifyContent: 'center', textDecoration: 'none' }}>
                            Batal
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            style={{ ...btnP, height: 44, justifyContent: 'center', opacity: processing ? 0.7 : 1, cursor: processing ? 'not-allowed' : 'pointer' }}
                        >
                            <AIcon name="plus" size={16} color="#fff" />
                            Buat Invoice
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
