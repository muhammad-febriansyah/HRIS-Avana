<?php

use App\Models\News;
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

it('allows only super admins to manage news', function (): void {
    actingAs($this->admin)->get(route('avana.berita'))->assertForbidden();
    actingAs($this->superAdmin)->get(route('avana.berita'))->assertOk();
});

it('searches and paginates news', function (): void {
    foreach (range(1, 10) as $number) {
        News::create([
            'title' => $number === 10 ? 'Company Update' : "News {$number}",
            'slug' => "news-{$number}",
            'body' => 'Isi berita.',
            'status' => 'draft',
        ]);
    }

    actingAs($this->superAdmin)
        ->get(route('avana.berita', ['q' => 'Company']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Company')
            ->where('news.total', 1)
            ->where('news.data.0.title', 'Company Update'));
});

it('renders a news detail page for a super admin', function (): void {
    $news = News::create([
        'title' => 'Detail Berita',
        'slug' => 'detail-berita',
        'body' => '<p>Isi <strong>berita</strong>.</p>',
        'status' => 'published',
    ]);

    actingAs($this->superAdmin)
        ->get(route('avana.berita.show', $news))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/berita/show', false)
            ->where('news.title', 'Detail Berita')
            ->where('news.body', '<p>Isi <strong>berita</strong>.</p>'));
});

it('creates news and stores its image', function (): void {
    Storage::fake('public');

    actingAs($this->superAdmin)->post(route('avana.berita.store'), [
        'title' => 'Kabar Baru HRIS',
        'slug' => 'Kabar Baru HRIS',
        'body' => 'Isi berita.',
        'status' => 'published',
        'image' => UploadedFile::fake()->image('news.jpg'),
    ])->assertSessionHas('success');

    $news = News::firstOrFail();
    expect($news->slug)->toBe('kabar-baru-hris')->and($news->published_at)->not->toBeNull();
    Storage::disk('public')->assertExists($news->image_path);
});

it('replaces the old image and deletes news image on destroy', function (): void {
    Storage::fake('public');
    $news = News::create([
        'title' => 'Lama', 'slug' => 'lama', 'body' => 'Isi',
        'image_path' => UploadedFile::fake()->image('old.jpg')->store('news', 'public'),
    ]);
    $old = $news->image_path;

    actingAs($this->superAdmin)->post(route('avana.berita.update', $news), [
        'title' => 'Baru', 'body' => 'Isi baru', 'status' => 'draft',
        'image' => UploadedFile::fake()->image('new.jpg'),
    ])->assertSessionHas('success');

    $news->refresh();
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($news->image_path);

    actingAs($this->superAdmin)->delete(route('avana.berita.destroy', $news))->assertSessionHas('success');
    Storage::disk('public')->assertMissing($news->image_path);
});
