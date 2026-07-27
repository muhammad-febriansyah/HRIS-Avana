<?php

use App\Models\Feature;
use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Rename the employee wall from "Sosmed" to "Ruang Kita".
 *
 * The labels live in rows, not only in code: AvanaNav seeds menu items with
 * `firstOrCreate`, so a renamed leaf in the definition never reaches a tenant
 * that already has the old row. Same for the feature name shown on the Hak
 * Akses matrix.
 */
return new class extends Migration
{
    /**
     * [menu key => new label]
     *
     * @var array<string, string>
     */
    private const MENU_LABELS = [
        'sosmed' => 'Ruang Kita',
        'saya-sosmed' => 'Ruang Kita',
    ];

    public function up(): void
    {
        foreach (self::MENU_LABELS as $key => $label) {
            MenuItem::query()->where('key', $key)->update(['label' => $label]);
        }

        Feature::query()->where('code', 'social')->update(['name' => 'Ruang Kita']);
    }

    public function down(): void
    {
        MenuItem::query()->where('key', 'sosmed')->update(['label' => 'Sosmed Karyawan']);
        MenuItem::query()->where('key', 'saya-sosmed')->update(['label' => 'Sosmed']);

        Feature::query()->where('code', 'social')->update(['name' => 'Sosmed Karyawan']);
    }
};
