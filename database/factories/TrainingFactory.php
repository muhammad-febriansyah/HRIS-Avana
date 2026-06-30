<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'tenant_id' => fn (): int => Tenant::create([
                'name' => fake()->company(),
                'slug' => Str::slug(fake()->unique()->company()),
            ])->id,
            'title' => fake()->sentence(3),
            'category' => fake()->randomElement(['Teknis', 'Manajerial', 'Soft Skill', 'Kepatuhan']),
            'type' => fake()->randomElement(['internal', 'external', 'online']),
            'start_date' => $startDate,
            'end_date' => fake()->dateTimeBetween($startDate, '+2 months'),
            'cost' => fake()->randomElement([0, 500_000, 1_500_000, 3_000_000]),
            'instructor' => fake()->optional()->name(),
            'quota' => fake()->optional()->numberBetween(10, 50),
            'status' => fake()->randomElement(['planned', 'ongoing', 'completed']),
            'description' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Indicate that the training is currently running.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'ongoing',
        ]);
    }
}
