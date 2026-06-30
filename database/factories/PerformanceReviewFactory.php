<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceReview>
 */
class PerformanceReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cycle = PerformanceCycle::factory();

        return [
            'tenant_id' => fn (array $attributes): int => PerformanceCycle::find($attributes['cycle_id'])->tenant_id,
            'cycle_id' => $cycle,
            'employee_id' => fn (array $attributes): int => Employee::create([
                'tenant_id' => PerformanceCycle::find($attributes['cycle_id'])->tenant_id,
                'employee_number' => 'EMP-'.fake()->unique()->numberBetween(10000, 99999),
                'full_name' => fake()->name(),
                'employment_status' => 'permanent',
                'status' => 'active',
            ])->id,
            'reviewer_id' => null,
            'self_score' => null,
            'manager_score' => null,
            'final_score' => null,
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
            'review_date' => null,
        ];
    }

    /**
     * Indicate that the review has been completed with final scores.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'self_score' => fake()->randomFloat(2, 60, 100),
            'manager_score' => fake()->randomFloat(2, 60, 100),
            'final_score' => fake()->randomFloat(2, 60, 100),
            'status' => 'completed',
            'review_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
