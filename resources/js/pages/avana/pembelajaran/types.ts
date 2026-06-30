/**
 * Shared types for the AvanaHR learning (LMS) module pages. These mirror
 * the `LearningController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A training row as serialized by `LearningController@index`. */
export interface TrainingRow {
    id: number;
    title: string;
    category: string;
    type: string;
    start_date: string | null;
    end_date: string | null;
    cost: number;
    instructor: string | null;
    quota: number | null;
    status: string;
    description: string | null;
    enrollments_count: number;
}

/** A training enrollment row as serialized by `LearningController@index`. */
export interface EnrollmentRow {
    id: number;
    training_id: number;
    training_title: string | null;
    employee: {
        name: string;
        employee_number: string | null;
    } | null;
    status: string;
    score: number | null;
    certificate_no: string | null;
    completed_date: string | null;
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

/** KPI counters shown at the top of the index page. */
export interface PembelajaranKpis {
    total_training: number;
    ongoing: number;
    peserta: number;
    completed: number;
}

/** Props for the learning index page (`index.tsx`). */
export interface PembelajaranIndexProps {
    trainings: TrainingRow[];
    enrollments: EnrollmentRow[];
    employees: EmployeeOption[];
    statuses: SelectOption[];
    kpis: PembelajaranKpis;
}

/** Flat form payload backing both the create and edit training forms. */
export interface TrainingFormData {
    title: string;
    category: string;
    type: string;
    start_date: string;
    end_date: string;
    cost: string;
    instructor: string;
    quota: string;
    status: string;
    description: string;
}

/** Empty defaults for the create training form. */
export const emptyTrainingForm: TrainingFormData = {
    title: '',
    category: '',
    type: 'internal',
    start_date: '',
    end_date: '',
    cost: '0',
    instructor: '',
    quota: '',
    status: 'planned',
    description: '',
};

/** Flat form payload backing the add-participant modal on the index page. */
export interface EnrollFormData {
    training_id: string;
    employee_id: string;
    status: string;
}

/** Empty defaults for the add-participant form. */
export const emptyEnrollForm: EnrollFormData = {
    training_id: '',
    employee_id: '',
    status: 'enrolled',
};

/** Flat form payload backing the update-enrollment modal. */
export interface EnrollmentUpdateFormData {
    status: string;
    score: string;
    certificate_no: string;
    completed_date: string;
}

/** Empty defaults for the update-enrollment form. */
export const emptyEnrollmentUpdateForm: EnrollmentUpdateFormData = {
    status: 'attended',
    score: '',
    certificate_no: '',
    completed_date: '',
};

/** Selectable training type enum options. */
export const TYPE_OPTIONS: SelectOption[] = [
    { value: 'internal', label: 'Internal' },
    { value: 'external', label: 'Eksternal' },
    { value: 'online', label: 'Online' },
];

/** Selectable training status enum options. */
export const STATUS_OPTIONS: SelectOption[] = [
    { value: 'planned', label: 'Direncanakan' },
    { value: 'ongoing', label: 'Berjalan' },
    { value: 'completed', label: 'Selesai' },
];

/** Selectable enrollment status enum options. */
export const ENROLLMENT_STATUS_OPTIONS: SelectOption[] = [
    { value: 'enrolled', label: 'Terdaftar' },
    { value: 'attended', label: 'Hadir' },
    { value: 'completed', label: 'Selesai' },
];

/** Indonesian label for a training type enum value. */
export function typeLabel(type: string): string {
    return TYPE_OPTIONS.find((option) => option.value === type)?.label ?? type;
}

/** Indonesian label for a training status enum value. */
export function statusLabel(status: string): string {
    return (
        STATUS_OPTIONS.find((option) => option.value === status)?.label ??
        status
    );
}

/** Indonesian label for an enrollment status enum value. */
export function enrollmentStatusLabel(status: string): string {
    return (
        ENROLLMENT_STATUS_OPTIONS.find((option) => option.value === status)
            ?.label ?? status
    );
}
