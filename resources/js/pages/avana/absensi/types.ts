/**
 * Shared types for the AvanaHR absensi module page. These mirror the
 * `AttendanceController@index` payload (attendances, filters, date, kpis,
 * branches).
 */

import type { PaginationMeta } from '../employees/types';

export type { FlashProps, PaginationMeta } from '../employees/types';

export interface AttendanceEmployee {
    id: number;
    name: string;
    employee_number: string;
    initials: string;
    avatar_color: string;
}

export interface AttendanceShift {
    id: number;
    name: string;
    label: string;
}

export interface LatLng {
    lat: number;
    lng: number;
}

export interface AttendanceWorkLocation {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
    radius_meter: number;
}

/** A single attendance row as serialized by `AttendanceResource`. */
export interface Attendance {
    id: number;
    employee: AttendanceEmployee | null;
    shift: AttendanceShift | null;
    date: string;
    date_raw: string | null;
    clock_in: string;
    clock_out: string;
    late_minutes: number;
    telat: string;
    status: string;
    status_label: string;
    location_status: string | null;
    clock_in_coords: LatLng | null;
    clock_out_coords: LatLng | null;
    work_location?: AttendanceWorkLocation | null;
    distance_meter: number | null;
}

export interface BranchOption {
    id: number;
    name: string;
}

export interface AbsensiFilters {
    search?: string;
    status?: string;
    branch_id?: string;
    sort?: string;
    direction?: string;
    per_page?: string;
    date: string;
}

export interface AbsensiProps {
    attendances: {
        data: Attendance[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    };
    filters: AbsensiFilters;
    date: { value: string; display: string };
    kpis: { hadir: number; terlambat: number; izin: number; alpa: number };
    branches: BranchOption[];
}
