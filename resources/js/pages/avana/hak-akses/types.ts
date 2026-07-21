/**
 * Shared types for the AvanaHR access-control (hak akses) page. These mirror
 * the `AccessController@index` payload (role cards, actions, matrix modules,
 * and the per-action matrix).
 */

export type { FlashProps } from '../employees/types';

/** A role card as serialized by `AccessController@index`. */
export interface AccessRole {
    id: number;
    name: string;
    code: string;
    desc: string;
    users: number;
    color: string;
    /** True when the current actor may not edit this role (self-lockout / super_admin). */
    locked: boolean;
}

/** A single togglable action (view/create/update/archive/export/approve). */
export interface AccessAction {
    key: string;
    label: string;
}

/** A matrix row: a UI menu group. `actionable` false = always-on row (Dashboard). */
export interface AccessModule {
    key: string;
    label: string;
    /** Section title this row belongs to (e.g. "TALENTA", "SISTEM"). */
    group: string;
    actionable: boolean;
    /** True when the menu maps to tenant features and its master switch is operable. */
    hasFeature: boolean;
    /** True when every feature the menu depends on is enabled for the tenant. */
    featureEnabled: boolean;
    /** Catalog feature id (null on fixed core rows) — drives inline edit/delete. */
    featureId: number | null;
    /** The feature's module_group (null on core rows) — for the edit modal. */
    moduleGroup: string | null;
    /** Permission prefixes the row owns — for the edit modal prefill. */
    permissionModules: string[];
}

/** Menu Builder tab payload — passed straight to the shared MenuBuilder page. */
export interface MenuBuilderData {
    tree: unknown[];
    parents: { id: number; label: string }[];
    sections: string[];
    features: { value: string; label: string }[];
    modules: string[];
    isSuperAdmin: boolean;
    selectedTenant: number;
    tenants: { id: number; name: string }[];
}

/** Per-action checked state for a menu/role pairing: `{ view: true, ... }`. */
export type MatrixCell = Record<string, boolean>;

export interface HakAksesProps {
    roles: AccessRole[];
    actions: AccessAction[];
    modules: AccessModule[];
    permHeaders: string[];
    /** matrix[moduleIdx][roleIdx][actionKey] */
    matrix: MatrixCell[][];
    isSuperAdmin: boolean;
    /** True when a concrete tenant is in context, so master switches operate. */
    hasTenant: boolean;
    /** Super-admin: may add/edit/delete features inline on the matrix. */
    canManageFeatures: boolean;
    /** module_group suggestions for the feature modal. */
    moduleGroups: string[];
    /** existing permission modules for the feature modal. */
    moduleOptions: string[];
    /** Super-admin: the "Struktur Menu" tab (Menu Builder) is available. */
    canManageMenu: boolean;
    /** Menu Builder tab data. */
    menu: MenuBuilderData;
}
