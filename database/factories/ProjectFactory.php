<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'name' => fake()->unique()->catchPhrase(),
            'code' => strtoupper(Str::random(4)),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the project has been archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'archived',
        ]);
    }
}
