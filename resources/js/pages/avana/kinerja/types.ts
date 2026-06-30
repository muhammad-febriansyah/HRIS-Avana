/**
 * Shared types for the AvanaHR performance management (Kinerja) module pages.
 * These mirror the `PerformanceController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A performance review row as serialized by `PerformanceController@index`. */
export interface ReviewRow {
    id: number;
    cycle_id: number;
    cycle: string | null;
    employee_id: number;
    employee: string | null;
    employee_number: string | null;
    reviewer_id: number | null;
    reviewer: string | null;
    self_score: number | null;
    manager_score: number | null;
    final_score: number | null;
    status: string;
    notes: string | null;
    review_date: string | null;
}

/** A performance cycle row as serialized by `PerformanceController@index`. */
export interface CycleRow {
    id: number;
    name: string;
    period_start: string | null;
    period_end: string | null;
    status: string;
    description: string | null;
    reviews_count: number;
}

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A selectable employee `{ id, name, employee_number }`. */
export interface EmployeeOption {
    id: number;
    name: string;
    employee_number: string | null;
}

/** A selectable cycle `{ id, name }`. */
export interface CycleOption {
    id: number;
    name: string;
}

/** KPI counters shown at the top of the index page. */
export interface KinerjaKpis {
    total_reviews: number;
    completed: number;
    in_progress: number;
    active_cycles: number;
}

/** Props for the performance index page (`index.tsx`). */
export interface KinerjaIndexProps {
    reviews: ReviewRow[];
    cycles: CycleRow[];
    employees: EmployeeOption[];
    cycleOptions: CycleOption[];
    statuses: SelectOption[];
    cycleStatuses: SelectOption[];
    kpis: KinerjaKpis;
}

/** Flat form payload backing both the create and edit review forms. */
export interface ReviewFormData {
    cycle_id: string;
    employee_id: string;
    reviewer_id: string;
    self_score: string;
    manager_score: string;
    final_score: string;
    status: string;
    notes: string;
    review_date: string;
}

/** Empty defaults for the create review form. */
export const emptyReviewForm: ReviewFormData = {
    cycle_id: '',
    employee_id: '',
    reviewer_id: '',
    self_score: '',
    manager_score: '',
    final_score: '',
    status: 'pending',
    notes: '',
    review_date: '',
};

/** Flat form payload backing the add-cycle modal on the index page. */
export interface CycleFormData {
    name: string;
    period_start: string;
    period_end: string;
    status: string;
    description: string;
}

/** Empty defaults for the add-cycle form. */
export const emptyCycleForm: CycleFormData = {
    name: '',
    period_start: '',
    period_end: '',
    status: 'draft',
    description: '',
};

/** Flat form payload backing the submit-score modal on the index page. */
export interface ScoreFormData {
    self_score: string;
    manager_score: string;
    final_score: string;
    status: string;
    review_date: string;
}

/** Empty defaults for the submit-score form. */
export const emptyScoreForm: ScoreFormData = {
    self_score: '',
    manager_score: '',
    final_score: '',
    status: 'pending',
    review_date: '',
};

/** Selectable review status enum options. */
export const REVIEW_STATUS_OPTIONS: SelectOption[] = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'self_review', label: 'Penilaian Mandiri' },
    { value: 'manager_review', label: 'Penilaian Atasan' },
    { value: 'completed', label: 'Selesai' },
];

/** Selectable cycle status enum options. */
export const CYCLE_STATUS_OPTIONS: SelectOption[] = [
    { value: 'draft', label: 'Draf' },
    { value: 'active', label: 'Aktif' },
    { value: 'closed', label: 'Selesai' },
];

/** A 360 feedback row as serialized by `PerformanceController@edit`. */
export interface FeedbackRow {
    id: number;
    type: string;
    reviewer_id: number | null;
    reviewer_name: string | null;
    rating: number | null;
    comment: string | null;
    created_at: string | null;
}

/** Flat form payload backing the add-feedback inline form. */
export interface FeedbackFormData {
    type: string;
    reviewer_id: string;
    rating: string;
    comment: string;
}

/** Empty defaults for the add-feedback form. */
export const emptyFeedbackForm: FeedbackFormData = {
    type: 'peer',
    reviewer_id: '',
    rating: '',
    comment: '',
};

/** Selectable 360 feedback type enum options. */
export const FEEDBACK_TYPE_OPTIONS: SelectOption[] = [
    { value: 'self', label: 'Diri Sendiri' },
    { value: 'peer', label: 'Rekan Kerja' },
    { value: 'manager', label: 'Atasan' },
    { value: 'subordinate', label: 'Bawahan' },
];

/** Indonesian label for a feedback type enum value. */
export function feedbackTypeLabel(type: string): string {
    return (
        FEEDBACK_TYPE_OPTIONS.find((option) => option.value === type)?.label ??
        type
    );
}

/** Indonesian label for a review status enum value. */
export function reviewStatusLabel(status: string): string {
    return (
        REVIEW_STATUS_OPTIONS.find((option) => option.value === status)
            ?.label ?? status
    );
}

/** Indonesian label for a cycle status enum value. */
export function cycleStatusLabel(status: string): string {
    return (
        CYCLE_STATUS_OPTIONS.find((option) => option.value === status)?.label ??
        status
    );
}
