<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a Super Admin / Tenant Admin control which modules (and therefore which
 * sidebar menus) are active for their tenant by toggling tenant_features.
 */
class FeatureController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $tenant = $request->user()->tenant;
        $enabled = $tenant
            ? $tenant->features()->pluck('is_enabled', 'feature_id')
            : collect();

        $features = Feature::orderBy('module_group')->orderBy('name')->get()->map(fn (Feature $feature): array => [
            'id' => $feature->id,
            'code' => $feature->code,
            'name' => $feature->name,
            'module_group' => $feature->module_group,
            'is_enabled' => (bool) ($enabled[$feature->id] ?? false),
        ]);

        return Inertia::render('avana/fitur/index', [
            'features' => $features,
            'tenantName' => $tenant?->name,
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'feature_id' => ['required', 'integer', 'exists:features,id'],
            'enabled' => ['required', 'boolean'],
        ]);

        $tenant = $request->user()->tenant;
        abort_if($tenant === null, 404);

        $tenant->features()->updateOrCreate(
            ['feature_id' => $validated['feature_id']],
            ['is_enabled' => $validated['enabled']],
        );

        return back()->with('success', 'Menu & fitur diperbarui');
    }

    private function authorizeAdmin(Request $request): void
    {
        $roles = $request->user()->roles()->pluck('code');
        Gate::allowIf($roles->contains('super_admin') || $roles->contains('admin_tenant_hr'));
    }
}
