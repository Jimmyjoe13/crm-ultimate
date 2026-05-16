<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'job_title' => $this->faker->jobTitle(),
            'lifecycle_stage' => $this->faker->randomElement(['lead', 'mql', 'sql', 'opportunity', 'customer']),
            'lead_status' => $this->faker->randomElement(['new', 'open', 'in_progress', null]),
            'custom_values' => [],
        ];
    }
}
