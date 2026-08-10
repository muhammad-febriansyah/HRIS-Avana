<?php

test('the app answers normally when MAINTENANCE_MODE is off', function () {
    config(['app.maintenance_mode' => false]);

    $this->get('/login')->assertOk();
});

test('MAINTENANCE_MODE=true closes every page with the branded 503', function () {
    config(['app.maintenance_mode' => true]);

    $this->get('/login')->assertStatus(503);
});

test('the maintenance secret still lets a request through', function () {
    config([
        'app.maintenance_mode' => true,
        'app.maintenance_secret' => 'letmein',
    ]);

    $this->get('/login?secret=letmein')->assertOk();
    $this->get('/login?secret=wrong')->assertStatus(503);
});
