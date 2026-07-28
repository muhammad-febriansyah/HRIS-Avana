/**
 * Period maths for the Tambah Klien wizard. A client's quota and subscription
 * window are derived from the package it is put on, so picking "HC Starter,
 * trial 14 hari" is enough — the wizard fills in the rest.
 */

import type { PackageOption, TenantFormData } from './types';

export const TRIAL_PRESETS = [7, 14, 30] as const;

export const CYCLE_LABELS: Record<string, string> = {
    monthly: 'Bulanan',
    quarterly: 'Per 3 Bulan',
    yearly: 'Tahunan',
};

/** Months one billing cycle covers. */
const CYCLE_MONTHS: Record<string, number> = {
    monthly: 1,
    quarterly: 3,
    yearly: 12,
};

/** Today as `YYYY-MM-DD`, in the browser's own timezone. */
export function today(): string {
    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 10);
}

function toDate(value: string): Date | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return Number.isNaN(date.getTime()) ? null : date;
}

function format(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/** Add whole months, clamping to the last day when the target month is shorter. */
function addMonths(date: Date, months: number): Date {
    const target = new Date(date.getTime());
    const day = target.getDate();

    target.setDate(1);
    target.setMonth(target.getMonth() + months);
    target.setDate(Math.min(day, daysInMonth(target)));

    return target;
}

function daysInMonth(date: Date): number {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
}

/**
 * When the subscription runs out: a trial after its day count, a paid client at
 * the end of one billing cycle. Mirrors `TenantController::periodEnd`.
 */
export function periodEnd(
    startDate: string,
    status: string,
    cycle: string,
    trialDays: string,
): string {
    const start = toDate(startDate);

    if (start === null) {
        return '';
    }

    if (status === 'trial') {
        const days = Number(trialDays) > 0 ? Number(trialDays) : 14;
        const end = new Date(start.getTime());
        end.setDate(end.getDate() + days);

        return format(end);
    }

    return format(addMonths(start, CYCLE_MONTHS[cycle] ?? 1));
}

/** How many days the chosen period covers — the "1 bulan = 31 hari" readout. */
export function periodDays(startDate: string, endDate: string): number | null {
    const start = toDate(startDate);
    const end = toDate(endDate);

    if (start === null || end === null) {
        return null;
    }

    return Math.round((end.getTime() - start.getTime()) / 86_400_000);
}

/** `2026-08-26` → `26 Agu 2026`. */
export function formatDate(value: string): string {
    const date = toDate(value);

    if (date === null) {
        return '—';
    }

    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/** `2500000` → `Rp 2.500.000`. */
export function formatRupiah(value: number): string {
    return `Rp ${Math.round(value).toLocaleString('id-ID')}`;
}

/**
 * `500000` → `500rb`, `2000000` → `2 jt`. Package cards are narrow, so the free
 * monthly AI allowance is shortened rather than spelled out in full digits.
 */
export function formatTokens(value: number): string {
    const compact = (amount: number, unit: string): string =>
        `${amount.toLocaleString('id-ID', { maximumFractionDigits: 1 })} ${unit}`;

    if (value >= 1_000_000) {
        return compact(value / 1_000_000, 'jt');
    }

    if (value >= 1_000) {
        return compact(value / 1_000, 'rb');
    }

    return value.toLocaleString('id-ID');
}

/** Apply a package's quota and billing cycle to the form data. */
export function applyPackage(
    data: TenantFormData,
    pkg: PackageOption | null,
): Partial<TenantFormData> {
    if (pkg === null) {
        return { package_id: '' };
    }

    const cycle = pkg.billing_cycle || 'monthly';

    return {
        package_id: String(pkg.id),
        billing_cycle: cycle,
        max_users: String(pkg.max_users),
        max_employees: String(pkg.max_employees),
        max_branches: String(pkg.max_branches),
        end_date: periodEnd(
            data.start_date || today(),
            data.status,
            cycle,
            data.trial_days,
        ),
    };
}

/** Fields each wizard step owns, so a step can be validated on its own. */
export const STEP_FIELDS: Record<number, (keyof TenantFormData)[]> = {
    0: [
        'package_id',
        'status',
        'billing_cycle',
        'trial_days',
        'start_date',
        'end_date',
    ],
    1: [
        'name',
        'company_name',
        'slug',
        'max_users',
        'max_employees',
        'max_branches',
        'billing_status',
    ],
    2: ['admin_name', 'admin_email', 'admin_password'],
};
