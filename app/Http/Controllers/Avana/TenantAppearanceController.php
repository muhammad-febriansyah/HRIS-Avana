<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Support\TenantTheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant-facing appearance editor: recolour the admin panel chrome (sidebar +
 * topbar). The theme is a small set of hex colours stored on the tenant; the
 * layout derives borders/tints and applies them as CSS variables. Gated by the
 * `appearance` permission (granted to tenant admins, assignable to others).
 */
class TenantAppearanceController extends Controller
{
    private const MODULE = 'appearance';

    /**
     * Show the theme editor with the current colours, tokens and presets.
     */
    public function edit(Request $request): Response
    {
        $this->ensureCan($request, 'view');
        $source = $this->themeSource($request);

        return Inertia::render('avana/tampilan/index', [
            'theme' => TenantTheme::resolve($source->theme),
            'defaults' => TenantTheme::DEFAULTS,
            'tokens' => TenantTheme::TOKENS,
            'presets' => TenantTheme::PRESETS,
        ]);
    }

    /**
     * Persist the tenant's theme colours.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $source = $this->themeSource($request);

        $rules = [];

        foreach (TenantTheme::keys() as $key) {
            $rules[$key] = ['required', 'string', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'];
        }

        $data = $request->validate($rules, [
            'regex' => 'Warna harus berupa kode heksadesimal, mis. #2F54C9.',
        ]);

        $source->update(['theme' => TenantTheme::resolve($data)]);

        return back()->with('success', 'Tema berhasil disimpan');
    }

    /**
     * Reset the tenant back to the built-in default theme.
     */
    public function reset(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $source = $this->themeSource($request);

        $source->update(['theme' => null]);

        return back()->with('success', 'Tema dikembalikan ke bawaan');
    }

    /**
     * The model that owns the theme being edited: the platform (website
     * settings) singleton when a super admin is in platform scope, otherwise the
     * effective tenant (the one being impersonated, or the user's own).
     */
    private function themeSource(Request $request): Model
    {
        $user = $request->user();
        $viewTenantId = (int) ($request->session()->get('view_tenant_id') ?? 0);

        if ($user->isSuperAdmin() && $viewTenantId === 0) {
            return WebsiteSetting::current();
        }

        $tenantId = $viewTenantId > 0 ? $viewTenantId : $user->tenant_id;

        return Tenant::query()->findOrFail($tenantId);
    }

    /**
     * Abort with 403 unless the user is a super admin or holds the permission.
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}
