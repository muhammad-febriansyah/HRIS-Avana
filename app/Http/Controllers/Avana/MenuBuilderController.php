<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MenuBuilderController extends Controller
{
    /**
     * Roles allowed to manage the sidebar menu.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Render the menu builder: the tenant's menu tree plus the option lists the
     * form needs (parents, sections, features, permission modules).
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $rows = MenuItem::forTenant($tenantId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = $rows->groupBy(fn (MenuItem $r): int => (int) ($r->parent_id ?? 0));

        $tree = $byParent->get(0, collect())->map(fn (MenuItem $item): array => [
            ...$this->row($item),
            'children' => $byParent->get($item->id, collect())->map(fn (MenuItem $c): array => $this->row($c))->all(),
        ])->all();

        return Inertia::render('avana/menu-builder/index', [
            'tree' => $tree,
            'parents' => $byParent->get(0, collect())->map(fn (MenuItem $i): array => ['id' => $i->id, 'label' => $i->label])->values()->all(),
            'sections' => $byParent->get(0, collect())->pluck('section')->filter()->unique()->values()->all(),
            'features' => Feature::orderBy('name')->get(['code', 'name'])->map(fn (Feature $f): array => ['value' => $f->code, 'label' => $f->name])->all(),
            'modules' => Permission::query()->distinct()->orderBy('module')->pluck('module')->all(),
        ]);
    }

    /**
     * Create a new menu item.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;
        $data = $this->validated($request);

        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            // Ensure the parent belongs to the same tenant.
            MenuItem::forTenant($tenantId)->whereKey($parentId)->firstOrFail();
        }

        $maxOrder = (int) MenuItem::forTenant($tenantId)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        MenuItem::create([
            'tenant_id' => $tenantId,
            'parent_id' => $parentId,
            'key' => $this->uniqueKey($tenantId, $data['label']),
            'section' => $parentId === null ? ($data['section'] ?? null) : null,
            'label' => $data['label'],
            'icon' => $data['icon'] ?? null,
            'href' => $data['href'] ?? null,
            'feature' => $data['feature'] ?? null,
            'modules' => $data['modules'] ?? [],
            'admin_only' => $data['admin_only'] ?? false,
            'super_admin_only' => $data['super_admin_only'] ?? false,
            'is_active' => true,
            'is_system' => false,
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Menu ditambahkan');
    }

    /**
     * Update an existing menu item (label/icon/href/gating; not its key).
     */
    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $menuItem);

        $data = $this->validated($request);

        $menuItem->update([
            'section' => $menuItem->parent_id === null ? ($data['section'] ?? $menuItem->section) : null,
            'label' => $data['label'],
            'icon' => $data['icon'] ?? null,
            'href' => $data['href'] ?? null,
            'feature' => $data['feature'] ?? null,
            'modules' => $data['modules'] ?? [],
            'admin_only' => $data['admin_only'] ?? false,
            'super_admin_only' => $data['super_admin_only'] ?? false,
        ]);

        return back()->with('success', 'Menu diperbarui');
    }

    /**
     * Delete a custom menu item. System (seeded) items can only be hidden.
     */
    public function destroy(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $menuItem);

        abort_if($menuItem->is_system, 422, 'Menu bawaan tidak bisa dihapus, sembunyikan saja.');

        $menuItem->delete();

        return back()->with('success', 'Menu dihapus');
    }

    /**
     * Toggle an item's visibility.
     */
    public function toggle(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $menuItem);

        $menuItem->update(['is_active' => ! $menuItem->is_active]);

        return back()->with('success', 'Visibilitas menu diperbarui');
    }

    /**
     * Reorder an item up or down among its siblings by swapping sort_order.
     */
    public function move(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $menuItem);

        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        $siblingsQuery = MenuItem::forTenant($menuItem->tenant_id)
            ->where('parent_id', $menuItem->parent_id);

        $neighbor = $direction === 'up'
            ? (clone $siblingsQuery)->where('sort_order', '<', $menuItem->sort_order)->orderByDesc('sort_order')->first()
            : (clone $siblingsQuery)->where('sort_order', '>', $menuItem->sort_order)->orderBy('sort_order')->first();

        if ($neighbor !== null) {
            $order = $menuItem->sort_order;
            $menuItem->update(['sort_order' => $neighbor->sort_order]);
            $neighbor->update(['sort_order' => $order]);
        }

        return back()->with('success', 'Urutan menu diperbarui');
    }

    /**
     * Validate the menu item payload.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'href' => ['nullable', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer'],
            'feature' => ['nullable', 'string', 'max:64'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'max:64'],
            'admin_only' => ['boolean'],
            'super_admin_only' => ['boolean'],
        ]);
    }

    /**
     * Build the frontend row shape for a menu item.
     *
     * @return array<string, mixed>
     */
    private function row(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'key' => $item->key,
            'parent_id' => $item->parent_id,
            'section' => $item->section,
            'label' => $item->label,
            'icon' => $item->icon,
            'href' => $item->href,
            'feature' => $item->feature,
            'modules' => $item->modules ?? [],
            'admin_only' => $item->admin_only,
            'super_admin_only' => $item->super_admin_only,
            'is_active' => $item->is_active,
            'is_system' => $item->is_system,
            'sort_order' => $item->sort_order,
        ];
    }

    /**
     * Generate a tenant-unique slug key from a label.
     */
    private function uniqueKey(int $tenantId, string $label): string
    {
        $base = Str::slug($label) ?: 'menu';
        $key = $base;
        $suffix = 1;

        while (MenuItem::forTenant($tenantId)->where('key', $key)->exists()) {
            $key = $base.'-'.(++$suffix);
        }

        return $key;
    }

    /**
     * Abort with 404 when the item is outside the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, MenuItem $menuItem): void
    {
        abort_if((int) $menuItem->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user may manage the menu.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless($user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty(), 403);
    }
}
