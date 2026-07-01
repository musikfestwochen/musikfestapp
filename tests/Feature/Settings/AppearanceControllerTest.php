<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('AppearanceController', function () {
    it('updates eastereggs_activated for authenticated user', function () {
        $user = User::factory()->create([
            'eastereggs_activated' => true,
        ]);

        $this->actingAs($user);

        $response = $this->patch(route('appearance.update'), [
            'eastereggs_activated' => '0',
        ]);

        $response->assertRedirect();

        expect($user->refresh()->eastereggs_activated)->toBeFalse();
    });

    it('requires auth', function () {
        $response = $this->patch(route('appearance.update'), [
            'eastereggs_activated' => false,
        ]);

        $response->assertRedirect();
    });
});
