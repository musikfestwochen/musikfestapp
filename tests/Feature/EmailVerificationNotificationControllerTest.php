<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('sends a new email verification notification', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post(route('verification.send'));

    Notification::assertSentTo($user, VerifyEmail::class);
    $response->assertRedirect();
    $response->assertSessionHas('status', 'verification-link-sent');
});

it('redirects to dashboard if email is already verified', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('verification.send'));

    Notification::assertNothingSent();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});
