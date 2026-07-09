import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import BillingController from '@/actions/App/Http/Controllers/Avana/BillingController';
import { ActionBtn, AIcon, btnOut, btnP, C } from '@/lib/avana';
import {
    DataTable,
    InvoiceStatusPill,
    KpiCard,
    SubscriptionStatusPill,
    Tabs,
} from './components';
import type { DataColumn } from './components';
import { BILLING_CYCLE_LABEL, formatDate, formatRupiah } from './types';
import type {
    BillingIndexProps,
    FlashProps,
    InvoiceRow,
    SubscriptionRow,
} from './types';

export default function BillingIndex({
    invoices,
    subscriptions,
    kpis,
}: BillingIndexProps) {
    const { flash } = usePage<FlashProps>().props;
    const [tab, setTab] = useState<'invoice' | 'subscription'>('invoice');

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
    }, [flash?.success]);

    const oneClick = (
        url: string,
        message: string,
        method: 'post' | 'delete' = 'post',
    ) => {
        router[method](
            url,
            {},
            { preserveScroll: true, onSuccess: () => toast.success(message) },
        );
    };

    const subscriptionColumns: DataColumn<SubscriptionRow>[] = [
        { header: 'Klien', bold: true, cell: (s) => s.tenant ?? '—' },
        { header: 'Paket', cell: (s) => s.package ?? '—' },
        {
            header: 'Siklus',
            cell: (s) =>
                BILLING_CYCLE_LABEL[s.billing_cycle] ?? s.billing_cycle,
        },
        { header: 'Harga', cell: (s) => formatRupiah(s.price) },
        {
            header: 'Status',
            cell: (s) => <SubscriptionStatusPill status={s.status} />,
        },
        {
            header: 'Aksi',
            align: 'right',
            cell: (s) => (
                <div
                    style={{
                        display: 'inline-flex',
                        justifyContent: 'flex-end',
                    }}
                >
                    <ActionBtn
                        icon="file-plus"
                        label="Generate Invoice"
                        variant="primary"
                        title="Generate invoice periode ini"
                        onClick={() =>
                            oneClick(
                                BillingController.generateInvoice(s.id).url,
                                'Invoice dibuat',
                            )
                        }
                    />
                </div>
            ),
        },
    ];

    const invoiceColumns: DataColumn<InvoiceRow>[] = [
        { header: 'Nomor', bold: true, cell: (i) => i.invoice_number },
        { header: 'Klien', cell: (i) => i.tenant ?? '—' },
        { header: 'Terbit', cell: (i) => formatDate(i.issue_date) },
        { header: 'Jatuh Tempo', cell: (i) => formatDate(i.due_date) },
        { header: 'Total', bold: true, cell: (i) => formatRupiah(i.total) },
        {
            header: 'Status',
            cell: (i) => <InvoiceStatusPill status={i.status} />,
        },
        {
            header: 'Aksi',
            align: 'right',
            cell: (i) => (
                <div
                    style={{
                        display: 'inline-flex',
                        gap: 6,
                        justifyContent: 'flex-end',
                        flexWrap: 'wrap',
                    }}
                >
                    <ActionBtn
                        icon="file-down"
                        label="PDF"
                        variant="primary"
                        href={BillingController.printInvoice(i.id).url}
                        download
                    />
                    {i.status !== 'paid' && i.status !== 'cancelled' ? (
                        <ActionBtn
                            icon="check"
                            label="Lunas"
                            variant="success"
                            onClick={() =>
                                oneClick(
                                    BillingController.markPaid(i.id).url,
                                    'Invoice lunas',
                                )
                            }
                        />
                    ) : null}
                    {i.status !== 'cancelled' ? (
                        <ActionBtn
                            icon="ban"
                            label="Batal"
                            variant="warning"
                            onClick={() =>
                                oneClick(
                                    BillingController.cancelInvoice(i.id).url,
                                    'Invoice dibatalkan',
                                )
                            }
                        />
                    ) : null}
                    <ActionBtn
                        icon="trash-2"
                        label="Hapus"
                        variant="danger"
                        onClick={() =>
                            oneClick(
                                BillingController.destroyInvoice(i.id).url,
                                'Invoice dihapus',
                                'delete',
                            )
                        }
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Billing & Invoice" />
            <div style={{ padding: '28px 32px' }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'flex-start',
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
                            <span>Platform</span>
                            <AIcon name="chevron-right" size={13} />
                            <span style={{ color: C.muted }}>
                                Billing & Invoice
                            </span>
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
                            Billing & Invoice
                        </h1>
                        <p
                            style={{
                                fontSize: 13.5,
                                color: C.muted,
                                margin: '6px 0 0',
                            }}
                        >
                            Kelola langganan klien & tagihan platform AvanaHR.
                        </p>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <Link
                            href={BillingController.createSubscription().url}
                            style={{ ...btnOut, textDecoration: 'none' }}
                        >
                            <AIcon name="repeat" size={15} />
                            Buat Langganan
                        </Link>
                        <Link
                            href={BillingController.createInvoice().url}
                            style={{ ...btnP, textDecoration: 'none' }}
                        >
                            <AIcon name="plus" size={15} />
                            Buat Invoice
                        </Link>
                    </div>
                </div>

                {/* KPIs */}
                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        marginBottom: 24,
                        flexWrap: 'wrap',
                    }}
                >
                    <KpiCard
                        icon="circle-dollar-sign"
                        label="Tagihan Tertunggak"
                        value={formatRupiah(kpis.outstanding)}
                        accent={C.amber}
                    />
                    <KpiCard
                        icon="check-check"
                        label="Lunas Bulan Ini"
                        value={formatRupiah(kpis.paid_this_month)}
                        accent={C.green}
                    />
                    <KpiCard
                        icon="triangle-alert"
                        label="Jatuh Tempo"
                        value={kpis.overdue_count}
                        accent={C.red}
                    />
                    <KpiCard
                        icon="repeat"
                        label="Langganan Aktif"
                        value={kpis.active_subscriptions}
                        accent={C.primary}
                    />
                    <KpiCard
                        icon="trending-up"
                        label="MRR"
                        value={formatRupiah(kpis.mrr)}
                        accent={C.sky}
                    />
                </div>

                <Tabs
                    active={tab}
                    onChange={(k) => setTab(k as 'invoice' | 'subscription')}
                    tabs={[
                        {
                            key: 'invoice',
                            label: 'Invoice',
                            icon: 'file-text',
                            count: invoices.length,
                        },
                        {
                            key: 'subscription',
                            label: 'Langganan',
                            icon: 'repeat',
                            count: subscriptions.length,
                        },
                    ]}
                />

                {tab === 'invoice' ? (
                    <DataTable<InvoiceRow>
                        columns={invoiceColumns}
                        rows={invoices}
                        rowKey={(i) => i.id}
                        searchText={(i) =>
                            `${i.invoice_number} ${i.tenant ?? ''} ${i.status}`
                        }
                        searchPlaceholder="Cari nomor / klien…"
                        empty="Belum ada invoice."
                    />
                ) : (
                    <DataTable<SubscriptionRow>
                        columns={subscriptionColumns}
                        rows={subscriptions}
                        rowKey={(s) => s.id}
                        searchText={(s) =>
                            `${s.tenant ?? ''} ${s.package ?? ''} ${s.status}`
                        }
                        searchPlaceholder="Cari klien / paket…"
                        empty="Belum ada langganan."
                    />
                )}
            </div>
        </>
    );
}
