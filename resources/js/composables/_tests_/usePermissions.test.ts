import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePermissions } from '../usePermissions';

// Mock @inertiajs/vue3
const mockPage = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => mockPage(),
}));

describe('usePermissions', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('can method', () => {
        it('returns false when user has no permissions or auth is missing', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: [], global_permissions: [], roles: [] } } });
            const { can } = usePermissions();
            expect(can('admin.users.view')).toBe(false);

            mockPage.mockReturnValue({ props: {} });
            expect(usePermissions().can('admin.users.view')).toBe(false);
        });

        it('returns true for exact permission match in regular/global permissions', () => {
            mockPage.mockReturnValue({
                props: { auth: { permissions: ['admin.users.view'], global_permissions: ['global.backup.create'], roles: [] } },
            });
            const { can } = usePermissions();
            expect(can('admin.users.view')).toBe(true);
            expect(can('global.backup.create')).toBe(true);
            expect(can('admin.users.delete')).toBe(false);
        });

        it('handles wildcard and global permissions', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: ['admin.users.*', '*'], global_permissions: ['system.*'], roles: [] } } });
            const { can } = usePermissions();
            expect(can('admin.users.view')).toBe(true);
            expect(can('system.backup.create')).toBe(true);
            expect(can('any.random.permission')).toBe(true);
        });

        it('returns true if permissions contains only global wildcard', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: ['*'], global_permissions: [], roles: [] } } });
            const { can } = usePermissions();
            expect(can('any.permission')).toBe(true);
        });
        it('returns true if global_permissions contains only global wildcard', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: [], global_permissions: ['*'], roles: [] } } });
            const { can } = usePermissions();
            expect(can('any.permission')).toBe(true);
        });
        it('returns true if user is SuperAdmin and has no permissions', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: [], global_permissions: [], roles: ['SuperAdmin'] } } });
            const { can } = usePermissions();
            expect(can('any.permission')).toBe(true);
        });
    });

    describe('is method', () => {
        it('returns false when user has no roles or auth is missing', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: [], global_permissions: [], roles: [] } } });
            const { is } = usePermissions();
            expect(is('Admin')).toBe(false);
            mockPage.mockReturnValue({ props: {} });
            expect(usePermissions().is('Admin')).toBe(false);
        });

        it('returns true for exact role match and SuperAdmin', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: [], global_permissions: [], roles: ['Admin', 'SuperAdmin'] } } });
            const { is } = usePermissions();
            expect(is('Admin')).toBe(true);
            expect(is('SuperAdmin')).toBe(true);
            expect(is('User')).toBe(true); // SuperAdmin covers all
        });

        it('is case-sensitive for role names', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: [], global_permissions: [], roles: ['Admin'] } } });
            const { is } = usePermissions();
            expect(is('Admin')).toBe(true);
            expect(is('admin')).toBe(false);
        });
    });

    describe('integration tests', () => {
        it('works with both permissions and roles', () => {
            mockPage.mockReturnValue({
                props: { auth: { permissions: ['admin.users.*'], global_permissions: ['system.backup'], roles: ['Admin'] } },
            });
            const { can, is } = usePermissions();
            expect(can('admin.users.view')).toBe(true);
            expect(can('system.backup')).toBe(true);
            expect(can('blog.posts.create')).toBe(false);
            expect(is('Admin')).toBe(true);
            expect(is('User')).toBe(false);
        });
        it('handles undefined auth properties gracefully', () => {
            mockPage.mockReturnValue({ props: { auth: { permissions: undefined, global_permissions: undefined, roles: undefined } } });
            const { can, is } = usePermissions();
            expect(can('admin.users.view')).toBe(false);
            expect(is('Admin')).toBe(false);
        });
    });
});
