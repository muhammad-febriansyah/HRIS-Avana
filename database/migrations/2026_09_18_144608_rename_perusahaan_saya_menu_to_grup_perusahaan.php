<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('menu_items')
            ->where('key', 'perusahaan-saya')
            ->where('label', 'Perusahaan Saya')
            ->update(['label' => 'Grup Perusahaan', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_items')
            ->where('key', 'perusahaan-saya')
            ->where('label', 'Grup Perusahaan')
            ->update(['label' => 'Perusahaan Saya', 'updated_at' => now()]);
    }
};
