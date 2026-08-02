<?php

use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make statutory overtime configurable instead of hard-coded.
 *
 * PP 35/2021 does not describe one multiplier — it describes a table: on a
 * workday the first hour pays 1,5x and every hour after it 2x, while on a rest
 * day or public holiday hours 1–7 pay 2x, hour 8 pays 3x and hour 9 onward 4x.
 * The engine only ever knew the workday half of that, so a request filed for a
 * Sunday was paid as if it were a Tuesday. `overtime_requests.day_type` records
 * which side of the table applies, and `overtime_rates` holds the table itself
 * so a tenant can restate it when the regulation moves.
 *
 * `overtime_policies` carries the rest of the rule set the documentation calls
 * for: the 4-hour/day and 18-hour/week ceilings, the 1/173 hourly divisor, and
 * the 75% floor — when the components marked as overtime basis add up to less
 * than 75% of monthly earnings, the basis becomes 75% of those earnings.
 */
return new class extends Migration
{
    /**
     * PP 35/2021 Pasal 31 in table form: [day type, first hour, last hour (null
     * = open ended), multiplier].
     *
     * @var list<array{0: string, 1: int, 2: int|null, 3: float}>
     */
    private const DEFAULT_RATES = [
        ['workday', 1, 1, 1.5],
        ['workday', 2, null, 2.0],
        ['holiday', 1, 7, 2.0],
        ['holiday', 8, 8, 3.0],
        ['holiday', 9, null, 4.0],
    ];

    public function up(): void
    {
        Schema::create('overtime_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('max_hours_per_day', 5, 2)->default(4);
            $table->decimal('max_hours_per_week', 5, 2)->default(18);
            $table->unsignedInteger('hours_divisor')->default(173);
            $table->decimal('fixed_basis_min_ratio', 5, 4)->default(0.75);
            $table->boolean('enforce_hour_limits')->default(true);
            $table->timestamps();
            $table->unique('tenant_id');
        });

        Schema::create('overtime_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('day_type')->default('workday');
            $table->unsignedInteger('hour_from')->default(1);
            $table->unsignedInteger('hour_to')->nullable();
            $table->decimal('multiplier', 6, 2)->default(1.5);
            $table->timestamps();
            $table->unique(['tenant_id', 'day_type', 'hour_from']);
        });

        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->string('day_type')->default('workday')->after('date');
        });

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $this->seedFor((int) $tenantId);
        }

        $this->seedMenu();
    }

    public function down(): void
    {
        MenuItem::query()->where('key', 'payroll-lembur')->delete();

        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->dropColumn('day_type');
        });

        Schema::dropIfExists('overtime_rates');
        Schema::dropIfExists('overtime_policies');
    }

    /**
     * Give a tenant the statutory defaults so overtime keeps paying exactly as
     * it did before this migration until someone edits the table.
     */
    private function seedFor(int $tenantId): void
    {
        DB::table('overtime_policies')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'max_hours_per_day' => 4,
            'max_hours_per_week' => 18,
            'hours_divisor' => 173,
            'fixed_basis_min_ratio' => 0.75,
            'enforce_hour_limits' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::DEFAULT_RATES as [$dayType, $from, $to, $multiplier]) {
            DB::table('overtime_rates')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'day_type' => $dayType,
                'hour_from' => $from,
                'hour_to' => $to,
                'multiplier' => $multiplier,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * The runtime sidebar is served from `menu_items` once a tenant has rows of
     * its own, so a code-side leaf in AvanaNav is not enough on its own.
     */
    private function seedMenu(): void
    {
        Feature::firstOrCreate(
            ['code' => 'payroll'],
            ['name' => 'Payroll', 'module_group' => 'payroll'],
        );

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $parent = MenuItem::forTenant((int) $tenantId)->where('key', 'payroll')->first();

            if ($parent === null) {
                continue;
            }

            MenuItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => 'payroll-lembur', 'parent_id' => $parent->id],
                [
                    'section' => $parent->section,
                    'label' => 'Setup Lembur',
                    'icon' => 'timer',
                    'href' => '/avana/payroll/lembur',
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
