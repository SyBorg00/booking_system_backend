<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffHour;
use App\Models\AppointmentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Carbon;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    /*
    |==========================================================================
    | APPOINTMENT STORE() API TESTS
    |==========================================================================
    */

    //This is to test a normal appointment creation flow
    public function test_user_can_create_appointment(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */

        // Create the business
        $business = Business::factory()->create();

        // Create the user associated with the staff member
        $user = User::factory()->create();

        // Authenticate the user
        $this->actingAs($user, 'sanctum');

        // Create staff for the business
        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        // Create customer for the same business
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        // Create service for the same business
        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        // Assign the service to the staff member
        $staff->services()->attach($service->id);

        // Create staff working hours
        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4, // Thursday
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);
        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */
        $response = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                [
                    'service_id' => $service->id,
                ],
            ],
            'notes' => 'Test appointment',
        ]);

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(201);

        $response->assertJson([
            'message' => 'Appointment created successfully.',
        ]);

        $this->assertDatabaseHas('appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'status' => 'pending',
        ]);

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);
    }

    //This is to test that a user cannot create an appointment past the current date
    public function test_cannot_create_past_appointment(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4, // Thursday
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */
        $response = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => '2020-01-01 09:00:00', // This should fail
            'services' => [
                [
                    'service_id' => $service->id,
                ],
            ],
            'notes' => 'Past appointment test',
        ]);

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'start_datetime',
        ]);

        $this->assertDatabaseCount('appointments', 0);
    }

    //This is to test that that a staff w/ no assigned service cannot create an appointment w/ said specified service
    public function test_cannot_create_appointment_with_unassigned_service(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        /*
        |================================================
        | IMPORTANT:
        |------------------------------------------------
        | Do NOT assign the service to the staff member
        | for this test to work
        |
        */

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4, // Thursday
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */
        $response = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                [
                    'service_id' => $service->id,
                ],
            ],
            'notes' => 'Unassigned service test',
        ]);

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'services',
        ]);

        $this->assertDatabaseCount('appointments', 0);

        $this->assertDatabaseCount('appointment_services', 0);
    }

    //This is to test that a user cannot create an appointment with a staff member from another business
    public function test_cannot_create_appointment_with_staff_from_another_business(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $otherUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $otherBusiness->id,
            'user_id' => $otherUser->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */
        $response = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Cross-business staff test',
        ]);

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['staff_id']);

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_services', 0);
    }

    //This is to test that a user cannot create an appointment that conflicts with an existing appointment for the same staff member
    public function test_cannot_create_conflicting_appointment(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $firstService = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($firstService->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Create the first appointment
        $firstResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $firstService->id],
            ],
            'notes' => 'First appointment',
        ]);

        $firstResponse->assertStatus(201);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        |   attempt to create another appointment during the same slot
        */

        // This will overlap with the first appointment
        $secondAppointmentDate = $appointmentDate->copy()->addMinutes(15);

        $secondResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $secondAppointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $firstService->id],
            ],
            'notes' => 'Conflicting appointment',
        ]);

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $secondResponse->assertStatus(422);
        $secondResponse->assertJsonValidationErrors(['start_datetime']);

        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_services', 1);
    }

    //This is to test that when an appointment is cancelled, the time slot is released and can be booked again
    public function test_cancelled_appointment_releases_time_slot(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Create the first appointment
        $firstResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Appointment to be cancelled',
        ]);

        $firstResponse->assertStatus(201);

        $firstAppointment = Appointment::latest('id')->first();

        $this->assertNotNull($firstAppointment);

        // Cancel the first appointment
        $cancelResponse = $this->patchJson(
            "/api/appointments/{$firstAppointment->id}",
            [
                'status' => 'cancelled',
            ]
        );

        $cancelResponse->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $firstAppointment->id,
            'status' => 'cancelled',
        ]);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        |  Now attempt to create another appointment
        |  in the same time slot
        */
        $secondResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => '2026-10-01 09:00:00',
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Appointment after cancellation',
        ]);

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $secondResponse->assertStatus(201);

        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('appointment_services', 2);
    }

    //This is to test that a user can confirm a pending appointment
    public function test_can_confirm_pending_appointment(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Create pending appointment
        $createResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Status lifecycle test',
        ]);

        $createResponse->assertStatus(201);

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);
        $this->assertEquals('pending', $appointment->status);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        | confirm appointment
        */

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Appointment updated successfully.',
        ]);

        $response->assertJsonPath(
            'data.status',
            'confirmed'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
    }

    //This is to test that a user can complete a confirmed appointment
    public function test_can_complete_confirmed_appointment(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Create pending appointment
        $createResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Complete appointment test',
        ]);

        $createResponse->assertStatus(201);

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);
        $this->assertEquals('pending', $appointment->status);

        // Confirm appointment first
        $confirmResponse = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        $confirmResponse->assertStatus(200);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        | complete the confirmed appointment
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'completed',
            ]
        );

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Appointment updated successfully.',
        ]);

        $response->assertJsonPath(
            'data.status',
            'completed'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    //This is to test that a user can cancel a confirmed appointment
    public function test_can_cancel_confirmed_appointment(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Create pending appointment
        $createResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Cancel appointment test',
        ]);

        $createResponse->assertStatus(201);

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);
        $this->assertEquals('pending', $appointment->status);

        // Confirm appointment first
        $confirmResponse = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        $confirmResponse->assertStatus(200);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */

        //cancel the confirmed appointment
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'cancelled',
            ]
        );

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Appointment updated successfully.',
        ]);

        $response->assertJsonPath(
            'data.status',
            'cancelled'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    //This is to test that a user can mark a confirmed appointment as no-show
    public function test_can_mark_confirmed_appointment_as_no_show(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Create pending appointment
        $createResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'No-show appointment test',
        ]);

        $createResponse->assertStatus(201);

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);
        $this->assertEquals('pending', $appointment->status);

        // Confirm appointment first
        $confirmResponse = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        $confirmResponse->assertStatus(200);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */

        //mark the confirmed appointment as no-show
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'no_show',
            ]
        );

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Appointment updated successfully.',
        ]);

        $response->assertJsonPath(
            'data.status',
            'no_show'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'no_show',
        ]);
    }

    //This is to test that a user cannot transition an appointment's current status to an invalid status (e.g., from completed back to confirmed)
    public function test_rejects_invalid_status_transition(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        //dynamic date
        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Create pending appointment
        $createResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Invalid transition test',
        ]);

        $createResponse->assertStatus(201);

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);

        // Confirm appointment
        $confirmResponse = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        $confirmResponse->assertStatus(200);

        // Complete appointment
        $completeResponse = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'completed',
            ]
        );

        $completeResponse->assertStatus(200);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */

        //Attempt invalid transition: completed -> confirmed
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    /*
    |==========================================================================
    | APPOINTMENT INDEX() API TEST
    |==========================================================================
    */
    // This is to test that the appointment index endpoint only returns appointments for the requested business.
    public function test_appointment_index_only_returns_appointments_for_requested_business()
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

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $otherStaff = Staff::factory()->create([
            'business_id' => $otherBusiness->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $otherCustomer = Customer::factory()->create([
            'business_id' => $otherBusiness->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $otherAppointmentDate = $appointmentDate
            ->copy()
            ->addDay();

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $otherService = Service::factory()->create([
            'business_id' => $otherBusiness->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);
        $otherStaff->services()->attach($otherService->id);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        $otherAppointment = Appointment::create([
            'business_id' => $otherBusiness->id,
            'customer_id' => $otherCustomer->id,
            'staff_id' => $otherStaff->id,
            'start_datetime' => $otherAppointmentDate,
            'end_datetime' => $otherAppointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        AppointmentService::create([
            'appointment_id' => $otherAppointment->id,
            'service_id' => $otherService->id,
            'price' => $otherService->price,
            'currency' => $otherBusiness->currency,
            'duration_minutes' => $otherService->duration_minutes,
            'buffer_minutes' => $otherService->buffer_minutes,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.id',
            $appointment->id
        );

        $response->assertJsonMissing([
            'id' => $otherAppointment->id,
        ]);
    }

    // This is to test that the appointment index endpoint can filter appointments by staff_id.
    public function test_appointment_index_can_filter_by_staff()
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

        $otherStaff = Staff::factory()->create([
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

        $staff->services()->attach($service->id);
        $otherStaff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $otherAppointmentDate = $appointmentDate
            ->copy()
            ->setTime(11, 0);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        $otherAppointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $otherStaff->id,
            'start_datetime' => $otherAppointmentDate,
            'end_datetime' => $otherAppointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        AppointmentService::create([
            'appointment_id' => $otherAppointment->id,
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
            "/api/appointments?business_id={$business->id}&staff_id={$staff->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.id',
            $appointment->id
        );

        $response->assertJsonPath(
            'data.0.staff_id',
            $staff->id
        );

        $response->assertJsonMissing([
            'id' => $otherAppointment->id,
        ]);
    }

    // This is to test that the appointment index endpoint can filter appointments by customer_id.
    public function test_appointment_index_can_filter_by_customer()
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

        $otherCustomer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $otherAppointmentDate = $appointmentDate
            ->copy()
            ->setTime(11, 0);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        $otherAppointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $otherCustomer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $otherAppointmentDate,
            'end_datetime' => $otherAppointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        AppointmentService::create([
            'appointment_id' => $otherAppointment->id,
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
            "/api/appointments?business_id={$business->id}&customer_id={$customer->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.id',
            $appointment->id
        );

        $response->assertJsonPath(
            'data.0.customer_id',
            $customer->id
        );

        $response->assertJsonMissing([
            'id' => $otherAppointment->id,
        ]);
    }

    // This is to test that the appointment index endpoint can filter appointments by status.
    public function test_appointment_index_can_filter_by_status()
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $otherAppointmentDate = $appointmentDate
            ->copy()
            ->setTime(11, 0);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'confirmed',
        ]);

        $otherAppointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $otherAppointmentDate,
            'end_datetime' => $otherAppointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        AppointmentService::create([
            'appointment_id' => $otherAppointment->id,
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
            "/api/appointments?business_id={$business->id}&status=confirmed"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.id',
            $appointment->id
        );

        $response->assertJsonPath(
            'data.0.status',
            'confirmed'
        );

        $response->assertJsonMissing([
            'id' => $otherAppointment->id,
        ]);
    }

    // This is to test that the appointment index endpoint can filter appointments by date.
    public function test_appointment_index_can_filter_by_date()
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

        $staff->services()->attach($service->id);

        // Target date
        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Different date
        $otherAppointmentDate = $appointmentDate
            ->copy()
            ->addDay()
            ->setTime(9, 0);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        $otherAppointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $otherAppointmentDate,
            'end_datetime' => $otherAppointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        AppointmentService::create([
            'appointment_id' => $otherAppointment->id,
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
            "/api/appointments?business_id={$business->id}&date={$appointmentDate->toDateString()}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.id',
            $appointment->id
        );

        $response->assertJsonMissing([
            'id' => $otherAppointment->id,
        ]);
    }

    // This is to test that the appointment index endpoint rejects invalid status values.
    public function test_appointment_index_rejects_invalid_status()
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

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}&status=scheduled"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'status',
        ]);
    }

    // This is to test that the appointment index endpoint rejects invalid date formats.
    public function test_appointment_index_rejects_invalid_date_format()
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

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}&date=09/10/2026"
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

    // This is to test that an unauthenticated user cannot view appointments.
    public function test_unauthenticated_user_cannot_view_appointments()
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
            "/api/appointments?business_id={$business->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // This is to test that a user cannot view appointments from another business.
    public function test_user_cannot_view_appointments_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        // Give the user access to the other business only
        $user->businesses()->attach($otherBusiness->id);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    // This is to test that a staff user cannot view appointments from another business.
    public function test_staff_user_cannot_view_appointments_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        //now, test with a staff member
        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $otherBusiness->id,
        ]);

        $this->actingAs($staffUser, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    // This is to test that the appointment index endpoint requires a business_id parameter.
    public function test_appointment_index_requires_business_id()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson('/api/appointments');

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'business_id',
        ]);
    }

    // This is to test that the appointment index endpoint rejects a nonexistent business_id parameter.
    public function test_appointment_index_rejects_nonexistent_business_id()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            '/api/appointments?business_id=999999'
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'business_id',
        ]);
    }

    // This is to test that the appointment index endpoint rejects a nonexistent staff_id parameter.
    public function test_appointment_index_rejects_nonexistent_staff_id()
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

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}&staff_id=999999"
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

    // This is to test that the appointment index endpoint rejects a nonexistent customer_id parameter.
    public function test_appointment_index_rejects_nonexistent_customer_id()
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

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}&customer_id=999999"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'customer_id',
        ]);
    }

    // This is to test that the appointment index endpoint rejects a non-integer staff_id parameter.
    public function test_appointment_index_rejects_non_integer_staff_id()
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

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}&staff_id=abc"
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

    // This is to test that the appointment index endpoint rejects a non-integer customer_id parameter.
    public function test_appointment_index_rejects_non_integer_customer_id()
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

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments?business_id={$business->id}&customer_id=abc"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'customer_id',
        ]);
    }

    // This is to test that the appointment index endpoint can combine multiple filters.
    public function test_appointment_index_can_combine_multiple_filters()
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

        $otherCustomer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $targetDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $matchingAppointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $targetDate,
            'end_datetime' => $targetDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        // Same business and staff, but different customer
        $differentCustomerAppointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $otherCustomer->id,
            'start_datetime' => $targetDate->copy()->addHours(2),
            'end_datetime' => $targetDate->copy()->addHours(2)->addMinutes(30),
            'status' => 'pending',
        ]);

        // Same business/staff/customer, but different status
        $differentStatusAppointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $targetDate->copy()->addHours(4),
            'end_datetime' => $targetDate->copy()->addHours(4)->addMinutes(30),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments"
                . "?business_id={$business->id}"
                . "&staff_id={$staff->id}"
                . "&customer_id={$customer->id}"
                . "&status=pending"
                . "&date={$targetDate->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        // assert that the returned appointment is the matching one, and not the other two
        $response->assertJsonPath(
            'data.0.id',
            $matchingAppointment->id
        );

        $response->assertJsonMissing([
            'id' => $differentCustomerAppointment->id,
        ]);

        $response->assertJsonMissing([
            'id' => $differentStatusAppointment->id,
        ]);
    }

    // This is to test that the appointment index endpoint returns empty data when a staff member has no matching appointments.
    public function test_appointment_index_returns_empty_data_when_staff_has_no_matching_appointments()
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

        $otherStaff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        // Appointment belongs to another staff member
        Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $otherStaff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments"
                . "?business_id={$business->id}"
                . "&staff_id={$staff->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    }

    // This is to test that the appointment index endpoint returns empty data when a customer has no matching appointments.
    public function test_appointment_index_returns_empty_data_when_customer_has_no_matching_appointments()
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

        $otherCustomer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        // Appointment belongs to another customer
        Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $otherCustomer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments"
                . "?business_id={$business->id}"
                . "&customer_id={$customer->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    }

    // This is to test that the appointment index endpoint returns empty data when the date input has no matching appointments.
    public function test_appointment_index_returns_empty_data_when_date_has_no_matching_appointments()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $requestedDate = $appointmentDate
            ->copy()
            ->addDay();

        Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments"
                . "?business_id={$business->id}"
                . "&date={$requestedDate->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    }

    // This is to test that the appointment index endpoint returns empty data when the status has no matching appointments.
    public function test_appointment_index_returns_empty_data_when_status_has_no_matching_appointments()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        // Only a pending appointment exists
        Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments"
                . "?business_id={$business->id}"
                . "&status=completed"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    }

    // This is to test that the appointment index endpoint returns empty data when the combined filters has no matching appointments.
    public function test_appointment_index_returns_empty_data_when_combined_filters_match_nothing()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments"
                . "?business_id={$business->id}"
                . "&staff_id={$staff->id}"
                . "&customer_id={$customer->id}"
                . "&status=confirmed"
                . "&date={$appointmentDate->format('Y-m-d')}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    }

    /*
    |==========================================================================
    | APPOINTMENT SHOW() API TEST
    |==========================================================================
    */

    // This is to test that a user can view a specific appointment from the respective business
    public function test_user_can_view_appointment_from_their_business()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments/{$appointment->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.id',
            $appointment->id
        );

        $response->assertJsonPath(
            'data.business_id',
            $business->id
        );

        $response->assertJsonPath(
            'data.staff_id',
            $staff->id
        );

        $response->assertJsonPath(
            'data.customer_id',
            $customer->id
        );
    }

    // This is to test that an admin cannot view a specific appointment from another business
    public function test_admin_cannot_view_appointment_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $business = Business::factory()->create();

        $otherBusiness = Business::factory()->create();

        // Give the admin access to the first business only.
        $admin->businesses()->attach($business->id);

        $staff = Staff::factory()->create([
            'business_id' => $otherBusiness->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $otherBusiness->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $otherBusiness->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments/{$appointment->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    // This is to test that a staff member cannot view a specific appointment from another business
    public function test_staff_user_cannot_view_appointment_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $staffBusiness = Business::factory()->create();
        $appointmentBusiness = Business::factory()->create();

        Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $staffBusiness->id,
        ]);

        $appointmentStaff = Staff::factory()->create([
            'business_id' => $appointmentBusiness->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $appointmentBusiness->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $appointmentBusiness->id,
            'staff_id' => $appointmentStaff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($staffUser, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments/{$appointment->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);
    }

    // This is to test that unauthenticated ysers cannot view the specific appointment
    public function test_unauthenticated_user_cannot_view_specific_appointment()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments/{$appointment->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);
    }

    // This is to test that a non-existent appointment does not return any data
    public function test_viewing_nonexistent_appointment_returns_not_found()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        // Use an ID that does not exist.
        $nonexistentAppointmentId = 999999;

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments/{$nonexistentAppointmentId}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    // This is to test that the specific appointment includes the customer, staff and service id in the data
    public function test_appointment_show_includes_customer_staff_and_services()
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
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
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
            "/api/appointments/{$appointment->id}"
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.customer.id',
            $customer->id
        );

        $response->assertJsonPath(
            'data.staff.id',
            $staff->id
        );

        $response->assertJsonPath(
            'data.appointment_services.0.service.id',
            $service->id
        );
    }

    // This is to test that a user cannot view an appointment from another business
    public function test_user_cannot_view_appointment_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $userBusiness = Business::factory()->create();
        $appointmentBusiness = Business::factory()->create();

        // User belongs to a different business.
        $user->businesses()->attach($userBusiness->id);

        $staff = Staff::factory()->create([
            'business_id' => $appointmentBusiness->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $appointmentBusiness->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $appointmentBusiness->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->getJson(
            "/api/appointments/{$appointment->id}"
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
    | APPOINTMENT UPDATE() API TEST
    |==========================================================================
    */

    // This is to test that a user can update the appointment notes
    public function test_user_can_update_appointment_notes()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
            'notes' => 'Original notes',
        ]);

        $this->actingAs($user, 'sanctum');


        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'notes' => 'Updated appointment notes',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.notes',
            'Updated appointment notes'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'notes' => 'Updated appointment notes',
            'status' => 'pending',
        ]);
    }

    // This is to test that a user can update the status of a "pending" appointment to "confirmed"
    public function test_user_can_confirm_pending_appointment()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.status',
            'confirmed'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
    }

    // This is to test that a user can update a "confirmed" appointment to "completed"
    public function test_user_can_complete_confirmed_appointment()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'completed',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.status',
            'completed'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    // This is to test that a user can update a "confirmed" appointment to "cancelled"
    public function test_user_can_cancel_confirmed_appointment()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'cancelled',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.status',
            'cancelled'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    // This is to test that a user can update a "confirmed" appointment as "no show"
    public function test_user_can_mark_confirmed_appointment_as_no_show()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'no_show',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.status',
            'no_show'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'no_show',
        ]);
    }

    // This is to test that the update rejects invalid status transitions
    public function test_appointment_update_rejects_invalid_status_transition()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'completed',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'cancelled',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        // Being specific here 
        $response->assertJsonPath(
            'message',
            "The appointment cannot transition from 'completed' to 'cancelled'."
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    // This is to test that an update rejects invalid status value
    public function test_appointment_update_rejects_invalid_status_value()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'scheduled',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'status',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    // This is to test that a user cannot an appointment from another business
    public function test_user_cannot_update_appointment_from_another_business()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $userBusiness = Business::factory()->create();
        $appointmentBusiness = Business::factory()->create();

        $user->businesses()->attach($userBusiness->id);

        $staff = Staff::factory()->create([
            'business_id' => $appointmentBusiness->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $appointmentBusiness->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $appointmentBusiness->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    // This is to test that unauthenticated users cannot access the update() method
    public function test_unauthenticated_user_cannot_update_appointment()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    // This is to test that a non-existing appointment returns a 404 error
    public function test_updating_nonexistent_appointment_returns_not_found()
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $nonexistentAppointmentId = 999999;

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$nonexistentAppointmentId}",
            [
                'status' => 'confirmed',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(404);
    }

    // This is to test that an empty request will not update any data from the selected appointment
    public function test_appointment_update_rejects_empty_request()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
            'notes' => 'Original notes',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            []
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.id',
            $appointment->id
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
            'notes' => 'Original notes',
        ]);
    }

    // This is to test that a note can be optionally updated to NULL
    public function test_appointment_notes_can_be_cleared()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
            'notes' => 'Customer requested a specific treatment.',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'notes' => null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'notes' => null,
        ]);
    }

    // This is to test that an invalid note types is rejected
    public function test_appointment_update_rejects_invalid_notes_type()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'notes' => ['invalid'],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'notes',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    // This is to test that updating ONLY the notes preserves the current status of the selected appointment
    public function test_notes_update_preserves_appointment_status()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'confirmed',
            'notes' => 'Original notes',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'notes' => 'Updated notes',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'notes' => 'Updated notes',
        ]);
    }

    // This is to test that updating ONLY the status preserves the appointment details (e.g: ownership)
    public function test_status_update_preserves_appointment_details()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $endDate = $appointmentDate->copy()->addMinutes(30);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $endDate,
            'status' => 'pending',
            'notes' => 'Keep these notes.',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $endDate->format('Y-m-d H:i:s'),
            'status' => 'confirmed',
            'notes' => 'Keep these notes.',
        ]);
    }

    // This is to test that updating ONLY the status preserves the appointment notes
    public function test_status_update_preserves_appointment_notes()
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

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
            'notes' => 'Important customer notes.',
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'status' => 'confirmed',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.notes',
            'Important customer notes.'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'notes' => 'Important customer notes.',
        ]);
    }

    // This is to test that a staff user can update an appointment in their own business
    public function test_staff_user_can_update_appointment_in_their_business()
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

        $staff = Staff::factory()->create([
            'user_id' => $staffUser->id,
            'business_id' => $business->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(10, 0, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(30),
            'status' => 'pending',
            'notes' => 'Original notes',
        ]);

        $this->actingAs($staffUser, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}",
            [
                'notes' => 'Updated by staff.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.notes',
            'Updated by staff.'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'notes' => 'Updated by staff.',
            'status' => 'pending',
        ]);
    }

    /*
    |==========================================================================
    | APPOINTMENT RESCHEDULE() API TESTS
    |==========================================================================
    */
    
    //This is to test that a user can reschedule an existing appointment to a new valid time slot
    public function test_can_reschedule_appointment(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        //dynamic date
        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        //dynamic reschedule date
        $rescheduleDate = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        // Create original appointment
        $createResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Reschedule test',
        ]);

        $createResponse->assertStatus(201);

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);

        // Original appointment should occupy 09:00 -> 09:45 (30 mins service + 15 mins buffer)
        $this->assertEquals(
            $appointmentDate->format('Y-m-d H:i:s'),
            $appointment->start_datetime->format('Y-m-d H:i:s')
        );

        $expectedOriginalEnd = $appointmentDate
            ->copy()
            ->addMinutes(45);

        $this->assertEquals(
            $expectedOriginalEnd->format('Y-m-d H:i:s'),
            $appointment->end_datetime->format('Y-m-d H:i:s')
        );

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */
        //reschedule to 10:00
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $rescheduleDate->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Appointment rescheduled successfully.',
        ]);

        $expectedRescheduleEnd = $rescheduleDate
            ->copy()
            ->addMinutes(45);

        $this->assertDatabaseHas(
            'appointments',
            [
                'id' => $appointment->id,
                'staff_id' => $staff->id,
                'start_datetime' => $rescheduleDate->format('Y-m-d H:i:s'),
                'end_datetime' => $expectedRescheduleEnd->format('Y-m-d H:i:s'),
            ]
        );

        // Ensure the appointment services were preserved
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);
    }

    //This is to test that a user cannot reschedule an existing appointment to a past time slot
    public function test_cannot_reschedule_appointment_to_past(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
        */
        $business = Business::factory()->create();

        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user, 'sanctum');

        $staff = Staff::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::factory()->create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        // Create a valid future appointment
        $createResponse = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'services' => [
                ['service_id' => $service->id],
            ],
            'notes' => 'Past reschedule test',
        ]);

        $createResponse->assertStatus(201);

        $appointment = Appointment::latest('id')->first();

        $this->assertNotNull($appointment);

        // Create a dynamically generated past date
        $pastDate = Carbon::now()
            ->subDay()
            ->setTime(9, 0);

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */
        // attempt to reschedule into the past
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $pastDate->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'start_datetime',
        ]);

        // Ensure the original appointment was not changed
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
        ]);
    }

    //This is to test that a user cannot reschedule an existing appointment to a time slot that conflicts with another appointment for the same staff member
    public function test_cannot_reschedule_appointment_to_conflicting_slot(): void
    {
        /*
        |---------------------------------------------
        | CREATE
        |---------------------------------------------
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        // First appointment: 09:00 - 09:45
        $firstAppointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $firstAppointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        // Second appointment: 10:00 - 10:45
        $secondAppointmentStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        $secondAppointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $secondAppointmentStart,
            'end_datetime' => $secondAppointmentStart->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $secondAppointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        $this->actingAs($user, 'sanctum');

        /*
        |---------------------------------------------
        | TEST
        |---------------------------------------------
        */
        // Try to move the second appointment into the first appointment's time.
        $conflictingStart = $appointmentDate
            ->copy()
            ->setTime(9, 15);

        $response = $this->patchJson(
            "/api/appointments/{$secondAppointment->id}/reschedule",
            [
                'start_datetime' => $conflictingStart->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |---------------------------------------------
        | ASSERT
        |---------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJson(['message' => 'The selected time conflicts with another appointment.',]);

        // The second appointment should remain at its original time.
        $this->assertDatabaseHas('appointments', [
            'id' => $secondAppointment->id,
            'start_datetime' => $secondAppointmentStart->format('Y-m-d H:i:s'),
            'end_datetime' => $secondAppointmentStart
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that a user can reschedule an existing appointment to a different staff member,
    // provided the new staff member is available and provides the service.
    public function test_can_reschedule_appointment_to_different_staff(): void
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

        //two staff members for the same business
        $originalStaff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $newStaff = Staff::factory()->create([
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

        // Both staff members provide the service.
        $originalStaff->services()->attach($service->id);
        $newStaff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Original staff works on this day.
        StaffHour::create([
            'staff_id' => $originalStaff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        // New staff also works on this day.
        StaffHour::create([
            'staff_id' => $newStaff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        // Create the original appointment.
        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $originalStaff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
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
        $newStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'staff_id' => $newStaff->id,
                'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Appointment rescheduled successfully.',
            'data' => [
                'id' => $appointment->id,
                'staff_id' => $newStaff->id,
            ],
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_id' => $newStaff->id,
            'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            'end_datetime' => $newStart
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);

        // The appointment service snapshot should remain unchanged.
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);
    }

    // This is to test that a user cannot reschedule an existing appointment to a different staff member who does not provide the service.
    public function test_cannot_reschedule_appointment_to_staff_without_service(): void
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

        $originalStaff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $newStaff = Staff::factory()->create([
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

        // Only the original staff provides the service.
        $originalStaff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        // Both staff members are working on this day.
        StaffHour::create([
            'staff_id' => $originalStaff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        StaffHour::create([
            'staff_id' => $newStaff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $originalStaff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
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
        $newStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'staff_id' => $newStaff->id,
                'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        // The appointment should not be moved to the incompatible staff member.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_id' => $originalStaff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that a user cannot reschedule an existing appointment to a staff member from a different business.
    public function test_cannot_reschedule_appointment_to_staff_from_another_business(): void
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

        $originalStaff = Staff::factory()->create([
            'business_id' => $business->id,
        ]);

        $otherBusinessStaff = Staff::factory()->create([
            'business_id' => $otherBusiness->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $originalStaff->services()->attach($service->id);

        // Give the original staff working hours.
        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $originalStaff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        // The other-business staff also has working hours.
        // This ensures the test specifically checks the business boundary,
        // rather than failing because the staff has no availability.
        StaffHour::create([
            'staff_id' => $otherBusinessStaff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $originalStaff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
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
        $newStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'staff_id' => $otherBusinessStaff->id,
                'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        // The appointment must remain assigned to its original staff.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'business_id' => $business->id,
            'staff_id' => $originalStaff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that a user can reschedule an existing appointment without changing the staff member.
    public function test_can_reschedule_appointment_without_changing_staff(): void
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
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

        // Do not provide staff_id.
        $newStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'id' => $appointment->id,
                'staff_id' => $staff->id,
            ],
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_id' => $staff->id,
            'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            'end_datetime' => $newStart
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);

        // Verify the service snapshot was preserved.
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);
    }

    // This is to test that a user cannot reschedule an existing appointment if they belong to a different business than the appointment's business.
    public function test_cannot_reschedule_appointment_from_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        $businessAdmin = User::factory()->create([
            'role' => 'admin',
        ]);

        $otherBusinessAdmin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Associate each admin with their own business.
        $businessAdmin->businesses()->attach($business->id);
        $otherBusinessAdmin->businesses()->attach($otherBusiness->id);

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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        // Authenticate as an admin belonging to another business.
        $this->actingAs($otherBusinessAdmin, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */

        $newStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */

        $response->assertStatus(403);

        // Verify the appointment was not modified.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that a staff user cannot reschedule an existing appointment if they belong to a different business than the appointment's business.
    public function test_staff_user_cannot_reschedule_appointment_from_another_business(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        $business = Business::factory()->create();
        $otherBusiness = Business::factory()->create();

        // Appointment belongs to Business A.
        $appointmentStaffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        $appointmentStaff = Staff::factory()->create([
            'user_id' => $appointmentStaffUser->id,
            'business_id' => $business->id,
        ]);

        // Authenticated staff user belongs to Business B.
        $otherStaffUser = User::factory()->create([
            'role' => 'staff',
        ]);

        Staff::factory()->create([
            'user_id' => $otherStaffUser->id,
            'business_id' => $otherBusiness->id,
        ]);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $service = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $appointmentStaff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $appointmentStaff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $appointmentStaff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        // Authenticate as staff belonging to another business.
        $this->actingAs($otherStaffUser, 'sanctum');

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $newStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(403);

        // Verify that the appointment remains unchanged.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'business_id' => $business->id,
            'staff_id' => $appointmentStaff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that an unauthenticated user cannot reschedule an existing appointment.
    public function test_unauthenticated_user_cannot_reschedule_appointment(): void
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */

        // No actingAs() — request is intentionally unauthenticated.
        $newStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(401);

        // Verify that the appointment was not modified.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'business_id' => $business->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that a user cannot reschedule an existing appointment without providing a new start_datetime.
    public function test_cannot_reschedule_appointment_without_start_datetime(): void
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
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
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            []
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'start_datetime',
        ]);

        // Appointment should remain unchanged.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that a user cannot reschedule an existing appointment to a staff member who does not exist.
    public function test_cannot_reschedule_appointment_with_invalid_staff_id(): void
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
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
        $newStart = $appointmentDate
            ->copy()
            ->setTime(10, 0);

        // Use an ID that does not exist.
        $invalidStaffId = 999999;

        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'staff_id' => $invalidStaffId,
                'start_datetime' => $newStart->format('Y-m-d H:i:s'),
            ]
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

        // Appointment should remain completely unchanged.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that a user cannot reschedule an existing appointment with an invalid start_datetime format.
    public function test_cannot_reschedule_appointment_with_invalid_start_datetime(): void
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
        ]);

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'pending',
        ]);

        AppointmentService::create([
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
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => 'not-a-valid-date',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'start_datetime',
        ]);

        // Appointment should remain unchanged.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate->format('Y-m-d H:i:s'),
            'end_datetime' => $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    // This is to test that a user can reschedule an existing appointment that has multiple services attached to it 
    // (and ensure the service snapshots are preserved).
    public function test_can_reschedule_multi_service_appointment()
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

        $service1 = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $service2 = Service::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 60,
            'buffer_minutes' => 10,
        ]);

        $staff->services()->attach([
            $service1->id,
            $service2->id,
        ]);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $rescheduleDate = $appointmentDate
            ->copy()
            ->setTime(11, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $this->actingAs($user, 'sanctum');

        // Total:
        // Service 1 = 30 + 15 = 45 minutes
        // Service 2 = 60 + 10 = 70 minutes
        // Total = 115 minutes
        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(115),
            'status' => 'pending',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service1->id,
            'price' => $service1->price,
            'currency' => $business->currency,
            'duration_minutes' => $service1->duration_minutes,
            'buffer_minutes' => $service1->buffer_minutes,
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service2->id,
            'price' => $service2->price,
            'currency' => $business->currency,
            'duration_minutes' => $service2->duration_minutes,
            'buffer_minutes' => $service2->buffer_minutes,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $rescheduleDate->toDateTimeString(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Appointment rescheduled successfully.',
            ]);

        $appointment->refresh();

        $this->assertEquals(
            $rescheduleDate->toDateTimeString(),
            $appointment->start_datetime->toDateTimeString()
        );

        $this->assertEquals(
            $rescheduleDate
                ->copy()
                ->addMinutes(115)
                ->toDateTimeString(),
            $appointment->end_datetime->toDateTimeString()
        );

        // Verify both service snapshots were preserved
        $this->assertDatabaseCount('appointment_services', 2);

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $service1->id,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $service2->id,
            'duration_minutes' => 60,
            'buffer_minutes' => 10,
        ]);
    }

    // This is to test that a user can reschedule an existing appointment and the appointment status remains unchanged.
    public function test_reschedule_preserves_appointment_status()
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $rescheduleDate = $appointmentDate
            ->copy()
            ->setTime(11, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $this->actingAs($user, 'sanctum');

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'confirmed',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $rescheduleDate->toDateTimeString(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Appointment rescheduled successfully.',
                'data' => [
                    'status' => 'confirmed',
                ],
            ]);

        $appointment->refresh();

        $this->assertEquals('confirmed', $appointment->status);

        $this->assertEquals(
            $rescheduleDate->toDateTimeString(),
            $appointment->start_datetime->toDateTimeString()
        );

        $this->assertEquals(
            $rescheduleDate
                ->copy()
                ->addMinutes(45)
                ->toDateTimeString(),
            $appointment->end_datetime->toDateTimeString()
        );
    }

    // This is to test that a user cannot reschedule an existing appointment if the appointment has already been completed.
    public function test_cannot_reschedule_completed_appointment()
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $rescheduleDate = $appointmentDate
            ->copy()
            ->setTime(11, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $this->actingAs($user, 'sanctum');

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'completed',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $rescheduleDate->toDateTimeString(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Only pending and confirmed appointments can be rescheduled.',
            ]);

        $appointment->refresh();

        // Verify appointment was not changed
        $this->assertEquals('completed', $appointment->status);

        $this->assertEquals(
            $appointmentDate->toDateTimeString(),
            $appointment->start_datetime->toDateTimeString()
        );

        $this->assertEquals(
            $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->toDateTimeString(),
            $appointment->end_datetime->toDateTimeString()
        );
    }

    // This is to test that a user cannot reschedule an existing appointment if the appointment has already been marked as cancelled.
    public function test_cannot_reschedule_cancelled_appointment()
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $rescheduleDate = $appointmentDate
            ->copy()
            ->setTime(11, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $this->actingAs($user, 'sanctum');

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'cancelled',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $rescheduleDate->toDateTimeString(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Only pending and confirmed appointments can be rescheduled.',
            ]);

        $appointment->refresh();

        // Verify appointment was not changed
        $this->assertEquals('cancelled', $appointment->status);

        $this->assertEquals(
            $appointmentDate->toDateTimeString(),
            $appointment->start_datetime->toDateTimeString()
        );

        $this->assertEquals(
            $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->toDateTimeString(),
            $appointment->end_datetime->toDateTimeString()
        );
    }

    // This is to test that a user cannot reschedule an existing appointment if the appointment has already been marked as no-show.
    public function test_cannot_reschedule_no_show_appointment()
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

        $staff->services()->attach($service->id);

        $appointmentDate = Carbon::now()
            ->next(Carbon::THURSDAY)
            ->setTime(9, 0);

        $rescheduleDate = $appointmentDate
            ->copy()
            ->setTime(11, 0);

        StaffHour::create([
            'staff_id' => $staff->id,
            'day_of_week' => $appointmentDate->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_off' => false,
        ]);

        $this->actingAs($user, 'sanctum');

        $appointment = Appointment::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => $appointmentDate,
            'end_datetime' => $appointmentDate->copy()->addMinutes(45),
            'status' => 'no_show',
        ]);

        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
            'currency' => $business->currency,
            'duration_minutes' => $service->duration_minutes,
            'buffer_minutes' => $service->buffer_minutes,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST
        |--------------------------------------------------------------------------
        */
        $response = $this->patchJson(
            "/api/appointments/{$appointment->id}/reschedule",
            [
                'start_datetime' => $rescheduleDate->toDateTimeString(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASSERT
        |--------------------------------------------------------------------------
        */
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Only pending and confirmed appointments can be rescheduled.',
            ]);

        $appointment->refresh();

        // Verify appointment was not changed
        $this->assertEquals('no_show', $appointment->status);

        $this->assertEquals(
            $appointmentDate->toDateTimeString(),
            $appointment->start_datetime->toDateTimeString()
        );

        $this->assertEquals(
            $appointmentDate
                ->copy()
                ->addMinutes(45)
                ->toDateTimeString(),
            $appointment->end_datetime->toDateTimeString()
        );
    }
}
