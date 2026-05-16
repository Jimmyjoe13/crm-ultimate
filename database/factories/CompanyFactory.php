<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company();
        $domain = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name)).'.com';

        return [
            'name' => $name,
            'domain' => $domain,
            'industry' => $this->faker->randomElement([
                'SaaS', 'Retail', 'Finance', 'Healthcare', 'Manufacturing',
                'Consulting', 'Media', 'Education', 'Real Estate', 'Logistics',
            ]),
            'phone' => $this->faker->phoneNumber(),
            'website' => 'https://www.'.$domain,
            'city' => $this->faker->city(),
            'country' => $this->faker->randomElement(['France', 'Belgique', 'Suisse', 'Canada']),
            'lifecycle_stage' => $this->faker->randomElement(['lead', 'mql', 'sql', 'customer']),
            'lead_status' => $this->faker->randomElement(['new', 'open', 'in_progress', null]),
            'custom_values' => [],
        ];
    }
}
