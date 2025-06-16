<?php

use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('verifies that the Gate::before callback (Super Admin)', function () {
    $user = User::factory()->create();

    setPermissionsOrgId(GLOBAL_ORG_ID);
    $user->assignRole('SuperAdmin');

    $result = Gate::forUser($user)->allows('anything'); // 'anything' triggers the before hook

    expect($result)->toBeTrue();
});
