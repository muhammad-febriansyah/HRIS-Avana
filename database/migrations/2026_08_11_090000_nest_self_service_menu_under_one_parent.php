<?php

use App\Models\MenuItem;
use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;

/**
 * Nest the self-service menus under a single collapsible "Layanan Saya" parent.
 *
 * The LAYANAN SAYA section was the only one built from flat top-level rows —
 * twenty-odd of them, which pushed the management sections off the first screen
 * and left several labels (Dokumen, Kinerja, Perjalanan Dinas) appearing twice
 * in one sidebar with nothing to tell them apart. {@see AvanaNav::groups()} now
 * defines them as children; the runtime sidebar reads `menu_items`, so every
 * already-seeded tenant needs its rows restructured to match.
 *
 * Rows are re-parented, never recreated: the menu key is what role visibility
 * (`role_menu_visibility`) and route gating join on, so preserving the existing
 * ids keeps per-role hidden menus intact.
 */
return new class extends Migration
{
    private const PARENT_KEY = 'saya';

    private const SECTION = 'LAYANAN SAYA';

    public function up(): void
    {
        $tenantIds = MenuItem::query()
            ->where('key', 'like', 'saya-%')
            ->whereNull('parent_id')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $children = MenuItem::query()
                ->where('key', 'like', 'saya-%')
                ->whereNull('parent_id')
                ->when(
                    $tenantId === null,
                    fn ($query) => $query->whereNull('tenant_id'),
                    fn ($query) => $query->where('tenant_id', $tenantId),
                )
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($children->isEmpty()) {
                continue;
            }

            $parent = MenuItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => self::PARENT_KEY, 'parent_id' => null],
                [
                    'section' => self::SECTION,
                    'label' => 'Layanan Saya',
                    'icon' => 'user-round',
                    'href' => null,
                    'feature' => null,
                    'modules' => [],
                    'admin_only' => false,
                    'super_admin_only' => false,
                    // The parent inherits the slot the first self-service row
                    // held, so the section stays where tenants expect it.
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => (int) $children->first()->sort_order,
                ],
            );

            foreach ($children as $order => $child) {
                $child->update([
                    'parent_id' => $parent->id,
                    'section' => null,
                    'sort_order' => $order + 1,
                ]);
            }
        }
    }

    public function down(): void
    {
        $parents = MenuItem::query()->where('key', self::PARENT_KEY)->whereNull('parent_id')->get();

        foreach ($parents as $parent) {
            $order = (int) $parent->sort_order;

            $children = MenuItem::query()
                ->where('parent_id', $parent->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($children as $child) {
                $child->update([
                    'parent_id' => null,
                    'section' => self::SECTION,
                    'sort_order' => $order++,
                ]);
            }

            $parent->delete();
        }
    }
};
