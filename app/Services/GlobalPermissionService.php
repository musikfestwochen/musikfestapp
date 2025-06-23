<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class GlobalPermissionService
{
    /**
     * Cache duration in minutes.
     *
     * @pest-mutate-ignore
     */
    private const int CACHE_DURATION = 60;

    // Cache key prefix for global permissions
    private const string CACHE_PREFIX = 'global_permission';

    // Cache key for super admin status
    private const string SUPER_ADMIN_CACHE_KEY = 'global_permission:%d:super_admin';

    public static function canGlobally(User $user, string $ability): ?bool
    {
        // Check super admin status using cache remember
        $superAdminCacheKey = sprintf(self::SUPER_ADMIN_CACHE_KEY, $user->id);
        $isSuperAdmin = Cache::remember($superAdminCacheKey, self::CACHE_DURATION, function () use ($user): bool {
            $oldPermissionsOrgId = getPermissionsOrgId();
            setPermissionsOrgId(GLOBAL_ORG_ID);
            $result = $user->roles()->where('name', 'SuperAdmin')->exists();
            setPermissionsOrgId($oldPermissionsOrgId);

            return $result;
        });
        if ($isSuperAdmin) {
            return true;
        }

        // Get all user permissions from cache
        $permissions = self::getUserGlobalPermissions($user);

        // Check if the user has the specific ability, supporting wildcards
        if (array_any($permissions, fn (string $permission): bool => fnmatch($permission, $ability) || fnmatch($ability, $permission))) {
            return true;
        }

        return null;
    }

    /**
     * Get all global permissions for a user.
     *
     * @return array<string> List of permission names
     */
    public static function getUserGlobalPermissions(?User $user): array
    {
        if (! $user instanceof User || ! $user->id) {
            return [];
        }

        // Create a unique cache key for this user's global permissions
        $cacheKey = self::getGlobalPermissionsCacheKey($user->id);

        // Try to get the result from cache
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($user) {
            // Store the current organization ID
            $currentOrgId = getPermissionsOrgId();

            // Temporarily set the global organization ID
            if ($currentOrgId !== GLOBAL_ORG_ID) {
                setPermissionsOrgId(GLOBAL_ORG_ID);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }

            // Get all permissions in the global context
            $globalPermissions = $user->getAllPermissions()->pluck('name')->toArray();

            // Reset the organization ID to its original value
            if ($currentOrgId !== GLOBAL_ORG_ID) {
                setPermissionsOrgId($currentOrgId);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }

            return $globalPermissions;
        });
    }

    /**
     * Generate a cache key for all global permissions of a user
     */
    public static function getGlobalPermissionsCacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.sprintf(':%d:permissions', $userId);
    }

    /**
     * Clear the permission cache for a specific user
     */
    public static function clearCache(int $userId): void
    {
        Cache::forget(self::getGlobalPermissionsCacheKey($userId));
        Cache::forget(sprintf(self::SUPER_ADMIN_CACHE_KEY, $userId));
    }
}
