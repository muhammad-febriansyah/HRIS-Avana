<?php

namespace Database\Factories;

use App\Models\SavedReport;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SavedReport>
 */
class SavedReportFactory extends Factory
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
            'name' => fake()->words(3, true),
            'entity' => 'employees',
            'columns' => ['full_name', 'email', 'department', 'status', 'join_date'],
            'filters' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the report targets the leave entity.
     */
    public function leave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'entity' => 'leave',
            'columns' => ['employee', 'leave_type', 'start_date', 'end_date', 'status'],
        ]);
    }
}
