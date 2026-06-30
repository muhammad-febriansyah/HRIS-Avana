<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Timesheet>
 */
class TimesheetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::create([
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company()),
        ]);

        return [
            'tenant_id' => $tenant->id,
            'employee_id' => Employee::create([
                'tenant_id' => $tenant->id,
                'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
                'full_name' => fake()->name(),
                'status' => 'active',
            ])->id,
            'project_id' => Project::factory()->create(['tenant_id' => $tenant->id])->id,
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'hours' => fake()->randomFloat(2, 1, 8),
            'task' => fake()->sentence(3),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
