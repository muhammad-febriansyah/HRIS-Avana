<?php

namespace Database\Factories;

use App\Models\PerformanceCycle;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PerformanceCycle>
 */
class PerformanceCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'tenant_id' => fn (): int => Tenant::create([
                'name' => fake()->company(),
                'slug' => Str::slug(fake()->unique()->company()),
            ])->id,
            'name' => 'Penilaian '.fake()->randomElement(['Q1', 'Q2', 'Q3', 'Q4']).' '.fake()->year(),
            'period_start' => $periodStart,
            'period_end' => fake()->dateTimeBetween($periodStart, '+3 months'),
            'status' => fake()->randomElement(['draft', 'active', 'closed']),
            'description' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Indicate that the cycle is currently active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'active',
        ]);
    }
}
