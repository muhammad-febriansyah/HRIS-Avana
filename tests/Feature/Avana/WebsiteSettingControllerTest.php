<?php

use App\Models\User;
use App\Models\WebsiteSetting;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
});

it('renders the website settings editor for a super admin', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.website-settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/website-settings/index', false)
            ->has('settings.site_name')
            ->has('settings.logo_url')
            ->has('settings.favicon_url')
            ->has('settings.meta_keywords')
            ->has('settings.social_instagram'));
});

it('forbids a non super admin from viewing or updating website settings', function (): void {
    actingAs($this->admin)
        ->get(route('avana.website-settings'))
        ->assertForbidden();

    actingAs($this->admin)
        ->post(route('avana.website-settings.update'), ['site_name' => 'Nope'])
        ->assertForbidden();

    expect(WebsiteSetting::query()->where('site_name', 'Nope')->exists())->toBeFalse();
});

it('saves the text settings for a super admin', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.website-settings.update'), [
            'site_name' => 'AvanaHR',
            'tagline' => 'HR modern',
            'meta_title' => 'AvanaHR — HRIS',
            'meta_keywords' => 'hris, payroll',
            'social_instagram' => 'https://instagram.com/avanahr',
            'contact_email' => 'halo@avanahr.co.id',
        ])
        ->assertSessionHas('success');

    $settings = WebsiteSetting::current();

    expect($settings->site_name)->toBe('AvanaHR');
    expect($settings->meta_keywords)->toBe('hris, payroll');
    expect($settings->social_instagram)->toBe('https://instagram.com/avanahr');
    expect($settings->contact_email)->toBe('halo@avanahr.co.id');
});

it('validates the contact email', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.website-settings.update'), [
            'contact_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('contact_email');
});

it('stores an uploaded logo on the public disk', function (): void {
    Storage::fake('public');

    actingAs($this->superAdmin)
        ->post(route('avana.website-settings.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 320, 120),
        ])
        ->assertSessionHas('success');

    $path = WebsiteSetting::current()->logo_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('deletes the previous logo when a new one is uploaded', function (): void {
    Storage::fake('public');

    actingAs($this->superAdmin)
        ->post(route('avana.website-settings.update'), [
            'logo' => UploadedFile::fake()->image('old.png'),
        ]);

    $oldPath = WebsiteSetting::current()->logo_path;
    Storage::disk('public')->assertExists($oldPath);

    actingAs($this->superAdmin)
        ->post(route('avana.website-settings.update'), [
            'logo' => UploadedFile::fake()->image('new.png'),
        ]);

    $newPath = WebsiteSetting::current()->logo_path;

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

it('removes the logo and frees the file when remove flag is set', function (): void {
    Storage::fake('public');

    actingAs($this->superAdmin)
        ->post(route('avana.website-settings.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

    $path = WebsiteSetting::current()->logo_path;
    Storage::disk('public')->assertExists($path);

    actingAs($this->superAdmin)
        ->post(route('avana.website-settings.update'), [
            'remove_logo' => true,
        ])
        ->assertSessionHas('success');

    expect(WebsiteSetting::current()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
