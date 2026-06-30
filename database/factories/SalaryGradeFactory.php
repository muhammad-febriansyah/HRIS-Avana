<?php

namespace Database\Factories;

use App\Models\SalaryGrade;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SalaryGrade>
 */
class SalaryGradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->numberBetween(4_000_000, 8_000_000);
        $mid = $min + fake()->numberBetween(1_000_000, 3_000_000);
        $max = $mid + fake()->numberBetween(1_000_000, 3_000_000);

        return [
            'tenant_id' => fn (): int => Tenant::create([
                'name' => fake()->company(),
                'slug' => Str::slug(fake()->unique()->company()),
            ])->id,
            'grade_code' => 'G'.fake()->unique()->numberBetween(1, 999),
            'grade_name' => fake()->jobTitle(),
            'level' => fake()->numberBetween(1, 12),
            'min_salary' => $min,
            'mid_salary' => $mid,
            'max_salary' => $max,
        ];
    }
}
