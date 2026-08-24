<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween(
            '+1 day',
            '+30 days'
        );

        $end = (clone $start);
        $end->modify('+1 hour');

        return [
            'start_datetime' => $start,
            'end_datetime' => $end,

            'status' => fake()->randomElement([
                'pending',
                'confirmed',
                'completed',
                'cancelled',
                'no_show',
            ]),

            'notes' => fake()->optional()->sentence(),
        ];
    }
}
