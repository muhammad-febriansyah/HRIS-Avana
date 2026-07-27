<?php

namespace Database\Factories;

use App\Models\Sop;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sop>
 */
class SopFactory extends Factory
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
            'sop_category_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('SOP-##-???')),
            'title' => 'SOP '.fake()->words(3, true),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'visibility' => Sop::VISIBILITY_PRIVATE,
            'status' => 'active',
            'version' => '1.0',
            'effective_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'file_path' => null,
            'file_name' => 'sop.pdf',
            'file_size' => 12345,
        ];
    }

    /**
     * Readable by every employee through the AI assistant.
     */
    public function publicVisibility(): static
    {
        return $this->state(fn (): array => ['visibility' => Sop::VISIBILITY_PUBLIC]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
