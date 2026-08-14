<?php

use Inertia\Testing\AssertableInertia;

test('the landing page renders the public marketing component for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->has('website.site_name')
            ->has('website.contact')
            ->has('website.social')
        );
});

test('the landing page keeps its auth entry points reachable', function () {
    $this->get(route('home'))->assertOk();
    $this->get(route('login'))->assertOk();
    $this->get(route('register'))->assertOk();
});
