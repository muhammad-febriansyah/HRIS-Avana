<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetAssignment>
 */
class AssetAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $asset = Asset::factory();

        return [
            'tenant_id' => fn (array $attributes): int => Asset::find($attributes['asset_id'])->tenant_id,
            'asset_id' => $asset,
            'employee_id' => null,
            'assigned_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'returned_date' => null,
            'condition_note' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the asset has already been returned.
     */
    public function returned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'returned_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
