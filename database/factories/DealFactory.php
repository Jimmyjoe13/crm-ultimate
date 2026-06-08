<?php

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class DealFactory extends Factory
{
    public function definition(): array
    {
        $pipeline = Pipeline::firstOrCreate(['name' => 'Default']);
        $stage    = PipelineStage::firstOrCreate(
            ['pipeline_id' => $pipeline->id, 'name' => 'New'],
            ['position' => 1, 'is_won' => false, 'is_lost' => false]
        );

        return [
            'name'              => $this->faker->bs().' — Deal',
            'amount'            => $this->faker->randomFloat(2, 1000, 150000),
            'currency'          => 'EUR',
            'close_date'        => $this->faker->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'status'            => $this->faker->randomElement(['open', 'open', 'open', 'won', 'lost']),
            'custom_values'     => [],
            'pipeline_id'       => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ];
    }
}
