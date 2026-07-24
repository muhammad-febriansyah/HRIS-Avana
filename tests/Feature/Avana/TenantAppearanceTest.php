<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Support\TenantTheme;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
});

it('renders the appearance editor with theme, tokens and presets', function (): void {
    actingAs($this->admin)
        ->get(route('avana.tampilan'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/tampilan/index', false)
            ->where('theme.sidebar_bg', '#FFFFFF')
            ->has('defaults.sidebar_accent')
            ->has('tokens.0.key')
            ->has('presets.0.colors'));
});

it('saves a valid theme to the tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.tampilan.update'), [
            'sidebar_bg' => '#0E1A3A',
            'sidebar_text' => '#AEB8D0',
            'sidebar_accent' => '#6E9BE6',
            'topbar_bg' => '#0E1A3A',
            'topbar_text' => '#FFFFFF',
        ])
        ->assertRedirect();

    expect($this->tenant->fresh()->theme)->toMatchArray([
        'sidebar_bg' => '#0E1A3A',
        'sidebar_accent' => '#6E9BE6',
    ]);
});

it('rejects invalid hex colours', function (): void {
    actingAs($this->admin)
        ->post(route('avana.tampilan.update'), [
            'sidebar_bg' => 'blue',
            'sidebar_text' => '#AEB8D0',
            'sidebar_accent' => '#6E9BE6',
            'topbar_bg' => '#0E1A3A',
            'topbar_text' => '#FFFFFF',
        ])
        ->assertSessionHasErrors('sidebar_bg');

    expect($this->tenant->fresh()->theme)->toBeNull();
});

it('resets the theme back to null (defaults)', function (): void {
    $this->tenant->update(['theme' => ['sidebar_bg' => '#000000']]);

    actingAs($this->admin)
        ->post(route('avana.tampilan.reset'))
        ->assertRedirect();

    expect($this->tenant->fresh()->theme)->toBeNull();
});

it('shares the resolved theme as a global inertia prop', function (): void {
    $this->tenant->update(['theme' => ['sidebar_bg' => '#123456']]);

    actingAs($this->admin)
        ->get(route('avana.tampilan'))
        ->assertInertia(fn (Assert $page) => $page->where('theme.sidebar_bg', '#123456'));
});

it('forbids a user without the appearance permission', function (): void {
    actingAs($this->employee)
        ->get(route('avana.tampilan'))
        ->assertForbidden();

    actingAs($this->employee)
        ->post(route('avana.tampilan.update'), TenantTheme::DEFAULTS)
        ->assertForbidden();
});

it('lets a super admin open the editor (platform theme)', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.tampilan'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/tampilan/index', false)
            ->has('theme.sidebar_bg'));
});

it('saves a super admin theme to the platform website settings', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.tampilan.update'), [
            'sidebar_bg' => '#101828',
            'sidebar_text' => '#AEB8D0',
            'sidebar_accent' => '#F59E0B',
            'topbar_bg' => '#101828',
            'topbar_text' => '#FFFFFF',
        ])
        ->assertRedirect();

    expect(WebsiteSetting::current()->theme)->toMatchArray([
        'sidebar_bg' => '#101828',
        'sidebar_accent' => '#F59E0B',
    ]);
    // The tenant's own theme is untouched.
    expect($this->tenant->fresh()->theme)->toBeNull();
});

it('resets the platform theme for a super admin', function (): void {
    WebsiteSetting::current()->update(['theme' => ['sidebar_bg' => '#000000']]);

    actingAs($this->superAdmin)
        ->post(route('avana.tampilan.reset'))
        ->assertRedirect();

    expect(WebsiteSetting::current()->theme)->toBeNull();
});

it('drops invalid colours and merges defaults when resolving', function (): void {
    $resolved = TenantTheme::resolve([
        'sidebar_bg' => '#abcdef',
        'sidebar_text' => 'not-a-color',
    ]);

    expect($resolved['sidebar_bg'])->toBe('#ABCDEF')
        ->and($resolved['sidebar_text'])->toBe(TenantTheme::DEFAULTS['sidebar_text'])
        ->and($resolved)->toHaveKeys(TenantTheme::keys());
});
