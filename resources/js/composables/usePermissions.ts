import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();

    const can = (permission: string) => {
        // For guests, fallback to empty array
        const permissions = page.props.auth?.permissions || [];

        // Check for exact permission match
        if (permissions.includes(permission)) {
            return true;
        }

        // Check for wildcard permissions
        // Split the permission into parts (e.g., 'admin.mgmt.users.update' -> ['admin', 'mgmt', 'users', 'update'])
        const parts = permission.split('.');

        // Check for wildcard matches at different levels
        for (let i = parts.length - 1; i >= 0; i--) {
            // Create a wildcard permission at this level (e.g., 'admin.mgmt.users.*')
            const wildcardPermission = [...parts.slice(0, i), '*'].join('.');
            if (permissions.includes(wildcardPermission)) {
                return true;
            }
        }

        // Check for global wildcard permission or 'SuperAdmin' role
        return !!permissions.includes('*') || is('SuperAdmin');
    };

    const is = (role: string) => {
        const roles = page.props.auth?.roles || [];
        return roles.includes(role) || roles.includes('SuperAdmin');
    };

    return {
        can,
        is,
    };
}
