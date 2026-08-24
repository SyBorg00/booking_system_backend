<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class StaffHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_off' => false,
        ];
    }
}
