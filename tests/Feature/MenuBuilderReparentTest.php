<?php

use App\Models\MenuItem;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenantId = (int) $this->superAdmin->tenant_id;
});

/**
 * The payload the Menu Builder modal submits, mirroring the edited row so only
 * the placement changes.
 *
 * @return array<string, mixed>
 */
function menuPayload(MenuItem $item, ?int $parentId, ?string $section = null): array
{
    return [
        'tenant_id' => $item->tenant_id,
        'label' => $item->label,
        'parent_id' => $parentId,
        'section' => $section,
        'href' => $item->href,
        'icon' => $item->icon,
        'feature' => $item->feature,
        'modules' => $item->modules ?? [],
        'admin_only' => $item->admin_only,
        'super_admin_only' => $item->super_admin_only,
    ];
}

it('promotes a child menu to the top level and shows it in the sidebar', function (): void {
    $dokumen = MenuItem::forTenant($this->tenantId)->where('key', 'dokumen')->firstOrFail();

    expect($dokumen->parent_id)->not->toBeNull();

    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $dokumen), menuPayload($dokumen, null))
        ->assertRedirect();

    $dokumen->refresh();

    expect($dokumen->parent_id)->toBeNull()
        // Inherits the group it was promoted out of.
        ->and($dokumen->section)->toBe('MANAJEMEN');

    $nav = AvanaNav::forUser($this->superAdmin->fresh());
    $manajemen = collect($nav)->firstWhere('title', 'MANAJEMEN');

    expect(collect($manajemen['items'])->pluck('label'))->toContain('Dokumen');
});

it('moves a top-level menu into another group', function (): void {
    $pengumuman = MenuItem::forTenant($this->tenantId)->where('key', 'pengumuman')->whereNull('parent_id')->firstOrFail();
    $hr = MenuItem::forTenant($this->tenantId)->where('key', 'hr')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $pengumuman), menuPayload($pengumuman, $hr->id))
        ->assertRedirect();

    $pengumuman->refresh();

    expect($pengumuman->parent_id)->toBe($hr->id)
        // A child carries no section of its own.
        ->and($pengumuman->section)->toBeNull();
});

it('lands a moved menu at the end of its new level instead of colliding', function (): void {
    $dokumen = MenuItem::forTenant($this->tenantId)->where('key', 'dokumen')->firstOrFail();

    $highestTopLevel = (int) MenuItem::forTenant($this->tenantId)->whereNull('parent_id')->max('sort_order');

    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $dokumen), menuPayload($dokumen, null))
        ->assertRedirect();

    expect($dokumen->refresh()->sort_order)->toBe($highestTopLevel + 1);
});

it('does not clone a leaf the tenant moved when the defaults are re-seeded', function (): void {
    $dokumen = MenuItem::forTenant($this->tenantId)->where('key', 'dokumen')->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $dokumen), menuPayload($dokumen, null))
        ->assertRedirect();

    AvanaNav::seedDefaultsFor($this->tenantId);

    expect(MenuItem::forTenant($this->tenantId)->where('key', 'dokumen')->count())->toBe(1)
        ->and($dokumen->refresh()->parent_id)->toBeNull();
});

it('appends a newly added child instead of colliding with an existing sibling', function (): void {
    $hr = MenuItem::forTenant($this->tenantId)->where('key', 'hr')->whereNull('parent_id')->firstOrFail();

    $sop = MenuItem::forTenant($this->tenantId)->where('key', 'sop')->firstOrFail();
    $slot = (int) $sop->sort_order;
    $sop->delete();

    // A tenant that predates the SOP menu already has that slot in use.
    MenuItem::forTenant($this->tenantId)
        ->where('parent_id', $hr->id)
        ->where('key', 'surat')
        ->update(['sort_order' => $slot]);

    $highest = (int) MenuItem::forTenant($this->tenantId)->where('parent_id', $hr->id)->max('sort_order');

    AvanaNav::seedDefaultsFor($this->tenantId);

    $reseeded = MenuItem::forTenant($this->tenantId)->where('key', 'sop')->firstOrFail();

    expect($reseeded->parent_id)->toBe($hr->id)
        ->and($reseeded->sort_order)->toBe($highest + 1);
});

it('rejects making a menu its own parent', function (): void {
    $hr = MenuItem::forTenant($this->tenantId)->where('key', 'hr')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $hr), menuPayload($hr, $hr->id))
        ->assertStatus(422);

    expect($hr->refresh()->parent_id)->toBeNull();
});

it('rejects nesting a menu deeper than two levels', function (): void {
    $pengumuman = MenuItem::forTenant($this->tenantId)->where('key', 'pengumuman')->whereNull('parent_id')->firstOrFail();
    $dokumen = MenuItem::forTenant($this->tenantId)->where('key', 'dokumen')->firstOrFail();

    // `dokumen` is itself a child, so it can never act as a parent.
    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $pengumuman), menuPayload($pengumuman, $dokumen->id))
        ->assertStatus(422);

    expect($pengumuman->refresh()->parent_id)->toBeNull();
});

it('rejects demoting a group that still has sub-menus', function (): void {
    $hr = MenuItem::forTenant($this->tenantId)->where('key', 'hr')->whereNull('parent_id')->firstOrFail();
    $kehadiran = MenuItem::forTenant($this->tenantId)->where('key', 'kehadiran')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $hr), menuPayload($hr, $kehadiran->id))
        ->assertStatus(422);

    expect($hr->refresh()->parent_id)->toBeNull();
});

it('leaves placement untouched when the parent is unchanged', function (): void {
    $dokumen = MenuItem::forTenant($this->tenantId)->where('key', 'dokumen')->firstOrFail();
    $originalOrder = $dokumen->sort_order;

    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $dokumen), [...menuPayload($dokumen, $dokumen->parent_id), 'label' => 'Dokumen Karyawan'])
        ->assertRedirect();

    $dokumen->refresh();

    expect($dokumen->label)->toBe('Dokumen Karyawan')
        ->and($dokumen->sort_order)->toBe($originalOrder)
        ->and($dokumen->parent_id)->not->toBeNull();
});

it('accepts the comma-separated modules string the modal submits', function (): void {
    $dokumen = MenuItem::forTenant($this->tenantId)->where('key', 'dokumen')->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->put(route('avana.menu-builder.update', $dokumen), [
            ...menuPayload($dokumen, null),
            // The modal serialises modules as text, not an array.
            'modules' => 'document, employee',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $dokumen->refresh();

    expect($dokumen->modules)->toBe(['document', 'employee'])
        ->and($dokumen->parent_id)->toBeNull();
});

it('blocks a non-super-admin from moving menus', function (): void {
    $hrAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $dokumen = MenuItem::forTenant($this->tenantId)->where('key', 'dokumen')->firstOrFail();

    $this->actingAs($hrAdmin)
        ->put(route('avana.menu-builder.update', $dokumen), menuPayload($dokumen, null))
        ->assertForbidden();

    expect($dokumen->refresh()->parent_id)->not->toBeNull();
});
