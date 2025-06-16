<?php

use App\Models\User;

it('can render login screen', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

it('allows users to authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect(auth()->check())->toBeTrue();
    $response->assertRedirect(route('home', absolute: false));
});

it('prevents users from authenticating with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(auth()->guest())->toBeTrue();
});

it('allows users to logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    expect(auth()->guest())->toBeTrue();
    $response->assertRedirect('/');
});

it('implements login rate limitation', function ($falseAttempts, $canLogin) {
    $user = User::factory()->create();

    for ($i = 0; $i < $falseAttempts; $i++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    if ($canLogin) {
        expect(auth()->check())->toBeTrue();
    } else {
        expect(auth()->guest())->toBeTrue();
    }

})->with([
    [5, false],
    [4, true],
]);
