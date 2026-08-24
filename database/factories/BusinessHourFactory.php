<?php

namespace Database\Factories;

use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->numberBetween(0, 6), //0 = Sunday ... 6 = Saturday
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'is_closed' => false,
        ];
    }
}
