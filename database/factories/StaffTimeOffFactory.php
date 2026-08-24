<?php

namespace Database\Factories;

use App\Models\StaffTimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffTimeOffFactory extends Factory
{

    public function definition(): array
    {
        $start = fake()->dateTimeBetween(
            'now',
            '+30 days'
        );

        $end = (clone $start);
        $end->modify('+2 hours');

        return [
            'start_datetime' => $start,
            'end_datetime' => $end,
            'reason' => fake()->randomElement([
                'Personal leave',
                'Vacation',
                'Appointment',
                'Day off',
            ]),
        ];
    }
}
