<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * "Gaji Karyawan" and "Penetapan Gaji Massal" are now tabs of the Master Gaji
 * page, not menu items of their own — same job in sequence (template, one
 * person, many people), one screen.
 *
 * Their menu_items rows are deleted, not deactivated: EnsureAvanaAccess treats
 * an inactive menu leaf as blocked (403), not merely hidden — a deactivated row
 * would have taken the route down along with the sidebar entry, and the
 * Master Gaji page's own tabs still link straight to these routes. Deleting the
 * leaf falls through to the next-longest matching leaf, the parent "Payroll"
 * entry, whose module gate (`payroll`) is the same one these routes already
 * enforce in their controllers.
 */
return new class extends Migration
{
    private const KEYS = ['payroll-gaji-karyawan', 'payroll-penetapan-massal'];

    public function up(): void
    {
        MenuItem::query()->whereIn('key', self::KEYS)->delete();
    }

    public function down(): void
    {
        // Not recreated: seedDefaultsFor() re-adds a leaf whose key it does not
        // already see, so simply re-running it restores these on rollback.
    }
};
