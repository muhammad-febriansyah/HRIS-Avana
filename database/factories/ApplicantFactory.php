<?php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $posting = JobPosting::factory();

        return [
            'tenant_id' => fn (array $attributes): int => JobPosting::find($attributes['job_posting_id'])->tenant_id,
            'job_posting_id' => $posting,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'source' => fake()->randomElement(['LinkedIn', 'JobStreet', 'Referral', 'Website', 'Walk-in']),
            'stage' => 'applied',
            'applied_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
