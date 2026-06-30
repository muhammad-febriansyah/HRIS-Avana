<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => fn (): int => $this->makeRequester(),
            'tenant_id' => fn (array $attributes): int => Employee::find($attributes['requester_id'])->tenant_id,
            'ticket_no' => 'TIK-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'category' => fake()->randomElement(['it', 'hr', 'payroll', 'facility', 'other']),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'open',
            'assignee_id' => null,
            'resolved_at' => null,
        ];
    }

    /**
     * Indicate that the ticket has been resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    /**
     * Create a requester employee under a fresh tenant and return its id.
     */
    private function makeRequester(): int
    {
        $tenant = Tenant::create([
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company()),
        ]);

        return Employee::create([
            'tenant_id' => $tenant->id,
            'employee_number' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'full_name' => fake()->name(),
            'employment_status' => 'permanent',
            'status' => 'active',
        ])->id;
    }
}
