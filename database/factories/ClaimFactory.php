<?php

namespace Database\Factories;

use App\Models\Claim;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Claim>
 */
class ClaimFactory extends Factory
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
            'employee_id' => fn (array $attributes): int => Employee::create([
                'tenant_id' => $attributes['tenant_id'],
                'employee_number' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
                'full_name' => fake()->name(),
                'employment_status' => 'permanent',
                'status' => 'active',
            ])->id,
            'claim_type' => fake()->randomElement(['medical', 'transport', 'meal', 'glasses', 'other']),
            'title' => fake()->sentence(3),
            'amount' => fake()->numberBetween(50_000, 5_000_000),
            'claim_date' => fake()->dateTimeBetween('-2 months', 'now'),
            'description' => fake()->optional()->paragraph(),
            'receipt_path' => null,
            'status' => 'pending',
            'approver_id' => null,
            'approved_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the claim has been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the claim has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'paid',
            'approved_at' => now(),
        ]);
    }
}
