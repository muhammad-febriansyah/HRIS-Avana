import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import BillingController from '@/actions/App/Http/Controllers/Avana/BillingController';
import { AIcon, btnOut, btnP, C, card, RupiahInput } from '@/lib/avana';
import { fieldLabelStyle, inputStyle, selectStyle } from './components';
import { BILLING_CYCLE_LABEL } from './types';
import type { FlashProps, PackageOption, TenantOption } from './types';

interface SubscriptionCreateProps {
    tenants: TenantOption[];
    packages: PackageOption[];
    subscriptionStatuses: string[];
    billingCycles: string[];
}

function FieldErr({ msg }: { msg?: string }) {
    if (!msg) {
        return null;
    }

    return <div style={{ fontSize: 12, color: C.red, marginTop: 5 }}>{msg}</div>;
}

export default function SubscriptionCreate({ tenants, packages, subscriptionStatuses, billingCycles }: SubscriptionCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    const form = useForm({
        tenant_id: '',
        package_id: '',
        status: 'active',
        billing_cycle: 'monthly',
        price: '',
        start_date: '',
        end_date: '',
    });

    const { data, setData, errors, processing } = form;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    return (
        <>
            <Head title="Buat Langganan" />
            <div style={{ padding: '28px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: C.faint, marginBottom: 14 }}>
                    <Link href={BillingController.index()} style={{ color: C.faint, textDecoration: 'none', cursor: 'pointer' }}>
                        Billing &amp; Invoice
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Langganan</span>
                </div>
                <h1 style={{ fontSize: 24, fontWeight: 600, color: C.navy, margin: '0 0 24px', letterSpacing: '-.01em' }}>
                    Buat Langganan Baru
                </h1>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(BillingController.storeSubscription().url);
                    }}
                    style={{ ...card, maxWidth: 640 }}
                >
                    <div style={{ padding: '22px 24px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                        <div style={{ gridColumn: '1 / -1' }}>
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
                            <label style={fieldLabelStyle}>Paket</label>
                            <select
                                value={data.package_id}
                                onChange={(e) => {
                                    const pkg = packages.find((p) => String(p.id) === e.target.value);
                                    setData((current) => ({
                                        ...current,
                                        package_id: e.target.value,
                                        price: pkg ? String(pkg.price) : current.price,
                                        billing_cycle: pkg ? pkg.billing_cycle : current.billing_cycle,
                                    }));
                                }}
                                style={selectStyle}
                            >
                                <option value="">Tanpa paket</option>
                                {packages.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            <FieldErr msg={errors.package_id} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Harga</label>
                            <RupiahInput value={data.price} onChange={(v) => setData('price', v)} invalid={!!errors.price} />
                            <FieldErr msg={errors.price} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Siklus</label>
                            <select value={data.billing_cycle} onChange={(e) => setData('billing_cycle', e.target.value)} style={selectStyle}>
                                {billingCycles.map((c) => (
                                    <option key={c} value={c}>
                                        {BILLING_CYCLE_LABEL[c] ?? c}
                                    </option>
                                ))}
                            </select>
                            <FieldErr msg={errors.billing_cycle} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Status</label>
                            <select value={data.status} onChange={(e) => setData('status', e.target.value)} style={selectStyle}>
                                {subscriptionStatuses.map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                            <FieldErr msg={errors.status} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Mulai</label>
                            <input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} style={inputStyle} />
                            <FieldErr msg={errors.start_date} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Berakhir</label>
                            <input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} style={inputStyle} />
                            <FieldErr msg={errors.end_date} />
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
                            Simpan Langganan
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
