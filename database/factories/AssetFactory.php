<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
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
            'code' => 'AST-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->randomElement(['Laptop', 'Monitor', 'Printer', 'Proyektor', 'Meja Kerja']).' '.fake()->word(),
            'category' => fake()->randomElement(['Elektronik', 'Furnitur', 'Kendaraan', 'Perangkat Lunak']),
            'purchase_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'purchase_cost' => fake()->numberBetween(1_000_000, 50_000_000),
            'depreciation_years' => fake()->numberBetween(3, 8),
            'condition' => fake()->randomElement(['good', 'fair', 'damaged']),
            'status' => 'available',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the asset is currently assigned.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'assigned',
        ]);
    }
}
