<?php

use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename the roster menus to the words the people using them actually say.
 *
 * A tenant's sidebar is seeded from {@see AvanaNav} once and then
 * owned by its own menu_items rows, so renaming the definition alone would
 * reach nobody who already has a sidebar.
 *
 * Only rows still carrying the seeded label are touched: a tenant that renamed
 * the entry in Menu Builder chose its own wording, and this must not undo that.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{0: string, 1: string}> key => [old label, new label]
     */
    private const RENAMES = [
        'roster' => ['Roster Shift', 'Jadwal Absensi'],
        'roster-pola' => ['Pola Roster', 'Pola Jadwal'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $key => [$old, $new]) {
            DB::table('menu_items')
                ->where('key', $key)
                ->where('label', $old)
                ->update(['label' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $key => [$old, $new]) {
            DB::table('menu_items')
                ->where('key', $key)
                ->where('label', $new)
                ->update(['label' => $old]);
        }
    }
};
