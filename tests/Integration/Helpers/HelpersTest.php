<?php

use Spatie\Permission\PermissionRegistrar;

it('defines GLOBAL_ORG_ID constant', function () {
    expect(defined('GLOBAL_ORG_ID'))->toBeTrue();
    expect(GLOBAL_ORG_ID)->toBe(0);
});

it('can set permissions org id with integer', function () {
    $testOrgId = 123;

    setPermissionsOrgId($testOrgId);

    expect(getPermissionsOrgId())->toBe($testOrgId);
});

it('can set permissions org id with string', function () {
    $testOrgId = 'test-org-456';

    setPermissionsOrgId($testOrgId);

    expect(getPermissionsOrgId())->toBe($testOrgId);
});

it('can set permissions org id with null', function () {
    setPermissionsOrgId(null);

    expect(getPermissionsOrgId())->toBeNull();
});

it('can set permissions org id with model', function () {
    $model = new class extends \Illuminate\Database\Eloquent\Model
    {
        protected $primaryKey = 'id';

        public function getKey()
        {
            return 789;
        }
    };

    setPermissionsOrgId($model);

    expect(getPermissionsOrgId())->toBe(789);
});

it('can get permissions org id', function () {
    // Set a known value first
    $testOrgId = 999;
    app(PermissionRegistrar::class)->setPermissionsTeamId($testOrgId);

    $result = getPermissionsOrgId();

    expect($result)->toBe($testOrgId);
});

it('setPermissionsOrgId function exists', function () {
    expect(function_exists('setPermissionsOrgId'))->toBeTrue();
});

it('getPermissionsOrgId function exists', function () {
    expect(function_exists('getPermissionsOrgId'))->toBeTrue();
});

it('setPermissionsOrgId calls PermissionRegistrar setPermissionsTeamId', function () {
    $mockRegistrar = Mockery::mock(PermissionRegistrar::class);
    $mockRegistrar->shouldReceive('setPermissionsTeamId')
        ->once()
        ->with(123);

    app()->instance(PermissionRegistrar::class, $mockRegistrar);

    setPermissionsOrgId(123);
});

it('getPermissionsOrgId calls PermissionRegistrar getPermissionsTeamId', function () {
    $mockRegistrar = Mockery::mock(PermissionRegistrar::class);
    $mockRegistrar->shouldReceive('getPermissionsTeamId')
        ->once()
        ->andReturn(456);

    app()->instance(PermissionRegistrar::class, $mockRegistrar);

    $result = getPermissionsOrgId();

    expect($result)->toBe(456);
});
