<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\User;
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
     * A completed review, with the full calibration record a completed review
     * is required to carry. Producing `status=completed` without a calibrator
     * would manufacture exactly the invalid state
     * {@see PerformanceReview::scopePublishable()} exists to exclude.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes): array {
            $finalScore = fake()->randomFloat(2, 60, 100);

            return [
                'self_score' => fake()->randomFloat(2, 60, 100),
                'manager_score' => $finalScore,
                'final_score' => $finalScore,
                'calibrated_score' => $finalScore,
                'calibrated_by' => User::factory()->state([
                    'tenant_id' => PerformanceCycle::find($attributes['cycle_id'])->tenant_id,
                ]),
                'calibrated_at' => now(),
                'status' => 'completed',
                'is_legacy' => false,
                'review_date' => fake()->dateTimeBetween('-1 month', 'now'),
            ];
        });
    }

    /**
     * A review carrying the pre-workflow shape: `completed` with a final score
     * but no calibration record. Quarantined via `is_legacy`, and therefore
     * never publishable — used to assert downstream consumers ignore it.
     */
    public function legacyCompleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'self_score' => null,
            'manager_score' => null,
            'final_score' => fake()->randomFloat(2, 60, 100),
            'calibrated_score' => null,
            'calibrated_by' => null,
            'calibrated_at' => null,
            'status' => 'completed',
            'is_legacy' => true,
            'review_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
