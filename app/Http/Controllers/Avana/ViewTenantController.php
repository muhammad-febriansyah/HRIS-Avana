<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ViewTenantController extends Controller
{
    /**
     * Set (or clear) the tenant a super admin is viewing. Only super admins may
     * do this; the choice lives in the session and is applied in-memory by the
     * ResolveActiveTenant middleware.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless($user->roles->contains(fn ($role): bool => $role->code === 'super_admin'), 403);

        $tenantId = (int) ($request->input('tenant_id') ?? 0);

        if ($tenantId > 0 && Tenant::whereKey($tenantId)->exists()) {
            $request->session()->put('view_tenant_id', $tenantId);
            $name = Tenant::whereKey($tenantId)->value('name');

            return back()->with('success', 'Menampilkan data tenant: '.$name);
        }

        $request->session()->forget('view_tenant_id');

        return back()->with('success', 'Kembali ke tenant Anda');
    }
}
