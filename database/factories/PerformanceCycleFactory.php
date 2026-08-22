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
        // A wide, deterministic window around today. Review dates are validated
        // against their cycle's period, so a randomly narrow window would make
        // every fixture date intermittently invalid.
        $periodStart = now()->subMonths(6)->startOfDay();

        return [
            'tenant_id' => fn (): int => Tenant::create([
                'name' => fake()->company(),
                'slug' => Str::slug(fake()->unique()->company()),
            ])->id,
            'name' => 'Penilaian '.fake()->randomElement(['Q1', 'Q2', 'Q3', 'Q4']).' '.fake()->year(),
            'period_start' => $periodStart,
            'period_end' => now()->addMonths(6)->endOfDay(),
            // Deterministically active: the whole review lifecycle is gated on
            // an open cycle, so a randomly drafted/closed cycle would make
            // every test built on this factory intermittently fail.
            'status' => 'active',
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
