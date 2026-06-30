/**
 * Shared types for the AvanaHR CRM (sales pipeline) module page. These mirror
 * the `CrmController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A selectable `{ id, name }` option (contacts / employees). */
export interface IdNameOption {
    id: number;
    name: string;
}

/** A deal card as serialized by `CrmController@index`. */
export interface DealCard {
    id: number;
    title: string;
    value: number;
    stage: string;
    contact_id: number | null;
    contact: string | null;
    company: string | null;
    owner_id: number | null;
    owner: string | null;
    expected_close: string | null;
    notes: string | null;
}

/** A contact row as serialized by `CrmController@index`. */
export interface ContactRow {
    id: number;
    name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    notes: string | null;
    deals_count: number;
}

/** Deals grouped by pipeline stage. */
export type Pipeline = Record<string, DealCard[]>;

/** KPI counters shown at the top of the page. */
export interface CrmKpis {
    total_deals: number;
    pipeline_value: number;
    won_value: number;
}

/** Props for the CRM index page (`index.tsx`). */
export interface CrmIndexProps {
    pipeline: Pipeline;
    contacts: ContactRow[];
    contactOptions: IdNameOption[];
    owners: IdNameOption[];
    stages: SelectOption[];
    kpis: CrmKpis;
}

/** Flat form payload backing the add-contact modal. */
export interface ContactFormData {
    name: string;
    company: string;
    email: string;
    phone: string;
    notes: string;
}

/** Empty defaults for the add-contact form. */
export const emptyContactForm: ContactFormData = {
    name: '',
    company: '',
    email: '',
    phone: '',
    notes: '',
};

/** Flat form payload backing the add/edit-deal modal. */
export interface DealFormData {
    contact_id: string;
    title: string;
    value: string;
    stage: string;
    owner_id: string;
    expected_close: string;
    notes: string;
}

/** Empty defaults for the add-deal form. */
export const emptyDealForm: DealFormData = {
    contact_id: '',
    title: '',
    value: '',
    stage: 'lead',
    owner_id: '',
    expected_close: '',
    notes: '',
};

/** Color tokens per deal pipeline stage: [text, background]. */
export const STAGE_COLORS: Record<string, [string, string]> = {
    lead: ['#6B7280', 'rgba(107,114,128,.12)'],
    qualified: ['#2F54C9', 'rgba(47,84,201,.1)'],
    proposal: ['#D97706', 'rgba(217,119,6,.1)'],
    won: ['#16A34A', 'rgba(22,163,74,.1)'],
    lost: ['#DC2626', 'rgba(220,38,38,.1)'],
};
