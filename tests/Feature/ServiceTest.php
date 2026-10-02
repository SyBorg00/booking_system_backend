<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    /*
    |==========================================================================
    | BASIC TEST CASE
    |==========================================================================
    */

    // Test that an unauthenticated user cannot view services for a business
    public function test_unauthenticated_user_cannot_view_services(): void
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
            "/api/businesses/{$business->id}/services"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // Test that a user can view the service list from their respective business
    public function test_user_can_view_services_from_their_business(): void
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

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/services"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $service->id,
        ]);
    }

    // Test that the index method only returns the service to that assigned business
    public function test_service_index_only_returns_services_for_requested_business(): void
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

        $serviceOne = Service::factory()->create([
            'business_id' => $businessOne->id,
        ]);

        $serviceTwo = Service::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$businessOne->id}/services"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $data = $response->json('data');

        $serviceIds = collect($data)
            ->pluck('id')
            ->all();

        $this->assertContains(
            $serviceOne->id,
            $serviceIds
        );

        $this->assertNotContains(
            $serviceTwo->id,
            $serviceIds
        );
    }

    // Test that the user can view a specific service through that assigned business
    public function test_user_can_view_specific_service_from_their_business(): void
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

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/services/{$service->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.id',
            $service->id
        );
    }

    // Test that users from their assigned business cannot view a specific service using a different business ID (403)
    public function test_user_cannot_view_services_from_another_business(): void
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

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$businessTwo->id}/services"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    // Test that users cannot view a specific service that belongs to another business (404)
    public function test_user_cannot_view_service_belonging_to_another_business(): void
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

        $service = Service::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$businessOne->id}/services/{$service->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    // Test that viewing a nonexistant service should return a 404 error
    public function test_viewing_nonexistent_service_returns_404(): void
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

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/services/999999"
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
    | CREATE & VALIDATION TEST CASE
    |==========================================================================
    */

    // Test that authenticated users can create a service for their assigned business
    public function test_user_can_create_service_for_their_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Haircut',
            'description' => 'Basic haircut service',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('services', [
            'business_id' => $business->id,
            'name' => 'Haircut',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ]);
    }

    // Test that a service created for a business is actually associated with that business in the database
    public function test_created_service_belongs_to_requested_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Facial',
            'description' => 'Basic facial treatment',
            'price' => 800,
            'duration_minutes' => 45,
            'buffer_minutes' => 15,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $serviceId = $response->json('data.id');

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'business_id' => $business->id,
        ]);
    }

    // Test that the service name is required when creating a service
    public function test_service_name_is_required(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    // Test that the service price cannot be negative when creating a service
    public function test_service_price_cannot_be_negative(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Haircut',
            'price' => -100,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price']);
    }

    // Test that the service duration must be greater than zero when creating a service
    public function test_service_duration_must_be_greater_than_zero(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Haircut',
            'price' => 500,
            'duration_minutes' => 0,
            'buffer_minutes' => 10,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['duration_minutes']);
    }

    // Test that the service buffer cannot be negative when creating a service
    public function test_service_buffer_cannot_be_negative(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Haircut',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => -10,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['buffer_minutes']);
    }
    
    // Test that the service status must be either 'active' or 'inactive' when creating a service
    public function test_service_status_must_be_valid(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Haircut',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'invalid_status',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    // Test that a user cannot create a service for a business they are not assigned to (403)
    public function test_user_cannot_create_service_for_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);

        $businessOne = Business::factory()->create();
        $businessTwo = Business::factory()->create();

        $user->businesses()->attach($businessOne->id);

        $payload = [
            'name' => 'Haircut',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$businessTwo->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);

        $this->assertDatabaseMissing('services', [
            'business_id' => $businessTwo->id,
            'name' => 'Haircut',
        ]);
    }

    /*
    |==========================================================================
    | UPDATE & VALIDATION TEST CASE
    |==========================================================================
    */

    // Test that a user can update a service for their assigned business
    public function test_user_can_update_service_from_their_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Old Service',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'Updated Service',
            'description' => 'Updated description',
            'price' => 750,
            'duration_minutes' => 45,
            'buffer_minutes' => 15,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'business_id' => $business->id,
            'name' => 'Updated Service',
            'description' => 'Updated description',
            'price' => 750,
            'duration_minutes' => 45,
            'buffer_minutes' => 15,
            'status' => 'active',
        ]);
    }

    // Test that a user can partially update a service for their assigned business
    public function test_user_can_partially_update_service(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Original Service',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ]);

        $payload = [
            'price' => 650,
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Original Service',
            'price' => 650,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ]);
    }

    // Test that the service name cannot be empty when updating a service
    public function test_service_name_cannot_be_empty_when_updating(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $payload = [
            'name' => '',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    // Test that the service price cannot be negative when updating a service
    public function test_service_price_cannot_be_negative_when_updating(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $payload = [
            'price' => -100,
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price']);
    }

    // Test that the service duration must be at least one minute when updating a service
    public function test_service_duration_must_be_at_least_one_when_updating(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $payload = [
            'duration_minutes' => 0,
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['duration_minutes']);
    }

    // Test that the service buffer cannot be negative when updating a service
    public function test_service_buffer_cannot_be_negative_when_updating(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $payload = [
            'buffer_minutes' => -10,
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['buffer_minutes']);
    }

    // Test that the service status must be either 'active' or 'inactive' when updating a service
    public function test_service_status_must_be_valid_when_updating(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $payload = [
            'status' => 'invalid_status',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    // Test that a user cannot update a service that belongs to another business (403)
    public function test_user_cannot_update_service_from_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);

        $businessOne = Business::factory()->create();
        $businessTwo = Business::factory()->create();

        $user->businesses()->attach($businessOne->id);

        $service = Service::factory()->create([
            'business_id' => $businessTwo->id,
            'name' => 'Original Service',
            'price' => 500,
        ]);

        $payload = [
            'name' => 'Unauthorized Update',
            'price' => 999,
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$businessTwo->id}/services/{$service->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'business_id' => $businessTwo->id,
            'name' => 'Original Service',
            'price' => 500,
        ]);
    }

    /*
    |==========================================================================
    | DELETION & VALIDATION TEST CASE
    |==========================================================================
    */

    // Test that a user can delete a service from their assigned business
    public function test_user_can_delete_service_from_their_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$business->id}/services/{$service->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertSoftDeleted('services', [
            'id' => $service->id,
        ]);
    }

    // Test that a deleted service is no longer returned by the index method
    public function test_deleted_service_is_no_longer_returned_by_index(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $deleteResponse = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$business->id}/services/{$service->id}"
            );

        $deleteResponse->assertStatus(200);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/services"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $data = $response->json('data');

        $serviceIds = collect($data)
            ->pluck('id')
            ->all();

        $this->assertNotContains($service->id, $serviceIds);
    }

    // Test that a user cannot delete a service using another business id (403)
    public function test_user_cannot_delete_service_from_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);

        $businessOne = Business::factory()->create();
        $businessTwo = Business::factory()->create();

        $user->businesses()->attach($businessOne->id);

        $service = Service::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$businessTwo->id}/services/{$service->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'business_id' => $businessTwo->id,
        ]);
    }

    // Test that deleting a non-existent service returns a 404 error
    public function test_deleting_nonexistent_service_returns_404(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$business->id}/services/999999"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    // Test that a user cannot delete a service that belongs to another business (404)
    public function test_user_cannot_delete_service_belonging_to_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);

        $businessOne = Business::factory()->create();
        $businessTwo = Business::factory()->create();

        $user->businesses()->attach($businessOne->id);

        $service = Service::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$businessOne->id}/services/{$service->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'business_id' => $businessTwo->id,
        ]);
    }

    /*
    |==========================================================================
    | FINAL REGRESSION & EDGE TEST CASE
    |==========================================================================
    */

    // Test that an unauthenticated user cannot create a service for a business (401)
    public function test_unauthenticated_user_cannot_create_service(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();

        $payload = [
            'name' => 'Haircut',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->postJson(
            "/api/businesses/{$business->id}/services",
            $payload
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // Test that an unauthenticated user cannot update a service for a business (401)
    public function test_unauthenticated_user_cannot_update_service(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $payload = [
            'name' => 'Updated Service',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->putJson(
            "/api/businesses/{$business->id}/services/{$service->id}",
            $payload
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // Test that an unauthenticated user cannot delete a service for a business (401)
    public function test_unauthenticated_user_cannot_delete_service(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->deleteJson(
            "/api/businesses/{$business->id}/services/{$service->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // Test that a service can be created with a null description
    public function test_service_can_have_null_description(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Haircut',
            'description' => null,
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('services', [
            'business_id' => $business->id,
            'name' => 'Haircut',
            'description' => null,
        ]);
    }

    // Test that a service can be created with zero buffer minutes
    public function test_service_can_have_zero_buffer_minutes(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Haircut',
            'price' => 500,
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('services', [
            'business_id' => $business->id,
            'buffer_minutes' => 0,
        ]);
    }

    // Test that a service can be created with zero price
    public function test_service_can_have_zero_price(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $payload = [
            'name' => 'Free Consultation',
            'price' => 0,
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'status' => 'active',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/services",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('services', [
            'business_id' => $business->id,
            'name' => 'Free Consultation',
            'price' => 0,
        ]);
    }

    // Test that a service can be updated to inactive
    public function test_service_can_be_updated_to_inactive(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'status' => 'active',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                [
                    'status' => 'inactive',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => 'inactive',
        ]);
    }

    // Test that a soft-deleted service cannot be viewed
    public function test_soft_deleted_service_cannot_be_viewed(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $service->delete();

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/services/{$service->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    // Test that a soft-deleted service cannot be updated
    public function test_soft_deleted_service_cannot_be_updated(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $service->delete();

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/services/{$service->id}",
                [
                    'name' => 'Updated Service',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    // Test that a soft-deleted service cannot be deleted again
    public function test_soft_deleted_service_cannot_be_deleted_again(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create(['role' => 'admin']);
        $business = Business::factory()->create();

        $user->businesses()->attach($business->id);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $service->delete();

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$business->id}/services/{$service->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

}
