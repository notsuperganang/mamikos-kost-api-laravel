<?php

namespace Database\Factories;

use App\Models\Kost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kost>
 */
class KostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->owner(),
            'name' => 'Kost '.fake()->lastName(),
            'location' => fake()->randomElement(['Yogyakarta', 'Sleman, Yogyakarta', 'Bandung', 'Jakarta Selatan', 'Depok']),
            'price' => fake()->numberBetween(5, 40) * 100_000,
            'available_rooms' => fake()->numberBetween(0, 6),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
