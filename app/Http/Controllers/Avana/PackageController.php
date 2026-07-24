<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->orderByDesc('is_active')
            ->orderBy('price')
            ->orderBy('id')
            ->get()
            ->map(fn (Package $package): array => [
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
        ]);
    }

    /**
     * Create a new package.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        Package::create($this->validated($request));

        return back()->with('success', 'Paket dibuat');
    }

    /**
     * Update an existing package.
     */
    public function update(Request $request, Package $package): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $package->update($this->validated($request, $package));

        return back()->with('success', 'Paket diperbarui');
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
            'feature_list' => ['nullable', 'array'],
            // Blank lines arrive as null (ConvertEmptyStringsToNull); allow them
            // and drop them below so the textarea can have empty rows.
            'feature_list.*' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'is_popular' => ['boolean'],
        ]);

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
