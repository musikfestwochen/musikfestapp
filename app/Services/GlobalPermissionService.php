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

        // Create a unique cache key based on user ID and ability
        $cacheKey = self::getCacheKey($user->id, $ability);

        // Try to get the result from cache
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($user, $ability) {
            // Store the old permissions organization ID
            $oldPermissionsOrgId = getPermissionsOrgId();

            // Temporarily set the global organization ID
            if ($oldPermissionsOrgId !== GLOBAL_ORG_ID) {
                setPermissionsOrgId(GLOBAL_ORG_ID);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }

            // Check if the user can perform the ability globally
            $globalPermissionService = app(GlobalPermissionService::class);
            $result = $globalPermissionService->checkGlobalPermission($ability, $user);

            // Reset the permissions organization ID to its original value
            if ($oldPermissionsOrgId !== GLOBAL_ORG_ID) {
                setPermissionsOrgId($oldPermissionsOrgId);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }

            // Return the result or null to continue checking
            return $result ?: null;
        });
    }

    /**
     * Generate a cache key for a user and ability
     */
    public static function getCacheKey(int $userId, string $ability): string
    {
        return self::CACHE_PREFIX.sprintf(':%d:%s', $userId, $ability);
    }

    /**
     * Clear the permission cache for a specific user and ability
     */
    public static function clearCache(int $userId, ?string $ability = null): void
    {
        if ($ability) {
            // Clear cache for specific ability
            Cache::forget(self::getCacheKey($userId, $ability));

            // Also clear the cache for all global permissions as they might be affected
            Cache::forget(self::getGlobalPermissionsCacheKey($userId));
        } else {
            // Clear all permission caches for this user
            // This uses a cache tag-based approach which requires Redis or Memcached
            // For simpler drivers like file or database, we'd need a different approach
            $pattern = self::CACHE_PREFIX.sprintf(':%d:*', $userId);

            // Get cache driver
            $driver = Cache::getDefaultDriver();

            // For Redis or Memcached, we can use pattern-based deletion
            if (in_array($driver, ['redis', 'memcached'])) {
                // This is a simplified approach - actual implementation would depend on the specific driver
                $keys = Cache::getRedis()->keys($pattern);
                foreach ($keys as $key) {
                    Cache::forget($key);
                }
            } else {
                // For other drivers, we can't easily clear by pattern, so we clear the known keys
                Cache::forget(self::getGlobalPermissionsCacheKey($userId));
            }
        }
    }

    /**
     * Generate a cache key for all global permissions of a user
     */
    public static function getGlobalPermissionsCacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.sprintf(':%d:global_all', $userId);
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
