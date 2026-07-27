<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Rename the employee wall's sidebar entry from "Sosmed" to "Ruang Kita".
 *
 * Seeding only creates missing keys, so an entry a tenant already has keeps its
 * old label — the rename has to be an explicit update. A tenant that renamed
 * the menu itself in the Menu Builder is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuItem::query()
            ->where('key', 'saya-sosmed')
            ->where('label', 'Sosmed')
            ->update(['label' => 'Ruang Kita']);
    }

    public function down(): void
    {
        MenuItem::query()
            ->where('key', 'saya-sosmed')
            ->where('label', 'Ruang Kita')
            ->update(['label' => 'Sosmed']);
    }
};
