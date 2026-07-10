<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-template monthly nominal for an included component. Master Gaji
     * becomes the single place nominals are set; a per-employee salary row still
     * overrides it for individual exceptions.
     */
    public function up(): void
    {
        Schema::table('salary_master_components', function (Blueprint $table): void {
            $table->decimal('amount', 15, 2)->default(0)->after('included');
        });
    }

    public function down(): void
    {
        Schema::table('salary_master_components', function (Blueprint $table): void {
            $table->dropColumn('amount');
        });
    }
};
