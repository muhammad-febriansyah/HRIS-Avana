<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Reimbursement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reimbursement>
 */
class ReimbursementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenantId = Tenant::query()->value('id')
            ?? Tenant::factory()->create()->id;

        return [
            'tenant_id' => $tenantId,
            'employee_id' => Employee::query()->where('tenant_id', $tenantId)->value('id'),
            'number' => 'RMB-'.fake()->unique()->numerify('######'),
            'category' => fake()->randomElement(array_keys(Reimbursement::CATEGORIES)),
            'title' => fake()->randomElement([
                'Taksi ke kantor klien',
                'Paket data internet',
                'Periksa kesehatan',
                'Pembelian ATK',
            ]),
            'amount' => fake()->numberBetween(50_000, 2_000_000),
            'expense_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'status' => 'pending',
        ];
    }
}
