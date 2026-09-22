<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    //This is to test the appointment creation functionality
    public function test_user_can_create_appointment(): void
    {
        // Arrange

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

        // Act

        $response = $this->postJson('/api/appointments', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'start_datetime' => '2026-10-01 09:00:00',
            'services' => [
                [
                    'service_id' => $service->id,
                ],
            ],
            'notes' => 'Test appointment',
        ]);

        // Assert

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

    //This is to test that a user cannot create an appointment in the past
    public function test_cannot_create_past_appointment(): void
    {
        // Arrange
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

        // Act
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

        // Assert
        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'start_datetime',
        ]);

        $this->assertDatabaseCount('appointments', 0);
    }
}
