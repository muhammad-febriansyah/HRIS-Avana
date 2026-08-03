<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop a flag nothing reads.
     *
     * The overtime basis is the set of components marked "Tetap" on Master
     * Komponen — one list for the whole tenant, which is how Setup Lembur
     * presents it and how payroll computes it. This column was the earlier,
     * per-master version of the same idea; the screen that wrote it is gone,
     * but the column, its cast, its place in the saved flag list and the demo
     * seeder's belief in it all stayed, so an API call could still set it and
     * change nothing.
     */
    public function up(): void
    {
        Schema::table('salary_master_components', function (Blueprint $table): void {
            $table->dropColumn('is_overtime_base');
        });
    }

    /**
     * Put the column back, defaulting the way it always did.
     */
    public function down(): void
    {
        Schema::table('salary_master_components', function (Blueprint $table): void {
            $table->boolean('is_overtime_base')->default(false)->after('is_prorate');
        });
    }
};
