<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use App\Support\Pph21Ter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the PPh 21 TER tariff master data again.
 *
 * The brackets have lived in PHP constants since `pph21_ter_rates` was dropped
 * on 2026-07-05. That is fine until the tariff moves — and it will: PP 58/2023
 * replaced the whole withholding scheme in 2024, and the Lampiran is exactly
 * the kind of thing a PMK revises. Shipping a release to change a tax rate is
 * the wrong shape for that.
 *
 * So the tables come back, dated. `pph21_ter_rates` holds the brackets for
 * Kategori A/B/C and the daily table; `pph21_ter_categories` holds the
 * PTKP→category mapping, which the same regulation sets. Every row carries an
 * effective window, so a payroll run for June still resolves the table that
 * was in force in June after a new one is entered for July.
 *
 * Both are global — PPh 21 is national law, not tenant policy. Seeded here from
 * the PP 58/2023 figures the engine already carried, so nothing changes on the
 * day this runs.
 */
return new class extends Migration
{
    /**
     * The day PP 58/2023 & PMK 168/2023 took effect.
     */
    private const STATUTORY_FROM = '2024-01-01';

    public function up(): void
    {
        Schema::create('pph21_ter_rates', function (Blueprint $table): void {
            $table->id();
            // A / B / C for the monthly tables, HARIAN for the daily one.
            $table->string('category', 16);
            $table->decimal('income_min', 15, 2)->default(0);
            // Null is the open-ended top bracket ("di atas ...").
            $table->decimal('income_max', 15, 2)->nullable();
            $table->decimal('rate', 8, 6)->default(0);
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
            $table->index(['category', 'effective_start_date']);
        });

        Schema::create('pph21_ter_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('ptkp_status', 8);
            $table->string('category', 16);
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->timestamps();
            $table->index(['ptkp_status', 'effective_start_date']);
        });

        $this->seedStatutory();
        $this->seedMenu();
    }

    public function down(): void
    {
        MenuItem::query()->where('key', 'payroll-ter')->delete();

        Schema::dropIfExists('pph21_ter_categories');
        Schema::dropIfExists('pph21_ter_rates');
    }

    /**
     * Write the PP 58/2023 tables the engine has been using, so the master
     * starts out agreeing with the code it replaces.
     */
    private function seedStatutory(): void
    {
        $rows = [];

        foreach (Pph21Ter::statutoryMonthly() as $category => $brackets) {
            $rows = array_merge($rows, $this->bracketRows($category, $brackets));
        }

        $rows = array_merge($rows, $this->bracketRows('HARIAN', Pph21Ter::statutoryDaily()));

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('pph21_ter_rates')->insert($chunk);
        }

        foreach (Pph21Ter::statutoryCategoryMap() as $status => $category) {
            DB::table('pph21_ter_categories')->insert([
                'ptkp_status' => $status,
                'category' => $category,
                'effective_start_date' => self::STATUTORY_FROM,
                'effective_end_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Turn a [upper bound, rate] list into rows, deriving each bracket's lower
     * bound from the one before it and leaving the top bound null.
     *
     * @param  list<array{0: int, 1: float}>  $brackets
     * @return list<array<string, mixed>>
     */
    private function bracketRows(string $category, array $brackets): array
    {
        $rows = [];
        $min = 0.0;

        foreach ($brackets as [$upTo, $rate]) {
            $isTop = $upTo >= PHP_INT_MAX;

            $rows[] = [
                'category' => $category,
                'income_min' => $min,
                'income_max' => $isTop ? null : $upTo,
                'rate' => $rate,
                'effective_start_date' => self::STATUTORY_FROM,
                'effective_end_date' => null,
                'source' => 'PP 58/2023 & PMK 168/2023',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $min = $isTop ? $min : (float) $upTo;
        }

        return $rows;
    }

    /**
     * The runtime sidebar is served from `menu_items` once a tenant has rows of
     * its own, so the leaf has to be inserted for each of them.
     */
    private function seedMenu(): void
    {
        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $parent = MenuItem::forTenant((int) $tenantId)->where('key', 'payroll')->first();

            if ($parent === null) {
                continue;
            }

            MenuItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => 'payroll-ter', 'parent_id' => $parent->id],
                [
                    'section' => $parent->section,
                    'label' => 'Tarif TER PPh 21',
                    'icon' => 'percent',
                    'href' => '/avana/payroll/ter',
                    'feature' => 'payroll',
                    'modules' => ['payroll'],
                    'admin_only' => false,
                    'super_admin_only' => false,
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => (int) MenuItem::forTenant((int) $tenantId)
                        ->where('parent_id', $parent->id)
                        ->max('sort_order') + 1,
                ],
            );
        }
    }
};
