<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
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
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(['holiday', 'meeting', 'training', 'event', 'deadline']),
            'start_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'end_date' => null,
            'all_day' => true,
            'color' => null,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
