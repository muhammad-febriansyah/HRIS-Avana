<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketReply>
 */
class TicketReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ticket = Ticket::factory();

        return [
            'ticket_id' => $ticket,
            'tenant_id' => fn (array $attributes): int => Ticket::find($attributes['ticket_id'])->tenant_id,
            'user_id' => fn (array $attributes): int => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'message' => fake()->paragraph(),
        ];
    }
}
