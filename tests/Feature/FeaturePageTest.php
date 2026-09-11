<?php

use App\Http\Controllers\FeaturePageController;
use Inertia\Testing\AssertableInertia;

it('renders every public feature page with its slug', function (string $featureSlug): void {
    $this->get(route('features.show', ['featureSlug' => $featureSlug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/feature')
            ->where('featureSlug', $featureSlug)
            ->where('canonicalUrl', route('features.show', ['featureSlug' => $featureSlug])),
        );
})->with(FeaturePageController::FEATURE_SLUGS);

it('renders CRM at its solution URL', function (): void {
    $this->get(route('solution.crm'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/feature')
            ->where('featureSlug', 'crm')
            ->where('canonicalUrl', route('solution.crm')),
        );
});

it('does not expose an unknown feature slug', function (): void {
    $this->get('/fitur/fitur-tidak-ada')->assertNotFound();
});
