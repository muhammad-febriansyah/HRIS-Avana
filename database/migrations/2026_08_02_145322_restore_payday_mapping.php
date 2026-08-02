<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring back "Mapping Payday" — this time reading it during a payroll run.
 *
 * The first version (2026_07_07_132605) was retired on 2026-07-31 because the
 * engine never looked at it: an admin could group employees and set a cut-off
 * and nothing on a payslip moved. The documented setup flow still ends on this
 * screen, so the answer is to wire it up rather than leave it out.
 *
 * A payday group now owns the attendance/overtime cut-off window for the
 * employees mapped to it, taking precedence over the Master Gaji window, and
 * resolves the date wages actually land — `end_of_month` for groups paid on the
 * last day, otherwise `pay_day` of the payroll month. Both land in the payslip
 * snapshot, so the configuration is visible in the result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paydays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            // 'date' pays on `pay_day` of the month; 'end_of_month' on its last day.
            $table->string('pay_mode')->default('date');
            $table->unsignedTinyInteger('pay_day')->nullable();
            // The attendance window, e.g. 21 s.d. 20 — a start day after the end
            // day opens the window in the previous month.
            $table->unsignedTinyInteger('cut_off_start_day')->nullable();
            $table->unsignedTinyInteger('cut_off_end_day')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('payday_id')
                ->nullable()
                ->after('salary_master_id')
                ->constrained('paydays')
                ->nullOnDelete();
        });

        $this->seedMenu();
    }

    public function down(): void
    {
        MenuItem::query()->where('key', 'payroll-payday')->delete();

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payday_id');
        });

        Schema::dropIfExists('paydays');
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
                ['tenant_id' => $tenantId, 'key' => 'payroll-payday', 'parent_id' => $parent->id],
                [
                    'section' => $parent->section,
                    'label' => 'Mapping Payday',
                    'icon' => 'calendar-check',
                    'href' => '/avana/payroll/payday',
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
