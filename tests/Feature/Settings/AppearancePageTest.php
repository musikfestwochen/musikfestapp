<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

describe('Appearance settings page shares eastereggs_activated', function () {
    it('includes eastereggs_activated in Inertia props and reflects DB state', function () {
        $user = User::factory()->create([
            'eastereggs_activated' => false,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('appearance'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/Appearance')
            ->where('auth.user.eastereggs_activated', false)
        );
    });

    it('persists changes and shares updated value after patch + redirect', function () {
        $user = User::factory()->create([
            'eastereggs_activated' => false,
        ]);

        $this->actingAs($user);

        // Update to true
        $this->followingRedirects()
            ->patch(route('appearance.update'), [
                'eastereggs_activated' => true,
            ]);

        // DB reflects
        expect($user->refresh()->eastereggs_activated)->toBeTrue();

        // Inertia share reflects
        $response = $this->get(route('appearance'));
        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/Appearance')
            ->where('auth.user.eastereggs_activated', true)
        );
    });
});
