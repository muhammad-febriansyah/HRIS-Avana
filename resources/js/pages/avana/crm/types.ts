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
    activities_count?: number;
    open_tasks_count?: number;
}

/** A deal enriched with contact and project detail (deal detail page). */
export interface DealDetail extends DealCard {
    contact_email: string | null;
    contact_phone: string | null;
    project_id: number | null;
    project: string | null;
}

/** A follow-up activity logged against a deal. */
export interface CrmActivity {
    id: number;
    type: string;
    note: string;
    activity_date: string | null;
    outcome: string | null;
    author: string | null;
}

/** A sales task attached to a deal. */
export interface CrmTask {
    id: number;
    title: string;
    due_date: string | null;
    due_date_raw: string | null;
    is_overdue: boolean;
    status: string;
    assignee_id: number | null;
    assignee: string | null;
}

/** A collaborating team member on a deal. */
export interface CrmMember {
    id: number;
    employee_id: number;
    name: string | null;
    role: string | null;
}

/** Props for the deal detail page (`deal.tsx`). */
export interface CrmDealProps {
    deal: DealDetail;
    activities: CrmActivity[];
    tasks: CrmTask[];
    members: CrmMember[];
    employeeOptions: IdNameOption[];
    projectOptions: IdNameOption[];
    activityTypes: SelectOption[];
}

/** A single funnel stage row on the insights dashboard. */
export interface FunnelRow {
    stage: string;
    label: string;
    count: number;
    value: number;
}

/** Per-owner performance row on the insights dashboard. */
export interface OwnerRow {
    name: string;
    deals: number;
    won: number;
    won_value: number;
    open_value: number;
}

/** Props for the CRM insights dashboard (`insights.tsx`). */
export interface CrmInsightsProps {
    funnel: FunnelRow[];
    kpis: {
        total_deals: number;
        open_value: number;
        won_value: number;
        win_rate: number;
        forecast: number;
        activities_this_month: number;
        open_tasks: number;
        overdue_tasks: number;
    };
    byOwner: OwnerRow[];
}

/** Indonesian labels for follow-up activity types. */
export const ACTIVITY_TYPE_LABELS: Record<string, string> = {
    call: 'Telepon',
    email: 'Email',
    meeting: 'Meeting',
    whatsapp: 'WhatsApp',
    visit: 'Kunjungan',
    note: 'Catatan',
};

/** Icon name per follow-up activity type. */
export const ACTIVITY_TYPE_ICONS: Record<string, string> = {
    call: 'phone',
    email: 'mail',
    meeting: 'users',
    whatsapp: 'message-circle',
    visit: 'map-pin',
    note: 'sticky-note',
};

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
