<?php

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $postedDate = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'tenant_id' => fn (): int => Tenant::create([
                'name' => fake()->company(),
                'slug' => Str::slug(fake()->unique()->company()),
            ])->id,
            'department_id' => null,
            'title' => fake()->jobTitle(),
            'location' => fake()->city(),
            'employment_type' => fake()->randomElement(['tetap', 'kontrak', 'magang', 'harian']),
            'quota' => fake()->numberBetween(1, 10),
            'status' => fake()->randomElement(['open', 'closed']),
            'description' => fake()->optional()->paragraph(),
            'posted_date' => $postedDate,
            'closing_date' => fake()->dateTimeBetween($postedDate, '+2 months'),
        ];
    }

    /**
     * Indicate that the posting is open for applicants.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'open',
        ]);
    }
}
