<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist-based HTML sanitizer for rich-text fields rendered to PDF.
 *
 * Parses untrusted HTML with DOMDocument, drops dangerous elements, unwraps
 * unknown tags (keeping their text), and strips every attribute except a small
 * vetted set. This protects the `{!! !!}` raw output in the letter PDF view.
 */
class HtmlSanitizer
{
    /**
     * Tags kept as-is when they appear in the input.
     *
     * @var array<int, string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 's', 'u',
        'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4',
        'blockquote', 'span', 'a',
    ];

    /**
     * Attributes kept per tag; everything else is removed.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_ATTRS = [
        'a' => ['href'],
    ];

    /**
     * Tags removed together with their contents (not unwrapped).
     *
     * @var array<int, string>
     */
    private const DROP_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form'];

    /**
     * URL schemes permitted on `href` attributes.
     *
     * @var array<int, string>
     */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Return a sanitized copy of the given HTML string.
     */
    public static function clean(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__sanitizer_root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__sanitizer_root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Recursively sanitize the children of a node in place.
     */
    private static function sanitizeChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::sanitizeChildren($child);
                self::unwrap($child);

                continue;
            }

            self::cleanAttributes($child, $tag);
            self::sanitizeChildren($child);
        }
    }

    /**
     * Strip every attribute on an element except the vetted allowlist.
     */
    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'href' && ! self::isSafeUrl($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
            }
        }
    }

    /**
     * Replace an element with its child nodes, discarding the element itself.
     */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * Whether a URL uses a permitted scheme (relative URLs are allowed).
     */
    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null || $scheme === false) {
            return true;
        }

        return in_array(strtolower($scheme), self::SAFE_SCHEMES, true);
    }
}
