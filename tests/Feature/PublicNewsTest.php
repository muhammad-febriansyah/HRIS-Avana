<?php

use App\Models\News;
use Inertia\Testing\AssertableInertia;

test('the landing page shares published news, newest first, drafts excluded', function () {
    News::factory()->create(['status' => 'published', 'published_at' => now()->subDays(2), 'title' => 'Older published']);
    News::factory()->create(['status' => 'published', 'published_at' => now()->subDay(), 'title' => 'Newer published']);
    News::factory()->create(['status' => 'draft', 'published_at' => null, 'title' => 'Still a draft']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->has('news', 2)
            ->where('news.0.title', 'Newer published')
        );
});

test('guests can browse the public news index and only see published articles', function () {
    News::factory()->create(['status' => 'published', 'title' => 'Visible article']);
    News::factory()->create(['status' => 'draft', 'title' => 'Hidden draft']);

    $this->get(route('berita'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/berita/index')
            ->has('news.data', 1)
            ->where('news.data.0.title', 'Visible article')
        );
});

test('guests can read a published article by slug, with an absolute share URL', function () {
    $news = News::factory()->create(['status' => 'published', 'title' => 'Read me', 'slug' => 'read-me']);

    $this->get(route('berita.show', $news))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/berita/show')
            ->where('news.title', 'Read me')
            ->where('news.url', route('berita.show', $news))
        );
});

test('the news index paginates once there are more articles than fit a page', function () {
    News::factory(7)->create(['status' => 'published']);

    $this->get(route('berita'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/berita/index')
            ->has('news.data', 6)
            ->where('news.last_page', 2)
        );

    $this->get(route('berita', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('news.data', 1)
        );
});

test('a draft article 404s for guests instead of leaking', function () {
    $news = News::factory()->create(['status' => 'draft', 'slug' => 'not-yet']);

    $this->get(route('berita.show', $news))->assertNotFound();
});
