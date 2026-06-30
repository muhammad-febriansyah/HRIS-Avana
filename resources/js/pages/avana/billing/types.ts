/**
 * Shared types for the AvanaHR platform Billing & Invoice module (Super Admin).
 * Mirrors `BillingController@index` / `@printInvoice` payloads.
 */

export type { FlashProps } from '../employees/types';

/** A single invoice line item. */
export interface InvoiceItem {
    id?: number;
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
}

/** An invoice row as serialized by the controller. */
export interface InvoiceRow {
    id: number;
    invoice_number: string;
    tenant_id: number;
    tenant: string | null;
    period_start: string | null;
    period_end: string | null;
    issue_date: string | null;
    due_date: string | null;
    subtotal: number;
    tax: number;
    total: number;
    status: string;
    paid_at: string | null;
    items: InvoiceItem[];
}

/** A subscription row. */
export interface SubscriptionRow {
    id: number;
    tenant_id: number;
    tenant: string | null;
    package: string | null;
    package_id: number | null;
    status: string;
    billing_cycle: string;
    price: number;
    start_date: string | null;
    end_date: string | null;
}

/** A `{ id, name }` tenant option. */
export interface TenantOption {
    id: number;
    name: string;
}

/** A package option with its default price + cycle. */
export interface PackageOption {
    id: number;
    name: string;
    price: number;
    billing_cycle: string;
}

/** KPI counters shown at the top of the billing dashboard. */
export interface BillingKpis {
    outstanding: number;
    paid_this_month: number;
    overdue_count: number;
    active_subscriptions: number;
    mrr: number;
}

/** Props for the billing index page. */
export interface BillingIndexProps {
    invoices: InvoiceRow[];
    subscriptions: SubscriptionRow[];
    tenants: TenantOption[];
    packages: PackageOption[];
    kpis: BillingKpis;
    subscriptionStatuses: string[];
    billingCycles: string[];
}

/** Props for the printable invoice page. */
export interface InvoicePrintProps {
    invoice: InvoiceRow & { tenant_company: string | null; notes: string | null };
}

/** Indonesian label for an invoice status. */
export const INVOICE_STATUS_LABEL: Record<string, string> = {
    unpaid: 'Belum Bayar',
    paid: 'Lunas',
    overdue: 'Jatuh Tempo',
    cancelled: 'Dibatalkan',
};

/** Indonesian label for a subscription status. */
export const SUBSCRIPTION_STATUS_LABEL: Record<string, string> = {
    active: 'Aktif',
    trial: 'Trial',
    past_due: 'Menunggak',
    cancelled: 'Dibatalkan',
};

/** Indonesian label for a billing cycle. */
export const BILLING_CYCLE_LABEL: Record<string, string> = {
    monthly: 'Bulanan',
    quarterly: 'Triwulan',
    yearly: 'Tahunan',
};

/** Format a number as Indonesian Rupiah. */
export function formatRupiah(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value || 0);
}

/** Format an ISO date string into a readable id-ID date. */
export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
