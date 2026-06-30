/**
 * Shared types for the AvanaHR menu & fitur (feature toggle) page. These mirror
 * the `FeatureController@index` payload.
 */

export type { FlashProps } from '../employees/types';

/** A feature toggle row as serialized by `FeatureController@index`. */
export interface Feature {
    id: number;
    code: string;
    name: string;
    module_group: string | null;
    is_enabled: boolean;
}

/** Props for the menu & fitur page (`index.tsx`). */
export interface FiturIndexProps {
    features: Feature[];
    tenantName: string | null;
}
