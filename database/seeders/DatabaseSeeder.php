<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffHour;
use App\Models\StaffTimeOff;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        --------------------------------------------------------------------------
            Business Section
        --------------------------------------------------------------------------
        */

        $business = Business::factory()->create([
            'name' => 'Demo Booking Business',
            'slug' => 'demo-booking-business',
        ]);

        /*
        --------------------------------------------------------------------------
            Business Hours Section
        --------------------------------------------------------------------------
        */

        for ($day = 0; $day <= 6; $day++) {
            BusinessHour::factory()->create([
                'business_id' => $business->id,
                'day_of_week' => $day,
                'is_closed' => in_array($day, [0]),
            ]);
        }

        /*
        --------------------------------------------------------------------------
            Services Section
        --------------------------------------------------------------------------
        */

        $services = Service::factory()
            ->count(5)
            ->create([
                'business_id' => $business->id,
            ]);

        /*
        --------------------------------------------------------------------------
            Staff Section
        --------------------------------------------------------------------------
        */

        $staffMembers = collect();

        for ($i = 0; $i < 3; $i++) {

            $user = User::factory()->create([
                'role' => 'staff',
                'status' => 'active',
            ]);

            $staff = Staff::factory()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
            ]);

            $staffMembers->push($staff);

            // Staff Services Subsection
            $staff->services()->attach(
                $services->random(rand(2, 4))->pluck('id')->toArray()
            );

            // Staff Hours Subsection
            for ($day = 1; $day <= 6; $day++) {
                StaffHour::factory()->create([
                    'staff_id' => $staff->id,
                    'day_of_week' => $day,
                    'is_off' => false,
                ]);
            }
        }

        /*
        --------------------------------------------------------------------------
            Staff Time Off Section
        --------------------------------------------------------------------------
        */

        StaffTimeOff::factory()->create([
            'staff_id' => $staffMembers->first()->id,
        ]);

        /*
        --------------------------------------------------------------------------
            Customers Section
        --------------------------------------------------------------------------
        */

        $customers = Customer::factory()
            ->count(10)
            ->create([
                'business_id' => $business->id,
            ]);

        /*
        --------------------------------------------------------------------------
            Appointments Section
        --------------------------------------------------------------------------
        */

        foreach ($customers->take(5) as $customer) {

            $staff = $staffMembers->random();

            $appointment = Appointment::factory()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'staff_id' => $staff->id,
            ]);

            /*
            | Attach 1-2 services to the appointment
            */

            $appointmentServices = $staff->services
                ->random(rand(1, min(2, $staff->services->count())));

            foreach ($appointmentServices as $service) {

                AppointmentService::factory()->create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $service->id,
                    'price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                    'currency' => $business->currency,
                ]);
            }
        }
    }
}
