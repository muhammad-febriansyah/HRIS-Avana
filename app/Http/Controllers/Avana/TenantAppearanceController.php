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
use Illuminate\Support\Facades\Storage;
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

        $logoPath = $this->currentLogoPath($request);

        return Inertia::render('avana/tampilan/index', [
            'theme' => TenantTheme::resolve($source->theme),
            'defaults' => TenantTheme::DEFAULTS,
            'tokens' => TenantTheme::TOKENS,
            'presets' => TenantTheme::PRESETS,
            'logo_url' => $logoPath !== null ? Storage::disk('public')->url($logoPath) : null,
            'is_platform' => $source instanceof WebsiteSetting,
        ]);
    }

    /**
     * Upload (replace) the company logo shown in the sidebar. For the platform
     * scope it updates the website-settings logo; for a tenant it updates that
     * tenant's company logo, which white-labels the sidebar for its users.
     */
    public function updateLogo(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $request->validate([
            'logo' => ['required', 'image', 'max:1024'],
        ], [
            'logo.image' => 'Berkas harus berupa gambar (PNG, JPG, atau WEBP).',
            'logo.max' => 'Ukuran logo maksimal 1 MB.',
        ]);

        $source = $this->logoSource($request);

        // Drop the previous file so replaced logos don't accumulate.
        if (! empty($source->logo_path)) {
            Storage::disk('public')->delete($source->logo_path);
        }

        $path = $request->file('logo')->store('company-logos', 'public');
        $source->update(['logo_path' => $path]);

        return back()->with('success', 'Logo berhasil diperbarui');
    }

    /**
     * Remove the company logo and fall back to the AvanaHR mark.
     */
    public function removeLogo(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $source = $this->logoSource($request);

        if (! empty($source->logo_path)) {
            Storage::disk('public')->delete($source->logo_path);
        }

        $source->update(['logo_path' => null]);

        return back()->with('success', 'Logo dihapus');
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
        // Only a super admin's "view as tenant" session may point the theme
        // source at another tenant; a normal user always edits their own, even
        // if a stale view_tenant_id somehow ends up in their session.
        $viewTenantId = $user->isSuperAdmin()
            ? (int) ($request->session()->get('view_tenant_id') ?? 0)
            : 0;

        if ($user->isSuperAdmin() && $viewTenantId === 0) {
            return WebsiteSetting::current();
        }

        $tenantId = $viewTenantId > 0 ? $viewTenantId : $user->tenant_id;

        return Tenant::query()->findOrFail($tenantId);
    }

    /**
     * The current logo path for the active scope, read-only (never creates a
     * company row on a GET request). Null when no logo has been uploaded.
     */
    private function currentLogoPath(Request $request): ?string
    {
        $source = $this->themeSource($request);

        if ($source instanceof WebsiteSetting) {
            return $source->logo_path;
        }

        /** @var Tenant $source */
        return $source->company?->logo_path;
    }

    /**
     * The model that carries the logo for the active scope: the website-settings
     * singleton for the platform, otherwise the active tenant's company row
     * (created on demand so a tenant without one can still set a logo).
     */
    private function logoSource(Request $request): Model
    {
        $source = $this->themeSource($request);

        if ($source instanceof WebsiteSetting) {
            return $source;
        }

        /** @var Tenant $source */
        return $source->company()->firstOrCreate([], [
            'name' => $source->company_name ?? $source->name,
        ]);
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
