<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Business;
use App\Models\Staff;
use App\Models\User;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    /*
    |==========================================================================
    | FOUNDATIONAL BEHAVIOR TEST CASES
    |==========================================================================
    */

    // Unauthenticated users should not be able to view the staff API
    public function test_unauthenticated_user_cannot_view_staff(): void
    {

        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/staff"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // Authenticated users can access staff index from their assigned business
    public function test_user_can_view_staff_from_their_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'first_name' => 'Alex',
            'last_name' => 'Cartwright',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/staff"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $staff->id,
                'business_id' => $business->id,
            ]);
    }

    // Index() should only return staff list that belongs to that business ONLY
    public function test_staff_index_only_returns_staff_for_requested_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $businessOne = Business::factory()->create();
        $businessTwo = Business::factory()->create();

        $user->businesses()->attach($businessOne->id);

        $staffUserOne = User::factory()->create([
            'role' => 'staff',
        ]);

        $staffUserTwo = User::factory()->create([
            'role' => 'staff',
        ]);

        $staffOne = Staff::factory()->create([
            'user_id' => $staffUserOne->id,
            'business_id' => $businessOne->id,
        ]);

        $staffTwo = Staff::factory()->create([
            'user_id' => $staffUserTwo->id,
            'business_id' => $businessTwo->id,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$businessOne->id}/staff"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $data = $response->json('data');

        $staffIds = collect($data)
            ->pluck('id')
            ->all();

        $this->assertContains(
            $staffOne->id,
            $staffIds
        );

        $this->assertNotContains(
            $staffTwo->id,
            $staffIds
        );
    }

    // show() should only show the details of the selected staff member
    public function test_user_can_view_specific_staff_from_their_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'first_name' => 'Alex',
            'last_name' => 'Cartwright',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $staff->id,
                'business_id' => $business->id,
            ]);
    }

    // Users from one business cannot access use the show() to access another staff member from another business
    public function test_user_cannot_view_staff_from_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $businessOne = Business::factory()->create();
        $businessTwo = Business::factory()->create();

        $user->businesses()->attach($businessOne->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $businessTwo->id,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$businessOne->id}/staff/{$staff->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    /*
    |==========================================================================
    | CREATE & VALIDATION TEST CASES
    |==========================================================================
    */

    // user should be able to create a staff member from their respective business
    public function test_user_can_create_staff_for_their_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'first_name' => 'Alex',
            'last_name' => 'Cartwright',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $staffUser->id,
                'phone' => '09171234567',
                'position' => 'Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(201)
            ->assertJsonFragment([
                'user_id' => $staffUser->id,
                'business_id' => $business->id,
                'phone' => '09171234567',
                'position' => 'Beautician',
            ]);

        $this->assertDatabaseHas('staff', [
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
            'phone' => '09171234567',
            'position' => 'Beautician',
        ]);
    }

    // the created staff member should be assigned to the requested business assigned
    public function test_created_staff_is_assigned_to_requested_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $staffUser->id,
                'phone' => '09171234567',
                'position' => 'Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('staff', [
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);
    }

    // the API should require the user id to function properly
    public function test_create_staff_requires_user_id(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'phone' => '09171234567',
                'position' => 'Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // the API should require the specified position to function properly
    public function test_create_staff_requires_position(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $staffUser->id,
                'phone' => '09171234567',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }

    // the API should reject the creation of a staff member with no user id
    public function test_create_staff_rejects_nonexistent_user_id(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $this->actingAs($user, 'sanctum');

        $nonExistentUserId = User::max('id') + 1;

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $nonExistentUserId,
                'phone' => '09171234567',
                'position' => 'Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // the phone unit can be NULL in the creation process
    public function test_create_staff_allows_null_phone(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $staffUser->id,
                'phone' => null,
                'position' => 'Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('staff', [
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
            'phone' => null,
            'position' => 'Beautician',
        ]);
    }

    // invalid phone format should be rejected when creating a staff member
    public function test_create_staff_rejects_invalid_phone(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $staffUser->id,
                'phone' => 123456789,
                'position' => 'Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    // A position name that exceeds the max length should be rejected from creation
    public function test_create_staff_rejects_position_over_max_length(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $staffUser->id,
                'phone' => '09171234567',
                'position' => str_repeat('A', 101),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }

    /*
    |==========================================================================
    | UPDATE & VALIDATION TEST CASES
    |==========================================================================
    */

    // Users should be able to update a staff member from their respective business
    public function test_user_can_update_staff_from_their_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
            'phone' => '09171234567',
            'position' => 'Beautician',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->putJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}",
            [
                'phone' => '09181234567',
                'position' => 'Senior Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'phone' => '09181234567',
                'position' => 'Senior Beautician',
            ]);

        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'phone' => '09181234567',
            'position' => 'Senior Beautician',
        ]);
    }

    // Staff users should be able to update a staff member from their respective business
    public function test_staff_user_can_update_staff_from_their_business(): void
    {
        // Arrange
        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $business = Business::factory()->create();

        $targetUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $targetUser->id,
            'business_id' => $business->id,
            'position' => 'Beautician',
        ]);

        // Associate the authenticated staff user with the business.
        Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($staffUser, 'sanctum');

        // Act
        $response = $this->putJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}",
            [
                'position' => 'Senior Beautician',
            ]
        );

        // Assert
        $response->assertStatus(200);

        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'position' => 'Senior Beautician',
        ]);
    }

    // Users should be able to update a staff member from their respective business with partial updates
    public function test_staff_update_supports_partial_updates(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
            'phone' => '09171234567',
            'position' => 'Beautician',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->putJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}",
            [
                'position' => 'Senior Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'phone' => '09171234567',
            'position' => 'Senior Beautician',
        ]);
    }

    // Users should be able to set the phone number of a staff member to null during an update
    public function test_staff_phone_can_be_set_to_null(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
            'phone' => '09171234567',
            'position' => 'Beautician',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->putJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}",
            [
                'phone' => null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'phone' => null,
        ]);
    }

    // Users should not be able to update a staff member with a non-existent user_id
    public function test_staff_update_rejects_nonexistent_user_id(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $nonExistentUserId = User::max('id') + 1;

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->putJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}",
            [
                'user_id' => $nonExistentUserId,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // Users should not be able to update a staff member with an invalid phone format
    public function test_staff_update_rejects_invalid_phone(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->putJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}",
            [
                'phone' => 123456789,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    // Users should not be able to update a staff member with a position that exceeds the max length
    public function test_staff_update_rejects_position_over_max_length(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->putJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}",
            [
                'position' => str_repeat('A', 101),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }

    // Users should not be able to update a staff member from another business
    public function test_user_cannot_update_staff_from_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $businessOne = Business::factory()->create();
        $businessTwo = Business::factory()->create();

        $user->businesses()->attach($businessOne->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $businessTwo->id,
            'phone' => '09171234567',
            'position' => 'Beautician',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->putJson(
            "/api/businesses/{$businessOne->id}/staff/{$staff->id}",
            [
                'position' => 'Senior Beautician',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);

        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'business_id' => $businessTwo->id,
            'position' => 'Beautician',
        ]);
    }

    /*
    |==========================================================================
    | DELETE & AUTHORIZATION TEST CASES
    |==========================================================================
    */

    // Super admin should be able to create a staff member for any business
    public function test_super_admin_can_create_staff(): void
    {
        // Arrange
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($superAdmin, 'sanctum');

        // Act
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $staffUser->id,
                'phone' => '09171234567',
                'position' => 'Beautician',
            ]
        );

        // Assert
        $response
            ->assertStatus(201)
            ->assertJsonFragment([
                'user_id' => $staffUser->id,
                'business_id' => $business->id,
            ]);
    }

    // Staff users should not be able to create a staff member for any business
    public function test_staff_user_cannot_create_staff(): void
    {
        // Arrange
        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $business = Business::factory()->create();

        $newStaffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($staffUser, 'sanctum');

        // Act
        $response = $this->postJson(
            "/api/businesses/{$business->id}/staff",
            [
                'user_id' => $newStaffUser->id,
                'phone' => '09171234567',
                'position' => 'Beautician',
            ]
        );

        // Assert
        $response->assertStatus(403);
    }

    // Staff users should be able to delete a staff member from their respective business
    public function test_staff_user_can_delete_staff_from_their_business(): void
    {
        // Arrange
        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $business = Business::factory()->create();

        $targetUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $targetUser->id,
            'business_id' => $business->id,
        ]);

        Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($staffUser, 'sanctum');

        // Act
        $response = $this->deleteJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}"
        );

        // Assert
        $response->assertStatus(200);

        $this->assertSoftDeleted('staff', [
            'id' => $staff->id,
        ]);
    }

    // Admin users should not be able to delete a staff member for any business (NOT TESTED)
    public function test_admin_can_delete_staff_from_their_business(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $admin->businesses()->attach($business->id);

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($admin, 'sanctum');

        // Act
        $response = $this->deleteJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}"
        );

        // Assert
        $response->assertStatus(200);

        $this->assertSoftDeleted('staff', [
            'id' => $staff->id,
        ]);
    }

    // Super admin users should be able to delete a staff member for any business
    public function test_super_admin_can_delete_staff(): void
    {
        // Arrange
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($superAdmin, 'sanctum');

        // Act
        $response = $this->deleteJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}"
        );

        // Assert
        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Staff deleted successfully.',
            ]);

        $this->assertSoftDeleted('staff', [
            'id' => $staff->id,
        ]);
    }

    // Super admin users should be able to update a staff member for any business
    public function test_super_admin_can_update_staff_from_any_business(): void
    {
        // Arrange
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
            'position' => 'Beautician',
        ]);

        $this->actingAs($superAdmin, 'sanctum');

        // Act
        $response = $this->putJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}",
            [
                'position' => 'Senior Beautician',
            ]
        );

        // Assert
        $response->assertStatus(200);

        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'position' => 'Senior Beautician',
        ]);
    }

    // Super admin users should be able to delete a staff member for any business
    public function test_super_admin_can_delete_staff_from_any_business(): void
    {
        // Arrange
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($superAdmin, 'sanctum');

        // Act
        $response = $this->deleteJson(
            "/api/businesses/{$business->id}/staff/{$staff->id}"
        );

        // Assert
        $response->assertStatus(200);

        $this->assertSoftDeleted('staff', [
            'id' => $staff->id,
        ]);
    }
}
