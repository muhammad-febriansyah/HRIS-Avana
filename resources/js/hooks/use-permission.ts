import { usePage } from '@inertiajs/react';

/**
 * Slice of the shared `auth` prop this hook depends on. `permissions` is the
 * flat list of effective `{module}.{action}` codes for the current user
 * (see HandleInertiaRequests); a super admin resolves to every code.
 */
interface AuthPermissionProps {
    auth?: {
        isSuperAdmin?: boolean;
        permissions?: string[];
    };
    [key: string]: unknown;
}

/**
 * Action-level permission gate, mirroring the backend `access` Gate.
 *
 * Backward-compatible / fail-open: a super admin always passes, and before the
 * feature is rolled out (no `permissions` prop) every check passes so no button
 * disappears. Once permissions are shared, `can()` reflects the granted codes.
 *
 * @example
 * const { can } = usePermission();
 * {can('employee.update') && <EditButton />}
 */
export function usePermission() {
    const auth = usePage<AuthPermissionProps>().props.auth;
    const isSuperAdmin = auth?.isSuperAdmin ?? false;
    const permissions = auth?.permissions;

    const can = (code: string): boolean => {
        if (isSuperAdmin) {
            return true;
        }

        // Not yet rolled out for this user — allow, so the UI is unchanged.
        if (!permissions) {
            return true;
        }

        return permissions.includes(code);
    };

    const canAny = (...codes: string[]): boolean => codes.some(can);
    const canAll = (...codes: string[]): boolean => codes.every(can);

    return { can, canAny, canAll, isSuperAdmin };
}
