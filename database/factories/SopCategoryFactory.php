<?php

namespace Database\Factories;

use App\Models\SopCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SopCategory>
 */
class SopCategoryFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('SOP-???')),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
