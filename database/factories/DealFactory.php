<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DealFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->bs().' — Deal',
            'amount' => $this->faker->randomFloat(2, 1000, 150000),
            'currency' => 'EUR',
            'close_date' => $this->faker->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['open', 'open', 'open', 'won', 'lost']),
            'custom_values' => [],
        ];
    }
}
