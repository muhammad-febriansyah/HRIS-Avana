/**
 * Shared types for the AvanaHR dynamic report builder. These mirror the
 * `DynamicReportController` payloads. The builder is constrained to the
 * server-side allowlist of entities, columns and filters.
 */

export type { FlashProps } from '../employees/types';

/** A selectable column within an entity. */
export interface ColumnOption {
    key: string;
    label: string;
}

/** A `{ value, label }` option for an equals filter. */
export interface FilterChoice {
    value: string;
    label: string;
}

/** A configurable filter within an entity. */
export interface FilterOption {
    key: string;
    label: string;
    type: 'like' | 'equals';
    options: FilterChoice[];
}

/** An allowlisted reportable entity and its builder metadata. */
export interface EntityOption {
    key: string;
    label: string;
    columns: ColumnOption[];
    filters: FilterOption[];
}

/** A persisted report definition row. */
export interface SavedReportRow {
    id: number;
    name: string;
    entity: string;
    entity_label: string;
    columns: string[];
    column_labels: string[];
    filters: Record<string, string>;
    created_at: string | null;
}

/** Props for the builder page (`index.tsx`). */
export interface DynamicReportIndexProps {
    reports: SavedReportRow[];
    entities: EntityOption[];
}

/** Props for the report preview page (`show.tsx`). */
export interface ReportShowProps {
    report: SavedReportRow;
    headers: string[];
    rows: Array<Array<string | number | null>>;
    count: number;
}

/** The builder form payload posted to `store`. */
export interface BuilderFormData {
    name: string;
    entity: string;
    columns: string[];
    filters: Record<string, string>;
}
