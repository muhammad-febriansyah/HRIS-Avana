<?php

use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Reparents the existing `kasbon` menu row under the new Finance group and adds
 * the Reimbursement and Settlement leaves beside it.
 *
 * AvanaNav::seedDefaultsFor keys on (tenant_id, key, parent_id), so on its own
 * it would leave the old `kasbon` row under `benefit-grp` and create a second
 * one under `finance` — the menu would show Cash Advance twice. Moving the row
 * here keeps each tenant's customisations and its sole kasbon entry intact.
 */
return new class extends Migration
{
    /**
     * The Finance leaves, mirroring AvanaNav::groups().
     *
     * @var array<int, array<string, mixed>>
     */
    private const LEAVES = [
        ['key' => 'reimbursement', 'label' => 'Reimbursement', 'icon' => 'receipt', 'href' => '/avana/reimbursement', 'feature' => 'reimbursement', 'modules' => ['claim']],
        ['key' => 'kasbon', 'label' => 'Cash Advance', 'icon' => 'hand-coins', 'href' => '/avana/kasbon', 'feature' => 'cash_advance', 'modules' => ['payroll']],
        ['key' => 'settlement', 'label' => 'Settlement', 'icon' => 'file-check', 'href' => '/avana/settlement', 'feature' => 'cash_advance', 'modules' => ['claim']],
    ];

    public function up(): void
    {
        $this->enableFinanceFeatures();

        $tenantIds = MenuItem::query()->distinct()->whereNotNull('tenant_id')->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $this->buildFinanceGroupFor((int) $tenantId);
        }
    }

    public function down(): void
    {
        $tenantIds = MenuItem::query()->distinct()->whereNotNull('tenant_id')->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $finance = MenuItem::where('tenant_id', $tenantId)->where('key', 'finance')->whereNull('parent_id')->first();

            if ($finance === null) {
                continue;
            }

            $benefitGroup = MenuItem::where('tenant_id', $tenantId)->where('key', 'benefit-grp')->whereNull('parent_id')->first();

            // Put Cash Advance back where it came from, on its old feature gate.
            MenuItem::where('tenant_id', $tenantId)
                ->where('key', 'kasbon')
                ->where('parent_id', $finance->id)
                ->update([
                    'parent_id' => $benefitGroup?->id,
                    'label' => 'Kasbon',
                    'feature' => 'payroll',
                    'sort_order' => 3,
                ]);

            // Cascades onto the reimbursement/settlement children.
            $finance->delete();
        }

        Feature::whereIn('code', ['reimbursement', 'cash_advance'])->delete();
    }

    /**
     * Register the two new feature codes and switch them on for every tenant —
     * a nav leaf whose feature is disabled is hidden, so without this the
     * Finance group would seed invisible.
     */
    private function enableFinanceFeatures(): void
    {
        $features = collect([
            'reimbursement' => 'Reimbursement',
            'cash_advance' => 'Cash Advance & Settlement',
        ])->map(fn (string $name, string $code): Feature => Feature::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'module_group' => 'payroll'],
        ));

        foreach (Tenant::all() as $tenant) {
            foreach ($features as $feature) {
                $tenant->features()->firstOrCreate(['feature_id' => $feature->id], ['is_enabled' => true]);
            }
        }
    }

    /**
     * Create the tenant's Finance parent and hang the three leaves off it,
     * moving `kasbon` across rather than duplicating it.
     */
    private function buildFinanceGroupFor(int $tenantId): void
    {
        $anchor = MenuItem::where('tenant_id', $tenantId)->where('key', 'benefit-grp')->whereNull('parent_id')->first();

        $finance = MenuItem::firstOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'finance', 'parent_id' => null],
            [
                'section' => $anchor?->section ?? 'PAYROLL & KEUANGAN',
                'label' => 'Finance',
                'icon' => 'receipt',
                'href' => null,
                'feature' => null,
                'modules' => [],
                'admin_only' => false,
                'super_admin_only' => false,
                'is_system' => true,
                'sort_order' => $anchor?->sort_order ?? 0,
            ],
        );

        foreach (self::LEAVES as $order => $leaf) {
            $existing = MenuItem::where('tenant_id', $tenantId)->where('key', $leaf['key'])->whereNotNull('parent_id')->first();

            $attributes = [
                'parent_id' => $finance->id,
                'section' => null,
                'label' => $leaf['label'],
                'icon' => $leaf['icon'],
                'href' => $leaf['href'],
                'feature' => $leaf['feature'],
                'modules' => $leaf['modules'],
                'admin_only' => false,
                'super_admin_only' => false,
                'is_system' => true,
                'sort_order' => $order,
            ];

            if ($existing === null) {
                MenuItem::create($attributes + ['tenant_id' => $tenantId, 'key' => $leaf['key']]);

                continue;
            }

            $existing->update($attributes);
        }
    }
};
