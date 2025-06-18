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
    
    /**
     * Creates a user with exactly one organization
     */
    public function withSingleOrganization(): static
    {
        return $this->afterCreating(function (User $user) {
            $organization = Organization::factory()->create();
            $user->organizations()->attach($organization->id);
        });
    }
    
    /**
     * Creates a user with multiple organizations (default is 2-3)
     */
    public function withMultipleOrganizations(int $min = 2, int $max = 3): static
    {
        return $this->afterCreating(function (User $user) use ($min, $max) {
            $orgCount = fake()->numberBetween($min, $max);
            $organizations = Organization::factory()->count($orgCount)->create();
            $user->organizations()->attach($organizations->pluck('id'));
        });
    }
    
    /**
     * Creates a user with OrganizationAdministrator role
     * 
     * @param Organization|null $organization A specific organization to attach to the user
     * @param array|null $organizations Array of organization IDs to attach to the user
     */
    public function organizationAdmin(Organization $organization = null, array $organizations = null): static
    {
        return $this->afterCreating(function (User $user) use ($organization, $organizations) {
            // Handle organization attachment
            if ($organization !== null) {
                $user->organizations()->attach($organization->id);
                setPermissionsOrgId($organization->id);
            } elseif ($organizations !== null) {
                $user->organizations()->attach($organizations);
                setPermissionsOrgId($organizations[0]);
            } else {
                // Create one if none provided
                $newOrg = Organization::factory()->create();
                $user->organizations()->attach($newOrg->id);
                setPermissionsOrgId($newOrg->id);
            }
            
            $user->assignRole('OrganizationAdministrator');
        });
    }
}
