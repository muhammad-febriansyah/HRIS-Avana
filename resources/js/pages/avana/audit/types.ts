/**
 * Shared types for the AvanaHR audit-trail page. These mirror the read-only
 * payload returned by `App\Http\Controllers\Avana\AuditController@index`.
 */

export type { FlashProps, PaginationMeta } from '../employees/types';

/** A single audit log row as serialized by `AuditController@index`. */
export interface AuditRow {
    id: number;
    action: 'created' | 'updated' | 'deleted';
    auditable_type: string;
    auditable_id: number;
    label: string;
    user: string | null;
    ip_address: string | null;
    changes: string[];
    created_at: string | null;
}

export interface AuditFilters {
    search?: string | null;
    action?: string | null;
    per_page?: string;
}

export interface AuditProps {
    logs: {
        data: AuditRow[];
        meta: import('../employees/types').PaginationMeta;
    };
    filters: AuditFilters;
}
