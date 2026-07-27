<?php

namespace App\Support;

/**
 * Dependency-free text extraction for uploaded SOP PDFs.
 *
 * The extracted text is what the AI assistant searches and answers from, so it
 * only needs to be good enough to read back: content streams are inflated and
 * the text-showing operators (`Tj`, `TJ`, `'`, `"`) are replayed into a plain
 * string. Scanned/image-only PDFs and PDFs using exotic font encodings yield
 * nothing — callers fall back to the summary the admin typed in.
 */
final class PdfTextExtractor
{
    /**
     * Hard ceiling on the stored text so one huge SOP cannot blow up the
     * assistant's prompt (or the `content` column).
     */
    private const MAX_CHARS = 40000;

    /**
     * Extract readable text from a PDF file, or an empty string when the file
     * is unreadable, image-only, or encoded in a way we cannot decode.
     */
    public static function fromFile(string $path, int $maxChars = self::MAX_CHARS): string
    {
        if (! is_readable($path)) {
            return '';
        }

        $raw = @file_get_contents($path);

        if ($raw === false || $raw === '') {
            return '';
        }

        return self::fromString($raw, $maxChars);
    }

    /**
     * Extract readable text from raw PDF bytes.
     */
    public static function fromString(string $raw, int $maxChars = self::MAX_CHARS): string
    {
        $text = '';

        foreach (self::contentStreams($raw) as $stream) {
            $text .= self::readTextOperators($stream)."\n";

            if (mb_strlen($text) > $maxChars * 2) {
                break;
            }
        }

        $text = self::normalise($text);

        return self::looksLikeText($text) ? mb_substr($text, 0, $maxChars) : '';
    }

    /**
     * Every inflated (or already plain) content stream inside the document.
     *
     * @return array<int, string>
     */
    private static function contentStreams(string $raw): array
    {
        if (preg_match_all('/stream\r?\n?(.*?)endstream/s', $raw, $matches) === false) {
            return [];
        }

        $streams = [];

        foreach ($matches[1] ?? [] as $chunk) {
            $decoded = self::inflate($chunk);

            // Only keep streams that actually carry text-drawing operators;
            // font programs and images are noise.
            if ($decoded !== '' && str_contains($decoded, 'BT')) {
                $streams[] = $decoded;
            }
        }

        return $streams;
    }

    /**
     * Inflate a FlateDecode stream, falling back to the raw bytes when the
     * stream is stored uncompressed.
     */
    private static function inflate(string $chunk): string
    {
        $trimmed = ltrim($chunk, "\r\n");

        foreach ([$trimmed, $chunk] as $candidate) {
            $result = @gzuncompress($candidate);

            if ($result === false) {
                $result = @gzinflate($candidate);
            }

            if (is_string($result) && $result !== '') {
                return $result;
            }
        }

        return str_contains($chunk, 'BT') ? $chunk : '';
    }

    /**
     * Replay a content stream's text operators into a plain string.
     *
     * Walks the stream token by token so escaped and nested parentheses inside
     * literal strings survive — a regex over `(...)` would truncate on those.
     */
    private static function readTextOperators(string $stream): string
    {
        $out = '';
        $length = strlen($stream);
        $index = 0;

        while ($index < $length) {
            $char = $stream[$index];

            if ($char === '(') {
                [$value, $index] = self::readLiteralString($stream, $index + 1);
                $out .= $value;

                continue;
            }

            if ($char === '<' && ($stream[$index + 1] ?? '') !== '<') {
                [$value, $index] = self::readHexString($stream, $index + 1);
                $out .= $value;

                continue;
            }

            // A large negative kern inside a TJ array stands in for a space.
            if ($char === '-' || ctype_digit($char)) {
                $start = $index;

                while ($index < $length && (ctype_digit($stream[$index]) || $stream[$index] === '-' || $stream[$index] === '.')) {
                    $index++;
                }

                if ((float) substr($stream, $start, $index - $start) <= -100.0) {
                    $out .= ' ';
                }

                continue;
            }

            if (ctype_alpha($char) || $char === "'" || $char === '"' || $char === '*') {
                $start = $index;

                while ($index < $length && (ctype_alpha($stream[$index]) || $stream[$index] === "'" || $stream[$index] === '"' || $stream[$index] === '*')) {
                    $index++;
                }

                $operator = substr($stream, $start, $index - $start);

                if (in_array($operator, ['Td', 'TD', 'T*', 'ET', "'", '"'], true)) {
                    $out .= "\n";
                }

                continue;
            }

            $index++;
        }

        return $out;
    }

    /**
     * Read a PDF literal string `(...)`, honouring escapes and nested parens.
     *
     * @return array{0: string, 1: int} the decoded value and the next offset
     */
    private static function readLiteralString(string $stream, int $index): array
    {
        $length = strlen($stream);
        $depth = 1;
        $value = '';

        while ($index < $length) {
            $char = $stream[$index];

            if ($char === '\\') {
                $next = $stream[$index + 1] ?? '';

                $value .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\x0C",
                    default => ctype_digit($next) ? '' : $next,
                };

                if (ctype_digit($next)) {
                    $octal = '';

                    while (strlen($octal) < 3 && ctype_digit($stream[$index + 1] ?? '')) {
                        $octal .= $stream[++$index];
                    }

                    $value .= chr(octdec($octal) % 256);
                    $index++;

                    continue;
                }

                $index += 2;

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return [$value, $index + 1];
                }
            }

            $value .= $char;
            $index++;
        }

        return [$value, $index];
    }

    /**
     * Read a PDF hex string `<...>`, decoding UTF-16BE when it carries a BOM.
     *
     * @return array{0: string, 1: int} the decoded value and the next offset
     */
    private static function readHexString(string $stream, int $index): array
    {
        $end = strpos($stream, '>', $index);

        if ($end === false) {
            return ['', strlen($stream)];
        }

        $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($stream, $index, $end - $index)) ?? '';

        if (strlen($hex) % 2 !== 0) {
            $hex .= '0';
        }

        $value = (string) hex2bin($hex);

        if (str_starts_with($value, "\xFE\xFF")) {
            $value = (string) mb_convert_encoding(substr($value, 2), 'UTF-8', 'UTF-16BE');
        }

        return [$value, $end + 1];
    }

    /**
     * Collapse the replayed operators into readable paragraphs.
     */
    private static function normalise(string $text): string
    {
        $text = (string) preg_replace('/[^\P{C}\n]+/u', '', $text);
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        $text = (string) preg_replace('/ *\n */', "\n", $text);

        return trim($text);
    }

    /**
     * Guard against fonts whose glyph codes decode to mojibake: real text is
     * mostly letters, digits, spaces and punctuation.
     */
    private static function looksLikeText(string $text): bool
    {
        $length = mb_strlen($text);

        if ($length < 20) {
            return false;
        }

        $readable = preg_match_all('/[\p{L}\p{N}\s\p{P}]/u', $text);

        return $readable !== false && $readable / $length >= 0.85;
    }
}
