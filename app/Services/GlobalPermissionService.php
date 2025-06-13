<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class GlobalPermissionService
{
    // Cache duration in minutes
    private const CACHE_DURATION = 60;

    // Cache key prefix for global permissions
    private const CACHE_PREFIX = 'global_permission';

    public static function canGlobally(User $user, string $ability): ?bool
    {
        // If user is a super admin, always return true
        if ($user->hasRole('SuperAdmin')) {
            return true;
        }

        // Get all user permissions from cache
        $permissions = self::getUserGlobalPermissions($user);
        
        // Check if the user has the specific ability
        return in_array($ability, $permissions) ? true : null;
    }

    /**
     * Clear the permission cache for a specific user
     */
    public static function clearCache(int $userId, ?string $ability = null): void
    {
        // Since we're now storing all permissions in a single cache entry,
        // we'll clear the entire cache for the user regardless of the specific ability
        Cache::forget(self::getGlobalPermissionsCacheKey($userId));
    }

    /**
     * Generate a cache key for all global permissions of a user
     */
    public static function getGlobalPermissionsCacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.sprintf(':%d:permissions', $userId);
    }

    /**
     * Get all global permissions for a user.
     *
     * @param  \App\Models\User|null  $user
     */
    public static function getUserGlobalPermissions($user): array
    {
        if (! $user || ! $user->id) {
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
}
