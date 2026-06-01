<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlaywrightTestSeeder extends Seeder
{
    /**
     * Seed the application's database for Playwright E2E tests.
     */
    public function run(): void
    {
        // Seed roles and permissions
        (new RolesAndPermissionsSeeder)->run();

        // 1. Super Admin (using factory helper)
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@e2e.test',
            'password' => Hash::make('superadminpassword'),
        ]);

        // 2. Organizations and users
        $organizations = [];
        foreach (range(1, 5) as $orgNum) {
            $org = Organization::factory()->create([
                'name' => 'Test Org '.$orgNum,
                'slug' => 'test-org-'.$orgNum,
            ]);
            $organizations[$orgNum] = $org;

            // Organization Administrator (using factory helper)
            User::factory()->organizationAdmin($org)->create([
                'name' => sprintf('Org%sAdmin', $orgNum),
                'email' => sprintf('org%sadmin@e2e.test', $orgNum),
                'password' => Hash::make(sprintf('org%sadminpassword', $orgNum)),
            ]);

            // 5 users per org (using factory helper for org assignment)
            foreach (range(1, 5) as $userNum) {
                User::factory()->create([
                    'name' => sprintf('Org%sUser%s', $orgNum, $userNum),
                    'email' => sprintf('org%suser%s@e2e.test', $orgNum, $userNum),
                    'password' => Hash::make(sprintf('org%suser%spassword', $orgNum, $userNum)),
                ])->organizations()->attach($org->id);
            }
        }

        // 3. Users with no organization
        foreach (range(1, 3) as $i) {
            User::factory()->create([
                'name' => 'NoOrgUser'.$i,
                'email' => sprintf('noorguser%s@e2e.test', $i),
                'password' => Hash::make(sprintf('noorguser%spassword', $i)),
            ]);
        }

        // 4. Users with multiple organizations (using factory helper)
        foreach (range(1, 2) as $i) {
            User::factory()->withMultipleOrganizations(2, 3)->create([
                'name' => 'MultiOrgUser'.$i,
                'email' => sprintf('multiuser%s@e2e.test', $i),
                'password' => Hash::make(sprintf('multiuser%spassword', $i)),
            ]);
        }

        // 5. Edge-case/random users
        // User who is org admin for multiple orgs
        $orgIds = array_map(fn (Organization|Collection $org) => $org->id, $organizations);
        User::factory()->organizationAdmin(null, [$orgIds[3], $orgIds[4]])->create([
            'name' => 'MultiOrgAdmin',
            'email' => 'multiorgadmin@e2e.test',
            'password' => Hash::make('multiorgadminpassword'),
        ]);

        // TODO: Add more edge cases as needed (e.g. deleted orgs, users with/without permissions, etc.)
    }
}
