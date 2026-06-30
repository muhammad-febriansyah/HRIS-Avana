<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
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
            'payroll_period_id' => null,
            'entry_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'account_code' => fake()->randomElement(['5101', '2101', '1101']),
            'account_name' => fake()->randomElement(['Beban Gaji', 'Hutang BPJS & Pajak', 'Kas/Bank']),
            'description' => fake()->optional()->sentence(),
            'debit' => 0,
            'credit' => 0,
        ];
    }
}
