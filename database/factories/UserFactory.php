<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Randomly create verified and unverified users.
     */
    public function randomVerified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => fake()->boolean() ? now() : null,
        ]);
    }

    /**
     * Configure the factory.
     */
    public function withOrganizations(): static
    {
        return $this->afterCreating(function (User $user) {
            // 20% chance user has no organizations
            if (fake()->boolean(20)) {
                return;
            }

            // 80% chance user has 0-3 organizations
            $orgCount = fake()->numberBetween(0, 3);
            $orgIds = Organization::query()->inRandomOrder(1)->limit($orgCount)->pluck('id');
            $user->organizations()->attach($orgIds);
        });
    }

    public function globalAdmin(): static
    {
        return $this->afterCreating(function (User $user) {

            // Set the permissions organization ID to the global organization ID
            setPermissionsOrgId(GLOBAL_ORG_ID);
            $user->assignRole('Admin');
        });
    }

    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            // Set the permissions organization ID to the global organization ID
            setPermissionsOrgId(GLOBAL_ORG_ID);
            $user->assignRole('SuperAdmin');
        });
    }
}
