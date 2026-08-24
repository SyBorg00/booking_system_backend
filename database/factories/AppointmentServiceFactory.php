<?php

namespace Database\Factories;

use App\Models\AppointmentService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentService>
 */
class AppointmentServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price' => fake()->randomFloat(2, 200, 3000),
            'duration_minutes' => fake()->randomElement([
                30,
                45,
                60,
                90,
                120,
            ]),
            'currency' => 'PHP',
        ];
    }
}
