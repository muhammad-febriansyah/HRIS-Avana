<?php

namespace Database\Factories;

use App\Models\Training;
use App\Models\TrainingEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingEnrollment>
 */
class TrainingEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $training = Training::factory();

        return [
            'tenant_id' => fn (array $attributes): int => Training::find($attributes['training_id'])->tenant_id,
            'training_id' => $training,
            'employee_id' => null,
            'status' => 'enrolled',
            'score' => null,
            'certificate_no' => null,
            'completed_date' => null,
        ];
    }

    /**
     * Indicate that the participant has completed the training.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
            'score' => fake()->randomFloat(2, 60, 100),
            'certificate_no' => 'CERT-'.fake()->unique()->numberBetween(1000, 9999),
            'completed_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
