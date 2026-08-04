<?php

namespace Database\Factories;

use App\Models\DataChangeRequest;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataChangeRequest>
 */
class DataChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employee = Employee::factory();

        return [
            'employee_id' => $employee,
            'tenant_id' => fn (array $attributes): int => (int) Employee::whereKey($attributes['employee_id'])->value('tenant_id'),
            'changes' => [
                'phone' => ['old' => fake()->phoneNumber(), 'new' => fake()->phoneNumber()],
            ],
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    /**
     * An already approved request.
     */
    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'decided_at' => now(),
        ]);
    }
}
