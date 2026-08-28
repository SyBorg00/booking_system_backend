<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => fake()->phoneNumber(),
            'position' => fake()->randomElement([
                'Staff',
                'Manager',
                'Specialist',
            ]),
            'photo' => null,
            'status' => 'active',
        ];
    }
}
