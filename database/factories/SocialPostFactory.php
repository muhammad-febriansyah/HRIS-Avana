<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPost>
 */
class SocialPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A post needs a real employee (and its tenant), so callers normally
        // pass both; this only covers the standalone case.
        $employee = Employee::query()->inRandomOrder()->first();

        return [
            'tenant_id' => $employee?->tenant_id,
            'employee_id' => $employee?->id,
            'social_category_id' => null,
            'body' => fake()->sentence(12),
            'image_path' => null,
            'likes_count' => 0,
            'comments_count' => 0,
            'status' => SocialPost::STATUS_PUBLISHED,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['status' => SocialPost::STATUS_HIDDEN]);
    }
}
