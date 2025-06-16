<?php

use App\Models\User;
use App\Services\GlobalPermissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Spatie\Permission\PermissionRegistrar;

covers(GlobalPermissionService::class);

// Constants for test data
const TEST_USER_ID = 123;
const TEST_USER_ID_2 = 456;
const TEST_ORG_ID = 5;
const CACHE_DURATION = 60;

beforeEach(function () {
    // Ensure GLOBAL_ORG_ID is defined
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    // Mock Cache facade for all tests
    Cache::shouldReceive('flush')->byDefault();
    Cache::shouldReceive('forget')->byDefault();
    Cache::shouldReceive('has')->andReturn(false)->byDefault();
    Cache::shouldReceive('get')->andReturn(null)->byDefault();
    Cache::shouldReceive('put')->byDefault();
    Cache::shouldReceive('remember')->byDefault();

    // Mock PermissionRegistrar for helper functions
    $this->permissionRegistrar = \Mockery::mock(PermissionRegistrar::class);
    $this->app->instance(PermissionRegistrar::class, $this->permissionRegistrar);

    $this->permissionRegistrar->shouldReceive('setPermissionsTeamId')->byDefault();
    $this->permissionRegistrar->shouldReceive('getPermissionsTeamId')->andReturn(GLOBAL_ORG_ID)->byDefault();
});

// Helper functions for common mock setups
function createMockUser(int $userId = TEST_USER_ID): MockInterface
{
    $user = \Mockery::mock(User::class);
    $user->shouldReceive('getAttribute')
        ->with('id')
        ->andReturn($userId);

    return $user;
}

function createMockUserWithoutId(): MockInterface
{
    $user = \Mockery::mock(User::class);
    $user->shouldReceive('getAttribute')
        ->with('id')
        ->andReturn(null);

    return $user;
}

function mockUserRole(MockInterface $user, string $role, bool $hasRole): void
{
    $user->shouldReceive('hasRole')
        ->with($role)
        ->once()
        ->andReturn($hasRole);
}

function mockCacheRemember(string $cacheKey, array $returnValue): void
{
    Cache::shouldReceive('remember')
        ->with($cacheKey, CACHE_DURATION, \Mockery::type('Closure'))
        ->once()
        ->andReturn($returnValue);
}

function mockCacheRememberWithClosure(string $cacheKey): void
{
    Cache::shouldReceive('remember')
        ->with($cacheKey, CACHE_DURATION, \Mockery::type('Closure'))
        ->once()
        ->andReturnUsing(function ($key, $duration, $closure) {
            return $closure();
        });
}

function mockCacheForget(string $cacheKey): void
{
    Cache::shouldReceive('forget')
        ->with($cacheKey)
        ->once()
        ->andReturn(true);
}

function createPermissionCollectionMock(array $permissions): array
{
    $permissionCollection = \Mockery::mock(Collection::class);
    $pluckedCollection = \Mockery::mock(Collection::class);

    $permissionCollection->shouldReceive('pluck')
        ->with('name')
        ->once()
        ->andReturn($pluckedCollection);

    $pluckedCollection->shouldReceive('toArray')
        ->once()
        ->andReturn($permissions);

    return [$permissionCollection, $pluckedCollection];
}

function mockUserPermissions(MockInterface $user, MockInterface $permissionCollection, bool $contextSwitch = true): void
{
    $user->shouldReceive('getAllPermissions')
        ->once()
        ->andReturn($permissionCollection);

    if ($contextSwitch) {
        $user->shouldReceive('unsetRelation')
            ->with('roles')
            ->twice()
            ->andReturnSelf();
        $user->shouldReceive('unsetRelation')
            ->with('permissions')
            ->twice()
            ->andReturnSelf();
    }
}

function mockContextSwitching(MockInterface $permissionRegistrar, int $currentOrgId, int $targetOrgId = GLOBAL_ORG_ID): void
{
    $permissionRegistrar->shouldReceive('getPermissionsTeamId')
        ->once()
        ->andReturn($currentOrgId);

    if ($currentOrgId !== $targetOrgId) {
        $permissionRegistrar->shouldReceive('setPermissionsTeamId')
            ->with($targetOrgId)
            ->once();
        $permissionRegistrar->shouldReceive('setPermissionsTeamId')
            ->with($currentOrgId)
            ->once();
    }
}

function getCacheKey(int $userId): string
{
    return sprintf('global_permission:%d:permissions', $userId);
}

describe('canGlobally', function () {
    it('returns true for super admin users regardless of permissions', function () {
        $user = \Mockery::mock(User::class);
        mockUserRole($user, 'SuperAdmin', true);

        $result = GlobalPermissionService::canGlobally($user, 'any-ability');

        expect($result)->toBeTrue();
    });

    it('super admin users bypass permission checking entirely', function () {
        $user = \Mockery::mock(User::class);
        mockUserRole($user, 'SuperAdmin', true);

        // SuperAdmin should NOT call getAttribute (which would be needed for getUserGlobalPermissions)
        // If the early return is removed, this would fail because getAttribute would be called
        $user->shouldNotReceive('getAttribute');

        // Cache should not be accessed for SuperAdmins
        Cache::shouldNotReceive('remember');

        $result = GlobalPermissionService::canGlobally($user, 'any-ability');

        expect($result)->toBeTrue();
    });

    it('returns exactly true (not truthy) for super admin users', function () {
        $user = \Mockery::mock(User::class);
        mockUserRole($user, 'SuperAdmin', true);

        $result = GlobalPermissionService::canGlobally($user, 'any-ability');

        // This test specifically checks that we get boolean true, not just a truthy value
        expect($result)->toBe(true);
        expect($result)->not->toBe(false);
        expect($result)->not->toBeNull();
    });

    it('returns true when user has the specific global permission', function () {
        $user = createMockUser();
        mockUserRole($user, 'SuperAdmin', false);
        mockCacheRemember(getCacheKey(TEST_USER_ID), ['test-permission', 'other-permission']);

        $result = GlobalPermissionService::canGlobally($user, 'test-permission');

        expect($result)->toBeTrue();
    });

    it('returns null when user does not have the specific global permission', function () {
        $user = createMockUser();
        mockUserRole($user, 'SuperAdmin', false);
        mockCacheRemember(getCacheKey(TEST_USER_ID), ['other-permission', 'another-permission']);

        $result = GlobalPermissionService::canGlobally($user, 'test-permission');

        expect($result)->toBeNull();
    });

    it('returns null when user has no global permissions', function () {
        $user = createMockUser();
        mockUserRole($user, 'SuperAdmin', false);
        mockCacheRemember(getCacheKey(TEST_USER_ID), []);

        $result = GlobalPermissionService::canGlobally($user, 'test-permission');

        expect($result)->toBeNull();
    });

    it('returns null for users without SuperAdmin role and without specific permission', function () {
        $user = createMockUser();
        mockUserRole($user, 'SuperAdmin', false);
        mockCacheRemember(getCacheKey(TEST_USER_ID), []);

        $result = GlobalPermissionService::canGlobally($user, 'any-ability');

        expect($result)->toBeNull();
    });
});

describe('clearCache', function () {
    it('clears the cache for a specific user', function () {
        mockCacheForget(getCacheKey(TEST_USER_ID));

        GlobalPermissionService::clearCache(TEST_USER_ID);
    });

    it('clears cache even when ability parameter is provided', function () {
        mockCacheForget(getCacheKey(TEST_USER_ID_2));

        GlobalPermissionService::clearCache(TEST_USER_ID_2, 'specific-ability');
    });
});

describe('getGlobalPermissionsCacheKey', function () {
    it('generates correct cache key format', function () {
        $result = GlobalPermissionService::getGlobalPermissionsCacheKey(TEST_USER_ID);

        expect($result)->toBe(getCacheKey(TEST_USER_ID));
    });

    it('handles different user IDs correctly', function () {
        $key1 = GlobalPermissionService::getGlobalPermissionsCacheKey(1);
        $key2 = GlobalPermissionService::getGlobalPermissionsCacheKey(999);

        expect($key1)->toBe(getCacheKey(1));
        expect($key2)->toBe(getCacheKey(999));
        expect($key1)->not->toBe($key2);
    });
});

describe('getUserGlobalPermissions', function () {
    it('returns empty array for null user', function () {
        $result = GlobalPermissionService::getUserGlobalPermissions(null);

        expect($result)->toBe([]);
    });

    it('returns empty array for user without ID', function () {
        $user = createMockUserWithoutId();

        $result = GlobalPermissionService::getUserGlobalPermissions($user);

        expect($result)->toBe([]);
    });

    it('returns cached permissions when available', function () {
        $user = createMockUser();
        $cachedPermissions = ['cached-permission-1', 'cached-permission-2'];
        mockCacheRemember(getCacheKey(TEST_USER_ID), $cachedPermissions);

        $result = GlobalPermissionService::getUserGlobalPermissions($user);

        expect($result)->toBe($cachedPermissions);
    });

    it('fetches and caches permissions when not in cache', function () {
        $user = createMockUser();
        $permissions = ['global-permission-1', 'global-permission-2'];

        [$permissionCollection, $pluckedCollection] = createPermissionCollectionMock($permissions);
        mockUserPermissions($user, $permissionCollection, true);
        mockContextSwitching($this->permissionRegistrar, TEST_ORG_ID, GLOBAL_ORG_ID);
        mockCacheRememberWithClosure(getCacheKey(TEST_USER_ID));

        $result = GlobalPermissionService::getUserGlobalPermissions($user);

        expect($result)->toBe($permissions);
    });

    it('preserves original organization context after fetching global permissions', function () {
        $user = createMockUser();
        $permissions = ['test-permission'];

        [$permissionCollection, $pluckedCollection] = createPermissionCollectionMock($permissions);
        mockUserPermissions($user, $permissionCollection, true);
        mockContextSwitching($this->permissionRegistrar, TEST_ORG_ID, GLOBAL_ORG_ID);
        mockCacheRememberWithClosure(getCacheKey(TEST_USER_ID));

        GlobalPermissionService::getUserGlobalPermissions($user);
    });

    it('handles users with no global permissions', function () {
        $user = createMockUser();

        [$permissionCollection, $pluckedCollection] = createPermissionCollectionMock([]);
        mockUserPermissions($user, $permissionCollection, true);
        mockContextSwitching($this->permissionRegistrar, TEST_ORG_ID, GLOBAL_ORG_ID);
        mockCacheRememberWithClosure(getCacheKey(TEST_USER_ID));

        $result = GlobalPermissionService::getUserGlobalPermissions($user);

        expect($result)->toBe([]);
    });

    it('does not change context when already in global organization', function () {
        $user = createMockUser();
        $permissions = ['test-permission'];

        [$permissionCollection, $pluckedCollection] = createPermissionCollectionMock($permissions);
        mockUserPermissions($user, $permissionCollection, false); // No context switch
        mockContextSwitching($this->permissionRegistrar, GLOBAL_ORG_ID, GLOBAL_ORG_ID);
        mockCacheRememberWithClosure(getCacheKey(TEST_USER_ID));

        $result = GlobalPermissionService::getUserGlobalPermissions($user);

        expect($result)->toBe($permissions);
    });
});
