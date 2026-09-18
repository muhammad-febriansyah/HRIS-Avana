<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Support\TenantContext;
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
        $tenantContext = app(TenantContext::class);
        $tenantContext->forget();

        if ($user !== null && $request->hasSession()) {
            $user->loadMissing('roles');

            $isSuperAdmin = $user->roles->contains(fn ($role): bool => $role->code === 'super_admin');
            $viewTenantId = (int) $request->session()->get('view_tenant_id', 0);

            if ($isSuperAdmin && $viewTenantId > 0 && Tenant::whereKey($viewTenantId)->exists()) {
                $user->tenant_id = $viewTenantId;
                $tenantContext->set($viewTenantId);

                // In-memory only. Assigning alone was not enough: the attribute
                // stayed dirty, so any later save() on this same instance wrote
                // the viewed tenant into the users table and moved the super
                // admin into that tenant for good — Settings → Profil did
                // exactly that. Syncing the original marks it unchanged, so a
                // save never carries it.
                $user->syncOriginalAttribute('tenant_id');
            } elseif (! $isSuperAdmin) {
                $activeTenantId = (int) $request->session()->get('active_tenant_id', 0);

                if ($activeTenantId > 0 && TenantMembership::query()
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $activeTenantId)
                    ->where('status', 'active')
                    ->whereHas('tenant', fn ($query) => $query->whereIn('status', ['trial', 'active']))
                    ->exists()) {
                    $user->tenant_id = $activeTenantId;
                    $tenantContext->set($activeTenantId);
                    $user->syncOriginalAttribute('tenant_id');
                } else {
                    $request->session()->forget('active_tenant_id');
                    $tenantContext->set($user->tenant_id);
                }
            }

            if ($tenantContext->id() !== null) {
                $user->unsetRelation('roles');
                $user->load('roles');
            }
        }

        return $next($request);
    }
}
