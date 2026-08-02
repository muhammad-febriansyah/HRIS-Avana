<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give `is_fixed` the meaning the setup design assigns it.
 *
 * The Setup Lembur design marks the overtime basis on the component — "Gaji
 * Pokok + komponen tunjangan yang ditandai 'Tetap'" — and `payroll_components`
 * has carried an `is_fixed` column since the first migration that nothing ever
 * read. It defaults to true, so every row currently claims to be a fixed
 * allowance, including deductions and the overtime component itself.
 *
 * This normalises it once: an earning is fixed unless it is paid per present
 * day or per overtime hour, and nothing that is a deduction or is the overtime
 * line itself counts. From here the flag is edited on the Setup Lembur screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Deductions are never part of a wage basis.
        DB::table('payroll_components')
            ->where(fn ($query) => $query->where('type', 'deduction')->orWhere('component_group', 'potongan'))
            ->update(['is_fixed' => false]);

        // Attendance-variable earnings are by definition not a fixed allowance.
        DB::table('payroll_components')
            ->whereIn('calc_basis', ['per_present_day', 'per_overtime_hour'])
            ->update(['is_fixed' => false]);

        // The overtime line is the result of the basis, never part of it.
        DB::table('payroll_components')
            ->where('code', 'LEMBUR')
            ->update(['is_fixed' => false]);

        // Gaji Pokok always counts — the design pins it as "selalu ikut".
        DB::table('payroll_components')
            ->where('code', 'BASIC')
            ->update(['is_fixed' => true]);
    }

    public function down(): void
    {
        // The column's previous value was the unread default; restoring "true
        // for everything" would only re-create the confusion.
    }
};
