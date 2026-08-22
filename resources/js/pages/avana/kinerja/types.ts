/**
 * Shared types for the AvanaHR performance management (Kinerja) module pages.
 * These mirror the `PerformanceController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A performance review row as serialized by `PerformanceController@index`. */
export interface ReviewRow {
    id: number;
    route_key: string;
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
    /** Pre-workflow row: completed but never calibrated, unusable downstream. */
    is_legacy: boolean;
    /** True only when the rating may be consumed by payroll and analytics. */
    is_publishable: boolean;
    cycle_status: string | null;
    notes: string | null;
    review_date: string | null;
}

/** Capability flags resolved server-side by `PerformanceController`. */
export interface KinerjaAbilities {
    create: boolean;
    update: boolean;
    archive: boolean;
    approve: boolean;
}

/** A snapshot taken when a completed review was reopened. */
export interface RevisionRow {
    id: number;
    from_status: string;
    to_status: string;
    self_score: number | null;
    manager_score: number | null;
    final_score: number | null;
    calibrated_score: number | null;
    reason: string;
    reopened_by: string | null;
    created_at: string | null;
}
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
    can: KinerjaAbilities;
    reviews: ReviewRow[];
    cycles: CycleRow[];
    employees: EmployeeOption[];
    cycleOptions: CycleOption[];
    statuses: SelectOption[];
    cycleStatuses: SelectOption[];
    kpis: KinerjaKpis;
}

/**
 * Flat form payload backing both the create and edit review forms.
 * Scores and status are workflow-controlled server-side (submit-score,
 * calibrate, reopen) and are not part of this payload.
 */
export interface ReviewFormData {
    cycle_id: string;
    employee_id: string;
    reviewer_id: string;
    notes: string;
    review_date: string;
}

/** Empty defaults for the create review form. */
export const emptyReviewForm: ReviewFormData = {
    cycle_id: '',
    employee_id: '',
    reviewer_id: '',
    notes: '',
    review_date: '',
};

/** Flat form payload backing the add-cycle modal on the index page. */
export interface CycleFormData {
    name: string;
    period_start: string;
    period_end: string;
    description: string;
}

/**
 * Empty defaults for the cycle form.
 *
 * `status` is deliberately absent: a cycle always starts as a draft and is
 * advanced through draft → active → closed by the status endpoint, which
 * enforces the transition rules (one active cycle at a time, no closing with
 * unfinished reviews).
 */
export const emptyCycleForm: CycleFormData = {
    name: '',
    period_start: '',
    period_end: '',
    description: '',
};

/**
 * Flat form payload backing the submit-score modal on the index page. Moves
 * the review from manager_review to calibration; status is not client-set.
 */
export interface ScoreFormData {
    manager_score: string;
    review_date: string;
}

/** Empty defaults for the submit-score form. */
export const emptyScoreForm: ScoreFormData = {
    manager_score: '',
    review_date: '',
};

/** Flat form payload backing the reopen-completed-review action. */
export interface ReopenFormData {
    to: string;
    reason: string;
}

/** Empty defaults for the reopen form. */
export const emptyReopenForm: ReopenFormData = {
    to: 'manager_review',
    reason: '',
};

/** Selectable review status enum options. */
export const REVIEW_STATUS_OPTIONS: SelectOption[] = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'self_review', label: 'Penilaian Mandiri' },
    { value: 'manager_review', label: 'Penilaian Atasan' },
    { value: 'calibration', label: 'Kalibrasi' },
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

/** A master KPI indicator, as serialized by `KpiIndicatorController`. */
export interface KpiIndicatorRow {
    id: number;
    name: string;
    unit: string | null;
    direction: string;
    category: string | null;
    description: string | null;
    is_active: boolean;
}

/** A KPI indicator option for the item picker. */
export interface KpiIndicatorOption {
    id: number;
    name: string;
    unit: string | null;
    direction: string;
}

/** A Key Result option for the "from Key Result" item picker. */
export interface KeyResultOption {
    id: number;
    title: string;
    objective_title: string | null;
    progress: number;
}

/** A KPI item assigned to a review, as serialized by `PerformanceController@edit`. */
export interface KpiItemRow {
    id: number;
    source: 'manual' | 'key_result';
    kpi_indicator_id: number | null;
    key_result_id: number | null;
    label: string;
    weight: number;
    direction: string;
    target_value: number | null;
    actual_value: number | null;
    achievement_pct: number;
}

/** Flat form payload backing the edit-KPI-item inline form. */
export interface KpiItemEditFormData {
    weight: string;
    kpi_indicator_id: string;
    target_value: string;
    actual_value: string;
}

/** Flat form payload backing the add-manual-KPI-item inline form. */
export interface KpiItemManualFormData {
    source: 'manual';
    kpi_indicator_id: string;
    weight: string;
    target_value: string;
    actual_value: string;
}

/** Empty defaults for the add-manual-KPI-item form. */
export const emptyKpiItemManualForm: KpiItemManualFormData = {
    source: 'manual',
    kpi_indicator_id: '',
    weight: '',
    target_value: '',
    actual_value: '',
};

/** Flat form payload backing the add-from-Key-Result inline form. */
export interface KpiItemKeyResultFormData {
    source: 'key_result';
    key_result_id: string;
    weight: string;
}

/** Empty defaults for the add-from-Key-Result form. */
export const emptyKpiItemKeyResultForm: KpiItemKeyResultFormData = {
    source: 'key_result',
    key_result_id: '',
    weight: '',
};

/** Selectable KPI indicator direction enum options. */
export const KPI_DIRECTION_OPTIONS: SelectOption[] = [
    { value: 'higher_better', label: 'Makin tinggi makin baik' },
    { value: 'lower_better', label: 'Makin rendah makin baik' },
];

/** Indonesian label for a KPI indicator direction enum value. */
export function kpiDirectionLabel(direction: string): string {
    return (
        KPI_DIRECTION_OPTIONS.find((option) => option.value === direction)
            ?.label ?? direction
    );
}
