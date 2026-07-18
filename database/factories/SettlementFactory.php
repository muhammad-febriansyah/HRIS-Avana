<?php

namespace Database\Factories;

use App\Models\Settlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Settlement>
 */
class SettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Perjalanan Dinas '.$this->faker->city(),
            'category' => 'transportasi',
            'department' => $this->faker->randomElement(['Sales', 'Operasional', 'Human Resources']),
            'submission_date' => now()->toDateString(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'status' => Settlement::STATUS_DRAFT,
        ];
    }
}
