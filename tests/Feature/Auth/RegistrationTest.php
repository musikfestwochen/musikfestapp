<?php

it('can render registration screen', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

it('allows new users to register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(auth()->check())->toBeTrue();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});
