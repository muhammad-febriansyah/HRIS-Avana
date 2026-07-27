<?php

use App\Models\EotmCoreValue;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Starter core values for Employee of the Month voting, so the ballot has
 * something to pick from on day one. Fully editable from the Sosmed screen —
 * nothing keys off these names.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const VALUES = [
        ['Jujur', 'shield-check', '#16A34A'],
        ['Kerjasama', 'handshake', '#2F54C9'],
        ['Integritas', 'award', '#7C3AED'],
        ['Komunikasi', 'message-circle', '#0EA5E9'],
        ['Customer Focus', 'heart', '#DB2777'],
    ];

    public function up(): void
    {
        Tenant::query()->get()->each(function (Tenant $tenant): void {
            foreach (self::VALUES as $index => [$name, $icon, $color]) {
                EotmCoreValue::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    [
                        'icon' => $icon,
                        'color' => $color,
                        'status' => 'active',
                        'sort_order' => $index,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        // Non-destructive: a tenant may have edited or built on these.
    }
};
