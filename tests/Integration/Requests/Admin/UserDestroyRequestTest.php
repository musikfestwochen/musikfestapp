<?php

use App\Http\Requests\Admin\UserDestroyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(UserDestroyRequest::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->request = new UserDestroyRequest;
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can destroy users', function () {
    $user = User::factory()->globalAdmin()->create();
    $userToDestroy = User::factory()->create();

    $response = $this->actingAs($user)->call('DELETE', route('admin.users.destroy', ['user' => $userToDestroy->id]));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getContent())->not()->toContain('You cannot delete your own account.');
});

it('denies authorization to delete themself', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->call('DELETE', route('admin.users.destroy', ['user' => $user->id]));

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toContain('You cannot delete your own account.');
});
