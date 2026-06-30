/**
 * Shared types, constants and pure helpers for the AvanaHR roster module.
 * These mirror the `RosterController@index` payload.
 */

export type { FlashProps, PaginationMeta } from '../employees/types';

/** An active employee row rendered as a roster grid row. */
export interface RosterEmployee {
    id: number;
    name: string;
    employee_number: string;
}

/** A selectable shift master row. */
export interface RosterShift {
    id: number;
    code: string | null;
    name: string;
    start_time: string | null;
    end_time: string | null;
}

/** A single employee/shift/date assignment cell. */
export interface RosterSchedule {
    id: number;
    employee_id: number;
    shift_id: number;
    date: string;
}

/** One column of the weekly grid. */
export interface RosterWeekDay {
    date: string;
    dow: string;
    day: number;
    label: string;
}

/** Props for the roster page (`index.tsx`). */
export interface RosterProps {
    employees: RosterEmployee[];
    shifts: RosterShift[];
    schedules: RosterSchedule[];
    week: RosterWeekDay[];
    week_start: string;
}

/** Deterministic chip palette assigned to shifts by their order. */
export const SHIFT_PALETTE = [
    '#2F54C9', '#16A34A', '#D97706', '#8b5cf6',
    '#0ea5e9', '#ec4899', '#14b8a6', '#f97316',
];

/** Format a Date as a local `Y-m-d` string (no UTC drift). */
export function toIso(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/** Two uppercase initials derived from a name. */
export function initialsOf(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean).slice(0, 2);

    return parts.map((part) => part.charAt(0).toUpperCase()).join('') || '?';
}
