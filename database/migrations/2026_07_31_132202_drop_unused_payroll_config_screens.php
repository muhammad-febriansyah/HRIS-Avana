<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire "Nilai Upah" and "Mapping Payday".
 *
 * Both were configuration screens the payroll engine never read: a search of
 * the whole app found their models used only by their own controllers. They
 * invited an admin to set something up and then changed nothing on a payslip,
 * which is worse than not offering them — the pay date actually comes from the
 * period, set in the Proses Gaji dialog.
 *
 * Tenants whose sidebar is driven by the `menu_items` table keep their own copy
 * of the nav, so the code-side removal is not enough on its own; the rows have
 * to go too or the link stays and 404s.
 *
 * The tables are dropped last. `employees.payday_id` goes with them — nothing
 * ever read it either.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')
            ->whereIn('key', ['payroll-nilai-upah', 'payroll-payday'])
            ->delete();

        if (Schema::hasColumn('employees', 'payday_id')) {
            Schema::table('employees', function ($table): void {
                $table->dropConstrainedForeignId('payday_id');
            });
        }

        Schema::dropIfExists('salary_grade_steps');
        Schema::dropIfExists('paydays');
    }

    public function down(): void
    {
        // The screens are gone; recreating empty tables would only invite them
        // back. Restore from the original migrations if they are ever wanted.
    }
};
