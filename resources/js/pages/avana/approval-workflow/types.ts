/* ---------- shared types (mirror ApprovalWorkflowController payloads) ---------- */

export interface Option {
    value: number;
    label: string;
}

export interface ModuleDef {
    key: string;
    label: string;
    description: string;
    icon: string;
    color: string;
}

export interface ApproverTypeDef {
    key: string;
    label: string;
    /** Which reference list this type selects from, or null when none. */
    ref: 'role' | 'department' | 'position' | 'employee' | null;
}

export interface WizardOptions {
    roles: Option[];
    departments: Option[];
    positions: Option[];
    employees: Option[];
    leaveTypes: Option[];
}

export interface StepDraft {
    /** Local id for stable React keys / drag ordering. */
    uid: string;
    approver_type: string;
    approver_role_id: number | null;
    approver_department_id: number | null;
    approver_position_id: number | null;
    approver_user_id: number | null;
    condition: null;
}

export type ConditionField = 'days' | 'amount' | 'hours' | 'leave_type';

/** Which condition fields each module can be judged on, keyed by request type. */
export type ConditionFieldMap = Record<string, ConditionField[]>;
export type ConditionOperator = '>' | '>=' | '=' | '<' | '<=';

export interface ConditionDraft {
    uid: string;
    enabled: boolean;
    field: ConditionField;
    operator: ConditionOperator;
    value: string;
    extra_approver_type: string;
    extra_approver_ref: number | null;
}

export interface WorkflowStep {
    step_order: number;
    approver_type: string;
    approver_type_label: string;
    approver_label: string;
    approver_role_id: number | null;
    approver_department_id: number | null;
    approver_position_id: number | null;
    approver_user_id: number | null;
    condition: unknown;
}

export interface WorkflowCondition {
    field: ConditionField;
    operator: ConditionOperator;
    value: string;
    extra_approver_type: string;
    extra_approver_ref: number | null;
}

export interface WorkflowRow {
    id: number;
    name: string;
    request_type: string;
    /** Division this flow is limited to; null = every division (default). */
    department_id: number | null;
    /** "Semua Divisi" or the department name. */
    scope_label: string;
    module_label: string;
    module_icon: string;
    module_color: string;
    approval_mode: 'sequential' | 'parallel';
    approval_mode_label: string;
    step_count: number;
    is_active: boolean;
    conditions: WorkflowCondition[];
    updated_at: string | null;
    steps: WorkflowStep[];
}
