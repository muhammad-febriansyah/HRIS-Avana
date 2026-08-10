<?php

use App\Models\MenuItem;
use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;

/**
 * Actually hide the payroll screens the client asked to withdraw.
 *
 * They were removed from the canonical {@see AvanaNav} definition,
 * but the runtime sidebar reads `menu_items`, and every tenant seeded before
 * that change still carries its own active rows — so the menus never went away
 * for anyone already using the app. This switches those rows off.
 *
 * The rows are kept (is_active = false) rather than deleted: a route with no
 * matching menu leaf resolves to no access requirement at all, so deleting them
 * would open these pages to every role. Hidden keeps them closed, and a tenant
 * can switch any of them back on from the Menu Builder.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const KEYS = [
        'payroll-perhitungan-hari',
        'payroll-koreksi',
        'payroll-rapel',
        'payroll-sales-order',
        'jurnal',
        'anggaran',
    ];

    public function up(): void
    {
        MenuItem::query()->whereIn('key', self::KEYS)->update(['is_active' => false]);
    }

    public function down(): void
    {
        MenuItem::query()->whereIn('key', self::KEYS)->update(['is_active' => true]);
    }
};
