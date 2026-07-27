<?php

namespace Database\Factories;

use App\Models\SocialCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SocialCategory>
 */
class SocialCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'tenant_id' => fn (): int => Tenant::create([
                'name' => fake()->company(),
                'slug' => Str::slug(fake()->unique()->company()),
            ])->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => 'sparkles',
            'color' => '#2F54C9',
            'description' => fake()->sentence(),
            'status' => 'active',
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
