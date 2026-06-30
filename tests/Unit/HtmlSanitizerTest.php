<?php

use App\Support\HtmlSanitizer;

it('returns an empty string for blank input', function (): void {
    expect(HtmlSanitizer::clean(''))->toBe('');
    expect(HtmlSanitizer::clean('   '))->toBe('');
});

it('removes script tags and their contents', function (): void {
    $clean = HtmlSanitizer::clean('<p>Hi</p><script>alert(1)</script>');

    expect($clean)
        ->toContain('<p>Hi</p>')
        ->not->toContain('script')
        ->not->toContain('alert(1)');
});

it('strips event handler attributes but keeps the element', function (): void {
    $clean = HtmlSanitizer::clean('<p onclick="steal()">Halo</p>');

    expect($clean)
        ->toContain('Halo')
        ->not->toContain('onclick');
});

it('drops javascript hrefs but keeps safe links', function (): void {
    expect(HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>'))
        ->not->toContain('javascript:');

    expect(HtmlSanitizer::clean('<a href="https://avanahr.co.id">x</a>'))
        ->toContain('href="https://avanahr.co.id"');
});

it('unwraps unknown tags while keeping their text', function (): void {
    $clean = HtmlSanitizer::clean('<div><font color="red">Teks {{nama}}</font></div>');

    expect($clean)
        ->toContain('Teks {{nama}}')
        ->not->toContain('<div')
        ->not->toContain('<font');
});

it('keeps the allowed formatting tags', function (): void {
    $body = '<h2>Judul</h2><p><strong>Tebal</strong> <em>miring</em></p><ul><li>A</li></ul>';

    expect(HtmlSanitizer::clean($body))
        ->toContain('<h2>Judul</h2>')
        ->toContain('<strong>Tebal</strong>')
        ->toContain('<em>miring</em>')
        ->toContain('<li>A</li>');
});

it('preserves placeholder tokens inside formatted html', function (): void {
    expect(HtmlSanitizer::clean('<p>Halo <strong>{{nama}}</strong></p>'))
        ->toContain('{{nama}}');
});
