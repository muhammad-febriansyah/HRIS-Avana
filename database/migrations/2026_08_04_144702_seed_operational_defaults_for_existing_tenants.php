<?php

use App\Support\DayCalcDefaults;
use App\Support\ShiftDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give the tenants that already exist what a new one is now provisioned with.
 *
 * Both sets are reference data payroll and the roster read before either can
 * run: with no Perhitungan Hari method a Master Gaji prorates nothing and pays
 * every component in full without saying so, and without the M/A/N legend a
 * rotation cannot be expressed at all. Only the demo tenant ever had them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DayCalcDefaults::seedDefaultsFor((int) $tenantId);
            ShiftDefaults::seedDefaultsFor((int) $tenantId);
        }
    }

    /**
     * Not reversible: a tenant may have edited or built on these rows by the
     * time anyone rolls back, and deleting a shift takes its roster with it.
     */
    public function down(): void
    {
        //
    }
};
