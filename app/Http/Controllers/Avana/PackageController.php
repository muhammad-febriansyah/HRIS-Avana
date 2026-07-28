<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiTokenPack;
use App\Models\Feature;
use App\Models\Package;
use App\Models\User;
use App\Support\FeatureGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin catalogue of subscription packages / pricing tiers: name, price,
 * quotas and a plain-text feature list per tier. The feature list is stored in
 * the DB (packages.feature_list) so pricing can be edited without a deploy.
 */
class PackageController extends Controller
{
    private const CYCLES = ['monthly', 'yearly'];

    /**
     * Show the package catalogue.
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $packages = Package::query()
            ->withCount('tenants')
            ->with(['features' => fn ($query) => $query->wherePivot('is_enabled', true)->select('features.id')])
            ->orderByDesc('is_active')
            ->orderBy('price')
            ->orderBy('id')
            ->get()
            ->map(fn (Package $package): array => [
                'feature_ids' => $package->features->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                'id' => $package->id,
                'name' => $package->name,
                'tagline' => $package->tagline,
                'code' => $package->code,
                'price' => (int) $package->price,
                'billing_cycle' => $package->billing_cycle,
                'max_users' => $package->max_users,
                'max_employees' => $package->max_employees,
                'max_branches' => $package->max_branches,
                'ai_token_quota' => $package->ai_token_quota,
                'feature_list' => $package->feature_list ?? [],
                'is_active' => $package->is_active,
                'is_popular' => $package->is_popular,
                'tenants_count' => $package->tenants_count,
            ]);

        return Inertia::render('avana/paket/index', [
            'packages' => $packages->values()->all(),
            'cycles' => self::CYCLES,
            'featureCatalog' => $this->featureCatalog(),
            'tokenPacks' => $this->tokenPacks(),
        ]);
    }

    /**
     * Create a new package.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $this->validated($request);
        $features = $data['features'];
        unset($data['features']);

        DB::transaction(function () use ($data, $features): void {
            $package = Package::create($this->withTokenPack($data));
            $this->syncFeatures($package, $features);
        });

        return back()->with('success', 'Paket dibuat');
    }

    /**
     * Update an existing package.
     */
    public function update(Request $request, Package $package): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $this->validated($request, $package);
        $features = $data['features'];
        unset($data['features']);

        DB::transaction(function () use ($package, $data, $features): void {
            $package->update($this->withTokenPack($data));
            $this->syncFeatures($package, $features);
        });

        return back()->with('success', 'Paket diperbarui');
    }

    /**
     * The sellable AI token packs the package form can point its monthly quota at.
     *
     * @return array<int, array{id: int, name: string, token_amount: int, price: int}>
     */
    private function tokenPacks(): array
    {
        return AiTokenPack::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'token_amount', 'price'])
            ->map(fn (AiTokenPack $pack): array => [
                'id' => $pack->id,
                'name' => $pack->name,
                'token_amount' => (int) $pack->token_amount,
                'price' => (int) $pack->price,
            ])
            ->all();
    }

    /**
     * Resolve the "custom" branch of the token quota picker: the super admin typed
     * an allowance that no pack sells yet, so the same numbers are also filed as a
     * new {@see AiTokenPack} — the catalogue and the package tier stay in step.
     *
     * An identical pack (same tokens, same price) is reused rather than duplicated.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withTokenPack(array $data): array
    {
        $pack = $data['token_pack'] ?? null;
        unset($data['token_pack']);

        if ($pack === null) {
            return $data;
        }

        $existing = AiTokenPack::query()
            ->where('token_amount', $pack['token_amount'])
            ->where('price', $pack['price'])
            ->first();

        $data['ai_token_quota'] = (int) ($existing?->token_amount ?? AiTokenPack::create([
            'name' => $pack['name'],
            'token_amount' => $pack['token_amount'],
            'price' => $pack['price'],
            'description' => $pack['description'] ?? null,
            'is_active' => true,
        ])->token_amount);

        return $data;
    }

    /**
     * The feature modules a package can grant, grouped the way the Hak Akses and
     * Kelola Fitur screens group them so a super admin reads one taxonomy.
     *
     * @return array<int, array{id: int, code: string, name: string, group: string}>
     */
    private function featureCatalog(): array
    {
        return Feature::query()
            ->get(['id', 'code', 'name', 'module_group'])
            ->sortBy(fn (Feature $feature): string => sprintf('%02d-%s', FeatureGroups::sortIndex($feature->module_group), $feature->name))
            ->values()
            ->map(fn (Feature $feature): array => [
                'id' => $feature->id,
                'code' => $feature->code,
                'name' => $feature->name,
                'group' => FeatureGroups::label($feature->module_group),
            ])
            ->all();
    }

    /**
     * Replace the package's entitlement set. An empty selection is stored as
     * "no rows", which downstream reads as the whole catalogue — the same
     * behaviour packages had before entitlements existed.
     *
     * @param  array<int, int>  $featureIds
     */
    private function syncFeatures(Package $package, array $featureIds): void
    {
        $package->features()->sync(
            collect($featureIds)
                ->unique()
                ->mapWithKeys(fn (int $id): array => [$id => ['is_enabled' => true]])
                ->all(),
        );
    }

    /**
     * Delete a package (soft delete keeps tenant history intact).
     */
    public function destroy(Request $request, Package $package): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $package->delete();

        return back()->with('success', 'Paket dihapus');
    }

    /**
     * Validate and normalise the package payload.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'billing_cycle' => ['required', Rule::in(self::CYCLES)],
            'max_users' => ['nullable', 'integer', 'min:0'],
            'max_employees' => ['nullable', 'integer', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:0'],
            'ai_token_quota' => ['nullable', 'integer', 'min:0'],
            // Present only when the quota was typed by hand and should also be
            // filed as a sellable pack; a pack it points at instead sends null.
            'token_pack' => ['nullable', 'array'],
            'token_pack.name' => ['required_with:token_pack', 'string', 'max:120'],
            'token_pack.token_amount' => ['required_with:token_pack', 'integer', 'min:1'],
            'token_pack.price' => ['required_with:token_pack', 'integer', 'min:0'],
            'token_pack.description' => ['nullable', 'string', 'max:255'],
            'feature_list' => ['nullable', 'array'],
            // Blank lines arrive as null (ConvertEmptyStringsToNull); allow them
            // and drop them below so the textarea can have empty rows.
            'feature_list.*' => ['nullable', 'string', 'max:120'],
            // The modules this tier actually unlocks for a tenant. Empty = all.
            'features' => ['nullable', 'array'],
            'features.*' => ['integer', 'exists:features,id'],
            'is_active' => ['boolean'],
            'is_popular' => ['boolean'],
        ]);

        $data['features'] = array_map('intval', $data['features'] ?? []);

        // Keep only non-empty, trimmed feature strings.
        $data['feature_list'] = array_values(array_filter(
            array_map(fn ($feature): string => trim((string) $feature), $data['feature_list'] ?? []),
            fn (string $feature): bool => $feature !== '',
        ));

        // A stable code: kept on update, slugged from the name on create.
        $data['code'] = $package?->code ?? $this->uniqueCode($data['name']);

        return $data;
    }

    /**
     * A unique slug code derived from the package name.
     */
    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'paket';
        $code = $base;
        $suffix = 1;

        while (Package::where('code', $code)->exists()) {
            $code = $base.'_'.(++$suffix);
        }

        return $code;
    }

    /**
     * Abort with 403 unless the acting user is a platform super admin.
     */
    private function ensureSuperAdmin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->roles()->where('code', 'super_admin')->exists(), 403);
    }
}
