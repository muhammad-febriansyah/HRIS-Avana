/**
 * Shared types for the face verification log page. These mirror the read-only
 * payload returned by `App\Http\Controllers\Avana\FaceScanLogController@index`.
 */

export type { PaginationMeta } from '../employees/types';

/** Diagnostics the device (or the server matcher) reported for one scan. */
export interface FaceScanMetrics {
    faces?: number;
    detector?: string;
    yaw?: number;
    roll?: number;
    left_eye_open?: number;
    right_eye_open?: number;
    smiling?: number;
    face_width_ratio?: number;
    center_x?: number;
    center_y?: number;
    frame_width?: number;
    frame_height?: number;
    embedding_dimensions?: number;
    score?: number;
    threshold?: number;
    fail_streak?: number;
    error?: string;
}

/** A single face scan attempt as serialized by `FaceScanLogController@index`. */
export interface FaceScanRow {
    id: number;
    context: 'enroll' | 'verify' | 'clock';
    outcome: 'ok' | 'fail' | 'blocked';
    reason: string;
    reason_label: string;
    message: string | null;
    step: number | null;
    metrics: FaceScanMetrics | null;
    employee: string | null;
    employee_number: string | null;
    platform: string | null;
    os_version: string | null;
    device_model: string | null;
    app_version: string | null;
    created_at: string | null;
}

export interface FaceScanFilters {
    search?: string | null;
    context?: string | null;
    outcome?: string | null;
    platform?: string | null;
    per_page?: string;
}

export interface FaceScanSummary {
    by_platform: Record<
        string,
        { ok: number; fail: number; blocked: number }
    >;
    top_reasons: { reason: string; label: string; count: number }[];
}

export interface FaceScanLogProps {
    logs: {
        data: FaceScanRow[];
        meta: import('../employees/types').PaginationMeta;
    };
    filters: FaceScanFilters;
    summary: FaceScanSummary;
}
