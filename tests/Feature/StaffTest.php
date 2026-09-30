<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Appointment;
use App\Models\Business;
use Illuminate\Support\Carbon;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffHour;
use App\Models\StaffTimeOff;
use App\Models\AppointmentService;
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
}
