/**
 * Shared types and entity configuration for the AvanaHR company setup
 * (perusahaan) module. These mirror the `CompanySetupController@index`
 * payload — a tabbed, multi-entity master CRUD (branches, departments,
 * positions, job levels, work locations, shifts).
 */

import type { NamedOption } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

export type Cell = string | number | boolean | null;

/** A loosely-typed master row; columns/fields index by key. */
export interface EntityRecord {
    id: number;
    [key: string]: Cell | number[] | undefined;
}

/** Relation option lists shared by the entity modals. */
export interface SetupOptions {
    departments: NamedOption[];
    branches: NamedOption[];
}

/** Props for the company setup page (`index.tsx`). */
export interface PerusahaanProps {
    branches: EntityRecord[];
    departments: EntityRecord[];
    positions: EntityRecord[];
    jobLevels: EntityRecord[];
    workLocations: EntityRecord[];
    shifts: EntityRecord[];
    options: SetupOptions;
}

/** A table column descriptor for an entity tab. */
export interface ColumnDef {
    header: string;
    key: string;
    kind?: 'status' | 'time';
    align?: 'left' | 'right';
}

/** A modal form field descriptor for an entity tab. */
export interface FieldDef {
    name: string;
    label: string;
    type: 'text' | 'number' | 'time' | 'textarea' | 'select';
    required?: boolean;
    span?: 'half' | 'full';
    default?: string;
    placeholder?: string;
    /** Static `{ value, label }` options for a plain select. */
    options?: { value: string; label: string }[];
    /** Pull `{ id, name }` options from `props.options` for a FK select. */
    optionsKey?: keyof SetupOptions;
}

/** Full configuration for one tab/entity. */
export interface TabDef {
    key: string;
    label: string;
    icon: string;
    emptyText: string;
    columns: ColumnDef[];
    fields: FieldDef[];
}

export const STATUS_OPTIONS = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

export const TABS: TabDef[] = [
    {
        key: 'branches',
        label: 'Cabang',
        icon: 'building-2',
        emptyText: 'Belum ada cabang.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Telepon', key: 'phone' },
            { header: 'Status', key: 'status', kind: 'status' },
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
                label: 'Nama Cabang',
                type: 'text',
                required: true,
                span: 'half',
            },
            { name: 'phone', label: 'Telepon', type: 'text', span: 'half' },
            {
                name: 'timezone',
                label: 'Zona Waktu',
                type: 'text',
                default: 'Asia/Jakarta',
                span: 'half',
            },
            {
                name: 'address',
                label: 'Alamat',
                type: 'textarea',
                span: 'full',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'full',
            },
        ],
    },
    {
        key: 'departments',
        label: 'Departemen',
        icon: 'network',
        emptyText: 'Belum ada departemen.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Status', key: 'status', kind: 'status' },
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
                label: 'Nama Departemen',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'parent_id',
                label: 'Departemen Induk',
                type: 'select',
                optionsKey: 'departments',
                span: 'full',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'full',
            },
        ],
    },
    {
        key: 'positions',
        label: 'Jabatan',
        icon: 'briefcase',
        emptyText: 'Belum ada jabatan.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Departemen', key: 'department_name' },
            { header: 'Status', key: 'status', kind: 'status' },
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
                label: 'Nama Jabatan',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'department_id',
                label: 'Departemen',
                type: 'select',
                optionsKey: 'departments',
                span: 'full',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'full',
            },
        ],
    },
    {
        key: 'job-levels',
        label: 'Jenjang Jabatan',
        icon: 'layers',
        emptyText: 'Belum ada jenjang jabatan.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Urutan', key: 'level_order' },
            { header: 'Status', key: 'status', kind: 'status' },
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
                label: 'Nama Jenjang',
                type: 'text',
                required: true,
                span: 'half',
            },
            {
                name: 'level_order',
                label: 'Urutan',
                type: 'number',
                default: '0',
                span: 'half',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'half',
            },
        ],
    },
    {
        key: 'work-locations',
        label: 'Lokasi Kerja',
        icon: 'map-pin',
        emptyText: 'Belum ada lokasi kerja.',
        columns: [
            { header: 'Kode', key: 'code' },
            { header: 'Nama', key: 'name' },
            { header: 'Cabang', key: 'branch_name' },
            { header: 'Radius (m)', key: 'radius_meter' },
            { header: 'Status', key: 'status', kind: 'status' },
        ],
        fields: [
            {
                name: 'name',
                label: 'Nama Lokasi',
                type: 'text',
                required: true,
                span: 'half',
            },
            { name: 'code', label: 'Kode', type: 'text', span: 'half' },
            {
                name: 'branch_id',
                label: 'Cabang',
                type: 'select',
                optionsKey: 'branches',
                span: 'full',
            },
            {
                name: 'latitude',
                label: 'Latitude',
                type: 'text',
                placeholder: '-6.2146',
                span: 'half',
            },
            {
                name: 'longitude',
                label: 'Longitude',
                type: 'text',
                placeholder: '106.8451',
                span: 'half',
            },
            {
                name: 'radius_meter',
                label: 'Radius (meter)',
                type: 'number',
                default: '100',
                span: 'half',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'half',
            },
            {
                name: 'address',
                label: 'Alamat',
                type: 'textarea',
                span: 'full',
            },
        ],
    },
    {
        key: 'shifts',
        label: 'Shift',
        icon: 'clock',
        emptyText: 'Belum ada shift.',
        columns: [
            { header: 'Nama', key: 'name' },
            { header: 'Jam Masuk', key: 'start_time', kind: 'time' },
            { header: 'Jam Keluar', key: 'end_time', kind: 'time' },
            { header: 'Toleransi', key: 'late_tolerance_minutes' },
            { header: 'Status', key: 'status', kind: 'status' },
        ],
        fields: [
            {
                name: 'name',
                label: 'Nama Shift',
                type: 'text',
                required: true,
                span: 'half',
            },
            { name: 'code', label: 'Kode', type: 'text', span: 'half' },
            {
                name: 'start_time',
                label: 'Jam Masuk',
                type: 'time',
                span: 'half',
            },
            {
                name: 'end_time',
                label: 'Jam Keluar',
                type: 'time',
                span: 'half',
            },
            {
                name: 'late_tolerance_minutes',
                label: 'Toleransi (menit)',
                type: 'number',
                default: '0',
                span: 'half',
            },
            {
                name: 'status',
                label: 'Status',
                type: 'select',
                default: 'active',
                options: STATUS_OPTIONS,
                span: 'half',
            },
        ],
    },
];

/** Build the initial flat form payload for a tab, optionally from a record. */
export function buildInitialForm(
    tab: TabDef,
    record: EntityRecord | null,
): Record<string, string> {
    const data: Record<string, string> = {};

    for (const field of tab.fields) {
        if (record) {
            const raw = record[field.name];
            let value = raw === null || raw === undefined ? '' : String(raw);

            if (field.type === 'time') {
                value = value.slice(0, 5);
            }

            data[field.name] = value;
        } else {
            data[field.name] = field.default ?? '';
        }
    }

    return data;
}
