/**
 * Shared types for the AvanaHR audit-trail page. These mirror the read-only
 * payload returned by `App\Http\Controllers\Avana\AuditController@index`.
 */

export type { FlashProps, PaginationMeta } from '../employees/types';

/** A single audit log row as serialized by `AuditController@changesData`. */
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

export type ActivityEvent =
    | 'login'
    | 'logout'
    | 'login_failed'
    | 'page_view'
    | 'data_created'
    | 'data_updated'
    | 'data_deleted';

/** A single row as serialized by `AuditController@activityData`. */
export interface ActivityRow {
    id: number;
    event: ActivityEvent;
    description: string | null;
    user: string | null;
    ip_address: string | null;
    path: string | null;
    created_at: string | null;
}

export interface TenantOption {
    id: number;
    name: string;
}

export type AuditTab = 'changes' | 'activity';

export interface AuditFilters {
    search?: string | null;
    action?: string | null;
    event?: string | null;
    tenant_id?: number | null;
    per_page?: string;
}

export interface AuditProps {
    tab: AuditTab;
    logs: {
        data: AuditRow[];
        meta: import('../employees/types').PaginationMeta;
    } | null;
    activity: {
        data: ActivityRow[];
        meta: import('../employees/types').PaginationMeta;
    } | null;
    tenants: TenantOption[];
    filters: AuditFilters;
}
