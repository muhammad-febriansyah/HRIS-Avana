<?php

namespace App\Http\Middleware;

use App\Models\Feature;
use App\Models\Permission;
use App\Support\AvanaNav;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the same role/permission gating the sidebar (AvanaNav) applies, at
 * the route level — so a hidden menu cannot be reached by typing its URL. The
 * requirement for each path is resolved from the single AvanaNav definition.
 */
class EnsureAvanaAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $user->loadMissing('roles.permissions');

        $roleCodes = $user->roles->pluck('code');

        // Super admin sees and reaches everything.
        if ($roleCodes->contains('super_admin')) {
            return $next($request);
        }

        $requirement = AvanaNav::requirementFor($request->path(), $user->tenant_id);

        // Not a gated menu path — leave it to the controller's own policies.
        if ($requirement === null) {
            return $next($request);
        }

        if ($requirement['superAdminOnly']) {
            abort(403);
        }

        $userModules = Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $user->roles->pluck('id')))
            ->distinct()
            ->pluck('module');

        if ($requirement['adminOnly']) {
            abort_unless($userModules->intersect(AvanaNav::manageModules())->isNotEmpty(), 403);
        }

        if ($requirement['modules'] !== [] && $userModules->intersect($requirement['modules'])->isEmpty()) {
            abort(403);
        }

        // A menu whose tenant feature is disabled is not reachable either.
        if ($requirement['feature'] !== null && $user->tenant_id !== null) {
            $enabled = Feature::whereIn('id', $user->tenant?->features()->where('is_enabled', true)->pluck('feature_id') ?? collect())
                ->pluck('code');

            abort_unless($enabled->contains($requirement['feature']), 403);
        }

        return $next($request);
    }
}
