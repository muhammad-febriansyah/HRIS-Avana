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

/** An active asset assignment held by the employee (mirrors `held_assets`). */
export type HeldAsset = {
    id: number;
    assigned_date: string | null;
    notes: string | null;
    asset: {
        id: number;
        code: string;
        name: string;
        category: string;
        condition_label: string;
    } | null;
};

/** An uploaded document belonging to the employee. */
export type EmployeeDocumentRow = {
    id: number;
    name: string;
    type: string | null;
    extension: string | null;
    size_label: string | null;
    uploaded_at: string | null;
    download_url: string | null;
};

/** One leave request in the employee's leave history. */
export type LeaveHistoryRow = {
    id: number;
    type: string;
    date_label: string;
    duration_label: string;
    status: string;
    status_label: string;
};

/** One payslip line in the employee's payroll history. */
export type PayrollHistoryRow = {
    id: number;
    period: string;
    net_salary_label: string;
    status: string;
    status_label: string;
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
    /** Display form, `d M Y`. Use `birth_date_raw` to fill a date input. */
    birth_date: string | null;
    birth_date_raw: string | null;
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
    has_login?: boolean;
    is_top_approver?: boolean;
    role_id?: number | null;
    account_active?: boolean;
    device?: {
        label: string;
        platform: string | null;
        last_login: string | null;
    } | null;
    initials: string;
    avatar_color: string;
    /** Opaque key used in URLs; never the primary key. */
    route_key: string;
    photo_url: string | null;
    branch?: NamedRef | null;
    department?: NamedRef | null;
    position?: NamedRef | null;
    job_level?: NamedRef | null;
    work_location?: {
        id: number;
        name: string | null;
        radius_meter?: number;
        status?: string;
    } | null;
    manager?: ManagerRef | null;
    /** The Master Gaji this employee is attached to, if any. */
    salary_master_id?: number | null;
    salary_master?: { id: number; code: string; category: string | null } | null;
    /** BPJS membership numbers, kept on the employee's BPJS profile. */
    bpjs_kesehatan_number?: string | null;
    /** PTKP code the PPh 21 calculation is based on. */
    ptkp_status?: string | null;
    bpjs_ketenagakerjaan_number?: string | null;
    contracts?: EmployeeContractRow[];
    custom_data?: Record<string, string>;
    held_assets?: HeldAsset[];
    documents?: EmployeeDocumentRow[];
    leave_history?: LeaveHistoryRow[];
    payroll_history?: PayrollHistoryRow[];
};

/** One row of the employee's contract history. */
export type EmployeeContractRow = {
    id: number;
    contract_number: string | null;
    contract_type: string | null;
    start_date: string | null;
    end_date: string | null;
    start_date_raw: string | null;
    end_date_raw: string | null;
    status: string | null;
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

/**
 * Role option, carrying whether the role may use the Flutter app — the picker
 * sits in the "Akun Aplikasi Mobile" section, so a role that bars the phone has
 * to say so before it is picked.
 */
export type RoleOption = {
    id: number;
    name: string;
    can_access_mobile: boolean;
};

/** Work-location option carries its branch so the picker can filter by branch. */
export type WorkLocationOption = {
    id: number;
    name: string;
    branch_id: number | null;
};

/**
 * A tenant account that belongs to no employee yet — an HR or finance login
 * made outside this form. Linking one turns it into that employee's mobile
 * account instead of leaving them with a second, duplicate login.
 */
export type LinkableUser = {
    id: number;
    name: string;
    email: string | null;
    roles: string;
};

/** Option lists shared by the create and edit forms. */
export type EmployeeFormOptions = {
    branches: NamedOption[];
    workLocations: WorkLocationOption[];
    departments: NamedOption[];
    positions: NamedOption[];
    jobLevels: NamedOption[];
    salaryMasters: NamedOption[];
    roles: RoleOption[];
    linkableUsers: LinkableUser[];
    managers: ManagerRef[];
    genders: SelectOption[];
    statuses: SelectOption[];
    contractTypes: SelectOption[];
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
    work_location_id: string;
    department_id: string;
    position_id: string;
    job_level_id: string;
    salary_master_id: string;
    contract_number: string;
    contract_type: string;
    contract_start_date: string;
    contract_end_date: string;
    bpjs_kesehatan_number: string;
    ptkp_status: string;
    bpjs_ketenagakerjaan_number: string;
    manager_id: string;
    status: string;
    password: string;
    role_id: string;
    link_user_id: string;
    is_top_approver: boolean;
    custom_data?: Record<string, string>;
};

/**
 * Sentinel the Atasan Langsung picker posts for "no manager above this person".
 * Distinct from an empty value, which only means the field is untouched — the
 * backend turns this into `manager_id: null` + `is_top_approver: true`.
 */
export const NO_MANAGER = 'none';

/**
 * Sentinel for "atasannya belum ditentukan" — `manager_id: null` +
 * `is_top_approver: false`. The escape hatch for the first hires of a company,
 * who have nobody to report to yet but are not the board either.
 */
export const UNASSIGNED_MANAGER = 'unassigned';

/** The six religions recognised by the Indonesian state, plus an escape hatch. */
export const RELIGIONS = [
    'Islam',
    'Kristen Protestan',
    'Katolik',
    'Hindu',
    'Buddha',
    'Konghucu',
    'Kepercayaan Lainnya',
] as const;

/** Default password offered when creating an employee login. */
export const DEFAULT_PASSWORD = 'karyawan123';

/** Wizard steps for the employee form, in the order they are shown. */
export const EMPLOYEE_STEPS = [
    { title: 'Data Personal', hint: 'Identitas sesuai KTP' },
    { title: 'Kepegawaian', hint: 'Posisi, atasan & kontrak' },
    { title: 'Akun & Akses', hint: 'Login aplikasi mobile' },
    { title: 'Data Tambahan', hint: 'Field kustom perusahaan' },
] as const;

/**
 * Which form fields belong to which step, so a server-side validation error
 * can send the wizard back to the step that owns it.
 */
export const STEP_FIELDS: string[][] = [
    [
        'full_name',
        'nik',
        'email',
        'phone',
        'birth_place',
        'birth_date',
        'gender',
        'marital_status',
        'religion',
        'address',
        'password',
    ],
    [
        'employee_number',
        'department_id',
        'position_id',
        'job_level_id',
        'manager_id',
        'is_top_approver',
        'employment_status',
        'salary_master_id',
        'contract_number',
        'contract_type',
        'contract_start_date',
        'contract_end_date',
        'bpjs_ketenagakerjaan_number',
        'bpjs_kesehatan_number',
        'ptkp_status',
        'join_date',
        'branch_id',
        'work_location_id',
        'attendance_scope',
        'status',
    ],
    ['role_id', 'link_user_id'],
    ['custom_data'],
];

/** A tenant-defined custom employee field. */
export type CustomFieldDef = {
    key: string;
    label: string;
    type: string;
    options: string[];
    is_required: boolean;
};

/** Success flash message shared on every Inertia response. */
export type FlashProps = {
    flash?: {
        success?: string;
        /** The action worked, but with a caveat the user should read. */
        warning?: string;
        /** The action was refused; the row is unchanged. */
        error?: string;
    };
    errors?: Record<string, string>;
};
