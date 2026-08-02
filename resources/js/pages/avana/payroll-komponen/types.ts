export type Category = 'pendapatan' | 'potongan' | 'pajak' | 'bpjs';
export type CalcType =
    'jumlah_tetap' | 'per_hari' | 'per_jam' | 'persentase' | 'rumus';

export interface Option {
    id: number;
    name: string;
}

export interface ComponentValue {
    id: number;
    kategori: string | null;
    employment_status: string | null;
    position: string | null;
    job_level: string | null;
    branch: string | null;
    value: number;
    note: string | null;
}

export interface Component {
    id: number;
    code: string | null;
    name: string;
    group: string;
    category: Category;
    calc_type: CalcType;
    is_taxable: boolean;
    /** Counts toward the BPJS contribution base. */
    is_bpjs_base: boolean;
    show_on_slip: boolean;
    calc_basis: string;
    period_basis: string | null;
    formula: string | null;
    basis_type: string | null;
    basis_value: number | null;
    basis_min: number | null;
    basis_max: number | null;
    basis_cut_off_day: number | null;
    payroll_formula_id: number | null;
    values: ComponentValue[];
    status: string;
}

export interface FormulaItem {
    id: number;
    tipe: string;
    component: string | null;
    component_id: number | null;
    operator: string;
    nilai: number;
    prorate: boolean;
}

export interface Formula {
    id: number;
    name: string;
    note: string | null;
    is_active: boolean;
    items: FormulaItem[];
}

export interface TaxBpjsRow {
    id: number;
    code: string;
    name: string;
    category: Category;
    is_active: boolean;
}

export interface Kpis {
    pendapatan: number;
    pendapatan_active: number;
    potongan: number;
    potongan_active: number;
    pajak_bpjs: number;
}
