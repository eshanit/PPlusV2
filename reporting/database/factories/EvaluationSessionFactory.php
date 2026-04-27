<?php

namespace Database\Factories;

use App\Models\EvaluationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationSession>
 */
class EvaluationSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'evaluation_group_id' => fake()->uuid(),
            'mentee_id' => UserFactory::new()->create()->id,
            'evaluator_id' => UserFactory::new()->create()->id,
            'tool_id' => ToolFactory::new()->create()->id,
            'eval_date' => fake()->date(),
            'facility_id' => FacilityFactory::new()->create()->id,
            'district_id' => FacilityFactory::new()->create()->district_id,
            'phase' => fake()->randomElement(['initial_intensive', 'ongoing', 'supervision']),
        ];
    }
}
