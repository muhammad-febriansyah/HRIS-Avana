/**
 * Shared types for the AvanaHR access-control (hak akses) page. These mirror
 * the `AccessController@index` payload (role cards, matrix modules, matrix).
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
}

/** A matrix row module: a UI menu group keyed by `key`. */
export interface AccessModule {
    key: string;
    label: string;
}

export interface HakAksesProps {
    roles: AccessRole[];
    modules: AccessModule[];
    permHeaders: string[];
    matrix: boolean[][];
}
