<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Pas de UserFactory dans ce projet → on crée un owner via createWithRole si non fourni.
            'owner_id' => fn () => User::createWithRole([
                'name' => $this->faker->name(),
                'email' => $this->faker->unique()->safeEmail(),
                'password' => bcrypt('password'),
                'role' => User::ROLE_SALES,
            ])->id,
            'name' => $this->faker->words(3, true),
            'subject' => 'Bonjour {{first_name}}',
            'body' => "Bonjour {{first_name}},\n\nÀ propos de {{company}}…\n\n{{owner_name}}",
            'category' => $this->faker->randomElement(['Relance', 'Prospection', 'Suivi', null]),
            'is_shared' => false,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn () => ['is_shared' => true]);
    }
}
