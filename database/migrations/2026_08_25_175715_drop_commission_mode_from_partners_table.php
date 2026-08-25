<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flat-only commission: a partner override is now just a flat rupiah
     * amount (`commission_value`), so the flat/percent mode selector goes.
     */
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('commission_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('commission_mode')->nullable()->after('npwp');
        });
    }
};
