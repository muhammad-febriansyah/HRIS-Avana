<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "View as tenant" for super admins. When a super admin has selected a tenant to
 * view (session view_tenant_id), their tenant_id is overridden IN MEMORY for the
 * request so every `forTenant($user->tenant_id)` query and shared nav reflects
 * that tenant — without persisting anything. Non-super-admins are never affected,
 * so tenant data can never leak to a regular user.
 */
class ResolveActiveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $request->hasSession()) {
            $user->loadMissing('roles');

            $isSuperAdmin = $user->roles->contains(fn ($role): bool => $role->code === 'super_admin');
            $viewTenantId = (int) $request->session()->get('view_tenant_id', 0);

            if ($isSuperAdmin && $viewTenantId > 0 && Tenant::whereKey($viewTenantId)->exists()) {
                // In-memory only — never saved back to the users table.
                $user->tenant_id = $viewTenantId;
            }
        }

        return $next($request);
    }
}
