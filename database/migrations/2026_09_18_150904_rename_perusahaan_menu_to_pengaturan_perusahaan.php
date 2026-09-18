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
            ->where('key', 'perusahaan')
            ->where('label', 'Perusahaan')
            ->update([
                'label' => 'Pengaturan Perusahaan',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_items')
            ->where('key', 'perusahaan')
            ->where('label', 'Pengaturan Perusahaan')
            ->update([
                'label' => 'Perusahaan',
                'updated_at' => now(),
            ]);
    }
};
