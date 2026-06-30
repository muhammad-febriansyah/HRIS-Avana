<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ShiftSwap;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShiftSwap>
 */
class ShiftSwapFactory extends Factory
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
            'requester_id' => $this->makeEmployee($tenant->id),
            'target_id' => $this->makeEmployee($tenant->id),
            'date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'requester_shift_id' => null,
            'target_shift_id' => null,
            'reason' => fake()->optional()->sentence(),
            'status' => 'pending',
        ];
    }

    /**
     * Create a minimal active employee for the given tenant and return its id.
     */
    private function makeEmployee(int $tenantId): int
    {
        return Employee::create([
            'tenant_id' => $tenantId,
            'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'status' => 'active',
        ])->id;
    }
}
