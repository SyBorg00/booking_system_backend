<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Haircut',
                'Hair Color',
                'Hair Treatment',
                'Manicure',
                'Pedicure',
                'Massage',
                'Facial',
                'Consultation',
            ]),

            'description' => fake()->sentence(),

            'price' => fake()->randomFloat(2, 200, 3000),

            'duration_minutes' => fake()->randomElement([
                30,
                45,
                60,
                90,
                120,
            ]),

            'buffer_minutes' => fake()->randomElement([
                0,
                10,
                15,
            ]),

            'status' => 'active',
        ];
    }
}
