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

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /*
    |==========================================================================
    | BASIC AVAILABILITY TEST CASES
    |==========================================================================
    */

    // This is to test that a selected staff can generate an available slots during working hours
    // when generating the availabilty list
    public function test_staff_has_available_slots_during_working_hours()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'date',
            'staff' => [
                'id',
                'last_name',
                'first_name',
            ],
            'services' => [
                '*' => [
                    'id',
                    'name',
                    'duration_minutes',
                    'buffer_minutes',
                ],
            ],
            'available_slots',
        ]);

        $this->assertNotEmpty(
            $response->json('available_slots')
        );
    }

    // This is to test that a selected staff generates an empty slot when the selected day
    // of the week is assigned as a day off
    public function test_staff_has_no_available_slots_when_day_is_off()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => true,
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertEmpty(
            $response->json('available_slots')
        );
    }

    // This is to test that a selected staff generates an empty slot when the selected day
    // of the week does not have a stff hour record to it
    public function test_staff_has_no_available_slots_when_no_working_hours_exist()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        // No StaffHour is created for this date.

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertEmpty(
            $response->json('available_slots')
        );
    }

    // This is to test that the availability slots respects the service duration and buffer in 
    // the calculation
    public function test_availability_respects_service_duration_and_buffer()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $this->assertNotEmpty($slots);

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['start']);
            $end = Carbon::parse($slot['end']);

            // 30-minute service + 15-minute buffer = 45 minutes occupied.
            $this->assertEquals(
                45,
                $start->diffInMinutes($end)
            );

            // Slot must remain inside working hours.
            $this->assertGreaterThanOrEqual(
                '09:00',
                $start->format('H:i')
            );

            $this->assertLessThanOrEqual(
                '10:00',
                $end->format('H:i')
            );
        }
    }

    /*
    |==========================================================================
    | STAFF/SERVICE RULE TEST CASES
    |==========================================================================
    */

    // This is to test that a staff member w/o the specific services assigned to them will
    // not generate any available slots
    public function test_staff_without_service_has_no_available_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        // Intentionally do NOT assign the service to the staff.

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertEmpty(
            $response->json('available_slots')
        );
    }

    // This is to test that a staff w/ the specified service can generate available slots
    public function test_staff_with_assigned_service_has_available_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertNotEmpty(
            $response->json('available_slots')
        );
    }

    // This is to test that multiple services will combine all the total durations into 
    // each generated slots
    public function test_multi_service_availability_uses_combined_duration_and_buffer()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $serviceOne = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $serviceTwo = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 45,
            'buffer_minutes' => 10,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach([
            $serviceOne->id,
            $serviceTwo->id,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$serviceOne->id}"
                . "&service_ids[]={$serviceTwo->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $this->assertNotEmpty($slots);

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['start']);
            $end = Carbon::parse($slot['end']);

            // 30 + 15 + 45 + 10 = 100 minutes.
            $this->assertEquals(
                100,
                $start->diffInMinutes($end)
            );
        }
    }

    // This is to test that the availability slots will not generate if at least one service is not currently
    // assigned by the specified staff member
    public function test_multi_service_availability_returns_no_slots_when_one_service_is_unassigned()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $serviceOne = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $serviceTwo = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 45,
            'buffer_minutes' => 10,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        // Staff provides service one but NOT service two.
        $staff->services()->attach($serviceOne->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$serviceOne->id}"
                . "&service_ids[]={$serviceTwo->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertEmpty(
            $response->json('available_slots')
        );
    }

    /*
    |==========================================================================
    | TIME-OFF HANDLING TEST CASES
    |==========================================================================
    */
    // This is to test that the generated available slots will not include a time slot
    // where an existing time off record overlaps to that time slot
    public function test_staff_time_off_blocks_available_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        StaffTimeOff::factory()->create([
            'staff_id' => $staff->id,
            'start_datetime' => $date->copy()->setTime(12, 0),
            'end_datetime' => $date->copy()->setTime(13, 0),
            'reason' => 'Personal appointment',
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $this->assertNotEmpty($slots);

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['start']);
            $end = Carbon::parse($slot['end']);

            // No available slot may overlap the 12:00–13:00 time-off.
            $this->assertTrue(
                $end->lte($date->copy()->setTime(12, 0))
                    || $start->gte($date->copy()->setTime(13, 0))
            );
        }
    }

    // This is to test that any time-off records of a specific staff that is outside working
    // hours does not affect the generated availability slots
    public function test_time_off_outside_working_hours_does_not_affect_availability()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        StaffTimeOff::factory()->create([
            'staff_id' => $staff->id,
            'start_datetime' => $date->copy()->setTime(18, 0),
            'end_datetime' => $date->copy()->setTime(19, 0),
            'reason' => 'Personal appointment',
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertNotEmpty(
            $response->json('available_slots')
        );
    }

    // This is to test that a time-off record that covers a whole day will completely 
    // block out all available slots in that specific date
    public function test_full_day_time_off_returns_no_available_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        StaffTimeOff::factory()->create([
            'staff_id' => $staff->id,
            'start_datetime' => $date->copy()->setTime(9, 0),
            'end_datetime' => $date->copy()->setTime(17, 0),
            'reason' => 'Day off',
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertEmpty(
            $response->json('available_slots')
        );
    }

    /*
    |==========================================================================
    | EXISTING APPOINTMENT HANDLING TEST CASES
    |==========================================================================
    */

    // This is to test that a pending appointment schedule blocks out that available time slot
    // for that specific staff member in that specific date
    public function test_pending_appointment_blocks_available_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $appointmentStart = $date->copy()->setTime(10, 0);
        $appointmentEnd = $appointmentStart->copy()->addMinutes(45);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentStart,
            'end_datetime' => $appointmentEnd,
            'status' => 'pending',
        ]);

        AppointmentService::factory()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['start']);
            $end = Carbon::parse($slot['end']);

            // No slot may overlap the pending appointment.
            $this->assertTrue(
                $end->lte($appointmentStart)
                    || $start->gte($appointmentEnd)
            );
        }
    }

    // This is to test that a confirmed appointment schedule also blocks out
    // that specific time slot availability for the staff member
    public function test_confirmed_appointment_blocks_available_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $appointmentStart = $date->copy()->setTime(10, 0);
        $appointmentEnd = $appointmentStart->copy()->addMinutes(45);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentStart,
            'end_datetime' => $appointmentEnd,
            'status' => 'confirmed',
        ]);

        AppointmentService::factory()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['start']);
            $end = Carbon::parse($slot['end']);

            // No slot may overlap the confirmed appointment.
            $this->assertTrue(
                $end->lte($appointmentStart)
                    || $start->gte($appointmentEnd)
            );
        }
    }

    // This is to test that a cancelled appointment schedule does not block
    // out the available time slot for the staff member
    public function test_cancelled_appointment_does_not_block_available_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $appointmentStart = $date->copy()->setTime(10, 0);
        $appointmentEnd = $appointmentStart->copy()->addMinutes(45);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentStart,
            'end_datetime' => $appointmentEnd,
            'status' => 'cancelled',
        ]);

        AppointmentService::factory()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);
       
        $slots = $response->json('available_slots');

        $this->assertNotEmpty($slots);

        $matchingSlotExists = false;

        $this->assertContains(
            [
                'start' => $appointmentStart->format('H:i'),
                'end' => $appointmentEnd->format('H:i'),
            ],
            $slots,
            'The cancelled appointment time should be available.'
        );
    }

    // This is to test that the availability does not simply stop generating slots after encountering 
    // one appointment
    public function test_multiple_active_appointments_block_their_respective_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $firstStart = $date->copy()->setTime(10, 0);
        $firstEnd = $firstStart->copy()->addMinutes(45);

        $secondStart = $date->copy()->setTime(14, 0);
        $secondEnd = $secondStart->copy()->addMinutes(45);

        foreach (
            [
                [$firstStart, $firstEnd, 'pending'],
                [$secondStart, $secondEnd, 'confirmed'],
            ] as [$start, $end, $status]
        ) {
            $appointment = Appointment::factory()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'staff_id' => $staff->id,
                'start_datetime' => $start,
                'end_datetime' => $end,
                'status' => $status,
            ]);

            AppointmentService::factory()->create([
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'price' => $service->price,
                'currency' => $business->currency,
                'duration_minutes' => $service->duration_minutes,
                'buffer_minutes' => $service->buffer_minutes,
            ]);
        }

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['start']);
            $end = Carbon::parse($slot['end']);

            $firstAppointmentOverlap =
                $start->lt($firstEnd)
                && $end->gt($firstStart);

            $secondAppointmentOverlap =
                $start->lt($secondEnd)
                && $end->gt($secondStart);

            $this->assertFalse(
                $firstAppointmentOverlap,
                'A slot overlaps the first active appointment.'
            );

            $this->assertFalse(
                $secondAppointmentOverlap,
                'A slot overlaps the second active appointment.'
            );
        }
    }

    /*
    |==========================================================================
    | AVAILABILITY BOUNDARY CONDITION TEST CASES
    |==========================================================================
    */

    // This is to test that the time slot that ends EXACTLY before the start of the appointment schedule
    // is still available on the lsit
    public function test_slot_ending_exactly_when_appointment_starts_remains_available()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $appointmentStart = $date->copy()->setTime(10, 0);
        $appointmentEnd = $appointmentStart->copy()->addMinutes(45);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentStart,
            'end_datetime' => $appointmentEnd,
            'status' => 'confirmed',
        ]);

        AppointmentService::factory()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $slots = $response->json('available_slots');

        $expectedSlot = [
            'start' => $date->copy()->setTime(9, 15)->format('H:i'),
            'end' => $date->copy()->setTime(10, 0)->format('H:i'),
        ];

        $this->assertContains(
            $expectedSlot,
            $slots,
            'The 09:15–10:00 slot should remain available.'
        );
    }

    // This is to test that the time slot that exactly starts at the end of the occupied schedule is still available 
    // (This is in case that the end schedule of the appointment is exactly the same as the start of that time slot)
    public function test_slot_starting_exactly_when_appointment_ends_remains_available()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $appointmentStart = $date->copy()->setTime(10, 0);
        $appointmentEnd = $appointmentStart->copy()->addMinutes(45);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentStart,
            'end_datetime' => $appointmentEnd,
            'status' => 'confirmed',
        ]);

        AppointmentService::factory()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);
        $slots = $response->json('available_slots');

        $expectedSlot = [
            'start' => $appointmentEnd->format('H:i'),
            'end' => $appointmentEnd->copy()
                ->addMinutes(45)
                ->format('H:i'),
        ];

        $this->assertContains(
            $expectedSlot,
            $slots,
            'The 10:45–11:30 slot should remain available.'
        );
    }

    // This is to test that a time slot schedule that exactly ends with the closing time should still be available from the list 
    public function test_slot_ending_exactly_at_closing_time_is_available()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        //set to 10:00:00 for test purposes
        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $expectedSlot = [
            'start' => $date->copy()->setTime(9, 15)->format('H:i'),
            'end' => $date->copy()->setTime(10, 0)->format('H:i'),
        ];

        $this->assertContains(
            $expectedSlot,
            $slots,
            'The 09:15–10:00 slot should remain available.'
        );
    }

    // This is to test that any slots that ends past the closing time should not be generated on the list
    public function test_slot_extending_past_closing_time_is_not_available()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->startOfDay();

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/businesses/{$business->id}/availability"
                . "?staff_id={$staff->id}"
                . "&service_ids[]={$service->id}"
                . "&date={$date->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        foreach ($slots as $slot) {
            $this->assertLessThanOrEqual(
                '10:00',
                $slot['end'],
                "Slot {$slot['start']}-{$slot['end']} extends past closing time."
            );
        }

        $invalidSlotExists = collect($slots)->contains([
            'start' => '09:30',
            'end' => '10:15',
        ]);

        $this->assertFalse(
            $invalidSlotExists,
            'A slot extending past closing time must not be available.'
        );
    }

    /*
    |==========================================================================
    | AVAILABILITY VALIDATION AND BUSINESS ISOLATION TEST CASES
    |==========================================================================
    */

    // This is to test that the availability API requires staff id to operate
    public function test_availability_requires_staff_id()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?service_ids[]=1"
                    . "&date=" . Carbon::today()->toDateString()
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['staff_id']);
    }

    // This is to test that the availability requires at least one service id in the process
    public function test_availability_requires_service_ids()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_ids']);
    }

    // This is to test the need of a valid date for the availability API
    public function test_availability_requires_date()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date']);
    }

    // This is to test that the API rejects nonexistent staff ids
    public function test_availability_rejects_nonexistent_staff_id()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id=999999"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['staff_id']);
    }

    // This is to test that the API rejects nonexistent service ids in the process
    public function test_availability_rejects_nonexistent_service_id()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]=999999"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_ids.0']);
    }

    // This is to test that the API rejects a staff member from another business
    public function test_availability_rejects_staff_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $otherBusiness->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'The selected staff member does not belong to this business.',
        ]);
    }

    // This is to test that the API rejects any services that comes from another business
    public function test_availability_rejects_service_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $otherBusiness->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'The selected service does not belong to this business.',
        ]);
    }

    /*
    |==========================================================================
    | AVAILABILITY AUTHENTICATION/AUTHORIZATION TEST CASES
    |==========================================================================
    */

    // This is to test that unauthenticated users cannot view the availability API
    public function test_unauthenticated_user_cannot_view_availability()
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
            "/api/businesses/{$business->id}/availability"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // This is to test that an admin can view the availability API in their own business
    public function test_admin_can_view_own_business_availability()
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

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);
    }

    // This is to test that an admin cannot view the API through another business id
    public function test_admin_cannot_view_another_business_availability()
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

        // HERE - Do NOT attach this business to the admin.

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    // This is to test that a staff user can only view the API under their respective business
    public function test_staff_user_can_view_own_business_availability()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'staff',
        ]);

        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);
    }

    // This is to test that a staff member cannot view the API through another business id
    public function test_staff_user_cannot_view_another_business_availability()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'staff',
        ]);

        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        Staff::factory()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$otherBusiness->id}/availability"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    /*
    |==========================================================================
    | AVAILABILITY RESPONSE CORRECTEDNESS TEST CASES
    |==========================================================================
    */

    // This is to test that the API returns the data w/ the proper requested date
    public function test_availability_returns_requested_date()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'date',
            $date->toDateString()
        );
    }

    // This is to test that the API returns the data w/ the correct staff info
    public function test_availability_returns_correct_staff_information()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create([
            'first_name' => 'Alex',
            'last_name' => 'Cartwright',
        ]);

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'staff.id',
            $staff->id
        );

        $response->assertJsonPath(
            'staff.first_name',
            'Alex'
        );

        $response->assertJsonPath(
            'staff.last_name',
            'Cartwright'
        );
    }

    // This is to test that the API returns the data w/ correct service information
    public function test_availability_returns_correct_service_information()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Facial',
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'services.0.id',
            $service->id
        );

        $response->assertJsonPath(
            'services.0.name',
            'Facial'
        );

        $response->assertJsonPath(
            'services.0.duration_minutes',
            30
        );

        $response->assertJsonPath(
            'services.0.buffer_minutes',
            15
        );
    }

    // This is to test that the generated slots have the correct structure
    public function test_availability_slots_have_correct_structure()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $this->assertNotEmpty($slots);

        foreach ($slots as $slot) {
            $this->assertArrayHasKey('start', $slot);
            $this->assertArrayHasKey('end', $slot);

            $this->assertIsString($slot['start']);
            $this->assertIsString($slot['end']);

            $this->assertMatchesRegularExpression(
                '/^\d{2}:\d{2}$/',
                $slot['start']
            );

            $this->assertMatchesRegularExpression(
                '/^\d{2}:\d{2}$/',
                $slot['end']
            );
        }
    }

    // This is to test that the API returns the data w/ correct multiple service info
    public function test_availability_returns_multiple_services_correctly()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $serviceOne = Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Facial',
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $serviceTwo = Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Massage',
            'duration_minutes' => 45,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach([
            $serviceOne->id,
            $serviceTwo->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$serviceOne->id}"
                    . "&service_ids[]={$serviceTwo->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $services = $response->json('services');

        $this->assertCount(2, $services);

        $this->assertEquals(
            $serviceOne->id,
            $services[0]['id']
        );

        $this->assertEquals(
            $serviceTwo->id,
            $services[1]['id']
        );
    }

    /*
    |==========================================================================
    | AVAILABILITY EDGE TEST CASES
    |==========================================================================
    */

    // This is to test that the API can generate slots on multiple working periods on a single day
    public function test_availability_supports_multiple_working_periods()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_off' => false,
        ]);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $this->assertContains([
            'start' => '09:00',
            'end' => '09:45',
        ], $slots);

        $this->assertContains([
            'start' => '11:15',
            'end' => '12:00',
        ], $slots);

        $this->assertContains([
            'start' => '13:00',
            'end' => '13:45',
        ], $slots);

        $this->assertContains([
            'start' => '16:15',
            'end' => '17:00',
        ], $slots);

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot['start']);
            $end = Carbon::parse($slot['end']);

            $this->assertFalse(
                $start->between(
                    Carbon::parse('12:00'),
                    Carbon::parse('13:00'),
                    false
                )
            );

            $this->assertGreaterThan(
                $start->timestamp,
                $end->timestamp
            );
        }
    }

    // This is to test that a time slot that starts exactly after a time off ends is available on the list
    public function test_slot_starting_exactly_when_time_off_ends_remains_available()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $staff->timeOffs()->create([
            'start_datetime' => $date->copy()->setTime(9, 15),
            'end_datetime' => $date->copy()->setTime(10, 0),
            'reason' => 'Test time off',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $this->assertContains([
            'start' => '10:00',
            'end' => '10:45',
        ], $slots);
    }

    // This is to test that a time slot that ends exactly where the time off starts is still available on the list
    public function test_slot_ending_exactly_when_time_off_starts_remains_available()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        $staff->timeOffs()->create([
            'start_datetime' => $date->copy()->setTime(9, 45),
            'end_datetime' => $date->copy()->setTime(10, 30),
            'reason' => 'Test time off',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $this->assertContains([
            'start' => '09:00',
            'end' => '09:45',
        ], $slots);
    }

    // This is to test that an appointment schedule that overlaps the beginning of working hours does not allow conflicting slots
    // e.g: Working Hours: 09:17:00 and Appointment: 09:00-09:45 -> only allow 10:00 to 10:45 to be listed
    public function test_appointment_at_start_of_working_hours_blocks_overlapping_slots()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $staff->services()->attach($service->id);

        Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $date->copy()->setTime(9, 0),
            'end_datetime' => $date->copy()->setTime(9, 45),
            'status' => 'confirmed',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $slots = $response->json('available_slots');

        $this->assertNotContains([
            'start' => '09:00',
            'end' => '09:45',
        ], $slots);

        $this->assertNotContains([
            'start' => '09:15',
            'end' => '10:00',
        ], $slots);

        $this->assertContains([
            'start' => '09:45',
            'end' => '10:30',
        ], $slots);
    }

    /*
    |==========================================================================
    | DATE AND INPUT EDGEs TEST CASES
    |==========================================================================
    */

    // Test that that the API rejects invalid date formatting
    public function test_availability_rejects_invalid_date()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date=not-a-date"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'date',
        ]);
    }

    // API should reject an empty service id in the input
    public function test_availability_rejects_empty_service_ids()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]="
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        //Hard coded to .0 for reasons
        $response->assertJsonValidationErrors([
            'service_ids.0',
        ]);
    }

    // API must reject any duplicate service ids from being processed
    public function test_availability_rejects_duplicate_service_ids()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'service_ids.1',
        ]);
    }

    // API rejects invalid staff id input
    public function test_availability_rejects_invalid_staff_id_type()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $service = Service::factory()->create([
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id=abc"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'staff_id',
        ]);
    }

    // API must rehect invalid service id input
    public function test_availability_rejects_invalid_service_id_type()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]=abc"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'service_ids.0',
        ]);
    }

    /*
    |==========================================================================
    | AVAILABILITY REGRESSION TEST CASES
    |==========================================================================
    */

    // API should not generate availability slots when a staff is not assigned to any services at all
    public function test_staff_without_assigned_services_has_no_availability()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        // Intentionally do not attach the service to the staff.

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertEmpty(
            $response->json('available_slots')
        );
    }

    // When assigning multiple services, the staff must actually have those services assigned to them
    public function test_multi_service_availability_requires_all_services_to_be_assigned()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $serviceOne = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $serviceTwo = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 45,
            'buffer_minutes' => 15,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        $staff->hours()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        // Staff provides only the first service.
        $staff->services()->attach($serviceOne->id);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$serviceOne->id}"
                    . "&service_ids[]={$serviceTwo->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertEmpty(
            $response->json('available_slots')
        );
    }

    // API should not be able to use a service own by a different business 
    public function test_availability_cannot_use_service_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        $staffUser = User::factory()->create();

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $otherBusiness->id,
        ]);

        $date = Carbon::today()->next(Carbon::THURSDAY);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/businesses/{$business->id}/availability"
                    . "?staff_id={$staff->id}"
                    . "&service_ids[]={$service->id}"
                    . "&date={$date->toDateString()}"
            );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'The selected service does not belong to this business.',
        ]);
    }
}
