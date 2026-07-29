/**
 * Shared types for the AvanaHR access-control (hak akses) page. These mirror
 * the `AccessController@index` payload (role cards, actions, matrix modules,
 * and the per-action matrix).
 */

export type { FlashProps } from '../employees/types';

/** One account holding a role. */
export interface RoleMember {
    id: number;
    name: string;
    email: string;
    position: string | null;
    status: string | null;
}

/** A role card as serialized by `AccessController@index`. */
export interface AccessRole {
    id: number;
    name: string;
    code: string;
    desc: string;
    users: number;
    color: string;
    isSystem: boolean;
    /** May its holders sign in to the Flutter app — separate from web access. */
    canAccessMobile: boolean;
    /** True when the current actor may not edit this role (self-lockout / super_admin). */
    locked: boolean;
    /** Who holds the role — answers "peran ini dipakai siapa?". */
    members: RoleMember[];
}

/** A single togglable action (view/create/update/archive/export/approve). */
export interface AccessAction {
    key: string;
    label: string;
}

/**
 * A matrix row = one real menu of the tenant's sidebar, in sidebar order.
 * `actionable` false = the menu has no permission module to grant (self-service
 * screens, Dashboard), so only its visibility can be set per role.
 */
export interface AccessModule {
    key: string;
    label: string;
    /** Section title this row belongs to (e.g. "TALENTA", "SISTEM"). */
    group: string;
    /** Collapsible parent this menu sits under, when nested. */
    parent: string | null;
    href: string | null;
    actionable: boolean;
    /** Permission prefixes the menu is gated by (`own` excluded). */
    permissionModules: string[];
    /** Tenant-wide on/off for this menu (Menu Builder's is_active). */
    menuActive: boolean;
    /** True for this very screen: turning it off would lock the admin out. */
    lockedActive: boolean;
    menuItemId: number | null;
    /** The package feature behind the menu, if any. */
    feature: string | null;
    featureLabel: string | null;
    hasFeature: boolean;
    /** False when the tenant's package does not include the menu's feature. */
    featureEnabled: boolean;
    /** True for self-service (ESS) menus — visibility is the only control. */
    selfService: boolean;
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

/**
 * State of one menu/role pairing: `visible` (does the role see the menu) plus
 * one entry per action, e.g. `{ visible: true, view: true, create: false }`.
 */
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
    /** The Flutter app's Menu Cepat tiles, in the order the phone shows them. */
    mobileMenu: MobileMenuTile[];
}

/** One shortcut on the mobile app's home screen. */
export interface MobileMenuTile {
    id: number;
    key: string;
    label: string;
    /** Iconsax name, as the Flutter app spells it. */
    icon: string;
    color: string;
    /** GetX route the tile opens. */
    route: string;
    /** Off hides the tile from the whole company, whatever the roles say. */
    isActive: boolean;
    /** Shown to each role, in the same order as `roles`. */
    visible: boolean[];
}
