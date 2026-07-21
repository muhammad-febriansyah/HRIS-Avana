<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin catalog of the product's features. Creating a feature here makes
 * it appear automatically in the Hak Akses matrix (as a master switch + per-role
 * action columns) and become enable-able per tenant — no code change. The
 * feature's `permission_modules` also get their `{module}.{action}` permission
 * rows seeded so the per-role toggles work immediately.
 *
 * Building the controller + `ensureCan` enforcement for a genuinely new page
 * still requires code; this only removes the feature/permission/matrix wiring.
 */
class FeatureCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $features = Feature::query()
            ->orderBy('module_group')
            ->orderBy('name')
            ->get()
            ->map(fn (Feature $feature): array => [
                'id' => $feature->id,
                'code' => $feature->code,
                'name' => $feature->name,
                'module_group' => $feature->module_group,
                'permission_modules' => $feature->permission_modules ?? [],
                'is_active' => (bool) $feature->is_active,
                'tenants' => $feature->tenants()->count(),
            ]);

        return Inertia::render('avana/katalog-fitur/index', [
            'features' => $features,
            'moduleGroups' => Feature::query()->distinct()->orderBy('module_group')->pluck('module_group')->filter()->values(),
            'moduleOptions' => Permission::query()->distinct()->orderBy('module')->pluck('module')->filter()->values(),
            'actions' => collect(PermissionCatalog::ACTIONS)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate($this->rules());

        $feature = Feature::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'module_group' => $validated['module_group'],
            'permission_modules' => $validated['permission_modules'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->ensurePermissions($feature->permission_modules ?? []);

        return back()->with('success', 'Fitur ditambahkan');
    }

    public function update(Request $request, Feature $feature): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        // Code is immutable after creation: menu leaves + tenant toggles reference
        // it, so renaming it would orphan their gating.
        $validated = $request->validate($this->rules(ignoreCode: $feature->id));

        $feature->update([
            'name' => $validated['name'],
            'module_group' => $validated['module_group'],
            'permission_modules' => $validated['permission_modules'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->ensurePermissions($feature->permission_modules ?? []);

        return back()->with('success', 'Fitur diperbarui');
    }

    public function destroy(Request $request, Feature $feature): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        // Hard delete so the code is freed for reuse; tenant_features cascade via
        // the FK. Menu leaves that referenced it simply stop matching a feature.
        $feature->forceDelete();

        return back()->with('success', 'Fitur dihapus');
    }

    /**
     * Ensure the full action-set permission rows exist for the given modules so
     * the Hak Akses matrix can grant/revoke them immediately.
     *
     * @param  array<int, string>  $modules
     */
    private function ensurePermissions(array $modules): void
    {
        foreach ($modules as $module) {
            foreach (PermissionCatalog::actionKeys() as $action) {
                $code = $module.'.'.$action;

                Permission::firstOrCreate(
                    ['code' => $code],
                    ['module' => $module, 'action' => $action, 'name' => $code],
                );
            }
        }
    }

    /**
     * Validation rules for create/update. `$ignoreCode` skips the unique check for
     * the row being updated (code itself stays unchanged on update).
     *
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreCode = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('features', 'code')->ignore($ignoreCode)],
            'name' => ['required', 'string', 'max:255'],
            'module_group' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'permission_modules' => ['array'],
            'permission_modules.*' => ['string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'is_active' => ['boolean'],
        ];
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
