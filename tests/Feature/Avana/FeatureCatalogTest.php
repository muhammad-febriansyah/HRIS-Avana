<?php

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('lists the feature catalog for a super admin', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.katalog-fitur'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/katalog-fitur/index')
            ->has('features')
            ->has('moduleOptions')
            ->has('actions', 6));
});

it('forbids a tenant admin from the feature catalog', function (): void {
    actingAs($this->admin)->get(route('avana.katalog-fitur'))->assertForbidden();
});

it('creates a feature and seeds its per-action permission codes', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.katalog-fitur.store'), [
            'code' => 'e_learning',
            'name' => 'E-Learning',
            'module_group' => 'talent',
            'permission_modules' => ['e_learning'],
            'is_active' => true,
        ])
        ->assertRedirect();

    $feature = Feature::where('code', 'e_learning')->first();
    expect($feature)->not->toBeNull();
    expect($feature->permission_modules)->toBe(['e_learning']);

    $expected = ['e_learning.view', 'e_learning.create', 'e_learning.update', 'e_learning.archive', 'e_learning.export', 'e_learning.approve'];
    expect(Permission::whereIn('code', $expected)->count())->toBe(6);
});

it('auto-appears in the Hak Akses matrix after creation — no code change', function (): void {
    actingAs($this->superAdmin)->post(route('avana.katalog-fitur.store'), [
        'code' => 'e_learning',
        'name' => 'E-Learning',
        'module_group' => 'talent',
        'permission_modules' => ['e_learning'],
    ]);

    // Switched on for the company, which is what makes it theirs to grant.
    Tenant::findOrFail($this->superAdmin->tenant_id)
        ->features()
        ->create([
            'feature_id' => Feature::where('code', 'e_learning')->value('id'),
            'is_enabled' => true,
        ]);

    actingAs($this->superAdmin)
        ->get(route('avana.hak-akses'))
        ->assertInertia(fn ($page) => $page
            ->where('modules', fn ($modules) => collect($modules)->firstWhere('key', 'e_learning') !== null
                && collect($modules)->firstWhere('key', 'e_learning')['actionable'] === true));
});

it('rejects a duplicate or malformed code', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.katalog-fitur.store'), [
            'code' => 'crm',
            'name' => 'Duplikat',
            'module_group' => 'crm',
        ])
        ->assertSessionHasErrors('code');

    actingAs($this->superAdmin)
        ->post(route('avana.katalog-fitur.store'), [
            'code' => 'Bad Code',
            'name' => 'Salah',
            'module_group' => 'core',
        ])
        ->assertSessionHasErrors('code');
});

it('updates a feature without changing its code', function (): void {
    $feature = Feature::create([
        'code' => 'demo_edit',
        'name' => 'Demo',
        'module_group' => 'core',
        'permission_modules' => [],
        'is_active' => true,
    ]);

    actingAs($this->superAdmin)
        ->put(route('avana.katalog-fitur.update', $feature), [
            'code' => 'demo_edit',
            'name' => 'Demo Diubah',
            'module_group' => 'engagement',
            'permission_modules' => ['demo_edit'],
            'is_active' => false,
        ])
        ->assertRedirect();

    $feature->refresh();
    expect($feature->name)->toBe('Demo Diubah');
    expect($feature->module_group)->toBe('engagement');
    expect($feature->permission_modules)->toBe(['demo_edit']);
    expect($feature->is_active)->toBeFalse();
    expect(Permission::where('code', 'demo_edit.view')->exists())->toBeTrue();
});

it('deletes a feature and cascades its tenant links', function (): void {
    $tenant = Tenant::firstOrFail();
    $feature = Feature::create([
        'code' => 'demo_del',
        'name' => 'Demo Delete',
        'module_group' => 'core',
        'permission_modules' => [],
        'is_active' => true,
    ]);
    $tenant->features()->create(['feature_id' => $feature->id, 'is_enabled' => true]);

    actingAs($this->superAdmin)
        ->delete(route('avana.katalog-fitur.destroy', $feature))
        ->assertRedirect();

    expect(Feature::where('code', 'demo_del')->exists())->toBeFalse();
    expect($tenant->features()->where('feature_id', $feature->id)->exists())->toBeFalse();
});

it('leaves a feature the company is not subscribed to out of the matrix', function (): void {
    actingAs($this->superAdmin)->post(route('avana.katalog-fitur.store'), [
        'code' => 'e_learning',
        'name' => 'E-Learning',
        'module_group' => 'talent',
        'permission_modules' => ['e_learning'],
    ]);

    actingAs($this->superAdmin)
        ->get(route('avana.hak-akses'))
        ->assertInertia(fn ($page) => $page
            ->where('modules', fn ($modules) => collect($modules)->firstWhere('key', 'e_learning') === null));
});
