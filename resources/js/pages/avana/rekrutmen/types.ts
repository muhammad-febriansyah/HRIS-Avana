/**
 * Shared types for the AvanaHR recruitment (ATS) module pages. These mirror
 * the `RecruitmentController` payloads (`index`, `create`, `edit`).
 */

export type { FlashProps } from '../employees/types';

/** A job posting row as serialized by `RecruitmentController@index`. */
export interface PostingRow {
    id: number;
    title: string;
    department: string | null;
    department_id: number | null;
    location: string | null;
    employment_type: string;
    quota: number;
    status: string;
    description: string | null;
    posted_date: string | null;
    closing_date: string | null;
    applicants_count: number;
}

/** An applicant card as serialized by `RecruitmentController@index`. */
export interface ApplicantCard {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    source: string | null;
    stage: string;
    applied_date: string | null;
    notes: string | null;
    job_posting_id: number;
    job_title: string | null;
}

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A medical checkup record on the candidate detail screen. */
export interface MedicalCheck {
    id: number;
    title: string;
    status: string;
    notes: string | null;
    checked_at: string | null;
    file_url: string | null;
}

/** A background-check record on the candidate detail screen. */
export interface BackgroundCheck {
    id: number;
    check_type: string;
    status: string;
    notes: string | null;
    requested_at: string | null;
    file_url: string | null;
}

/** Full candidate detail payload (`RecruitmentController@showApplicant`). */
export interface ApplicantDetail {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    source: string | null;
    stage: string;
    position: string | null;
    photo_url: string | null;
    linkedin_url: string | null;
    portfolio_url: string | null;
    cv_url: string | null;
    notes: string | null;
    applied_date: string | null;
    interview_at: string | null;
    offered_at: string | null;
    offer_note: string | null;
    job_posting_id: number;
    job_title: string | null;
    employment_type: string | null;
    medical_checks: MedicalCheck[];
    background_checks: BackgroundCheck[];
}

/** Props for the candidate detail page (`candidate.tsx`). */
export interface CandidateProps {
    applicant: ApplicantDetail;
    stages: SelectOption[];
}

/** Indonesian label for a background-check type. */
export const BACKGROUND_TYPE_OPTIONS: SelectOption[] = [
    { value: 'employment', label: 'Riwayat Kerja' },
    { value: 'education', label: 'Pendidikan' },
    { value: 'criminal', label: 'Catatan Kriminal' },
    { value: 'reference', label: 'Referensi' },
];

/** A selectable department `{ id, name }`. */
export interface DepartmentOption {
    id: number;
    name: string;
}

/** Applicants grouped by pipeline stage. */
export type Pipeline = Record<string, ApplicantCard[]>;

/** KPI counters shown at the top of the index page. */
export interface RecruitmentKpis {
    open_postings: number;
    total_applicants: number;
    hired: number;
    in_process: number;
}

/** Props for the recruitment index page (`index.tsx`). */
export interface RekrutmenIndexProps {
    postings: PostingRow[];
    pipeline: Pipeline;
    departments: DepartmentOption[];
    stages: SelectOption[];
    kpis: RecruitmentKpis;
}

/** Flat form payload backing both the create and edit posting forms. */
export interface PostingFormData {
    title: string;
    department_id: string;
    location: string;
    employment_type: string;
    quota: string;
    status: string;
    description: string;
    posted_date: string;
    closing_date: string;
}

/** Empty defaults for the create posting form. */
export const emptyPostingForm: PostingFormData = {
    title: '',
    department_id: '',
    location: '',
    employment_type: 'tetap',
    quota: '1',
    status: 'open',
    description: '',
    posted_date: '',
    closing_date: '',
};

/** Flat form payload backing the add-applicant modal on the index page. */
export interface ApplicantFormData {
    job_posting_id: string;
    name: string;
    email: string;
    phone: string;
    source: string;
    stage: string;
    applied_date: string;
    notes: string;
}

/** Empty defaults for the add-applicant form. */
export const emptyApplicantForm: ApplicantFormData = {
    job_posting_id: '',
    name: '',
    email: '',
    phone: '',
    source: '',
    stage: 'applied',
    applied_date: '',
    notes: '',
};

/** Selectable employment type enum options. */
export const EMPLOYMENT_TYPE_OPTIONS: SelectOption[] = [
    { value: 'tetap', label: 'Tetap' },
    { value: 'kontrak', label: 'Kontrak' },
    { value: 'magang', label: 'Magang' },
    { value: 'harian', label: 'Harian' },
];

/** Selectable posting status enum options. */
export const STATUS_OPTIONS: SelectOption[] = [
    { value: 'open', label: 'Dibuka' },
    { value: 'closed', label: 'Ditutup' },
];

/** Indonesian label for an employment type enum value. */
export function employmentTypeLabel(type: string): string {
    return (
        EMPLOYMENT_TYPE_OPTIONS.find((option) => option.value === type)
            ?.label ?? type
    );
}

/** Indonesian label for a posting status enum value. */
export function statusLabel(status: string): string {
    return (
        STATUS_OPTIONS.find((option) => option.value === status)?.label ??
        status
    );
}
