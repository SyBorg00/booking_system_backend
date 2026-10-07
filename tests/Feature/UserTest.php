<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    // Test that an unauthenticated user cannot fetch the list of users
    public function test_unauthenticated_user_cannot_fetch_users(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    // Test that a super admin can fetch the list of users
    public function test_super_admin_can_fetch_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'role',
                ],
            ],
        ]);
    }

    // Test that an admin can fetch the list of users
    public function test_admin_can_fetch_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
    }

    // Test that a staff user cannot fetch the list of users
    public function test_staff_cannot_fetch_users(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($staff, 'sanctum');

        $response = $this->getJson('/api/users');

        $response->assertStatus(403);
    }

    // Test that the user list does not expose sensitive fields like password and remember_token
    public function test_user_list_does_not_expose_sensitive_fields(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);

        $response->assertJsonMissing([
            'password' => $user->password,
        ]);

        $response->assertJsonMissing([
            'remember_token' => $user->remember_token,
        ]);
    }
}
