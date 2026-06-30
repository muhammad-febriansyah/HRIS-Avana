<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => Tenant::create([
                'name' => fake()->company(),
                'slug' => Str::slug(fake()->unique()->company()),
            ])->id,
            'category' => fake()->randomElement(['payroll', 'recruitment', 'training', 'operational', 'benefit', 'other']),
            'period_type' => 'monthly',
            'period' => '2026-07',
            'planned_amount' => fake()->numberBetween(1_000_000, 100_000_000),
            'actual_amount' => fake()->numberBetween(0, 100_000_000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
