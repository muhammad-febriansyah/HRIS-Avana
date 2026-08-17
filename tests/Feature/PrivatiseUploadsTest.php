<?php

use App\Support\PrivateFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

it('moves a personal upload off the public disk', function (): void {
    Storage::disk('public')->put('avatars/user-1.png', 'foto');
    Storage::disk('public')->put('field-visits/photo-9.jpg', 'kunjungan');

    artisan('avana:privatise-uploads')->assertSuccessful();

    // Gone from the tree the web server answers without a session…
    Storage::disk('public')->assertMissing('avatars/user-1.png');
    Storage::disk('public')->assertMissing('field-visits/photo-9.jpg');

    // …and readable where the signed link looks for it.
    expect(Storage::disk(PrivateFile::DISK)->get('avatars/user-1.png'))->toBe('foto');
    expect(Storage::disk(PrivateFile::DISK)->get('field-visits/photo-9.jpg'))->toBe('kunjungan');
});

it('leaves published assets where the browser can reach them', function (): void {
    // Logos, onboarding slides and generated images are meant to be public;
    // dragging them behind a signature would break every screen showing them.
    Storage::disk('public')->put('company-logos/tenant-1.png', 'logo');
    Storage::disk('public')->put('onboarding/slide-1.png', 'slide');

    artisan('avana:privatise-uploads')->assertSuccessful();

    Storage::disk('public')->assertExists('company-logos/tenant-1.png');
    Storage::disk('public')->assertExists('onboarding/slide-1.png');
});

it('drops the stray public copy rather than overwriting the live file', function (): void {
    Storage::disk(PrivateFile::DISK)->put('avatars/user-1.png', 'yang dipakai');
    Storage::disk('public')->put('avatars/user-1.png', 'salinan lama');

    artisan('avana:privatise-uploads')->assertSuccessful();

    expect(Storage::disk(PrivateFile::DISK)->get('avatars/user-1.png'))->toBe('yang dipakai');
    Storage::disk('public')->assertMissing('avatars/user-1.png');
});

it('touches nothing on a dry run', function (): void {
    Storage::disk('public')->put('avatars/user-1.png', 'foto');

    artisan('avana:privatise-uploads', ['--dry-run' => true])->assertSuccessful();

    Storage::disk('public')->assertExists('avatars/user-1.png');
    Storage::disk(PrivateFile::DISK)->assertMissing('avatars/user-1.png');
});
