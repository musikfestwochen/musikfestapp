<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserTableTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_renders_users_table_correctly(): void
    {
        // Create a user with admin permissions
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->givePermissionTo('users:index');

        // Create some test users
        User::factory()->count(5)->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertInertia(fn (AssertableInertia $page): \Illuminate\Testing\Fluent\AssertableJson => $page
                ->component('admin/AdminUsers')
                ->has('users')
                ->has('users.data', 6) // 5 created users + the admin
                ->has('users.meta')
                ->has('users.links')
            );
    }
}
