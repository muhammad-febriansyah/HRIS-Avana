<?php

use App\Models\WebsiteSetting;
use Inertia\Testing\AssertableInertia;

test('the privacy policy page renders the seeded default content when unconfigured', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/legal/privacy')
            ->where('content', WebsiteSetting::defaultPrivacyPolicyHtml())
        );
});

test('a super admin can edit the privacy policy content shown on the public page', function () {
    WebsiteSetting::current()->update(['privacy_policy' => '<p>Custom kebijakan privasi AvanaHR.</p>']);

    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('content', '<p>Custom kebijakan privasi AvanaHR.</p>')
        );
});

test('the landing page shares the app store links only once configured, so the footer badges stay conditional', function () {
    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('website.apps.playstore_url', null)
            ->where('website.apps.appstore_url', null)
        );

    WebsiteSetting::current()->update([
        'playstore_url' => 'https://play.google.com/store/apps/details?id=id.avanahr.app',
        'appstore_url' => 'https://apps.apple.com/id/app/avanahr/id0000000000',
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('website.apps.playstore_url', 'https://play.google.com/store/apps/details?id=id.avanahr.app')
            ->where('website.apps.appstore_url', 'https://apps.apple.com/id/app/avanahr/id0000000000')
        );
});
