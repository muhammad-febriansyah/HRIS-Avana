<?php

namespace App\Http\Middleware;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\RoleMenuVisibility;
use App\Support\AvanaNav;
use App\Support\PendingApprover;
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

        // A hidden menu blocks access entirely (sidebar + route).
        if (($requirement['is_active'] ?? true) === false) {
            abort(403);
        }

        // The approval centre stays open to whoever a request is currently
        // waiting on, whatever their role carries: a workflow step names its
        // approver directly, and a hidden menu or a missing module would strand
        // the request on someone who cannot reach it.
        $isNamedApprover = ($requirement['key'] ?? null) === AvanaNav::APPROVAL_CENTRE_KEY
            && PendingApprover::awaits($user);

        // Hidden for every role this user holds (Hak Akses → kolom Tampil): the
        // menu is gone from the sidebar, so the URL must be closed too.
        if (($requirement['key'] ?? null) !== null
            && ! $isNamedApprover
            && in_array($requirement['key'], RoleMenuVisibility::keysHiddenForAll($user->roles->pluck('id')), true)) {
            abort(403);
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
            // The approval centre is the exception: a workflow can route a
            // request to someone who holds none of these modules, and closing
            // the screen to them strands it on a step its own approver cannot
            // reach. Only while something is actually waiting on them.
            abort_unless($isNamedApprover, 403);
        }

        // A menu whose tenant feature is disabled is not reachable either. A
        // leaf may name several — all of them must be on, matching how the
        // sidebar decides whether to show the menu at all.
        $requiredFeatures = AvanaNav::featureCodes($requirement['feature']);

        if ($requiredFeatures !== [] && $user->tenant_id !== null) {
            $enabled = Feature::whereIn('id', $user->tenant?->features()->where('is_enabled', true)->pluck('feature_id') ?? collect())
                ->pluck('code');

            foreach ($requiredFeatures as $code) {
                abort_unless($enabled->contains($code), 403);
            }
        }

        return $next($request);
    }
}
