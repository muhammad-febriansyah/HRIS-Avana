/**
 * Shared types, constants and pure helpers for the AvanaHR payroll-config
 * module. These mirror the `PayrollConfigController@index` payload.
 *
 * NOTE: bpjs_programs / bpjs_rates / pph21_ter_rates are GLOBAL master tables
 * (no tenant scope). Only the profile counts are tenant-scoped.
 */

import { rp } from '@/lib/avana';

export type { FlashProps, PaginationMeta } from '../employees/types';

type Numeric = string | number | null;

export interface BpjsRate {
    id: number;
    employee_rate: Numeric;
    company_rate: Numeric;
    max_wage: Numeric;
    min_wage: Numeric;
    risk_level: string | null;
    effective_start_date: string | null;
    effective_end_date: string | null;
    is_active: boolean;
}

export interface BpjsProgram {
    id: number;
    code: string;
    name: string;
    type: string | null;
    description: string | null;
    is_active: boolean;
    rates: BpjsRate[];
}

export interface TerRate {
    id: number;
    category: string;
    income_min: Numeric;
    income_max: Numeric;
    rate: Numeric;
    effective_start_date: string | null;
    effective_end_date: string | null;
    is_active: boolean;
}

export interface ProfileStats {
    bpjs_profiles: number;
    tax_profiles: number;
}

export interface PayrollConfigProps {
    programs: BpjsProgram[];
    terRates: TerRate[];
    profileStats: ProfileStats;
}

/** A flat record indexed by field/column key — feeds the table and modal. */
export type FlatRecord = Record<string, string | number | boolean | null> & {
    id: number;
};

/** A table column descriptor. */
export interface ColumnDef {
    header: string;
    key: string;
    /** Secondary key for the range renderer. */
    key2?: string;
    kind?: 'percent' | 'rupiah' | 'rupiah-range' | 'active' | 'date';
    align?: 'left' | 'right';
}

/** A modal form field descriptor. */
export interface FieldDef {
    name: string;
    label: string;
    type: 'text' | 'number' | 'date' | 'textarea' | 'select';
    required?: boolean;
    span?: 'half' | 'full';
    step?: string;
    placeholder?: string;
    hint?: string;
    options?: { value: string; label: string }[];
}

/** Full configuration for one tab/section. */
export interface SectionDef {
    key: 'bpjs' | 'pph21';
    label: string;
    title: string;
    icon: string;
    emptyText: string;
    columns: ColumnDef[];
    fields: FieldDef[];
    addLabel: string;
    entityLabel: string;
}

export const ACTIVE_OPTIONS = [
    { value: '1', label: 'Aktif' },
    { value: '0', label: 'Nonaktif' },
];

export const BPJS_TYPE_OPTIONS = [
    { value: 'kesehatan', label: 'Kesehatan' },
    { value: 'jht', label: 'JHT (Jaminan Hari Tua)' },
    { value: 'jp', label: 'JP (Jaminan Pensiun)' },
    { value: 'jkk', label: 'JKK (Kecelakaan Kerja)' },
    { value: 'jkm', label: 'JKM (Kematian)' },
    { value: 'ketenagakerjaan', label: 'Ketenagakerjaan' },
];

export const SECTIONS: SectionDef[] = [
    {
        key: 'bpjs',
        label: 'Program BPJS',
        title: 'Program BPJS',
        icon: 'shield-plus',
        emptyText: 'Belum ada program BPJS.',
        addLabel: 'Tambah Program',
        entityLabel: 'Program BPJS',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Jenis', key: 'type' },
            { header: 'Iuran Karyawan', key: 'employee_rate', kind: 'percent' },
            {
                header: 'Iuran Perusahaan',
                key: 'company_rate',
                kind: 'percent',
            },
            { header: 'Status', key: 'is_active', kind: 'active' },
        ],
        fields: [
            {
                name: 'code',
                label: 'Kode',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'name',
                label: 'Nama Program',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'type',
                label: 'Jenis',
                type: 'select',
                required: true,
                span: 'half',
                options: BPJS_TYPE_OPTIONS,
            },
            {
                name: 'effective_start_date',
                label: 'Tanggal Berlaku',
                type: 'date',
                required: true,
                span: 'half',
            },
            {
                name: 'employee_rate',
                label: 'Iuran Karyawan',
                type: 'number',
                required: true,
                span: 'half',
                step: '0.0001',
                hint: 'Desimal, 0.01 = 1%',
            },
            {
                name: 'company_rate',
                label: 'Iuran Perusahaan',
                type: 'number',
                required: true,
                span: 'half',
                step: '0.0001',
                hint: 'Desimal, 0.04 = 4%',
            },
            {
                name: 'min_wage',
                label: 'Upah Minimum',
                type: 'number',
                span: 'half',
                placeholder: '0',
            },
            {
                name: 'max_wage',
                label: 'Upah Maksimum',
                type: 'number',
                span: 'half',
                placeholder: '0',
            },
            {
                name: 'description',
                label: 'Deskripsi',
                type: 'textarea',
                span: 'full',
            },
            {
                name: 'is_active',
                label: 'Status',
                type: 'select',
                span: 'full',
                options: ACTIVE_OPTIONS,
            },
        ],
    },
    {
        key: 'pph21',
        label: 'Tarif PPh 21 (TER)',
        title: 'Tarif PPh 21 (TER)',
        icon: 'percent',
        emptyText: 'Belum ada tarif PPh 21.',
        addLabel: 'Tambah Tarif',
        entityLabel: 'Tarif PPh 21',
        columns: [
            { header: 'Kategori', key: 'category' },
            {
                header: 'Rentang Penghasilan',
                key: 'income_min',
                key2: 'income_max',
                kind: 'rupiah-range',
            },
            { header: 'Tarif', key: 'rate', kind: 'percent' },
            {
                header: 'Tanggal Berlaku',
                key: 'effective_start_date',
                kind: 'date',
            },
            { header: 'Status', key: 'is_active', kind: 'active' },
        ],
        fields: [
            {
                name: 'category',
                label: 'Kategori',
                type: 'text',
                required: true,
                span: 'half',
                placeholder: 'A / B / C',
            },
            {
                name: 'effective_start_date',
                label: 'Tanggal Berlaku',
                type: 'date',
                required: true,
                span: 'half',
            },
            {
                name: 'income_min',
                label: 'Penghasilan Minimum',
                type: 'number',
                required: true,
                span: 'half',
                placeholder: '0',
            },
            {
                name: 'income_max',
                label: 'Penghasilan Maksimum',
                type: 'number',
                span: 'half',
                placeholder: '0',
            },
            {
                name: 'rate',
                label: 'Tarif',
                type: 'number',
                required: true,
                span: 'half',
                step: '0.0001',
                hint: 'Desimal, 0.05 = 5%',
            },
            {
                name: 'is_active',
                label: 'Status',
                type: 'select',
                span: 'half',
                options: ACTIVE_OPTIONS,
            },
        ],
    },
];

/** Format a decimal fraction (0.01) as a percentage string (1%). */
export function asPercent(value: string | number | boolean | null): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const pct = Number(value) * 100;

    return `${pct.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

/** Format a numeric value as rupiah, or an em dash when empty. */
export function asRupiah(value: string | number | boolean | null): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return rp(Number(value));
}

/** Flatten a BPJS program (with its latest rate) into a single record. */
export function flattenProgram(program: BpjsProgram): FlatRecord {
    const rate = program.rates[0] ?? null;

    return {
        id: program.id,
        code: program.code,
        name: program.name,
        type: program.type,
        description: program.description,
        is_active: program.is_active,
        employee_rate: rate?.employee_rate ?? null,
        company_rate: rate?.company_rate ?? null,
        min_wage: rate?.min_wage ?? null,
        max_wage: rate?.max_wage ?? null,
        risk_level: rate?.risk_level ?? null,
        effective_start_date: rate?.effective_start_date ?? null,
    };
}

/** Flatten a PPh 21 TER rate into a single record. */
export function flattenTerRate(rate: TerRate): FlatRecord {
    return {
        id: rate.id,
        category: rate.category,
        income_min: rate.income_min,
        income_max: rate.income_max,
        rate: rate.rate,
        effective_start_date: rate.effective_start_date,
        is_active: rate.is_active,
    };
}

/** Build the initial flat string form payload for a section. */
export function buildInitialForm(
    section: SectionDef,
    record: FlatRecord | null,
): Record<string, string> {
    const data: Record<string, string> = {};

    for (const field of section.fields) {
        if (record) {
            const raw = record[field.name];

            if (field.name === 'is_active') {
                data[field.name] =
                    raw === true || raw === '1' || raw === 1 ? '1' : '0';
            } else if (raw === null || raw === undefined) {
                data[field.name] = '';
            } else {
                data[field.name] = String(raw);
            }
        } else {
            data[field.name] = field.name === 'is_active' ? '1' : '';
        }
    }

    return data;
}
