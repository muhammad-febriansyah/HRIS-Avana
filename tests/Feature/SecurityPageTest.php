<?php

use Inertia\Testing\AssertableInertia;

test('the security page renders for guests', function () {
    $this->get(route('security'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/security')
            ->has('website.site_name')
        );
});

test('the security page does not disturb the landing page or auth routes', function () {
    $this->get(route('home'))->assertOk();
    $this->get(route('login'))->assertOk();
    $this->get(route('security'))->assertOk();
});
