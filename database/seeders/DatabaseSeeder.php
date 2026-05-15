<?php

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin CRM',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ],
        );

        $pipeline = Pipeline::query()->firstOrCreate([
            'name' => 'Default Sales Pipeline',
        ], [
            'is_default' => true,
        ]);

        $stages = [
            ['name' => 'Prospecting', 'position' => 10, 'probability' => 10],
            ['name' => 'Qualified', 'position' => 20, 'probability' => 30],
            ['name' => 'Proposal', 'position' => 30, 'probability' => 60],
            ['name' => 'Won', 'position' => 40, 'probability' => 100, 'is_won' => true],
            ['name' => 'Lost', 'position' => 50, 'probability' => 0, 'is_lost' => true],
        ];

        foreach ($stages as $stage) {
            $pipeline->stages()->firstOrCreate(['position' => $stage['position']], $stage);
        }
    }
}
