<?php

namespace Tests\Feature;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class BusinessHourTest extends TestCase
{
    use RefreshDatabase;


    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    protected function createBusinessWithHours(): Business
    {
        $business = Business::factory()->create();

        foreach (range(0, 6) as $day) {
            $business->businessHours()->create([
                'day_of_week' => $day,
                'open_time' => null,
                'close_time' => null,
                'is_closed' => true,
            ]);
        }

        return $business;
    }

    protected function validSchedule(): array
    {
        return [
            'business_hours' => [
                [
                    'day_of_week' => 0,
                    'open_time' => null,
                    'close_time' => null,
                    'is_closed' => true,
                ],
                [
                    'day_of_week' => 1,
                    'open_time' => '09:00',
                    'close_time' => '18:00',
                    'is_closed' => false,
                ],
                [
                    'day_of_week' => 2,
                    'open_time' => '09:00',
                    'close_time' => '18:00',
                    'is_closed' => false,
                ],
                [
                    'day_of_week' => 3,
                    'open_time' => '09:00',
                    'close_time' => '18:00',
                    'is_closed' => false,
                ],
                [
                    'day_of_week' => 4,
                    'open_time' => '09:00',
                    'close_time' => '18:00',
                    'is_closed' => false,
                ],
                [
                    'day_of_week' => 5,
                    'open_time' => '09:00',
                    'close_time' => '18:00',
                    'is_closed' => false,
                ],
                [
                    'day_of_week' => 6,
                    'open_time' => '09:00',
                    'close_time' => '15:00',
                    'is_closed' => false,
                ],
            ],
        ];
    }

    protected function assignAdminToBusiness(
        User $admin,
        Business $business
    ): void {
        $business->users()->attach($admin->id, [
            'role' => 'owner',
        ]);
    }

    protected function createStaffForBusiness(
        User $user,
        Business $business
    ): Staff {
        return Staff::factory()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX TESTS
    |--------------------------------------------------------------------------
    */

    // Test that a super admin can view business hours for any business.
    public function test_super_admin_is_authorized_to_update_business_hours(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $this->assertTrue(
            $user->can('updateSchedule', [
                BusinessHour::class,
                $business,
            ])
        );
    }

    // Test that an assigned admin can view business hours for their business.
    public function test_super_admin_can_view_business_hours(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = $this->createBusinessWithHours();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/businesses/{$business->id}/hours");

        $response->assertOk()
            ->assertJsonCount(7, 'data');

        $this->assertDatabaseCount('business_hours', 7);
    }

    // Test that an assigned admin can view business hours for their business.
    public function test_assigned_admin_can_view_business_hours(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = $this->createBusinessWithHours();

        $this->assignAdminToBusiness($admin, $business);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/businesses/{$business->id}/hours");

        $response->assertOk()
            ->assertJsonCount(7, 'data');
    }

    // Test that a staff member can view business hours for their assigned business.
    public function test_staff_can_view_own_business_hours(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
        ]);

        $business = $this->createBusinessWithHours();

        $this->createStaffForBusiness($user, $business);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/businesses/{$business->id}/hours");

        $response->assertOk()
            ->assertJsonCount(7, 'data');
    }

    // Test that a staff member cannot view business hours for a business they are not assigned to.
    public function test_staff_cannot_view_another_business_hours(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
        ]);

        $ownBusiness = $this->createBusinessWithHours();
        $otherBusiness = $this->createBusinessWithHours();

        $this->createStaffForBusiness($user, $ownBusiness);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/businesses/{$otherBusiness->id}/hours");

        $response->assertForbidden();
    }

    // Test that an unassigned admin cannot view business hours for a business they are not assigned to.
    public function test_unassigned_admin_cannot_view_business_hours(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = $this->createBusinessWithHours();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/businesses/{$business->id}/hours");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE TESTS
    |--------------------------------------------------------------------------
    */

    // Test that a super admin can update business hours for any business.
    public function test_super_admin_can_update_business_hours(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = $this->createBusinessWithHours();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $this->validSchedule()
            );

        $response->assertOk()
            ->assertJsonCount(7, 'data');

        $this->assertDatabaseHas('business_hours', [
            'business_id' => $business->id,
            'day_of_week' => 1,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'is_closed' => false,
        ]);

        $this->assertDatabaseCount('business_hours', 7);
    }

    // Test that an assigned admin can update business hours for their business.
    public function test_assigned_admin_can_update_business_hours(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = $this->createBusinessWithHours();

        $this->assignAdminToBusiness($admin, $business);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $this->validSchedule()
            );

        $response->assertOk();

        $this->assertDatabaseHas('business_hours', [
            'business_id' => $business->id,
            'day_of_week' => 1,
            'is_closed' => false,
        ]);
    }

    // Test that a staff member cannot update business hours for their assigned business.
    public function test_staff_cannot_update_business_hours(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
        ]);

        $business = $this->createBusinessWithHours();

        $this->createStaffForBusiness($user, $business);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $this->validSchedule()
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('business_hours', [
            'business_id' => $business->id,
            'day_of_week' => 1,
            'is_closed' => true,
        ]);
    }

    // Test that an unassigned admin cannot update business hours for a business they are not assigned to.
    public function test_unassigned_admin_cannot_update_business_hours(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = $this->createBusinessWithHours();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $this->validSchedule()
            );

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION TESTS
    |--------------------------------------------------------------------------
    */

    // Test that the business hours update request rejects duplicate days of the week.
    public function test_business_hours_reject_duplicate_days(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = $this->createBusinessWithHours();

        $schedule = $this->validSchedule();

        $schedule['business_hours'][1]['day_of_week'] = 0;

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $schedule
            );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'business_hours.1.day_of_week',
            ]);
    }

    // Test that the business hours update request requires all seven days of the week to be present.
    public function test_business_hours_require_all_seven_days(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = $this->createBusinessWithHours();

        $schedule = $this->validSchedule();

        array_pop($schedule['business_hours']);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $schedule
            );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'business_hours',
            ]);
    }

    // Test that an open business day requires both opening and closing times to be provided.
    public function test_open_business_day_requires_opening_and_closing_times(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = $this->createBusinessWithHours();

        $schedule = $this->validSchedule();

        $schedule['business_hours'][1]['open_time'] = null;

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $schedule
            );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'business_hours.1.open_time',
            ]);
    }

    // Test that a closed business day can have null opening and closing times.
    public function test_closing_time_must_be_later_than_opening_time(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = $this->createBusinessWithHours();

        $schedule = $this->validSchedule();

        $schedule['business_hours'][1]['open_time'] = '18:00';
        $schedule['business_hours'][1]['close_time'] = '09:00';

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $schedule
            );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'business_hours.1.close_time',
            ]);
    }

    // Test that a closed business day can have null opening and closing times.
    public function test_closed_day_can_have_null_times(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = $this->createBusinessWithHours();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/businesses/{$business->id}/hours",
                $this->validSchedule()
            );

        $response->assertOk();

        $this->assertDatabaseHas('business_hours', [
            'business_id' => $business->id,
            'day_of_week' => 0,
            'open_time' => null,
            'close_time' => null,
            'is_closed' => true,
        ]);
    }

    // Test that updating business hours creates missing weekday records if they don't exist.
    public function test_updating_business_hours_creates_missing_weekday_records(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        // Create only six weekday records manually.
        foreach (range(0, 5) as $day) {
            BusinessHour::create([
                'business_id' => $business->id,
                'day_of_week' => $day,
                'open_time' => null,
                'close_time' => null,
                'is_closed' => true,
            ]);
        }

        $this->actingAs($user, 'sanctum');

        $response = $this->putJson(
            "/api/businesses/{$business->id}/hours",
            $this->validSchedule()
        );

        $response->assertOk()
            ->assertJsonCount(7, 'data');

        $this->assertDatabaseCount('business_hours', 7);

        $this->assertDatabaseHas('business_hours', [
            'business_id' => $business->id,
            'day_of_week' => 6,
        ]);
    }


}
