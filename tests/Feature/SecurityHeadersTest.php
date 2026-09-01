<?php

test('html responses carry the browser security headers', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Permissions-Policy'))->toContain('geolocation=(self)');
});

test('the content security policy ships report-only until it is explicitly enforced', function () {
    $this->get('/login')
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('Content-Security-Policy-Report-Only');
});

test('enforcing the policy moves it to the blocking header', function () {
    config(['security.csp.enforce' => true]);

    $response = $this->get('/login')->assertOk();

    $policy = $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain('https://fonts.gstatic.com');
});

test('the map and font hosts the app actually uses are allowed', function () {
    $policy = (string) $this->get('/login')->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain('https://*.tile.openstreetmap.org')
        ->toContain('https://routing.openstreetmap.de')
        ->toContain('https://fonts.googleapis.com');
});

test('HSTS is withheld outside production so localhost is never pinned to https', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
});

test('json responses get the sniffing guard without the document-only headers', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only')
        ->assertHeaderMissing('X-Frame-Options');
});
