<?php

it('answers a web app preflight for the API', function (): void {
    $this->call('OPTIONS', '/api/v1/app-config', [], [], [], [
        'HTTP_ORIGIN' => 'https://mobile.avanahr.id',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ])->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin');
});

it('sends CORS headers on signed private files so the web app can draw them', function (): void {
    $this->get('/berkas/missing.jpg', ['Origin' => 'https://mobile.avanahr.id'])
        ->assertHeader('Access-Control-Allow-Origin');
});
