<?php

use App\Models\OnboardingSlide;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('renders the onboarding slides screen for a super admin', function (): void {
    OnboardingSlide::create(['title' => 'Slide A', 'subtitle' => 'Sub A', 'sort_order' => 0, 'is_active' => true]);

    actingAs($this->superAdmin)
        ->get(route('avana.onboarding-slides'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/onboarding-slides/index', false)
            ->has('slides', 1)
            ->where('slides.0.title', 'Slide A'));
});

it('forbids a non super admin from managing onboarding slides', function (): void {
    actingAs($this->admin)->get(route('avana.onboarding-slides'))->assertForbidden();

    actingAs($this->admin)
        ->post(route('avana.onboarding-slides.store'), ['title' => 'Nope'])
        ->assertForbidden();

    expect(OnboardingSlide::query()->where('title', 'Nope')->exists())->toBeFalse();
});

it('creates a slide for a super admin', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.onboarding-slides.store'), [
            'title' => 'Absensi Praktis',
            'subtitle' => 'Clock-in dari ponsel',
            'sort_order' => 2,
            'is_active' => true,
        ])
        ->assertSessionHas('success');

    $slide = OnboardingSlide::where('title', 'Absensi Praktis')->firstOrFail();

    expect($slide->subtitle)->toBe('Clock-in dari ponsel');
    expect($slide->sort_order)->toBe(2);
    expect($slide->is_active)->toBeTrue();
});

it('requires a title', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.onboarding-slides.store'), ['subtitle' => 'no title'])
        ->assertSessionHasErrors('title');
});

it('stores an uploaded SVG on the public disk', function (): void {
    Storage::fake('public');

    actingAs($this->superAdmin)
        ->post(route('avana.onboarding-slides.store'), [
            'title' => 'Vector Slide',
            'image' => UploadedFile::fake()->create('slide.svg', 8, 'image/svg+xml'),
        ])
        ->assertSessionHas('success');

    $path = OnboardingSlide::where('title', 'Vector Slide')->firstOrFail()->image_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('updates a slide and swaps its image', function (): void {
    Storage::fake('public');

    $slide = OnboardingSlide::create([
        'title' => 'Old',
        'image_path' => UploadedFile::fake()->image('old.png')->store('onboarding', 'public'),
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $oldPath = $slide->image_path;

    actingAs($this->superAdmin)
        ->post(route('avana.onboarding-slides.update', $slide), [
            'title' => 'New',
            'is_active' => false,
            'image' => UploadedFile::fake()->image('new.png'),
        ])
        ->assertSessionHas('success');

    $slide->refresh();

    expect($slide->title)->toBe('New');
    expect($slide->is_active)->toBeFalse();
    expect($slide->image_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($slide->image_path);
});

it('deletes a slide and frees its image', function (): void {
    Storage::fake('public');

    $slide = OnboardingSlide::create([
        'title' => 'Gone',
        'image_path' => UploadedFile::fake()->image('x.png')->store('onboarding', 'public'),
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $path = $slide->image_path;

    actingAs($this->superAdmin)
        ->delete(route('avana.onboarding-slides.destroy', $slide))
        ->assertSessionHas('success');

    expect(OnboardingSlide::find($slide->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
