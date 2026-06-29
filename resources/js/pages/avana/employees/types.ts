/**
 * Shared types for the AvanaHR employee module pages. These mirror the
 * `EmployeeResource` / `formOptions` payloads returned by the backend
 * `App\Http\Controllers\Avana\EmployeeController`.
 */

/** Compact `{ id, name }` shape for a loaded relation. */
export type NamedRef = {
    id: number;
    name: string | null;
};

/** Manager relation carries the employee number for disambiguation. */
export type ManagerRef = {
    id: number;
    name: string;
    employee_number: string;
};

/** A single employee record as serialized by `EmployeeResource`. */
export type Employee = {
    id: number;
    employee_number: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    nik: string | null;
    gender: string | null;
    birth_date: string | null;
    birth_place: string | null;
    religion: string | null;
    marital_status: string | null;
    address: string | null;
    employment_status: string;
    employment_label: string;
    join_date: string | null;
    join_date_raw: string | null;
    status: string;
    status_label: string;
    initials: string;
    avatar_color: string;
    branch?: NamedRef | null;
    department?: NamedRef | null;
    position?: NamedRef | null;
    job_level?: NamedRef | null;
    work_location?: NamedRef | null;
    manager?: ManagerRef | null;
};

/** Laravel paginator `meta` block carried by a resource collection. */
export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

/** `{ value, label }` option used by enum selects. */
export type SelectOption = {
    value: string;
    label: string;
};

/** `{ id, name }` option used by relation selects. */
export type NamedOption = {
    id: number;
    name: string;
};

/** Option lists shared by the create and edit forms. */
export type EmployeeFormOptions = {
    branches: NamedOption[];
    departments: NamedOption[];
    positions: NamedOption[];
    jobLevels: NamedOption[];
    managers: ManagerRef[];
    genders: SelectOption[];
    statuses: SelectOption[];
    employmentStatuses: SelectOption[];
};

/** Flat string-only form payload backing both the create and edit forms. */
export type EmployeeFormData = {
    full_name: string;
    email: string;
    phone: string;
    nik: string;
    gender: string;
    birth_date: string;
    birth_place: string;
    religion: string;
    marital_status: string;
    address: string;
    employment_status: string;
    join_date: string;
    branch_id: string;
    department_id: string;
    position_id: string;
    job_level_id: string;
    manager_id: string;
    status: string;
};

/** Success flash message shared on every Inertia response. */
export type FlashProps = {
    flash?: {
        success?: string;
    };
};
