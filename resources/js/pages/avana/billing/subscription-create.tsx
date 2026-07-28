import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import BillingController from '@/actions/App/Http/Controllers/Avana/BillingController';
import { DatePicker } from '@/components/avana/date-picker';
import { AIcon, btnOut, btnSave, C, card, RupiahInput } from '@/lib/avana';
import { fieldLabelStyle, inputStyle, selectStyle } from './components';
import { BILLING_CYCLE_LABEL, SUBSCRIPTION_STATUS_LABEL } from './types';
import type { FlashProps, PackageOption, TenantOption } from './types';

/** Months a billing cycle covers, for deriving the end date from the start. */
const CYCLE_MONTHS: Record<string, number> = {
    monthly: 1,
    quarterly: 3,
    yearly: 12,
};

/** `YYYY-MM-DD` of today. */
function today(): string {
    return new Date().toISOString().slice(0, 10);
}

/**
 * The date `months` after `start`, clamped to the end of a shorter month (31 Jan
 * + 1 month = 28/29 Feb, never 3 March).
 */
function addMonths(start: string, months: number): string {
    const [y, m, d] = start.split('-').map(Number);

    if (!y || !m || !d) {
        return '';
    }

    const lastDay = new Date(y, m - 1 + months + 1, 0).getDate();
    const date = new Date(y, m - 1 + months, Math.min(d, lastDay));

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

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

    return (
        <div style={{ fontSize: 12, color: C.red, marginTop: 5 }}>{msg}</div>
    );
}

export default function SubscriptionCreate({
    tenants,
    packages,
    subscriptionStatuses,
    billingCycles,
}: SubscriptionCreateProps) {
    const { flash } = usePage<FlashProps>().props;

    // The period is prefilled from today: a subscription saved without an end
    // date never expires, so it would never warn, never lock, and never be
    // renewable — an easy trap on a form where both dates are optional.
    const form = useForm({
        tenant_id: '',
        package_id: '',
        status: 'active',
        billing_cycle: 'monthly',
        price: '',
        start_date: today(),
        end_date: addMonths(today(), 1),
    });

    const { data, setData, errors, processing } = form;

    /** Move the end date along with whatever drives it, unless it was cleared. */
    const deriveEnd = (start: string, cycle: string): string =>
        start === '' ? '' : addMonths(start, CYCLE_MONTHS[cycle] ?? 1);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    return (
        <>
            <Head title="Buat Langganan" />
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
                        href={BillingController.index()}
                        style={{
                            color: C.faint,
                            textDecoration: 'none',
                            cursor: 'pointer',
                        }}
                    >
                        Billing &amp; Invoice
                    </Link>
                    <AIcon name="chevron-right" size={13} />
                    <span style={{ color: C.muted }}>Buat Langganan</span>
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
                    Buat Langganan Baru
                </h1>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(BillingController.storeSubscription().url);
                    }}
                    style={{ ...card }}
                >
                    <div
                        style={{
                            padding: '22px 24px',
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 16,
                        }}
                    >
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label style={fieldLabelStyle}>Klien</label>
                            <select
                                value={data.tenant_id}
                                onChange={(e) =>
                                    setData('tenant_id', e.target.value)
                                }
                                style={selectStyle}
                            >
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
                                    const pkg = packages.find(
                                        (p) => String(p.id) === e.target.value,
                                    );
                                    setData((current) => {
                                        const cycle = pkg
                                            ? pkg.billing_cycle
                                            : current.billing_cycle;

                                        return {
                                            ...current,
                                            package_id: e.target.value,
                                            price: pkg
                                                ? String(pkg.price)
                                                : current.price,
                                            billing_cycle: cycle,
                                            end_date: deriveEnd(
                                                current.start_date,
                                                cycle,
                                            ),
                                        };
                                    });
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
                            <RupiahInput
                                value={data.price}
                                onChange={(v) => setData('price', v)}
                                invalid={!!errors.price}
                            />
                            <FieldErr msg={errors.price} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Siklus</label>
                            <select
                                value={data.billing_cycle}
                                onChange={(e) =>
                                    setData((current) => ({
                                        ...current,
                                        billing_cycle: e.target.value,
                                        end_date: deriveEnd(
                                            current.start_date,
                                            e.target.value,
                                        ),
                                    }))
                                }
                                style={selectStyle}
                            >
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
                            <select
                                value={data.status}
                                onChange={(e) =>
                                    setData('status', e.target.value)
                                }
                                style={selectStyle}
                            >
                                {subscriptionStatuses.map((s) => (
                                    <option key={s} value={s}>
                                        {SUBSCRIPTION_STATUS_LABEL[s] ?? s}
                                    </option>
                                ))}
                            </select>
                            <FieldErr msg={errors.status} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Mulai</label>
                            <DatePicker
                                value={data.start_date}
                                onChange={(nextValue) =>
                                    setData((current) => ({
                                        ...current,
                                        start_date: nextValue,
                                        end_date: deriveEnd(
                                            nextValue,
                                            current.billing_cycle,
                                        ),
                                    }))
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                            <FieldErr msg={errors.start_date} />
                        </div>
                        <div>
                            <label style={fieldLabelStyle}>Berakhir</label>
                            <DatePicker
                                value={data.end_date}
                                onChange={(nextValue) =>
                                    setData('end_date', nextValue)
                                }
                                placeholder="Pilih tanggal"
                                width="100%"
                            />
                            <FieldErr msg={errors.end_date} />
                            <div
                                style={{
                                    fontSize: 11.5,
                                    color: C.faint,
                                    marginTop: 5,
                                }}
                            >
                                Terisi otomatis dari siklus — bisa diubah.
                                Dikosongkan berarti langganan tanpa masa
                                berakhir (tidak pernah mengingatkan atau
                                mengunci).
                            </div>
                        </div>
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
                            href={BillingController.index().url}
                            style={{
                                ...btnOut,
                                height: 44,
                                justifyContent: 'center',
                                textDecoration: 'none',
                            }}
                        >
                            <AIcon name="x" size={16} />
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
                            <AIcon name="plus" size={16} color="#fff" />
                            Simpan Langganan
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
