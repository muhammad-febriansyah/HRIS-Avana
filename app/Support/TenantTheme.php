<?php

namespace App\Support;

/**
 * Per-tenant appearance theme for the admin panel chrome (sidebar + topbar).
 *
 * A theme is five hex colours; borders and tints are derived from them in the
 * frontend. {@see self::resolve()} merges a stored (possibly partial or invalid)
 * theme over the built-in defaults and drops anything that is not a valid hex.
 */
final class TenantTheme
{
    /**
     * The built-in default theme (matches the classic AvanaHR look).
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'sidebar_bg' => '#FFFFFF',
        'sidebar_text' => '#5B6472',
        'sidebar_accent' => '#2F54C9',
        'topbar_bg' => '#FFFFFF',
        'topbar_text' => '#1A2333',
    ];

    /**
     * Token metadata for the settings UI: key, label, short hint.
     *
     * @var array<int, array{key: string, label: string, hint: string}>
     */
    public const TOKENS = [
        ['key' => 'sidebar_bg', 'label' => 'Latar Sidebar', 'hint' => 'Warna dasar menu samping'],
        ['key' => 'sidebar_text', 'label' => 'Teks Sidebar', 'hint' => 'Warna item menu non-aktif'],
        ['key' => 'sidebar_accent', 'label' => 'Aksen Sidebar', 'hint' => 'Warna item menu aktif'],
        ['key' => 'topbar_bg', 'label' => 'Latar Topbar', 'hint' => 'Warna bar atas (navbar)'],
        ['key' => 'topbar_text', 'label' => 'Teks Topbar', 'hint' => 'Warna teks & ikon bar atas'],
    ];

    /**
     * Ready-made palettes the admin can apply in one click.
     *
     * @var array<int, array{key: string, name: string, colors: array<string, string>}>
     */
    public const PRESETS = [
        [
            'key' => 'default',
            'name' => 'AvanaHR (Default)',
            'colors' => self::DEFAULTS,
        ],
        [
            'key' => 'midnight',
            'name' => 'Midnight',
            'colors' => [
                'sidebar_bg' => '#0E1A3A',
                'sidebar_text' => '#AEB8D0',
                'sidebar_accent' => '#6E9BE6',
                'topbar_bg' => '#0E1A3A',
                'topbar_text' => '#F4F6FB',
            ],
        ],
        [
            'key' => 'forest',
            'name' => 'Forest',
            'colors' => [
                'sidebar_bg' => '#0F2E22',
                'sidebar_text' => '#A7C4B5',
                'sidebar_accent' => '#34D399',
                'topbar_bg' => '#FFFFFF',
                'topbar_text' => '#1A2333',
            ],
        ],
        [
            'key' => 'plum',
            'name' => 'Plum',
            'colors' => [
                'sidebar_bg' => '#2A1533',
                'sidebar_text' => '#C9B3D3',
                'sidebar_accent' => '#C084FC',
                'topbar_bg' => '#2A1533',
                'topbar_text' => '#F6F0FA',
            ],
        ],
        [
            'key' => 'sand',
            'name' => 'Sand',
            'colors' => [
                'sidebar_bg' => '#FBF7EF',
                'sidebar_text' => '#6B5E48',
                'sidebar_accent' => '#C2751B',
                'topbar_bg' => '#FFFFFF',
                'topbar_text' => '#1A2333',
            ],
        ],
    ];

    /**
     * Merge a stored theme over the defaults, keeping only valid hex colours.
     *
     * @param  array<string, mixed>|null  $raw
     * @return array<string, string>
     */
    public static function resolve(?array $raw): array
    {
        $theme = self::DEFAULTS;

        foreach (self::DEFAULTS as $key => $default) {
            $value = $raw[$key] ?? null;

            if (is_string($value) && self::isHex($value)) {
                $theme[$key] = strtoupper($value);
            }
        }

        return $theme;
    }

    /**
     * The token keys.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * Whether a string is a #RGB or #RRGGBB hex colour.
     */
    public static function isHex(string $value): bool
    {
        return preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $value) === 1;
    }
}
