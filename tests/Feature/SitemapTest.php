<?php

use App\Models\News;

test('sitemap lists static marketing pages and published news, drafts excluded', function () {
    $published = News::factory()->create(['status' => 'published', 'slug' => 'visible-article']);
    News::factory()->create(['status' => 'draft', 'slug' => 'hidden-draft']);

    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    $xml = $response->getContent();

    expect($xml)
        ->toContain(route('home'))
        ->toContain(route('berita'))
        ->toContain(route('features.show', ['featureSlug' => 'core-hr']))
        ->toContain(route('features.show', ['featureSlug' => 'data-karyawan']))
        ->toContain(route('features.show', ['featureSlug' => 'struktur-organisasi']))
        ->toContain(route('features.show', ['featureSlug' => 'administrasi-karier']))
        ->toContain(route('solution.crm'))
        ->toContain(route('berita.show', $published))
        ->not->toContain('hidden-draft');
});
