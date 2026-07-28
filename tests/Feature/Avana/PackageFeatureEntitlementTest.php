<?php

use App\Models\AiTokenPack;
use App\Models\Feature;
use App\Models\Package;
use App\Models\SubscriptionOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SubscriptionStatus;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->tenantAdmin->tenant_id);
});

it('stores the modules a super admin ticks for a package', function (): void {
    $features = Feature::query()->take(3)->pluck('id')->all();

    actingAs($this->superAdmin)
        ->post(route('avana.paket.store'), [
            'name' => 'Paket Dashboard Saja',
            'price' => 250_000,
            'billing_cycle' => 'monthly',
            'features' => $features,
            'is_active' => true,
            'is_popular' => false,
        ])
        ->assertSessionHas('success');

    $package = Package::where('name', 'Paket Dashboard Saja')->firstOrFail();

    expect($package->entitledFeatureIds())->toEqualCanonicalizing($features);
});

it('files a custom AI token allowance as a sellable pack and uses it as the quota', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.paket.store'), [
            'name' => 'Paket Token Custom',
            'price' => 750_000,
            'billing_cycle' => 'monthly',
            'token_pack' => [
                'name' => 'Paket Kilat',
                'token_amount' => 750_000,
                'price' => 200_000,
            ],
        ])
        ->assertSessionHas('success');

    $pack = AiTokenPack::where('name', 'Paket Kilat')->firstOrFail();

    expect($pack->token_amount)->toBe(750_000)
        ->and($pack->price)->toBe(200_000)
        ->and($pack->is_active)->toBeTrue()
        ->and(Package::where('name', 'Paket Token Custom')->firstOrFail()->ai_token_quota)->toBe(750_000);
});

it('reuses an identical token pack instead of duplicating the catalogue', function (): void {
    $pack = AiTokenPack::create([
        'name' => 'Paket Starter',
        'token_amount' => 500_000,
        'price' => 150_000,
        'is_active' => true,
    ]);

    actingAs($this->superAdmin)
        ->post(route('avana.paket.store'), [
            'name' => 'Paket Pakai Ulang',
            'price' => 1_000_000,
            'billing_cycle' => 'monthly',
            'token_pack' => [
                'name' => 'Nama Lain Tapi Sama',
                'token_amount' => 500_000,
                'price' => 150_000,
            ],
        ])
        ->assertSessionHas('success');

    expect(AiTokenPack::where('token_amount', 500_000)->where('price', 150_000)->count())->toBe(1)
        ->and(AiTokenPack::where('name', 'Nama Lain Tapi Sama')->exists())->toBeFalse()
        ->and(Package::where('name', 'Paket Pakai Ulang')->firstOrFail()->ai_token_quota)->toBe($pack->token_amount);
});

it('rejects a custom token allowance that has no name or price', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.paket.store'), [
            'name' => 'Paket Tanpa Harga',
            'price' => 300_000,
            'billing_cycle' => 'monthly',
            'token_pack' => ['token_amount' => 250_000],
        ])
        ->assertSessionHasErrors(['token_pack.name', 'token_pack.price']);

    expect(Package::where('name', 'Paket Tanpa Harga')->exists())->toBeFalse();
});

it('offers the active token packs to the paket screen', function (): void {
    AiTokenPack::create([
        'name' => 'Paket Aktif',
        'token_amount' => 100_000,
        'price' => 50_000,
        'is_active' => true,
    ]);
    AiTokenPack::create([
        'name' => 'Paket Arsip',
        'token_amount' => 200_000,
        'price' => 80_000,
        'is_active' => false,
    ]);

    $packs = collect(
        actingAs($this->superAdmin)->get(route('avana.paket'))->viewData('page')['props']['tokenPacks']
    );

    expect($packs->pluck('name'))->toContain('Paket Aktif')
        ->and($packs->pluck('name'))->not->toContain('Paket Arsip');
});

it('exposes the module catalog and each package selection to the paket screen', function (): void {
    $package = Package::create([
        'name' => 'Paket Kecil',
        'code' => 'kecil',
        'price' => 100_000,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);
    $feature = Feature::query()->firstOrFail();
    $package->features()->sync([$feature->id => ['is_enabled' => true]]);

    actingAs($this->superAdmin)
        ->get(route('avana.paket'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/paket/index', false)
            ->has('featureCatalog.0', fn (Assert $row) => $row
                ->has('id')->has('code')->has('name')->has('group'))
            ->etc());

    $packages = collect(
        actingAs($this->superAdmin)->get(route('avana.paket'))->viewData('page')['props']['packages']
    );

    expect($packages->firstWhere('name', 'Paket Kecil')['feature_ids'])->toBe([$feature->id]);
});

it('lists the modules a tier grants on the pricing cards', function (): void {
    $package = Package::create([
        'name' => 'Paket Ringkas',
        'code' => 'ringkas',
        'price' => 300_000,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);
    $feature = Feature::where('code', 'attendance')->first() ?? Feature::query()->firstOrFail();
    $package->features()->sync([$feature->id => ['is_enabled' => true]]);

    $packages = collect(
        actingAs($this->tenantAdmin)->get(route('avana.langganan'))->viewData('page')['props']['packages']
    );

    $ringkas = $packages->firstWhere('name', 'Paket Ringkas');

    expect($ringkas['features'])->toBe([$feature->name])
        ->and($ringkas['grants_all_features'])->toBeFalse();

    // A package nobody scoped still advertises the whole catalogue.
    $unscoped = $packages->firstWhere('name', 'HC Growth');

    expect($unscoped['grants_all_features'])->toBeTrue();
});

it('narrows the tenant to the modules of the package they paid for', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed']])]);

    $kept = Feature::query()->take(2)->pluck('id')->all();
    $dropped = Feature::query()->whereNotIn('id', $kept)->pluck('id');

    $package = Package::create([
        'name' => 'Paket Terbatas',
        'code' => 'terbatas',
        'price' => 400_000,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);
    $package->features()->sync(collect($kept)->mapWithKeys(fn (int $id): array => [$id => ['is_enabled' => true]])->all());

    actingAs($this->tenantAdmin)
        ->post(route('avana.langganan.purchase'), ['package_id' => $package->id, 'cycle' => 'monthly']);

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan.callback', ['order' => $order->order_number]));

    SubscriptionStatus::forget();

    $enabled = $this->tenant->features()->where('is_enabled', true)->pluck('feature_id')->all();

    expect($enabled)->toEqualCanonicalizing($kept);

    $stillOn = $this->tenant->features()
        ->whereIn('feature_id', $dropped)
        ->where('is_enabled', true)
        ->count();

    expect($stillOn)->toBe(0);
});

it('hands a tenant the modules of the tier a super admin moves them onto', function (): void {
    $kept = Feature::query()->take(1)->pluck('id')->all();

    $package = Package::create([
        'name' => 'Paket Pindah',
        'code' => 'pindah',
        'price' => 200_000,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);
    $package->features()->sync(collect($kept)->mapWithKeys(fn (int $id): array => [$id => ['is_enabled' => true]])->all());

    actingAs($this->superAdmin)
        ->put(route('avana.klien.update', $this->tenant), [
            'name' => $this->tenant->name,
            'package_id' => $package->id,
            'status' => 'active',
            'max_users' => 10,
            'max_employees' => 100,
            'max_branches' => 5,
        ])
        ->assertSessionHas('success');

    expect($this->tenant->features()->where('is_enabled', true)->pluck('feature_id')->all())
        ->toEqualCanonicalizing($kept);
});

it('keeps granting everything when a package scopes no modules', function (): void {
    $package = Package::create([
        'name' => 'Paket Penuh',
        'code' => 'penuh',
        'price' => 900_000,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    actingAs($this->superAdmin)
        ->put(route('avana.klien.update', $this->tenant), [
            'name' => $this->tenant->name,
            'package_id' => $package->id,
            'status' => 'active',
            'max_users' => 10,
            'max_employees' => 100,
            'max_branches' => 5,
        ])
        ->assertSessionHas('success');

    expect($this->tenant->features()->where('is_enabled', true)->count())
        ->toBe(Feature::query()->count());
});
