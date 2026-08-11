<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenantId = (int) $this->user->tenant_id;
});

it('defines the self-service screens as children of one collapsible parent', function (): void {
    $section = collect(AvanaNav::groups())->firstWhere('title', 'LAYANAN SAYA');

    expect($section['items'])->toHaveCount(1)
        ->and($section['items'][0]['id'])->toBe('saya')
        ->and($section['items'][0]['label'])->toBe('Layanan Saya')
        ->and($section['items'][0]['children'])->not->toBeEmpty();

    $childIds = collect($section['items'][0]['children'])->pluck('id');

    expect($childIds)->toContain('saya-profil', 'saya-slip', 'saya-dokumen', 'saya-onboarding');
});

it('nests an already-seeded tenant self-service rows under the parent', function (): void {
    $parent = MenuItem::forTenant($this->tenantId)
        ->where('key', 'saya')
        ->whereNull('parent_id')
        ->firstOrFail();

    expect($parent->section)->toBe('LAYANAN SAYA')
        ->and($parent->href)->toBeNull();

    $strays = MenuItem::forTenant($this->tenantId)
        ->where('key', 'like', 'saya-%')
        ->whereNull('parent_id')
        ->count();

    expect($strays)->toBe(0)
        ->and(MenuItem::where('parent_id', $parent->id)->count())->toBeGreaterThan(0);
});

it('renders the tenant sidebar with self-service collapsed into a single row', function (): void {
    $groups = collect(AvanaNav::forUser($this->user));
    $section = $groups->firstWhere('title', 'LAYANAN SAYA');

    expect($section['items'])->toHaveCount(1)
        ->and($section['items'][0]['label'])->toBe('Layanan Saya')
        ->and($section['items'][0]['children'])->not->toBeEmpty();
});

it('keeps every self-service screen reachable after nesting', function (): void {
    $leafKeys = collect(AvanaNav::allLeaves($this->tenantId))->pluck('id');

    expect($leafKeys)->toContain('saya-profil', 'saya-cuti', 'saya-slip', 'saya-onboarding')
        ->and($leafKeys)->not->toContain('saya');
});

it('seeds a fresh tenant with the nested structure and no empty duplicates', function (): void {
    $tenant = Tenant::create([
        'name' => 'Uji Nesting Menu',
        'slug' => 'uji-nesting-menu',
        'status' => 'active',
    ]);

    AvanaNav::seedDefaultsFor($tenant->id);
    AvanaNav::seedDefaultsFor($tenant->id);

    $parents = MenuItem::forTenant($tenant->id)->where('key', 'saya')->get();

    expect($parents)->toHaveCount(1);

    $children = MenuItem::forTenant($tenant->id)->where('parent_id', $parents->first()->id)->get();

    expect($children->pluck('key'))->toContain('saya-profil', 'saya-slip')
        ->and(MenuItem::forTenant($tenant->id)->where('key', 'like', 'saya-%')->whereNull('parent_id')->count())->toBe(0);
});
