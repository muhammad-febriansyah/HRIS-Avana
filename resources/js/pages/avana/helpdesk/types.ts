/**
 * Shared types for the AvanaHR HR Helpdesk (ticketing) module pages. These
 * mirror the `HelpdeskController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A ticket row as serialized by `HelpdeskController@index`. */
export interface TicketRow {
    id: number;
    ticket_no: string;
    requester_id: number;
    requester: string | null;
    requester_number: string | null;
    category: string;
    subject: string;
    description: string;
    priority: string;
    status: string;
    assignee_id: number | null;
    assignee: string | null;
    resolved_at: string | null;
    created_at: string | null;
    replies_count: number;
}

/** A single reply within a ticket thread. */
export interface TicketReply {
    id: number;
    message: string;
    user: string | null;
    created_at: string | null;
}

/** A ticket plus its reply thread, as serialized by `HelpdeskController@edit`. */
export interface TicketDetail {
    id: number;
    ticket_no: string;
    requester_id: number;
    requester: string | null;
    category: string;
    subject: string;
    description: string;
    priority: string;
    status: string;
    assignee_id: number | null;
    assignee: string | null;
    resolved_at: string | null;
    created_at: string | null;
    replies: TicketReply[];
}

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string | null;
    employee_number: string | null;
}

/** A selectable user `{ id, name }`. */
export interface UserOption {
    id: number;
    name: string;
}

/** KPI counters shown at the top of the index page. */
export interface HelpdeskKpis {
    open: number;
    in_progress: number;
    resolved: number;
    closed: number;
}

/** Props for the helpdesk index page (`index.tsx`). */
export interface HelpdeskIndexProps {
    tickets: TicketRow[];
    employees: EmployeeOption[];
    users: UserOption[];
    categories: SelectOption[];
    priorities: SelectOption[];
    statuses: SelectOption[];
    kpis: HelpdeskKpis;
}

/** Flat form payload backing both the create and edit ticket forms. */
export interface TicketFormData {
    requester_id: string;
    category: string;
    subject: string;
    description: string;
    priority: string;
    assignee_id: string;
}

/** Empty defaults for the create ticket form. */
export const emptyTicketForm: TicketFormData = {
    requester_id: '',
    category: 'it',
    subject: '',
    description: '',
    priority: 'medium',
    assignee_id: '',
};

/** Selectable ticket category enum options. */
export const CATEGORY_OPTIONS: SelectOption[] = [
    { value: 'it', label: 'IT' },
    { value: 'hr', label: 'HR' },
    { value: 'payroll', label: 'Payroll' },
    { value: 'facility', label: 'Fasilitas' },
    { value: 'other', label: 'Lainnya' },
];

/** Selectable ticket priority enum options. */
export const PRIORITY_OPTIONS: SelectOption[] = [
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
    { value: 'urgent', label: 'Mendesak' },
];

/** Selectable ticket status enum options, in workflow order. */
export const STATUS_OPTIONS: SelectOption[] = [
    { value: 'open', label: 'Terbuka' },
    { value: 'in_progress', label: 'Diproses' },
    { value: 'resolved', label: 'Selesai' },
    { value: 'closed', label: 'Ditutup' },
];

/** Indonesian label for a ticket category enum value. */
export function categoryLabel(category: string): string {
    return (
        CATEGORY_OPTIONS.find((option) => option.value === category)?.label ??
        category
    );
}

/** Indonesian label for a ticket priority enum value. */
export function priorityLabel(priority: string): string {
    return (
        PRIORITY_OPTIONS.find((option) => option.value === priority)?.label ??
        priority
    );
}

/** Indonesian label for a ticket status enum value. */
export function statusLabel(status: string): string {
    return (
        STATUS_OPTIONS.find((option) => option.value === status)?.label ?? status
    );
}
