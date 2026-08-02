<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Put the payroll submenu in the order the setup documentation prescribes.
 *
 * The documentation opens by naming the sequence — Master Komponen → Master
 * Gaji → UMR → Struktur & Skala Upah → BPJS & Pajak → Mapping Payday, then
 * Lembur — because each screen depends on the one before it. The menu listed
 * them in the order they happened to be built, which reads as arbitrary to
 * anyone following the document.
 *
 * Everything the documentation does not mention keeps its place after the
 * setup screens, in running order. A tenant that has since reordered its own
 * menu in the Menu Builder is left alone.
 */
return new class extends Migration
{
    /**
     * The documented setup order first, then the screens that run against it.
     *
     * @var list<string>
     */
    private const ORDER = [
        // The run screen carries the same key as the group it sits in.
        'payroll',
        'payroll-komponen',
        'payroll-master-gaji',
        'payroll-umr',
        'struktur-upah',
        'payroll-config',
        'payroll-ter',
        'payroll-payday',
        'payroll-lembur',
        'payroll-perhitungan-hari',
        'payroll-koreksi',
        'payroll-rapel',
        'payroll-sales-order',
        'jurnal',
        'anggaran',
    ];

    public function up(): void
    {
        $this->applyOrder(self::ORDER);
    }

    public function down(): void
    {
        // The previous order was incidental; re-seeding it would be inventing
        // an arrangement rather than restoring one.
    }

    /**
     * @param  list<string>  $keys
     */
    private function applyOrder(array $keys): void
    {
        foreach (Tenant::query()->pluck('id') as $tenantId) {
            // The group and its run screen share the key `payroll`, so the
            // parent has to be picked by having no parent of its own.
            $parent = MenuItem::forTenant((int) $tenantId)
                ->where('key', 'payroll')
                ->whereNull('parent_id')
                ->first();

            if ($parent === null) {
                continue;
            }

            $base = (int) MenuItem::forTenant((int) $tenantId)
                ->where('parent_id', $parent->id)
                ->min('sort_order');

            foreach ($keys as $index => $key) {
                MenuItem::forTenant((int) $tenantId)
                    ->where('parent_id', $parent->id)
                    ->where('key', $key)
                    ->update(['sort_order' => $base + $index]);
            }
        }
    }
};
