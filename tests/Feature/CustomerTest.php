<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |==========================================================================
    | BASIC TEST CASE
    |==========================================================================
    */

    // Test that an unauthenticated user cannot view customers of a business
    public function test_unauthenticated_user_cannot_view_customers(): void
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
            "/api/businesses/{$business->id}/customers"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // Test that an unauthenticated user cannot view a specific customer of a business
    public function test_user_can_view_customers_from_their_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/customers"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $customer->id,
        ]);
    }

    // Test that the customer index only returns customers for the requested business
    public function test_customer_index_only_returns_customers_for_requested_business(): void
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

        $customerOne = Customer::factory()->create([
            'business_id' => $businessOne->id,
        ]);

        $customerTwo = Customer::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$businessOne->id}/customers"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $data = $response->json('data');

        $customerIds = collect($data)
            ->pluck('id')
            ->all();

        $this->assertContains(
            $customerOne->id,
            $customerIds
        );

        $this->assertNotContains(
            $customerTwo->id,
            $customerIds
        );
    }

    // Test that a user can view a specific customer from their business
    public function test_user_can_view_specific_customer_from_their_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.id',
            $customer->id
        );
    }

    // Test that a user cannot view customers from another business
    public function test_user_cannot_view_customers_from_another_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$businessTwo->id}/customers"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    // Test that a user cannot view a specific customer belonging to another business
    public function test_user_cannot_view_customer_belonging_to_another_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$businessOne->id}/customers/{$customer->id}"
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

    // Test that a user can create a customer for their business
    public function test_user_can_create_customer_for_their_business(): void
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

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09171234567',
            'email' => 'john@example.com',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $response->assertJsonPath(
            'data.first_name',
            'John'
        );

        $response->assertJsonPath(
            'data.last_name',
            'Doe'
        );

        $this->assertDatabaseHas('customers', [
            'business_id' => $business->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);
    }

    // Test that a created customer is assigned to the requested business
    public function test_created_customer_is_assigned_to_requested_business(): void
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

        $payload = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '09181234567',
            'email' => 'jane@example.com',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $customerId = $response->json('data.id');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'business_id' => $business->id,
        ]);
    }

    // Test that a user cannot create a customer for another business
    public function test_user_cannot_create_customer_for_another_business(): void
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

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09171234567',
            'email' => 'john@example.com',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$businessTwo->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);

        $this->assertDatabaseMissing('customers', [
            'email' => 'john@example.com',
        ]);
    }

    // Test that creating a customer requires a first name for validation
    public function test_create_customer_requires_first_name(): void
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

        $payload = [
            'last_name' => 'Doe',
            'phone' => '09171234567',
            'email' => 'john@example.com',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'first_name',
        ]);
    }

    // Test that creating a customer requires a last name for validation
    public function test_create_customer_requires_last_name(): void
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

        $payload = [
            'first_name' => 'John',
            'phone' => '09171234567',
            'email' => 'john@example.com',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'last_name',
        ]);
    }

    // Test that creating a customer allows a null phone number
    public function test_create_customer_allows_null_phone(): void
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

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => null,
            'email' => 'john@example.com',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'business_id' => $business->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => null,
        ]);
    }

    // Test that creating a customer allows a null email address
    public function test_create_customer_allows_null_email(): void
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

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09171234567',
            'email' => null,
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'business_id' => $business->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => null,
        ]);
    }

    // Test that creating a customer rejects an invalid email address
    public function test_create_customer_rejects_invalid_email(): void
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

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09171234567',
            'email' => 'not-an-email',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'email',
        ]);
    }

    // Test that creating a customer rejects an invalid phone number
    public function test_create_customer_rejects_invalid_phone(): void
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

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => ['invalid'],
            'email' => 'john@example.com',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'phone',
        ]);
    }

    /*
    |==========================================================================
    | UPDATE & VALIDATION TEST CASES
    |==========================================================================
    */

    // Test that a user can update a customer from their business
    public function test_user_can_update_customer_from_their_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09171234567',
            'email' => 'john@example.com',
        ]);

        $payload = [
            'first_name' => 'Jonathan',
            'last_name' => 'Doe',
            'phone' => '09181234567',
            'email' => 'jonathan@example.com',
        ];

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                $payload
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.first_name',
            'Jonathan'
        );

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'Jonathan',
            'last_name' => 'Doe',
            'phone' => '09181234567',
            'email' => 'jonathan@example.com',
        ]);
    }

    // Test that updating a customer supports partial updates
    public function test_customer_update_supports_partial_updates(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09171234567',
            'email' => 'john@example.com',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                [
                    'phone' => '09181234567',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '09181234567',
            'email' => 'john@example.com',
        ]);
    }

    // Test that updating a customer allows setting the phone number to null
    public function test_customer_phone_can_be_set_to_null(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'phone' => '09171234567',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
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

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'phone' => null,
        ]);
    }

    // Test that updating a customer allows setting the email address to null
    public function test_customer_email_can_be_set_to_null(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'email' => 'john@example.com',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                [
                    'email' => null,
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => null,
        ]);
    }

    // Test that updating a customer rejects an invalid email address
    public function test_customer_update_rejects_invalid_email(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'email' => 'john@example.com',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                [
                    'email' => 'invalid-email',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'email',
        ]);
    }

    // Test that updating a customer rejects an invalid phone number
    public function test_customer_update_rejects_invalid_phone(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'phone' => '09171234567',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                [
                    'phone' => ['invalid'],
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'phone',
        ]);
    }

    // Test that updating a customer rejects an empty first name
    public function test_customer_update_rejects_empty_first_name(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'first_name' => 'John',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                [
                    'first_name' => '',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'first_name',
        ]);
    }

    // Test that updating a customer rejects an empty last name
    public function test_customer_update_rejects_empty_last_name(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'last_name' => 'Doe',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                [
                    'last_name' => '',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'last_name',
        ]);
    }

    // Test that a user cannot update a customer from another business
    public function test_user_cannot_update_customer_from_another_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $businessTwo->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$businessTwo->id}/customers/{$customer->id}",
                [
                    'first_name' => 'Updated',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'John',
        ]);
    }

    // Test that a user cannot update a customer through the wrong business
    public function test_user_cannot_update_customer_through_wrong_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $businessTwo->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$businessOne->id}/customers/{$customer->id}",
                [
                    'first_name' => 'Updated',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'John',
        ]);
    }

    /*
    |==========================================================================
    | EDGE TEST CASES, AUTHORIZATION AND MORE DETAILED CRUD
    |==========================================================================
    */

    // Test that viewing a non-existent customer returns a 404 status code
    public function test_viewing_nonexistent_customer_returns_404(): void
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
                "/api/businesses/{$business->id}/customers/999999"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    // Test that updating a non-existent customer returns a 404 status code
    public function test_updating_nonexistent_customer_returns_404(): void
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
            ->putJson(
                "/api/businesses/{$business->id}/customers/999999",
                [
                    'first_name' => 'Updated',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    // Test that a super admin can view customers from any business
    public function test_super_admin_can_view_customers_from_any_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/customers"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $customer->id,
        ]);
    }

    // Test that a super admin can create a customer for any business
    public function test_super_admin_can_create_customer_for_any_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                [
                    'first_name' => 'Super',
                    'last_name' => 'Admin',
                    'phone' => '09171234567',
                    'email' => 'superadmin@example.com',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'business_id' => $business->id,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@example.com',
        ]);
    }

    // Test that a super admin can update a customer from any business
    public function test_super_admin_can_update_customer_from_any_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($superAdmin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                [
                    'first_name' => 'Updated',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'Updated',
            'last_name' => 'Doe',
        ]);
    }

    // Test that a staff user can view customers from their business
    public function test_staff_user_can_view_customers_from_their_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $business = Business::factory()->create();

        Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($staffUser, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/customers"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $customer->id,
        ]);
    }

    // Test that a staff user cannot view customers from another business
    public function test_staff_user_cannot_view_customers_from_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $businessOne = Business::factory()->create();
        $businessTwo = Business::factory()->create();

        Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $businessOne->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($staffUser, 'sanctum')
            ->getJson(
                "/api/businesses/{$businessTwo->id}/customers"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    // Test that an invalid customer update does not modify existing data
    public function test_invalid_customer_update_does_not_modify_existing_data(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}",
                [
                    'email' => 'invalid-email',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);
    }

    // Test that an invalid customer creation does not create a new customer
    public function test_invalid_customer_creation_does_not_create_customer(): void
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
            ->postJson(
                "/api/businesses/{$business->id}/customers",
                [
                    'first_name' => '',
                    'last_name' => '',
                    'email' => 'invalid-email',
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $this->assertDatabaseCount('customers', 0);
    }

    /*
    |==========================================================================
    | DELETION TESTING
    |==========================================================================
    */

    // Test that a user can delete a customer from their business
    public function test_user_can_delete_customer_from_their_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Customer deleted successfully.',
        ]);

        $this->assertSoftDeleted('customers', [
            'id' => $customer->id,
        ]);
    }

    // Test that a user cannot delete a customer from another business (403 Forbidden)
    public function test_user_cannot_delete_customer_from_another_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$businessTwo->id}/customers/{$customer->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
        ]);
    }

    // Test that a user cannot delete a customer through the wrong business (404 Not Found)
    public function test_user_cannot_delete_customer_through_wrong_business(): void
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

        $customer = Customer::factory()->create([
            'business_id' => $businessTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$businessOne->id}/customers/{$customer->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
        ]);
    }

    // Test that a super admin can delete a customer from any business
    public function test_super_admin_can_delete_customer_from_any_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/businesses/{$business->id}/customers/{$customer->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Customer deleted successfully.',
        ]);

        $this->assertSoftDeleted('customers', [
            'id' => $customer->id,
        ]);
    }

    // Test that deleting a non-existent customer returns a 404 status code
    public function test_deleting_nonexistent_customer_returns_404(): void
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
            ->deleteJson(
                "/api/businesses/{$business->id}/customers/999999"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }
}
