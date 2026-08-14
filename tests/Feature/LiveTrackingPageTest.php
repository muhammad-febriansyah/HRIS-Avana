<?php

use Inertia\Testing\AssertableInertia;

test('the live tracking page renders for guests', function () {
    $this->get(route('live-tracking'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/live-tracking')
            ->has('website.site_name')
        );
});

test('the live tracking page does not disturb the landing page or auth routes', function () {
    $this->get(route('home'))->assertOk();
    $this->get(route('login'))->assertOk();
    $this->get(route('live-tracking'))->assertOk();
});
