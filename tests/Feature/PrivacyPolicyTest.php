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

test('the terms of service page renders the seeded default content when unconfigured', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/legal/terms')
            ->where('content', WebsiteSetting::defaultTermsOfServiceHtml())
        );
});

test('a super admin can edit the terms of service content shown on the public page', function () {
    WebsiteSetting::current()->update(['terms_of_service' => '<p>Custom syarat & ketentuan AvanaHR.</p>']);

    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('content', '<p>Custom syarat & ketentuan AvanaHR.</p>')
        );
});

test('the account deletion page renders the configured content', function () {
    WebsiteSetting::current()->update([
        'account_deletion' => '<p>Penghapusan akun AvanaHR.</p>',
    ]);

    $this->get(route('account-deletion'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/legal/account-deletion')
            ->where('content', '<p>Penghapusan akun AvanaHR.</p>')
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
